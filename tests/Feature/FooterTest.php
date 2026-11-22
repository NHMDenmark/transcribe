<?php

namespace Tests\Feature;

use App\Models\Fields;
use App\Models\FooterBlock;
use App\Models\Language;
use App\Models\Navigation;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Specimen;
use App\Models\User;
use App\Models\User_categories;
use App\Services\ContentTexts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class FooterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['en', 'dk'] as $iso) {
            $language = new Language();
            $language->iso2 = $iso;
            $language->save();
        }
        $cat = new User_categories();
        $cat->id = 5;
        $cat->category = 'level 5';
        $cat->points = 20;
        $cat->save();
    }

    private function user(string $role = 'user'): User
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

    private function block(string $type, array $attrs = [], array $title = ['en' => 'Contact', 'dk' => 'Kontakt'], array $body = ['en' => '<p>Nordre Ringgade</p>', 'dk' => '<p>DK address</p>']): FooterBlock
    {
        $b = new FooterBlock();
        $b->type = $type;
        $b->position = $attrs['position'] ?? (int) FooterBlock::max('position') + 1;
        $b->width = $attrs['width'] ?? 4;
        $b->active = $attrs['active'] ?? true;
        $b->data = $attrs['data'] ?? null;
        $b->save();

        app(ContentTexts::class)->set('footer_title_' . $b->id, $title);
        if ($type === 'text') {
            app(ContentTexts::class)->set('footer_body_' . $b->id, $body);
        }

        return $b;
    }

    private function node(string $slug, array $attrs = []): Navigation
    {
        $n = new Navigation();
        $n->position = (int) Navigation::max('position') + 1;
        $n->type = $attrs['type'] ?? 'link';
        $n->slug = $slug;
        $n->link_url = $attrs['link_url'] ?? '/projects';
        $n->active = true;
        $n->show_in_menu = $attrs['show_in_menu'] ?? false;
        $n->members_only = $attrs['members_only'] ?? false;
        $n->save();
        app(ContentTexts::class)->set('navigation_name_' . $n->id, ['en' => ucfirst($slug)]);

        return $n;
    }

    private function dom(string $html): \DOMXPath
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();

        return new \DOMXPath($doc);
    }

    // ---- what visitors see -----------------------------------------------------------------

    public function test_a_text_block_is_drawn_in_the_footer_of_every_page(): void
    {
        $this->block('text');

        $this->get('/login')->assertOk()->assertSee('site-footer', false)->assertSee('Contact')->assertSee('Nordre Ringgade');
    }

    public function test_the_footer_uses_the_visitors_language_and_the_default_one_when_it_has_no_text(): void
    {
        $block = $this->block('text', title: ['en' => 'Contact', 'dk' => ''], body: ['en' => '<p>English only</p>', 'dk' => '']);

        $this->assertSame('Contact', app(ContentTexts::class)->get('footer_title_' . $block->id, 'dk'));
        $this->assertSame('<p>English only</p>', app(ContentTexts::class)->get('footer_body_' . $block->id, 'dk'));

        // and what the footer hands the page for a Danish visitor
        $this->assertSame('Contact', app(\App\Services\SiteFooter::class)->blocks(null, 'dk')[0]['title']);
    }

    public function test_there_is_no_footer_at_all_when_there_is_nothing_to_show_a_visitor(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('site-footer', false);

        // an inactive block, and a text block with no text, are nothing either
        $this->block('text', ['active' => false]);
        $this->block('text', title: ['en' => '', 'dk' => ''], body: ['en' => '', 'dk' => '']);
        $this->get('/login')->assertDontSee('site-footer', false);
    }

    public function test_staff_always_get_a_way_into_the_footer_editor(): void
    {
        $this->actingAs($this->user('admin'))->get('/projects')->assertSee('site-footer', false)->assertSee('href="' . url('footer') . '"', false);
    }

    public function test_other_people_get_no_footer_when_it_is_empty_and_no_way_into_the_editor_when_it_is_not(): void
    {
        $this->block('text');

        $this->actingAs($this->user())->get('/projects')->assertSee('site-footer', false)->assertDontSee('href="' . url('footer') . '"', false);
    }

    public function test_blocks_come_in_their_order_with_their_width(): void
    {
        $this->block('text', ['position' => 2, 'width' => 6], ['en' => 'Second'], ['en' => '<p>b</p>']);
        $this->block('text', ['position' => 1, 'width' => 3], ['en' => 'First'], ['en' => '<p>a</p>']);

        $html = $this->get('/login')->assertSeeInOrder(['First', 'Second'])->getContent();
        $xpath = $this->dom($html);

        $this->assertSame(1, $xpath->query('//footer//div[contains(@class,"col-md-3")][.//h6[contains(.,"First")]]')->length);
        $this->assertSame(1, $xpath->query('//footer//div[contains(@class,"col-md-6")][.//h6[contains(.,"Second")]]')->length);
    }

    public function test_a_links_block_lists_the_menu_items_the_viewer_may_open(): void
    {
        $open = $this->node('privacy', ['link_url' => '/projects']);
        $external = $this->node('institution', ['link_url' => 'https://example.org/partner']);
        $members = $this->node('members-area', ['members_only' => true]);
        $heading = $this->node('heading', ['type' => 'group']);
        $this->block('links', ['data' => ['nodes' => [$open->id, $external->id, $members->id, $heading->id, 9999]]], ['en' => 'Links']);

        $xpath = $this->dom($this->get('/login')->assertOk()->getContent());
        $links = fn($x) => array_map(fn($a) => trim($a->textContent), iterator_to_array($x->query('//footer//ul//a')));

        // a visitor: not the members-only one, not a bare heading, not a node that does not exist
        $this->assertSame(['Privacy', 'Institution'], $links($xpath));
        $this->assertSame(1, $xpath->query('//footer//a[@href="https://example.org/partner"][@target="_blank"]')->length);
        $this->assertSame(0, $xpath->query('//footer//a[@href="' . url('/projects') . '"][@target="_blank"]')->length);

        // somebody logged in sees the members' one too
        $labels = array_column(array_column(app(\App\Services\SiteFooter::class)->blocks($this->user(), 'en'), 'links')[0], 'label');
        $this->assertSame(['Privacy', 'Institution', 'Members-area'], $labels);
    }

    public function test_a_links_block_with_nothing_the_viewer_may_open_is_left_out(): void
    {
        $members = $this->node('members-area', ['members_only' => true]);
        $this->block('links', ['data' => ['nodes' => [$members->id]]], ['en' => 'Only for members']);

        $this->get('/login')->assertDontSee('Only for members')->assertDontSee('site-footer', false);
    }

    public function test_the_transcription_workspace_has_no_footer(): void
    {
        $this->block('text');
        $project = new Project();
        $project->name = 'P';
        $project->user_id = 0;
        $project->active = 1;
        $project->field_view_default = 'all';
        $project->save();
        $field = new Fields();
        $field->name = 'locality';
        $field->type = 'text';
        $field->position = 1;
        $field->min_user_level = 5;
        $field->autocomplete = 0;
        $field->save();
        $project->fields()->attach($field->id);
        Specimen::create(['specimen_id' => '999090', 'project_id' => $project->id, 'images' => 'a.jpg']);

        $this->actingAs($this->user());
        $this->get('/projects')->assertSee('site-footer', false);
        $this->get('/projects/' . $project->id . '/999090')->assertOk()->assertDontSee('site-footer', false);
    }

    // ---- who may edit ----------------------------------------------------------------------

    public function test_only_root_and_admin_may_edit_the_footer(): void
    {
        $block = $this->block('text');

        foreach (['user', 'project_manager'] as $role) {
            $viewer = $this->user($role);
            $this->actingAs($viewer)->get('/footer')->assertForbidden();
            $this->actingAs($viewer)->get('/footer/create')->assertForbidden();
            $this->actingAs($viewer)->get('/footer/' . $block->id . '/edit')->assertForbidden();
            $this->actingAs($viewer)->post('/footer', ['type' => 'text', 'width' => 4, 'titles' => ['en' => 'X']])->assertForbidden();
            $this->actingAs($viewer)->delete('/footer/' . $block->id)->assertForbidden();
            $this->actingAs($viewer)->post('/footer/sort', ['ids' => [$block->id]])->assertForbidden();
        }

        $this->assertNotNull(FooterBlock::find($block->id));
        $this->actingAs($this->user('admin'))->get('/footer')->assertOk();
        $this->actingAs($this->user('root'))->get('/footer')->assertOk();
        Auth::logout();
        $this->get('/footer')->assertRedirect(route('login'));
    }

    // ---- editing ---------------------------------------------------------------------------

    public function test_a_text_block_is_created_with_its_texts_per_language(): void
    {
        $this->actingAs($this->user('admin'))->post('/footer', [
            'type' => 'text', 'width' => 6, 'active' => 1,
            'titles' => ['en' => '<b>Find</b> us', 'dk' => 'Find os'],
            'bodies' => ['en' => '<p>Street <em>1</em></p>', 'dk' => ''],
        ])->assertRedirect('/footer');

        $block = FooterBlock::firstOrFail();
        $this->assertSame('text', $block->type);
        $this->assertSame(6, (int) $block->width);
        $this->assertTrue($block->active);

        $texts = app(ContentTexts::class);
        $this->assertSame('Find us', $texts->exact('footer_title_' . $block->id, 'en'));    // titles are plain text
        $this->assertStringContainsString('<em>1</em>', $texts->exact('footer_body_' . $block->id, 'en'));
    }

    public function test_a_links_block_keeps_the_chosen_items(): void
    {
        $a = $this->node('privacy');
        $b = $this->node('terms');

        $this->actingAs($this->user('admin'))->post('/footer', [
            'type' => 'links', 'width' => 4, 'active' => 1, 'titles' => ['en' => 'Legal'], 'nodes' => [$a->id, $b->id, $a->id],
        ])->assertRedirect('/footer');

        $this->assertSame([$a->id, $b->id], FooterBlock::firstOrFail()->data['nodes']);
    }

    public function test_the_block_must_have_something_in_it_and_a_known_width(): void
    {
        $admin = $this->user('admin');
        $node = $this->node('privacy');

        $this->actingAs($admin)->post('/footer', ['type' => 'text', 'width' => 4, 'titles' => ['en' => ''], 'bodies' => ['en' => '<p></p>']])->assertSessionHasErrors('bodies');
        $this->actingAs($admin)->post('/footer', ['type' => 'links', 'width' => 4, 'titles' => ['en' => 'L']])->assertSessionHasErrors('nodes');
        $this->actingAs($admin)->post('/footer', ['type' => 'links', 'width' => 4, 'titles' => ['en' => 'L'], 'nodes' => [99999]])->assertSessionHasErrors('nodes.0');
        $this->actingAs($admin)->post('/footer', ['type' => 'text', 'width' => 5, 'titles' => ['en' => 'T']])->assertSessionHasErrors('width');
        $this->actingAs($admin)->post('/footer', ['type' => 'poem', 'width' => 4, 'titles' => ['en' => 'T']])->assertSessionHasErrors('type');
        $this->assertSame(0, FooterBlock::count());
    }

    public function test_editing_keeps_the_kind_and_the_position(): void
    {
        $admin = $this->user('admin');
        $block = $this->block('text', ['position' => 7]);

        $this->actingAs($admin)->put('/footer/' . $block->id, [
            'type' => 'links', 'width' => 12, 'titles' => ['en' => 'New title'], 'bodies' => ['en' => '<p>New</p>'],
        ])->assertRedirect('/footer');

        $block->refresh();
        $this->assertSame('text', $block->type);
        $this->assertSame(7, (int) $block->position);
        $this->assertSame(12, (int) $block->width);
        $this->assertFalse($block->active);   // the "shown" box was not ticked
        $this->assertSame('New title', app(ContentTexts::class)->exact('footer_title_' . $block->id, 'en'));
    }

    public function test_dragging_reorders_the_blocks(): void
    {
        $admin = $this->user('admin');
        $a = $this->block('text', [], ['en' => 'A']);
        $b = $this->block('text', [], ['en' => 'B']);
        $c = $this->block('text', [], ['en' => 'C']);

        $this->actingAs($admin)->postJson('/footer/sort', ['ids' => [$c->id, $a->id, $b->id]])->assertOk();

        $this->assertSame([$c->id, $a->id, $b->id], FooterBlock::orderBy('position')->pluck('id')->all());
        $this->actingAs($admin)->postJson('/footer/sort', ['ids' => [99999]])->assertStatus(422);
    }

    public function test_deleting_a_block_removes_its_texts(): void
    {
        $block = $this->block('text');

        $this->actingAs($this->user('admin'))->delete('/footer/' . $block->id)->assertRedirect('/footer');

        $this->assertNull(FooterBlock::find($block->id));
        $this->assertSame('', app(ContentTexts::class)->exact('footer_title_' . $block->id, 'en'));
        $this->assertSame('', app(ContentTexts::class)->exact('footer_body_' . $block->id, 'en'));
    }

    // ---- the editor screens ----------------------------------------------------------------

    public function test_the_footer_screen_lists_the_blocks_and_the_forms_have_working_markup(): void
    {
        $admin = $this->user('admin');
        $node = $this->node('privacy');
        $text = $this->block('text', ['width' => 6]);
        $links = $this->block('links', ['data' => ['nodes' => [$node->id]]], ['en' => 'Legal']);

        $xpath = $this->dom($this->actingAs($admin)->get('/footer')->assertOk()->assertSee('Contact')->assertSee('Legal')->getContent());
        $this->assertSame(2, $xpath->query('//ul[@id="footer-blocks"]/li[@data-id]')->length);

        // a text block: width, shown, texts with an editor per language; no list of menu items
        $form = '//form[contains(@action,"/footer")]';
        $xpath = $this->dom($this->actingAs($admin)->get('/footer/create?type=text')->assertOk()->getContent());
        $this->assertSame('text', $xpath->query($form . '//input[@name="type"]')->item(0)->getAttribute('value'));
        $this->assertSame(1, $xpath->query($form . '//select[@name="width"]')->length);
        $this->assertSame(1, $xpath->query($form . '//input[@name="active"][@type="checkbox"]')->length);
        $this->assertSame(1, $xpath->query($form . '//input[@name="titles[dk]"]')->length);
        $this->assertSame(1, $xpath->query($form . '//textarea[@name="bodies[en]"]')->length);
        $this->assertSame(0, $xpath->query($form . '//input[@name="nodes[]"]')->length);

        // a links block: the menu items to choose from (not the bare headings), the chosen one ticked
        $heading = $this->node('heading', ['type' => 'group']);
        $xpath = $this->dom($this->actingAs($admin)->get('/footer/' . $links->id . '/edit')->assertOk()->getContent());
        $this->assertSame(1, $xpath->query($form . '//input[@name="nodes[]"][@value="' . $node->id . '"][@checked]')->length);
        $this->assertSame(0, $xpath->query($form . '//input[@name="nodes[]"][@value="' . $heading->id . '"]')->length);
        $this->assertSame(0, $xpath->query($form . '//textarea')->length);
        $this->assertSame(1, $xpath->query($form . '//button[@type="submit"]')->length);
    }
}
