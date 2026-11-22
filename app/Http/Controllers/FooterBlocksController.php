<?php

namespace App\Http\Controllers;

use App\Models\FooterBlock;
use App\Models\Language;
use App\Services\ContentTexts;
use App\Services\SiteNavigation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The footer: blocks of text and lists of links to menu items, side by side. For root and admin
 * (capability manage-content, set on the routes).
 */
class FooterBlocksController extends Controller
{
    public function __construct(private SiteNavigation $nav, private ContentTexts $texts)
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $rows = [];
        foreach (FooterBlock::orderBy('position')->orderBy('id')->get() as $block) {
            $title = trim(strip_tags($this->texts->get('footer_title_' . $block->id, app()->getLocale())));
            $rows[] = [
                'id' => $block->id,
                'type' => $block->type,
                'width' => $block->width,
                'active' => $block->active,
                'title' => $title,
                'summary' => $block->type === 'links'
                    ? count((array) ($block->data['nodes'] ?? [])) . ' link(s)'
                    : \Illuminate\Support\Str::limit(trim(strip_tags($this->texts->get('footer_body_' . $block->id, app()->getLocale()))), 80),
            ];
        }

        return view('footer.index', ['blocks' => $rows]);
    }

    public function create(Request $request)
    {
        $type = in_array($request->query('type'), FooterBlock::TYPES, true) ? $request->query('type') : 'text';

        return view('footer.form', $this->formData(new FooterBlock(), $type));
    }

    public function store(Request $request)
    {
        $this->save($request, new FooterBlock());

        return redirect('/footer')->with('success', 'Saved');
    }

    public function edit($id)
    {
        $block = FooterBlock::findOrFail($id);

        return view('footer.form', $this->formData($block, $block->type));
    }

    public function update(Request $request, $id)
    {
        $this->save($request, FooterBlock::findOrFail($id));

        return redirect('/footer')->with('success', 'Saved');
    }

    public function destroy($id)
    {
        $block = FooterBlock::findOrFail($id);
        $this->texts->forget('footer_title_' . $block->id, 'footer_body_' . $block->id);
        $block->delete();

        return redirect('/footer')->with('success', 'Deleted');
    }

    /** Drag and drop: the blocks in their new order. */
    public function sort(Request $request)
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:footer_blocks,id'],
        ])['ids'];

        foreach (array_values($ids) as $position => $id) {
            $block = FooterBlock::find($id);
            $block->position = $position;
            $block->save();
        }

        return response()->json(['ok' => true]);
    }

    // ---- saving ---------------------------------------------------------------------------

    private function save(Request $request, FooterBlock $block): FooterBlock
    {
        $isNew = !$block->exists;
        $languages = Language::pluck('iso2')->all();
        $type = $isNew ? $request->input('type') : $block->type;

        $data = $request->validate([
            'type' => $isNew ? ['required', Rule::in(FooterBlock::TYPES)] : ['nullable'],
            'width' => ['required', 'integer', Rule::in(FooterBlock::WIDTHS)],
            'titles' => ['nullable', 'array'],
            'bodies' => ['nullable', 'array'],
            'nodes' => ['nullable', 'array'],
            'nodes.*' => ['integer', 'exists:navigation,id'],
        ]);

        $titles = $this->byLanguage($data['titles'] ?? [], $languages, true);
        $bodies = $this->byLanguage($data['bodies'] ?? [], $languages, false);

        if ($type === 'text' && !array_filter($titles) && !array_filter($bodies, fn($b) => trim(strip_tags($b)) !== '')) {
            throw ValidationException::withMessages(['bodies' => 'A text block needs a title or a text.']);
        }
        if ($type === 'links' && empty($data['nodes'])) {
            throw ValidationException::withMessages(['nodes' => 'Choose at least one menu item to link to.']);
        }

        if ($isNew) {
            $block->type = $type;
            $block->position = (int) FooterBlock::max('position') + 1;
        }
        $block->width = (int) $data['width'];
        $block->active = $request->boolean('active');
        $block->data = $type === 'links' ? ['nodes' => array_values(array_unique(array_map('intval', $data['nodes'])))] : null;
        $block->save();

        $this->texts->set('footer_title_' . $block->id, $titles);
        if ($type === 'text') {
            $this->texts->set('footer_body_' . $block->id, $bodies);
        }

        return $block;
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

    private function formData(FooterBlock $block, string $type): array
    {
        // what a links block can point to: every menu item that is not a bare heading, indented by depth
        $nodes = [];
        $walk = function (?int $parent, int $depth) use (&$walk, &$nodes) {
            foreach ($this->nav->children($parent) as $node) {
                if ($node['type'] !== 'group') {
                    $nodes[] = ['id' => $node['id'], 'label' => str_repeat('— ', $depth) . $this->nav->label($node, app()->getLocale())];
                }
                $walk($node['id'], $depth + 1);
            }
        };
        $walk(null, 0);

        return [
            'block' => $block,
            'type' => $type,
            'widths' => FooterBlock::WIDTHS,
            'langCodes' => Language::pluck('iso2')->all(),
            'nodes' => $nodes,
            'chosen' => array_map('intval', (array) ($block->data['nodes'] ?? [])),
            'titles' => $block->exists ? $this->texts->all('footer_title_' . $block->id) : [],
            'bodies' => $block->exists ? $this->texts->all('footer_body_' . $block->id) : [],
        ];
    }
}
