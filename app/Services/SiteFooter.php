<?php

namespace App\Services;

use App\Models\FooterBlock;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * The footer, for whoever is looking: the active blocks in order, their texts in the visitor's
 * language, and, for a links block, the menu items that viewer may actually open (so a link to a
 * page for logged-in users does not show to a visitor).
 */
class SiteFooter
{
    private const CACHE_KEY = 'site.footer.v1';

    private ?array $rows = null;

    public function __construct(private ContentTexts $texts, private SiteNavigation $nav)
    {
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->rows = null;
    }

    /** @return array<int, array> every block, active or not, in order (plain arrays: cacheable) */
    public function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        return $this->rows = Cache::rememberForever(self::CACHE_KEY, fn() => FooterBlock::orderBy('position')->orderBy('id')->get()
            ->map(fn($b) => [
                'id' => $b->id,
                'type' => $b->type,
                'width' => (int) $b->width,
                'active' => (bool) $b->active,
                'nodes' => array_map('intval', (array) ($b->data['nodes'] ?? [])),
            ])->all());
    }

    /** What the footer shows this viewer: blocks with something in them, ready to print. */
    public function blocks(?User $viewer, string $language): array
    {
        $out = [];

        foreach ($this->rows() as $row) {
            if (!$row['active']) {
                continue;
            }

            $title = trim(strip_tags($this->texts->get('footer_title_' . $row['id'], $language)));
            $block = ['id' => $row['id'], 'type' => $row['type'], 'width' => $row['width'], 'title' => $title, 'body' => '', 'links' => []];

            if ($row['type'] === 'text') {
                $block['body'] = $this->texts->get('footer_body_' . $row['id'], $language);
                if ($title === '' && trim(strip_tags($block['body'])) === '') {
                    continue;
                }
            } else {
                foreach ($row['nodes'] as $nodeId) {
                    $node = $this->nav->node($nodeId);
                    if (!$node || $node['type'] === 'group' || !$this->nav->listed($node, $viewer)) {
                        continue;
                    }
                    $block['links'][] = [
                        'id' => $nodeId,
                        'label' => $this->nav->label($node, $language),
                        'url' => $this->nav->url($node),
                        'external' => $node['type'] === 'link' && preg_match('#^https?://#i', (string) $node['link_url']),
                    ];
                }
                if (!$block['links']) {
                    continue;
                }
            }

            $out[] = $block;
        }

        return $out;
    }
}
