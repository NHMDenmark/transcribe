<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Models\FooterBlock;
use App\Models\Language;
use App\Models\Navigation;
use App\Models\User;
use App\Models\User_categories;
use App\Services\ContentTexts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The pencils of the inline text editor (shown in edit mode): valid markup, in the right place, on every text of the
 * website, the ones of the CMS too (menu names, page titles and texts, the footer), and only for those who may edit them.
 */
class InlineEditPencilTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['en', 'da'] as $iso) {
            $language = new Language();
            $language->iso2 = $iso;
            $language->save();
        }
        $cat = new User_categories();
        $cat->id = 5;
        $cat->category = 'level 5';
        $cat->points = 20;
        $cat->save();

        $this->admin = $this->user('admin');
    }

    private function user(string $role): User
    {
        $user = new User();
        $user->category = 5;
        $user->role = $role;
        $user->name = ucfirst($role);
        $user->email = uniqid() . '@example.test';
        $user->password = bcrypt('password');
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function node(string $slug): Navigation
    {
        $n = new Navigation();
        $n->position = (int) Navigation::max('position') + 1;
        $n->type = 'contents';
        $n->slug = $slug;
        $n->active = true;
        $n->show_in_menu = true;
        $n->members_only = false;
        $n->save();
        app(ContentTexts::class)->set('navigation_name_' . $n->id, ['en' => ucfirst($slug), 'da' => 'DK ' . $slug]);

        return $n;
    }

    private function content(Navigation $node, string $title = 'A title'): ContentItem
    {
        $c = new ContentItem();
        $c->navigation_id = $node->id;
        $c->position = 1;
        $c->members_only = false;
        $c->publish_at = now()->subDay();
        $c->save();
        app(ContentTexts::class)->set('content_title_' . $c->id, ['en' => $title, 'da' => 'DK ' . $title]);
        app(ContentTexts::class)->set('content_body_' . $c->id, ['en' => '<p>The body</p>', 'da' => '<p>DK body</p>']);

        return $c;
    }

    private function footer(string $type, array $data = []): FooterBlock
    {
        $b = new FooterBlock();
        $b->type = $type;
        $b->position = 1;
        $b->width = 4;
        $b->active = true;
        $b->data = $data ?: null;
        $b->save();
        app(ContentTexts::class)->set('footer_title_' . $b->id, ['en' => 'Contact', 'da' => 'Kontakt']);
        if ($type === 'text') {
            app(ContentTexts::class)->set('footer_body_' . $b->id, ['en' => '<p>Street 1</p>', 'da' => '<p>Vej 1</p>']);
        }

        return $b;
    }

    /** The pencils of a page: [identifier => html flag]. */
    private function pencils(string $html): array
    {
        preg_match_all('/<span class="dialog-edit-trigger"[^>]*data-dialog="([^"]+)" data-html="([01])"/', $html, $m, PREG_SET_ORDER);

        return array_column(array_map(fn($x) => [$x[1], $x[2] === '1'], $m), 1, 0);
    }

    // ---- the markup -----------------------------------------------------------------------------------

    public function test_a_pencil_is_a_span_never_a_link_inside_a_link(): void
    {
        foreach (['/projects', '/users', '/fields'] as $page) {
            $html = $this->actingAs($this->admin)->get($page)->assertOk()->getContent();

            $this->assertGreaterThan(5, substr_count($html, 'class="dialog-edit-trigger"'), $page);
            // an <a> inside an <a> is invalid: the browser closes the first and the pencil ends up loose
            $this->assertSame(0, preg_match('/<a [^>]*class="dialog-edit-trigger"/', $html), $page);
            $this->assertSame(substr_count($html, 'class="dialog-edit-trigger"'), substr_count($html, '<span class="dialog-edit-trigger"'), $page);
        }
    }

    public function test_the_pencil_can_be_reached_and_used_from_the_keyboard(): void
    {
        $html = $this->actingAs($this->admin)->get('/projects')->getContent();

        $this->assertStringContainsString('<span class="dialog-edit-trigger" role="button" tabindex="0"', $html);
        // one listener on the document, in the capture phase: it works inside links, buttons and menus, and stops the click there
        $this->assertStringContainsString("document.addEventListener('click'", $html);
        $this->assertStringContainsString("document.addEventListener('keydown'", $html);
        $this->assertStringContainsString("e.key === 'Enter'", $html);
    }

    public function test_the_menus_put_the_pencil_inside_the_item_and_the_navbar_one_at_its_corner(): void
    {
        $html = $this->actingAs($this->admin)->get('/projects')->getContent();

        $this->assertStringContainsString('body.dialog-edit-mode .navbar .dropdown-menu .dialog-edit-trigger', $html);
        $this->assertStringContainsString('body.dialog-edit-mode .navbar .dropdown-header', $html);   // headings too: they had none
        $this->assertStringContainsString('body.dialog-edit-mode .navbar .nav-link .dialog-edit-trigger', $html);
    }

    // ---- the texts of the CMS ---------------------------------------------------------------------------

    public function test_the_names_of_the_menu_have_a_pencil(): void
    {
        $about = $this->node('about-us');
        $this->content($about);

        $pencils = $this->pencils($this->actingAs($this->admin)->get('/projects')->getContent());

        $this->assertArrayHasKey('navigation_name_' . $about->id, $pencils);
        $this->assertFalse($pencils['navigation_name_' . $about->id]);   // a name is plain text
    }

    public function test_a_page_has_a_pencil_for_each_title_and_a_rich_one_for_each_text(): void
    {
        $node = $this->node('news');
        $first = $this->content($node, 'First item');
        $second = $this->content($node, 'Second item');

        $pencils = $this->pencils($this->actingAs($this->admin)->get('/news')->assertOk()->getContent());

        foreach ([$first, $second] as $c) {
            $this->assertFalse($pencils['content_title_' . $c->id]);   // plain
            $this->assertTrue($pencils['content_body_' . $c->id]);     // HTML: the rich editor
        }
        // with several contents the page's own heading is the item's name
        $this->assertArrayHasKey('navigation_name_' . $node->id, $pencils);
    }

    public function test_a_page_with_a_single_content_has_its_title_pencil_too(): void
    {
        $node = $this->node('single');
        $c = $this->content($node, 'Only one');

        $pencils = $this->pencils($this->actingAs($this->admin)->get('/single')->getContent());

        $this->assertArrayHasKey('content_title_' . $c->id, $pencils);
        $this->assertArrayHasKey('content_body_' . $c->id, $pencils);
    }

    public function test_the_footer_has_a_pencil_for_its_titles_texts_and_the_names_of_its_links(): void
    {
        $page = $this->node('privacy');
        $this->content($page);
        $text = $this->footer('text');
        $links = $this->footer('links', ['nodes' => [$page->id]]);

        $pencils = $this->pencils($this->actingAs($this->admin)->get('/projects')->getContent());

        $this->assertFalse($pencils['footer_title_' . $text->id]);
        $this->assertTrue($pencils['footer_body_' . $text->id]);
        $this->assertFalse($pencils['footer_title_' . $links->id]);
        $this->assertArrayHasKey('navigation_name_' . $page->id, $pencils);
    }

    public function test_the_management_screens_of_the_cms_have_them_too(): void
    {
        $node = $this->node('team');
        $c = $this->content($node, 'Members');
        $block = $this->footer('text');

        $tree = $this->pencils($this->actingAs($this->admin)->get('/navigation')->getContent());
        $this->assertArrayHasKey('navigation_name_' . $node->id, $tree);

        $contents = $this->pencils($this->actingAs($this->admin)->get('/contents')->getContent());
        $this->assertArrayHasKey('navigation_name_' . $node->id, $contents);
        $this->assertArrayHasKey('content_title_' . $c->id, $contents);

        $footer = $this->pencils($this->actingAs($this->admin)->get('/footer')->getContent());
        $this->assertArrayHasKey('footer_title_' . $block->id, $footer);
    }

    public function test_the_pencil_of_a_text_opens_the_values_of_every_language_and_saves_them(): void
    {
        $node = $this->node('team');
        $c = $this->content($node, 'Members');
        $identifier = 'content_title_' . $c->id;

        // what the modal fetches
        $this->actingAs($this->admin)->getJson('/dialog_values/' . $identifier)->assertOk()->assertExactJson(['en' => 'Members', 'da' => 'DK Members']);

        // what it saves
        $this->actingAs($this->admin)->post('/editdialog_inline', ['identifier' => $identifier, 'en' => 'Our members', 'da' => 'Vores medlemmer'])->assertRedirect();

        $texts = app(ContentTexts::class);
        $this->assertSame('Our members', $texts->exact($identifier, 'en'));
        $this->assertSame('Vores medlemmer', $texts->exact($identifier, 'da'));
        $this->get('/team')->assertOk();
    }

    // ---- who sees them ----------------------------------------------------------------------------------

    public function test_only_those_who_may_edit_texts_get_the_pencils(): void
    {
        $node = $this->node('team');
        $this->content($node);
        $this->footer('text');

        foreach (['user', 'project_manager'] as $role) {
            $html = $this->actingAs($this->user($role))->get('/team')->assertOk()->getContent();
            $this->assertStringNotContainsString('dialog-edit-trigger"', $html, $role);
        }

        \Illuminate\Support\Facades\Auth::logout();
        $this->assertStringNotContainsString('dialog-edit-trigger"', $this->get('/team')->assertOk()->getContent());
    }
}
