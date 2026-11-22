<?php

namespace App\Http\Controllers;

use App\Models\ContentItem;
use App\Models\Language;
use App\Models\Navigation;
use App\Services\ContentTexts;
use App\Services\SiteNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The structure of the menu: groups, links and "contents" items, in a tree of any depth. For root and
 * admin (capability manage-content, set on the routes). The contents themselves are managed by
 * ContentItemsController and shown under the item they are assigned to.
 */
class NavigationController extends Controller
{
    public function __construct(private SiteNavigation $nav, private ContentTexts $texts)
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return view('navigation.index', ['tree' => $this->tree(null)]);
    }

    public function create(Request $request)
    {
        $type = in_array($request->query('type'), Navigation::TYPES, true) ? $request->query('type') : 'contents';

        return view('navigation.form', $this->formData(new Navigation(), $type, $request->integer('parent_id') ?: null));
    }

    public function store(Request $request)
    {
        $this->save($request, new Navigation());

        return redirect('/navigation')->with('success', 'Saved');
    }

    public function edit($id)
    {
        $node = Navigation::findOrFail($id);

        return view('navigation.form', $this->formData($node, $node->type, $node->parent_id));
    }

    public function update(Request $request, $id)
    {
        $this->save($request, Navigation::findOrFail($id));

        return redirect('/navigation')->with('success', 'Saved');
    }

    /**
     * Deletes the item and the items under it. Contents are never deleted along with them: if there
     * are any, nothing happens and the admin is told to move or delete them first.
     */
    public function destroy($id)
    {
        $root = Navigation::findOrFail($id);
        $ids = array_merge([$root->id], $this->nav->descendantIds($root->id));

        $contents = ContentItem::whereIn('navigation_id', $ids)->count();
        if ($contents > 0) {
            return redirect('/navigation')->with('error', 'This item (or one under it) still holds ' . $contents . ' content(s). Move or delete them first.');
        }

        DB::transaction(function () use ($ids) {
            foreach (Navigation::whereIn('id', $ids)->get() as $node) {
                $this->texts->forget('navigation_name_' . $node->id);
                $node->delete();
            }
        });

        return redirect('/navigation')->with('success', 'Deleted');
    }

    /** Drag and drop: the whole tree comes back as a flat list of id, parent_id, position. */
    public function sort(Request $request)
    {
        $items = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:navigation,id'],
            'items.*.parent_id' => ['nullable', 'integer', 'exists:navigation,id'],
            'items.*.position' => ['required', 'integer', 'min:0'],
        ])['items'];

        $rows = Navigation::all()->keyBy('id');
        $proposed = [];
        foreach ($rows as $id => $row) {
            $proposed[$id] = ['parent_id' => $row->parent_id ? (int) $row->parent_id : null, 'position' => (int) $row->position];
        }
        foreach ($items as $item) {
            $proposed[(int) $item['id']] = [
                'parent_id' => ($item['parent_id'] ?? null) ? (int) $item['parent_id'] : null,
                'position' => (int) $item['position'],
            ];
        }

        try {
            $this->assertValidTree($proposed, $rows);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }

        DB::transaction(function () use ($items, $rows) {
            foreach ($items as $item) {
                $node = $rows[(int) $item['id']];
                $node->parent_id = ($item['parent_id'] ?? null) ?: null;
                $node->position = (int) $item['position'];
                $node->save();
            }
        });

        return response()->json(['ok' => true]);
    }

    // ---- saving ---------------------------------------------------------------------------

    private function save(Request $request, Navigation $node): Navigation
    {
        $isNew = !$node->exists;
        $languages = Language::pluck('iso2')->all();
        $type = $isNew ? $request->input('type') : $node->type;

        $rules = [
            'type' => $isNew ? ['required', Rule::in(Navigation::TYPES)] : ['nullable'],
            'parent_id' => ['nullable', 'integer', 'exists:navigation,id'],
            'slug' => ['nullable', 'max:100', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
            'labels' => ['required', 'array'],
            'sort_mode' => ['nullable', Rule::in(Navigation::SORT_MODES)],
        ];
        if ($type === 'link') {
            $rules['link_url'] = ['required', 'max:500', 'regex:#^(/|https?://)#i'];
        }
        $data = $request->validate($rules);

        $labels = $this->cleanLabels($data['labels'], $languages);
        if (!array_filter($labels, fn($l) => $l !== '')) {
            throw ValidationException::withMessages(['labels' => 'Give the item a name in at least one language.']);
        }

        $parentId = ($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null;
        $this->assertParent($node, $parentId);
        $slug = $this->slugFor($node, $parentId, $type, $data['slug'] ?? null, $labels);

        DB::transaction(function () use ($request, $node, $isNew, $type, $parentId, $slug, $data, $labels) {
            $moved = $isNew || ((int) $node->parent_id) !== ((int) $parentId);

            if ($isNew) {
                $node->type = $type;
            }
            $node->parent_id = $parentId;
            $node->slug = $slug;
            $node->members_only = $request->boolean('members_only');
            $node->active = $request->boolean('active');
            $node->show_in_menu = $request->boolean('show_in_menu');
            if ($type === 'link') {
                $node->link_url = $data['link_url'];
            }
            if ($type === 'contents') {
                $node->sort_mode = $data['sort_mode'] ?? 'manual';
            }
            if ($moved) {
                $node->position = (int) Navigation::where('parent_id', $parentId)->max('position') + 1;
            }
            $node->save();

            $this->texts->set('navigation_name_' . $node->id, $labels);
        });

        return $node;
    }

    /** Names are plain text, one per known language. */
    private function cleanLabels(array $byLanguage, array $languages): array
    {
        $out = [];
        foreach ($languages as $language) {
            $out[$language] = trim(strip_tags((string) ($byLanguage[$language] ?? '')));
        }

        return $out;
    }

    /** A link cannot hold anything below it, and a node cannot go under itself or one of its own descendants. */
    private function assertParent(Navigation $node, ?int $parentId): void
    {
        if (!$parentId) {
            return;
        }

        $parent = Navigation::find($parentId);
        if ($parent->type === 'link') {
            throw ValidationException::withMessages(['parent_id' => 'A link cannot have anything under it.']);
        }
        if ($node->exists && ($parentId === (int) $node->id || in_array($parentId, $this->nav->descendantIds($node->id), true))) {
            throw ValidationException::withMessages(['parent_id' => 'An item cannot go under itself.']);
        }
    }

    /**
     * The address segment: the one typed, or one made from the first name. A typed one that is taken
     * is an error; a made-up one gets a number until it is free.
     */
    private function slugFor(Navigation $node, ?int $parentId, string $type, ?string $typed, array $labels): string
    {
        $reserved = $this->nav->reservedTopLevelSlugs();
        $taken = function (string $slug) use ($node, $parentId, $type, $reserved) {
            $sibling = Navigation::where('parent_id', $parentId)->where('slug', $slug)
                ->when($node->exists, fn($q) => $q->where('id', '!=', $node->id))->exists();

            return $sibling || ($parentId === null && $type !== 'link' && in_array($slug, $reserved, true));
        };

        if ($typed) {
            if ($taken($typed)) {
                throw ValidationException::withMessages(['slug' => 'This address is already used here or reserved by the application.']);
            }

            return $typed;
        }

        $base = SiteNavigation::slugFrom((string) collect($labels)->first(fn($l) => $l !== '')) ?: $type;
        $slug = $base;
        for ($i = 2; $taken($slug); $i++) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    /** What dragging may never produce: loops, anything under a link, a clash between siblings, a reserved top-level address. */
    private function assertValidTree(array $proposed, $rows): void
    {
        $reserved = $this->nav->reservedTopLevelSlugs();
        $seen = [];

        foreach ($proposed as $id => $p) {
            $steps = 0;
            for ($cursor = $p['parent_id']; $cursor; $cursor = $proposed[$cursor]['parent_id'] ?? null) {
                if ($cursor === $id || ++$steps > count($proposed)) {
                    throw ValidationException::withMessages(['items' => 'An item cannot go under itself.']);
                }
            }

            if ($p['parent_id'] && $rows[$p['parent_id']]->type === 'link') {
                throw ValidationException::withMessages(['items' => 'A link cannot have anything under it.']);
            }

            $slug = $rows[$id]->slug;
            $key = ($p['parent_id'] ?? 0) . '/' . $slug;
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['items' => 'Two items under the same parent would share the address "' . $slug . '".']);
            }
            $seen[$key] = true;

            if ($p['parent_id'] === null && $rows[$id]->type !== 'link' && in_array($slug, $reserved, true)) {
                throw ValidationException::withMessages(['items' => 'The address "' . $slug . '" is reserved by the application, so it cannot be a top-level item.']);
            }
        }
    }

    // ---- what the views need --------------------------------------------------------------

    private function tree(?int $parentId): array
    {
        $out = [];
        foreach ($this->nav->children($parentId) as $node) {
            $out[] = $node + [
                'label' => $this->nav->label($node, app()->getLocale()),
                'state' => $this->nav->state($node),
                'path' => $this->nav->path($node['id']),
                'content_count' => count($node['contents']),
                'children_tree' => $this->tree($node['id']),
            ];
        }

        return $out;
    }

    private function formData(Navigation $node, string $type, ?int $parentId): array
    {
        $excluded = $node->exists ? array_merge([$node->id], $this->nav->descendantIds($node->id)) : [];

        // possible parents: anything but a link, the item itself and what is under it, indented by depth
        $parents = [];
        $walk = function (?int $parent, int $depth) use (&$walk, &$parents, $excluded) {
            foreach ($this->nav->children($parent) as $candidate) {
                if ($candidate['type'] === 'link' || in_array($candidate['id'], $excluded, true)) {
                    continue;
                }
                $parents[] = ['id' => $candidate['id'], 'label' => str_repeat('— ', $depth) . $this->nav->label($candidate, app()->getLocale())];
                $walk($candidate['id'], $depth + 1);
            }
        };
        $walk(null, 0);

        return [
            'node' => $node,
            'type' => $type,
            'langCodes' => Language::pluck('iso2')->all(),
            'parents' => $parents,
            'parentId' => $parentId,
            'sortModes' => Navigation::SORT_MODES,
            'labels' => $node->exists ? $this->texts->all('navigation_name_' . $node->id) : [],
            'path' => $node->exists ? $this->nav->path($node->id) : null,
        ];
    }
}
