<?php

namespace App\Services;

use App\Models\ContentItem;
use App\Models\Navigation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The menu tree, the contents assigned to its items, and who may see what.
 *
 * Everything is read once and cached without expiry (Navigation and ContentItem flush it on every
 * save and delete); who sees which part is decided per request, so one cached tree serves everybody.
 *
 * Root and admin always see everything (to prepare and preview). Anybody else needs:
 *  - for a node: it and every ancestor active and not "members only" (unless logged in);
 *  - for a contents node: at least one content that is live for them. A content is live when it has
 *    a publish date that has come, an archive date that has not, and is either open to everybody or
 *    the viewer is logged in. A content with no publish date is a draft.
 * {@see state()} tells staff what a visitor would actually get.
 */
class SiteNavigation
{
    // versioned: a change in the shape of the cached tree must never meet a cache written by the old code
    private const CACHE_KEY = 'site.navigation.v3';

    /** What staff are warned about; "login only" is just how a members-only platform works. */
    public const NOTICE_STATES = ['draft', 'scheduled', 'archived', 'inactive', 'empty'];

    private ?array $nodes = null;

    public function __construct(private ContentTexts $texts)
    {
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->nodes = null;
    }

    /** @return array<int, array> id => node, each with its 'contents' (plain arrays, so they can be cached) */
    public function nodes(): array
    {
        if ($this->nodes !== null) {
            return $this->nodes;
        }

        return $this->nodes = Cache::rememberForever(self::CACHE_KEY, function () {
            $contents = ContentItem::orderBy('position')->orderBy('id')->get()->groupBy('navigation_id');
            $out = [];

            foreach (Navigation::orderBy('position')->orderBy('id')->get() as $n) {
                $out[$n->id] = [
                    'id' => $n->id,
                    'parent_id' => $n->parent_id ? (int) $n->parent_id : null,
                    'position' => (int) $n->position,
                    'type' => $n->type,
                    'sort_mode' => $n->sort_mode ?: 'manual',
                    'slug' => $n->slug,
                    'link_url' => $n->link_url,
                    'active' => (bool) $n->active,
                    'show_in_menu' => (bool) $n->show_in_menu,
                    'members_only' => (bool) $n->members_only,
                    'contents' => ($contents->get($n->id) ?? collect())->map(fn($c) => [
                        'id' => $c->id,
                        'position' => (int) $c->position,
                        'members_only' => (bool) $c->members_only,
                        'publish_at' => $c->publish_at?->timestamp,
                        'archive_at' => $c->archive_at?->timestamp,
                    ])->values()->all(),
                ];
            }

            return $out;
        });
    }

    public function node(int $id): ?array
    {
        return $this->nodes()[$id] ?? null;
    }

    /** The children of a node (null = the top level), in menu order. */
    public function children(?int $parentId): array
    {
        $children = array_filter($this->nodes(), fn($n) => $n['parent_id'] === $parentId);
        usort($children, fn($a, $b) => [$a['position'], $a['id']] <=> [$b['position'], $b['id']]);

        return $children;
    }

    /** Ancestors from the top down, ending with the node itself. */
    public function chain(int $id): array
    {
        $chain = [];
        $guard = 0;
        while ($id && ($node = $this->node($id)) && $guard++ < 100) {
            array_unshift($chain, $node);
            $id = $node['parent_id'] ?? 0;
        }

        return $chain;
    }

    public function path(int $id): string
    {
        return implode('/', array_column($this->chain($id), 'slug'));
    }

    public function descendantIds(int $id): array
    {
        $ids = [];
        foreach ($this->children($id) as $child) {
            $ids[] = $child['id'];
            $ids = array_merge($ids, $this->descendantIds($child['id']));
        }

        return $ids;
    }

    public function findByPath(string $path): ?array
    {
        $parent = null;
        $found = null;

        foreach (array_filter(explode('/', trim($path, '/')), 'strlen') as $segment) {
            $found = null;
            foreach ($this->children($parent) as $child) {
                if ($child['slug'] === $segment) {
                    $found = $child;
                    break;
                }
            }
            if (!$found) {
                return null;
            }
            $parent = $found['id'];
        }

        return $found;
    }

    // ---- who may see what -----------------------------------------------------------------

    private function audience(?User $viewer): string
    {
        return !$viewer ? 'guest' : ($viewer->isAdmin() ? 'staff' : 'member');
    }

    /** Why a content cannot be seen by this audience (guest, member, staff), or null if it can. */
    public function contentReason(array $content, string $audience): ?string
    {
        if ($audience === 'staff') {
            return null;
        }
        if (!$content['publish_at']) {
            return 'draft';
        }

        $now = now()->timestamp;
        if ($content['publish_at'] > $now) {
            return 'scheduled';
        }
        if ($content['archive_at'] && $content['archive_at'] <= $now) {
            return 'archived';
        }

        return ($content['members_only'] && $audience === 'guest') ? 'login only' : null;
    }

    /** What a visitor gets of a content: live, login only, or why not (draft, scheduled, archived). */
    public function contentState(array $content): string
    {
        if ($this->contentReason($content, 'guest') === null) {
            return 'live';
        }

        return $this->contentReason($content, 'member') === null ? 'login only' : $this->contentReason($content, 'member');
    }

    /** The contents of a node this viewer may read, in the order the node asks for. */
    public function visibleContents(array $node, ?User $viewer, string $language = 'en'): array
    {
        $audience = $this->audience($viewer);

        return $this->orderedContents(
            $node,
            $language,
            array_values(array_filter($node['contents'], fn($c) => $this->contentReason($c, $audience) === null))
        );
    }

    /**
     * The contents of a node (or the given part of them) in the order the node asks for: by hand,
     * newest first, oldest first, or by title. Anything unpublished goes last; ties fall back to the
     * hand-made order and then the id, so the order is always the same.
     */
    public function orderedContents(array $node, string $language, ?array $contents = null): array
    {
        $contents = $contents ?? $node['contents'];
        $mode = $node['sort_mode'] ?? 'manual';
        $titles = [];
        if ($mode === 'title') {
            foreach ($contents as $c) {
                $titles[$c['id']] = mb_strtolower(trim(strip_tags($this->texts->get('content_title_' . $c['id'], $language))));
            }
        }

        usort($contents, function ($a, $b) use ($mode, $titles) {
            if ($mode === 'newest' || $mode === 'oldest') {
                $cmp = ($a['publish_at'] === null) <=> ($b['publish_at'] === null);   // unpublished last
                if ($cmp === 0 && $a['publish_at'] !== null) {
                    $cmp = $mode === 'newest' ? $b['publish_at'] <=> $a['publish_at'] : $a['publish_at'] <=> $b['publish_at'];
                }
                if ($cmp !== 0) {
                    return $cmp;
                }
            } elseif ($mode === 'title') {
                $cmp = strnatcmp($titles[$a['id']], $titles[$b['id']]);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            return [$a['position'], $a['id']] <=> [$b['position'], $b['id']];
        });

        return $contents;
    }

    /** Why this audience cannot reach the node, or null if it can. */
    public function blocks(array $node, string $audience): ?string
    {
        if ($audience === 'staff') {
            return null;
        }

        foreach ($this->chain($node['id']) as $step) {
            if (!$step['active']) {
                return 'inactive';
            }
            if ($step['members_only'] && $audience === 'guest') {
                return 'login only';
            }
        }

        if ($node['type'] === 'contents') {
            if (!$node['contents']) {
                return 'empty';
            }

            $reasons = array_map(fn($c) => $this->contentReason($c, $audience), $node['contents']);
            if (in_array(null, $reasons, true)) {
                return null;
            }

            // nothing can be read: "login only" if logging in would help, else the first obstacle
            return in_array('login only', $reasons, true) ? 'login only' : $reasons[0];
        }

        return null;
    }

    public function accessible(array $node, ?User $viewer): bool
    {
        return $this->blocks($node, $this->audience($viewer)) === null;
    }

    /**
     * Whether the node is listed (in the menu, in the footer) for this viewer: what they may open, plus,
     * for a visitor, an item that is open to everybody but whose contents all need a login, which then
     * leads to the login page. An item (or something above it) that is itself for logged-in users only,
     * or inactive, is not listed for a visitor.
     */
    public function listed(array $node, ?User $viewer): bool
    {
        if ($this->accessible($node, $viewer)) {
            return true;
        }
        if ($viewer) {
            return false;
        }

        foreach ($this->chain($node['id']) as $step) {
            if (!$step['active'] || $step['members_only']) {
                return false;
            }
        }

        return $this->blocks($node, 'member') === null;
    }

    /** What a visitor gets of a node: live, login only, or why not. */
    public function state(array $node): string
    {
        if ($this->blocks($node, 'guest') === null) {
            return 'live';
        }

        return $this->blocks($node, 'member') === null ? 'login only' : $this->blocks($node, 'member');
    }

    // ---- the menu -------------------------------------------------------------------------

    /**
     * The menu this viewer sees, nested. Each item: id, label, url, external, state (only for staff,
     * and only when something is worth telling them), children.
     */
    public function menu(?User $viewer, string $language, ?int $parentId = null): array
    {
        $items = [];

        foreach ($this->children($parentId) as $node) {
            if (!$node['show_in_menu'] || !$this->listed($node, $viewer)) {
                continue;
            }

            $children = $this->menu($viewer, $language, $node['id']);
            if ($node['type'] === 'group' && !$children) {
                continue;
            }

            $state = $this->state($node);
            $items[] = [
                'id' => $node['id'],
                'label' => $this->label($node, $language),
                'url' => $this->url($node),
                'external' => $node['type'] === 'link' && $this->isExternal((string) $node['link_url']),
                'state' => ($viewer && $viewer->isAdmin() && in_array($state, self::NOTICE_STATES, true)) ? $state : null,
                'children' => $children,
            ];
        }

        return $items;
    }

    public function label(array $node, string $language): string
    {
        $text = trim(strip_tags($this->texts->get('navigation_name_' . $node['id'], $language)));

        return $text !== '' ? $text : $node['slug'];
    }

    public function url(array $node): string
    {
        return match ($node['type']) {
            'group' => '#',
            'link' => $this->isExternal((string) $node['link_url']) ? (string) $node['link_url'] : url((string) $node['link_url']),
            default => url('/' . $this->path($node['id'])),
        };
    }

    private function isExternal(string $url): bool
    {
        return (bool) preg_match('#^https?://#i', $url);
    }

    /** The first contents item under a group this viewer may open: where a group's own address leads. */
    public function firstOpenNode(array $node, ?User $viewer): ?array
    {
        foreach ($this->children($node['id']) as $child) {
            if (!$this->listed($child, $viewer)) {
                continue;
            }
            if ($child['type'] === 'contents') {
                return $child;
            }
            if ($child['type'] === 'group' && ($deeper = $this->firstOpenNode($child, $viewer))) {
                return $deeper;
            }
        }

        return null;
    }

    // ---- slugs ----------------------------------------------------------------------------

    public static function slugFrom(string $text): string
    {
        return Str::slug($text);
    }

    /** First segments of every address the application already answers: no top-level item may take them. */
    public function reservedTopLevelSlugs(): array
    {
        $reserved = [];
        foreach (Route::getRoutes() as $route) {
            $first = explode('/', trim($route->uri(), '/'))[0] ?? '';
            if ($first !== '' && !str_starts_with($first, '{')) {
                $reserved[$first] = true;
            }
        }

        return array_keys($reserved);
    }
}
