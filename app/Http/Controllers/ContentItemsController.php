<?php

namespace App\Http\Controllers;

use App\Models\ContentItem;
use App\Models\Language;
use App\Models\Navigation;
use App\Services\ContentTexts;
use App\Services\SiteNavigation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The contents (texts), kept apart from the menu; every one belongs to one of its "contents" items.
 * For root and admin (capability manage-content, set on the routes). They are listed, reordered and
 * moved between items on the Contents screen, and can also be added, edited and deleted from the
 * page of the item they are on (back=page brings the admin back there).
 */
class ContentItemsController extends Controller
{
    public function __construct(private SiteNavigation $nav, private ContentTexts $texts)
    {
        $this->middleware('auth');
    }

    /** Every contents item with what is in it, in the order the item asks for. */
    public function index()
    {
        $language = app()->getLocale();
        $groups = [];

        $walk = function (?int $parent, int $depth) use (&$walk, &$groups, $language) {
            foreach ($this->nav->children($parent) as $node) {
                if ($node['type'] === 'contents') {
                    $rows = [];
                    foreach ($this->nav->orderedContents($node, $language) as $c) {
                        $title = trim(strip_tags($this->texts->get('content_title_' . $c['id'], $language)));
                        $rows[] = $c + [
                            'title' => $title !== '' ? $title : '#' . $c['id'],
                            'state' => $this->nav->contentState($c),
                        ];
                    }
                    $groups[] = [
                        'id' => $node['id'],
                        'label' => $this->nav->label($node, $language),
                        'depth' => $depth,
                        'path' => $this->nav->path($node['id']),
                        'sort_mode' => $node['sort_mode'],
                        'state' => $this->nav->state($node),
                        'rows' => $rows,
                    ];
                }
                $walk($node['id'], $depth + 1);
            }
        };
        $walk(null, 0);

        return view('contents.index', ['groups' => $groups]);
    }

    public function create(Request $request)
    {
        return view('contents.form', $this->formData(new ContentItem(), $request->integer('navigation_id') ?: null, $request));
    }

    public function store(Request $request)
    {
        $content = $this->save($request, new ContentItem());

        return $this->after($request, (int) $content->navigation_id)->with('success', 'Saved');
    }

    public function edit(Request $request, $id)
    {
        $content = ContentItem::findOrFail($id);

        return view('contents.form', $this->formData($content, $content->navigation_id, $request));
    }

    public function update(Request $request, $id)
    {
        $content = $this->save($request, ContentItem::findOrFail($id));

        return $this->after($request, (int) $content->navigation_id)->with('success', 'Saved');
    }

    public function destroy(Request $request, $id)
    {
        $content = ContentItem::findOrFail($id);
        $navigationId = (int) $content->navigation_id;
        $this->texts->forget('content_title_' . $content->id, 'content_body_' . $content->id);
        $content->delete();

        return $this->after($request, $navigationId)->with('success', 'Deleted');
    }

    /** Back to the page the admin came from (the item's page, if they came from there) or the Contents screen. */
    private function after(Request $request, int $navigationId)
    {
        $node = $request->input('back') === 'page' && $navigationId ? $this->nav->node($navigationId) : null;

        return redirect($node ? $this->nav->url($node) : '/contents');
    }

    /** Drag and drop on the Contents screen: moves contents between items and reorders them. */
    public function sort(Request $request)
    {
        $items = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:content_items,id'],
            'items.*.navigation_id' => ['required', 'integer', 'exists:navigation,id'],
            'items.*.position' => ['required', 'integer', 'min:0'],
        ])['items'];

        $targets = Navigation::whereIn('id', array_column($items, 'navigation_id'))->get()->keyBy('id');
        foreach ($items as $item) {
            if ($targets[$item['navigation_id']]->type !== 'contents') {
                return response()->json(['ok' => false, 'message' => 'Contents can only be placed under an item of the kind "contents".'], 422);
            }
        }

        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                $content = ContentItem::find($item['id']);
                $content->navigation_id = (int) $item['navigation_id'];
                $content->position = (int) $item['position'];
                $content->save();
            }
        });

        return response()->json(['ok' => true]);
    }

    // ---- saving ---------------------------------------------------------------------------

    private function save(Request $request, ContentItem $content): ContentItem
    {
        $languages = Language::pluck('iso2')->all();

        $data = $request->validate([
            'navigation_id' => ['required', 'integer', 'exists:navigation,id'],
            'publish_at' => ['nullable', 'date'],
            'archive_at' => ['nullable', 'date'],
            'titles' => ['nullable', 'array'],
            'bodies' => ['nullable', 'array'],
        ]);

        $titles = $this->byLanguage($data['titles'] ?? [], $languages, true);
        if (!array_filter($titles, fn($t) => $t !== '')) {
            throw ValidationException::withMessages(['titles' => 'Give the content a title in at least one language.']);
        }

        $navigationId = (int) $data['navigation_id'];
        if (Navigation::find($navigationId)->type !== 'contents') {
            throw ValidationException::withMessages(['navigation_id' => 'Contents can only be placed under an item of the kind "contents".']);
        }

        $publishAt = !empty($data['publish_at']) ? Carbon::parse($data['publish_at']) : null;
        if (!$publishAt && $request->boolean('publish_now')) {
            $publishAt = now()->startOfMinute();
        }
        $archiveAt = !empty($data['archive_at']) ? Carbon::parse($data['archive_at']) : null;
        if ($publishAt && $archiveAt && $archiveAt->lte($publishAt)) {
            throw ValidationException::withMessages(['archive_at' => 'The archive date must be after the publish date.']);
        }

        DB::transaction(function () use ($request, $content, $navigationId, $publishAt, $archiveAt, $titles, $languages, $data) {
            $moved = !$content->exists || ((int) $content->navigation_id) !== $navigationId;

            $content->navigation_id = $navigationId;
            $content->members_only = $request->boolean('members_only');
            $content->publish_at = $publishAt;
            $content->archive_at = $archiveAt;
            $content->created_by = $content->created_by ?: auth()->id();
            $content->updated_by = auth()->id();
            if ($moved) {
                $content->position = (int) ContentItem::where('navigation_id', $navigationId)->max('position') + 1;
            }
            $content->save();

            $this->texts->set('content_title_' . $content->id, $titles);
            $this->texts->set('content_body_' . $content->id, $this->byLanguage($data['bodies'] ?? [], $languages, false));
        });

        return $content;
    }

    /** Keeps only known languages; titles are plain text, bodies are HTML. */
    private function byLanguage(array $byLanguage, array $languages, bool $plain): array
    {
        $out = [];
        foreach ($languages as $language) {
            $text = (string) ($byLanguage[$language] ?? '');
            $out[$language] = $plain ? trim(strip_tags($text)) : $text;
        }

        return $out;
    }

    private function formData(ContentItem $content, ?int $navigationId, Request $request): array
    {
        // where it can go: the "contents" items, indented by depth
        $places = [];
        $walk = function (?int $parent, int $depth) use (&$walk, &$places) {
            foreach ($this->nav->children($parent) as $node) {
                if ($node['type'] === 'contents') {
                    $places[] = ['id' => $node['id'], 'label' => str_repeat('— ', $depth) . $this->nav->label($node, app()->getLocale())];
                }
                $walk($node['id'], $depth + 1);
            }
        };
        $walk(null, 0);

        return [
            'content' => $content,
            'langCodes' => Language::pluck('iso2')->all(),
            'places' => $places,
            'navigationId' => $navigationId,
            'back' => $request->input('back') === 'page' ? 'page' : 'list',
            'cancelUrl' => ($request->input('back') === 'page' && $navigationId && ($node = $this->nav->node($navigationId)))
                ? $this->nav->url($node)
                : url('contents'),
            'titles' => $content->exists ? $this->texts->all('content_title_' . $content->id) : [],
            'bodies' => $content->exists ? $this->texts->all('content_body_' . $content->id) : [],
        ];
    }
}
