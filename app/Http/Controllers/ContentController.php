<?php

namespace App\Http\Controllers;

use App\Services\ContentTexts;
use App\Services\SiteNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Serves the items of the navigation tree. Registered as the router's fallback, so it only ever
 * sees addresses nothing else in the application answers.
 */
class ContentController extends Controller
{
    public function show(Request $request, SiteNavigation $nav, ContentTexts $texts)
    {
        $node = $nav->findByPath($request->path());
        abort_if(!$node, 404);

        $viewer = auth()->user();
        if (!$nav->accessible($node, $viewer)) {
            // someone who would get in by logging in is asked to; for everybody else it does not exist
            return (!$viewer && $nav->blocks($node, 'member') === null)
                ? redirect()->guest(route('login'))
                : abort(404);
        }

        if ($node['type'] === 'link') {
            $url = $nav->url($node);

            return preg_match('#^https?://#i', (string) $node['link_url']) ? redirect()->away($url) : redirect($url);
        }

        if ($node['type'] === 'group') {
            $first = $nav->firstOpenNode($node, $viewer);
            abort_if(!$first, 404);

            return redirect($nav->url($first));
        }

        $contents = [];
        foreach ($nav->visibleContents($node, $viewer, $this->language()) as $content) {
            $contents[] = $content + [
                'title' => trim(strip_tags($texts->get('content_title_' . $content['id'], $this->language()))),
                'body' => $texts->get('content_body_' . $content['id'], $this->language()),
                'state' => $nav->contentState($content),
            ];
        }

        return view('content.page', [
            'node' => $node,
            'nav' => $nav,
            'chain' => $nav->chain($node['id']),
            'contents' => $contents,
            'state' => $nav->state($node),
            'canEdit' => $viewer && Gate::allows('manage-content'),
        ]);
    }

    private function language(): string
    {
        return view()->shared('active_language') ?: app()->getLocale();
    }
}
