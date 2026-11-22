<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Models\Language;
use App\Models\Navigation;
use App\Models\Setting;
use App\Models\User;
use App\Models\User_categories;
use App\Services\ContentTexts;
use App\Services\SiteNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ContentManagementTest extends TestCase
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

    /** A menu item. A "contents" item gets no content: add them with content(). */
    private function item(string $slug, array $attrs = [], ?Navigation $parent = null): Navigation
    {
        $n = new Navigation();
        $n->parent_id = $parent?->id;
        $n->position = (int) Navigation::where('parent_id', $parent?->id)->max('position') + 1;
        $n->type = $attrs['type'] ?? 'contents';
        $n->slug = $slug;
        $n->sort_mode = $attrs['sort_mode'] ?? 'manual';
        $n->link_url = $attrs['link_url'] ?? null;
        $n->active = $attrs['active'] ?? true;
        $n->show_in_menu = $attrs['show_in_menu'] ?? true;
        $n->members_only = $attrs['members_only'] ?? false;
        $n->save();

        app(ContentTexts::class)->set('navigation_name_' . $n->id, ['en' => ucfirst($slug), 'dk' => 'DK ' . $slug]);

        return $n;
    }

    /** A content; live from yesterday unless said otherwise (publish_at => null makes it a draft). */
    private function content(?Navigation $node, array $attrs = [], string $title = 'Title', string $body = '<p>Body</p>'): ContentItem
    {
        $c = new ContentItem();
        $c->navigation_id = $node?->id;
        $c->position = (int) ContentItem::where('navigation_id', $node?->id)->max('position') + 1;
        $c->members_only = $attrs['members_only'] ?? false;
        $c->publish_at = array_key_exists('publish_at', $attrs) ? $attrs['publish_at'] : now()->subDay();
        $c->archive_at = $attrs['archive_at'] ?? null;
        $c->save();

        app(ContentTexts::class)->set('content_title_' . $c->id, ['en' => $title, 'dk' => 'DK ' . $title]);
        app(ContentTexts::class)->set('content_body_' . $c->id, ['en' => $body, 'dk' => '<p>DK ' . strip_tags($body) . '</p>']);

        return $c;
    }

    /** A contents item with one live content, in one go. */
    private function page(string $slug, array $node = [], array $content = [], ?Navigation $parent = null, string $title = 'Title', string $body = '<p>Body</p>'): Navigation
    {
        $n = $this->item($slug, $node + ['type' => 'contents'], $parent);
        $this->content($n, $content, $title, $body);

        return $n;
    }

    /** What the next request would see: the texts and the tree are re-read, as they are on every real request. */
    private function freshRequest(): void
    {
        foreach ([\App\Services\DialogTextRepository::class, ContentTexts::class, SiteNavigation::class] as $service) {
            $this->app->forgetInstance($service);
        }
    }

    private function dom(string $html): \DOMXPath
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();

        return new \DOMXPath($doc);
    }

    // ---- what the migration carried over ---------------------------------------------------

    public function test_the_old_menu_is_carried_over_as_ordinary_items_and_contents(): void
    {
        $this->assertSame(['projects', 'about', 'tutorial'], Navigation::orderBy('position')->pluck('slug')->all());
        $this->assertSame('link', Navigation::where('slug', 'projects')->value('type'));

        $about = Navigation::where('slug', 'about')->firstOrFail();
        $this->assertSame('contents', $about->type);
        $this->assertSame(1, $about->contents()->count());
        $this->assertNotNull($about->contents()->first()->publish_at);
    }

    // ---- serving ---------------------------------------------------------------------------

    public function test_a_public_content_is_shown_to_a_visitor_even_when_the_platform_requires_a_login(): void
    {
        $this->assertSame('login_required', Setting::current()->anonymousAccess());
        $this->page('welcome', title: 'Welcome here', body: '<p>Hello world</p>');

        $this->get('/welcome')->assertOk()->assertSee('Welcome here')->assertSee('Hello world', false);
    }

    public function test_the_visitors_language_is_used_and_the_default_one_when_it_has_no_text(): void
    {
        $node = $this->page('welcome', title: 'Welcome');
        $content = $node->contents()->first();

        $this->get('/welcome')->assertSee('Welcome');

        app(ContentTexts::class)->set('content_title_' . $content->id, ['dk' => '']);
        $this->freshRequest();
        $this->assertSame('Welcome', app(ContentTexts::class)->get('content_title_' . $content->id, 'dk'));
    }

    public function test_items_can_sit_at_any_depth_and_the_address_follows_the_tree(): void
    {
        $a = $this->item('team', ['type' => 'group']);
        $b = $this->item('people', ['type' => 'group'], $a);
        $c = $this->page('anna', parent: $b, title: 'Anna page');

        $this->get('/team/people/anna')->assertOk()->assertSee('Anna page');
        $this->assertSame('team/people/anna', app(SiteNavigation::class)->path($c->id));
        $this->get('/anna')->assertNotFound();
    }

    public function test_an_unknown_address_is_a_404(): void
    {
        $this->get('/nothing-here')->assertNotFound();
    }

    public function test_a_group_leads_to_its_first_item_the_visitor_may_open(): void
    {
        $g = $this->item('guide', ['type' => 'group']);
        $this->page('secret', ['members_only' => true], parent: $g);   // not for a visitor
        $this->page('intro', parent: $g, title: 'Intro page');

        $this->get('/guide')->assertRedirect('/guide/intro');
    }

    public function test_a_link_redirects_to_where_it_points(): void
    {
        $this->item('docs', ['type' => 'link', 'link_url' => 'https://example.org/docs']);
        $this->item('go', ['type' => 'link', 'link_url' => '/projects']);

        $this->get('/docs')->assertRedirect('https://example.org/docs');
        $this->get('/go')->assertRedirect(url('/projects'));
    }

    // ---- who may see what ------------------------------------------------------------------

    public function test_a_content_for_logged_in_users_only_asks_a_visitor_to_log_in(): void
    {
        $this->page('open', title: 'Open one');
        $this->page('members', content: ['members_only' => true], title: 'Members one');

        $this->get('/open')->assertOk();
        $this->get('/members')->assertRedirect(route('login'));

        $user = $this->user();
        $this->actingAs($user)->get('/members')->assertOk()->assertSee('Members one');
        $this->actingAs($this->user('admin'))->get('/members')->assertOk();
    }

    public function test_a_menu_item_for_logged_in_users_only_hides_everything_under_it(): void
    {
        $parent = $this->item('secret-area', ['type' => 'group', 'members_only' => true]);
        $this->page('inside', parent: $parent);

        $this->get('/secret-area/inside')->assertRedirect(route('login'));
        $this->actingAs($this->user())->get('/secret-area/inside')->assertOk();
    }

    public function test_an_inactive_item_is_gone_for_everyone_but_staff(): void
    {
        $this->page('off', ['active' => false]);

        $this->get('/off')->assertNotFound();
        $this->actingAs($this->user())->get('/off')->assertNotFound();
        $this->actingAs($this->user('admin'))->get('/off')->assertOk();
    }

    public function test_an_item_with_no_content_is_empty_for_visitors_and_open_to_staff(): void
    {
        $this->item('bare');

        $this->get('/bare')->assertNotFound();
        $this->actingAs($this->user('admin'))->get('/bare')->assertOk()->assertSee('empty');
    }

    public function test_publish_and_archive_dates_and_drafts(): void
    {
        $this->page('soon', content: ['publish_at' => now()->addDay()]);
        $this->page('live', content: ['publish_at' => now()->subDay(), 'archive_at' => now()->addDay()]);
        $this->page('old', content: ['publish_at' => now()->subDays(5), 'archive_at' => now()->subDay()]);
        $this->page('draft', content: ['publish_at' => null]);

        foreach (['soon', 'old', 'draft'] as $slug) {
            $this->get('/' . $slug)->assertNotFound();
        }
        $this->get('/live')->assertOk();

        // staff open all of them and are told why they are not public
        $admin = $this->user('admin');
        $this->actingAs($admin)->get('/soon')->assertOk()->assertSee('Not public yet')->assertSee('scheduled');
        $this->actingAs($admin)->get('/old')->assertOk()->assertSee('archived');
        $this->actingAs($admin)->get('/draft')->assertOk()->assertSee('draft');
    }

    public function test_one_content_gives_the_page_its_title_and_several_are_listed_in_order(): void
    {
        $solo = $this->page('solo', title: 'Only one');
        $this->get('/solo')->assertOk()->assertSee('<h1>Only one</h1>', false);

        $many = $this->item('many');
        $this->content($many, title: 'First part');
        $this->content($many, title: 'Second part');
        $this->content($many, ['publish_at' => null], title: 'Hidden draft');
        $this->freshRequest();   // the texts of the first request are memoised; a real second request re-reads them

        $this->get('/many')->assertOk()
            ->assertSee('<h1>Many</h1>', false)
            ->assertSeeInOrder(['First part', 'Second part'])
            ->assertDontSee('Hidden draft');

        // the draft is there for staff
        $this->actingAs($this->user('admin'))->get('/many')->assertSee('Hidden draft');
    }

    public function test_contents_the_visitor_may_not_read_are_left_out_of_a_page_that_has_others(): void
    {
        $node = $this->item('mixed');
        $this->content($node, title: 'For everybody');
        $this->content($node, ['members_only' => true], title: 'For members');

        $this->get('/mixed')->assertOk()->assertSee('For everybody')->assertDontSee('For members');
        $this->actingAs($this->user())->get('/mixed')->assertSee('For everybody')->assertSee('For members');
    }

    public function test_staff_get_an_edit_button_and_other_people_do_not(): void
    {
        $node = $this->page('welcome');
        $content = $node->contents()->first();

        $this->actingAs($this->user('admin'))->get('/welcome')->assertSee('contents/' . $content->id . '/edit', false);
        $this->actingAs($this->user())->get('/welcome')->assertDontSee('contents/' . $content->id . '/edit', false);
    }

    // ---- the menu --------------------------------------------------------------------------

    public function test_the_menu_shows_each_person_only_what_they_may_open(): void
    {
        $this->page('open-page');
        $this->page('members-page', ['members_only' => true]);
        $this->item('draft-page');
        $this->page('hidden-from-menu', ['show_in_menu' => false]);

        $labels = fn($user) => array_column(app(SiteNavigation::class)->menu($user, 'en'), 'label');

        $this->assertContains('Open-page', $labels(null));
        $this->assertNotContains('Members-page', $labels(null));
        $this->assertContains('Members-page', $labels($this->user()));
        $this->assertNotContains('Draft-page', $labels($this->user()));
        $this->assertContains('Draft-page', $labels($this->user('admin')));
        $this->assertNotContains('Hidden-from-menu', $labels($this->user('admin')));
    }

    public function test_an_open_item_whose_contents_all_need_a_login_stays_in_the_menu_and_leads_to_the_login(): void
    {
        $this->page('reserved', content: ['members_only' => true], title: 'For members');
        $this->page('hidden-by-item', ['members_only' => true]);            // the item itself is for logged-in users
        $this->page('switched-off', ['active' => false], ['members_only' => true]);
        $this->item('nothing-yet');                                          // no contents at all
        $draftOnly = $this->item('only-a-draft');
        $this->content($draftOnly, ['publish_at' => null, 'members_only' => true]);

        $labels = fn($user) => array_column(app(SiteNavigation::class)->menu($user, 'en'), 'label');

        // a visitor sees the one that logging in would open, and nothing else of these
        $visitor = $labels(null);
        $this->assertContains('Reserved', $visitor);
        foreach (['Hidden-by-item', 'Switched-off', 'Nothing-yet', 'Only-a-draft'] as $hidden) {
            $this->assertNotContains($hidden, $visitor, $hidden);
        }

        // clicking it asks for the login, and a logged-in user gets the page
        $this->get('/reserved')->assertRedirect(route('login'));
        $this->actingAs($this->user())->get('/reserved')->assertOk()->assertSee('For members');
    }

    public function test_a_group_holding_only_items_that_need_a_login_stays_in_the_menu(): void
    {
        $g = $this->item('area', ['type' => 'group']);
        $this->page('inside', content: ['members_only' => true], parent: $g);

        $menu = collect(app(SiteNavigation::class)->menu(null, 'en'));

        $this->assertSame(['Inside'], array_column($menu->firstWhere('label', 'Area')['children'], 'label'));
        // the group itself leads to its first item, which asks for the login
        $this->get('/area')->assertRedirect('/area/inside');
        $this->get('/area/inside')->assertRedirect(route('login'));
    }

    public function test_the_footer_lists_such_an_item_too(): void
    {
        $node = $this->page('reserved', content: ['members_only' => true]);
        $block = new \App\Models\FooterBlock();
        $block->type = 'links';
        $block->position = 1;
        $block->width = 4;
        $block->active = true;
        $block->data = ['nodes' => [$node->id]];
        $block->save();

        $links = app(\App\Services\SiteFooter::class)->blocks(null, 'en')[0]['links'] ?? [];

        $this->assertSame(['Reserved'], array_column($links, 'label'));
    }
    public function test_staff_are_warned_in_the_menu_only_about_what_is_not_public(): void
    {
        $this->page('fine');
        $this->item('bare');

        $menu = collect(app(SiteNavigation::class)->menu($this->user('admin'), 'en'));

        $this->assertNull($menu->firstWhere('label', 'Fine')['state']);
        $this->assertSame('empty', $menu->firstWhere('label', 'Bare')['state']);
    }

    public function test_a_group_with_nothing_to_show_is_left_out_of_the_menu(): void
    {
        $g = $this->item('empty-group', ['type' => 'group']);
        $this->page('members-only', ['members_only' => true], parent: $g);

        $this->assertNotContains('Empty-group', array_column(app(SiteNavigation::class)->menu(null, 'en'), 'label'));
        $this->assertContains('Empty-group', array_column(app(SiteNavigation::class)->menu($this->user(), 'en'), 'label'));
    }

    public function test_the_menu_is_nested_to_any_depth_and_uses_the_visitors_language(): void
    {
        $a = $this->item('a', ['type' => 'group']);
        $b = $this->item('b', ['type' => 'group'], $a);
        $this->page('c', parent: $b);

        $top = collect(app(SiteNavigation::class)->menu(null, 'dk'))->firstWhere('label', 'DK a');

        $this->assertSame('DK b', $top['children'][0]['label']);
        $this->assertSame('DK c', $top['children'][0]['children'][0]['label']);
    }

    public function test_the_layout_draws_the_tree(): void
    {
        $this->actingAs($this->user())->get('/projects')->assertSee('/about', false);
    }

    // ---- who may edit ----------------------------------------------------------------------

    public function test_only_root_and_admin_may_manage_the_menu_and_the_contents(): void
    {
        $content = $this->content(null);

        foreach (['user', 'project_manager'] as $role) {
            $viewer = $this->user($role);
            $this->actingAs($viewer)->get('/navigation')->assertForbidden();
            $this->actingAs($viewer)->get('/navigation/create')->assertForbidden();
            $this->actingAs($viewer)->post('/navigation', ['type' => 'group', 'labels' => ['en' => 'X']])->assertForbidden();
            $this->actingAs($viewer)->post('/navigation/sort', ['items' => []])->assertForbidden();
            $this->actingAs($viewer)->get('/contents/create')->assertForbidden();
            $this->actingAs($viewer)->get('/contents/' . $content->id . '/edit')->assertForbidden();
            $this->actingAs($viewer)->post('/contents', ['titles' => ['en' => 'X']])->assertForbidden();
            $this->actingAs($viewer)->delete('/contents/' . $content->id)->assertForbidden();
            $this->actingAs($viewer)->post('/contents/sort', ['items' => []])->assertForbidden();
        }

        $this->assertNotNull(ContentItem::find($content->id));
        $this->actingAs($this->user('admin'))->get('/navigation')->assertOk();
        $this->actingAs($this->user('root'))->get('/contents/create')->assertOk();
    }

    public function test_a_visitor_is_sent_to_the_login_page_by_the_editors(): void
    {
        $this->get('/navigation')->assertRedirect(route('login'));
        $this->get('/contents/create')->assertRedirect(route('login'));
        $this->postJson('/navigation/sort', ['items' => []])->assertUnauthorized();
        $this->postJson('/contents/sort', ['items' => []])->assertUnauthorized();
    }

    // ---- creating and editing menu items ---------------------------------------------------

    public function test_a_new_menu_item_is_created_with_its_names(): void
    {
        $this->actingAs($this->user('admin'))->post('/navigation', [
            'type' => 'contents', 'active' => 1, 'show_in_menu' => 1, 'members_only' => 1,
            'labels' => ['en' => 'Our team', 'dk' => 'Vores hold'],
        ])->assertRedirect('/navigation');

        $node = Navigation::where('slug', 'our-team')->firstOrFail();
        $this->assertSame('contents', $node->type);
        $this->assertTrue($node->members_only);
        $this->assertSame('Vores hold', app(ContentTexts::class)->exact('navigation_name_' . $node->id, 'dk'));
    }

    public function test_the_address_is_made_from_the_name_and_a_taken_one_gets_a_number(): void
    {
        $admin = $this->user('admin');
        $make = fn() => $this->actingAs($admin)->post('/navigation', ['type' => 'group', 'labels' => ['en' => 'Resources']]);

        $make();
        $make();

        $this->assertSame(['resources', 'resources-2'], Navigation::whereIn('slug', ['resources', 'resources-2'])->orderBy('id')->pluck('slug')->all());
    }

    public function test_a_typed_address_that_is_taken_or_reserved_is_refused(): void
    {
        $admin = $this->user('admin');
        $this->item('taken');

        $post = fn($slug) => $this->actingAs($admin)->post('/navigation', ['type' => 'group', 'labels' => ['en' => 'X'], 'slug' => $slug]);

        $post('taken')->assertSessionHasErrors('slug');
        $post('users')->assertSessionHasErrors('slug');        // something the application already answers
        $post('contents')->assertSessionHasErrors('slug');     // so is the content editor
        $post('Not Valid!')->assertSessionHasErrors('slug');
    }

    public function test_a_name_in_at_least_one_language_is_required_and_a_link_needs_an_address(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post('/navigation', ['type' => 'group', 'labels' => ['en' => '', 'dk' => '']])->assertSessionHasErrors('labels');
        $this->actingAs($admin)->post('/navigation', ['type' => 'link', 'labels' => ['en' => 'L']])->assertSessionHasErrors('link_url');
        $this->actingAs($admin)->post('/navigation', ['type' => 'link', 'labels' => ['en' => 'L'], 'link_url' => 'javascript:alert(1)'])->assertSessionHasErrors('link_url');
        $this->actingAs($admin)->post('/navigation', ['type' => 'page', 'labels' => ['en' => 'L']])->assertSessionHasErrors('type');
    }

    public function test_names_are_plain_text(): void
    {
        $this->actingAs($this->user('admin'))->post('/navigation', ['type' => 'group', 'labels' => ['en' => '<script>x</script>Safe name']]);

        $node = Navigation::latest('id')->firstOrFail();
        $this->assertSame('xSafe name', app(ContentTexts::class)->exact('navigation_name_' . $node->id, 'en'));
    }

    public function test_editing_an_item_cannot_change_its_kind(): void
    {
        $admin = $this->user('admin');
        $node = $this->item('welcome');

        $this->actingAs($admin)->put('/navigation/' . $node->id, [
            'type' => 'group',   // ignored: the kind is fixed once created
            'members_only' => 1, 'active' => 1, 'show_in_menu' => 1, 'slug' => 'welcome', 'labels' => ['en' => 'Welcome'],
        ])->assertRedirect('/navigation');

        $node->refresh();
        $this->assertSame('contents', $node->type);
        $this->assertTrue($node->members_only);
    }

    public function test_an_item_cannot_be_put_under_itself_or_under_a_link(): void
    {
        $admin = $this->user('admin');
        $parent = $this->item('parent', ['type' => 'group']);
        $child = $this->item('child', ['type' => 'group'], $parent);
        $link = $this->item('somewhere', ['type' => 'link', 'link_url' => '/projects']);
        $body = fn($parentId) => ['labels' => ['en' => 'Parent'], 'parent_id' => $parentId, 'slug' => 'parent'];

        $this->actingAs($admin)->put('/navigation/' . $parent->id, $body($child->id))->assertSessionHasErrors('parent_id');
        $this->actingAs($admin)->put('/navigation/' . $parent->id, $body($parent->id))->assertSessionHasErrors('parent_id');
        $this->actingAs($admin)->put('/navigation/' . $parent->id, $body($link->id))->assertSessionHasErrors('parent_id');
    }

    // ---- creating and editing contents -----------------------------------------------------

    public function test_publish_now_publishes_a_content(): void
    {
        $node = $this->item('team');

        $this->actingAs($this->user('admin'))->post('/contents', [
            'navigation_id' => $node->id, 'publish_now' => 1, 'titles' => ['en' => 'Hello'],
        ]);

        $content = ContentItem::latest('id')->firstOrFail();
        $this->assertNotNull($content->publish_at);
        Auth::logout();
        $this->get('/team')->assertOk()->assertSee('Hello');
    }

    // ---- dragging --------------------------------------------------------------------------

    public function test_dragging_moves_items_and_renumbers_them(): void
    {
        $admin = $this->user('admin');
        $a = $this->item('aa', ['type' => 'group']);
        $b = $this->page('bb');
        $c = $this->page('cc');

        $this->actingAs($admin)->postJson('/navigation/sort', ['items' => [
            ['id' => $c->id, 'parent_id' => null, 'position' => 0],
            ['id' => $a->id, 'parent_id' => null, 'position' => 1],
            ['id' => $b->id, 'parent_id' => $a->id, 'position' => 0],   // b goes under a
        ]])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame($a->id, (int) $b->fresh()->parent_id);
        $this->assertSame('aa/bb', app(SiteNavigation::class)->path($b->id));
        $this->get('/aa/bb')->assertOk();
        $this->get('/bb')->assertNotFound();   // the old address is gone
        $this->assertSame(0, (int) $c->fresh()->position);
    }

    public function test_dragging_refuses_loops_links_with_children_and_clashing_addresses(): void
    {
        $admin = $this->user('admin');
        $a = $this->item('aa', ['type' => 'group']);
        $b = $this->item('bb', ['type' => 'group'], $a);
        $link = $this->item('ll', ['type' => 'link', 'link_url' => '/projects']);
        $x = $this->item('same');
        $g = $this->item('holder', ['type' => 'group']);
        $y = $this->item('same', [], $g);

        $this->actingAs($admin)->postJson('/navigation/sort', ['items' => [['id' => $a->id, 'parent_id' => $b->id, 'position' => 0]]])->assertStatus(422);
        $this->actingAs($admin)->postJson('/navigation/sort', ['items' => [['id' => $x->id, 'parent_id' => $link->id, 'position' => 0]]])->assertStatus(422);
        $this->actingAs($admin)->postJson('/navigation/sort', ['items' => [['id' => $y->id, 'parent_id' => null, 'position' => 5]]])->assertStatus(422);

        $this->assertNull($a->fresh()->parent_id);
        $this->assertSame($g->id, (int) $y->fresh()->parent_id);
    }

    public function test_a_content_cannot_be_dragged_onto_a_group_or_a_link(): void
    {
        $admin = $this->user('admin');
        $group = $this->item('heading', ['type' => 'group']);
        $content = $this->content(null);

        $this->actingAs($admin)->postJson('/contents/sort', ['items' => [['id' => $content->id, 'navigation_id' => $group->id, 'position' => 0]]])->assertStatus(422);
        $this->assertNull($content->fresh()->navigation_id);
    }

    // ---- the editor screens ----------------------------------------------------------------

    public function test_the_menu_item_form_opens_for_each_kind_with_working_markup(): void
    {
        $admin = $this->user('admin');
        $parent = $this->item('parent', ['type' => 'group']);

        foreach (['contents', 'group', 'link'] as $type) {
            $html = $this->actingAs($admin)->get('/navigation/create?type=' . $type . '&parent_id=' . $parent->id)->assertOk()->getContent();
            $xpath = $this->dom($html);

            $form = '//form[contains(@action,"/navigation")][.//input[@name="type"]]';
            $this->assertSame($type, $xpath->query($form . '//input[@name="type"]')->item(0)->getAttribute('value'));
            $this->assertSame(1, $xpath->query($form . '//select[@name="parent_id"]')->length);
            $this->assertSame(1, $xpath->query($form . '//select[@name="parent_id"]/option[@selected][@value="' . $parent->id . '"]')->length);
            $this->assertSame(1, $xpath->query($form . '//input[@name="members_only"][@type="checkbox"]')->length);
            $this->assertSame(1, $xpath->query($form . '//input[@name="labels[en]"]')->length);
            $this->assertSame(1, $xpath->query($form . '//input[@name="labels[dk]"]')->length);
            $this->assertSame($type === 'link' ? 1 : 0, $xpath->query($form . '//input[@name="link_url"]')->length);
            $this->assertSame(1, $xpath->query($form . '//button[@type="submit"]')->length);

            // no script, no visibility selector, and no piece of the page leaking out of the form
            $this->assertStringNotContainsString('onchange', $html);
            $this->assertSame(0, $xpath->query('//select[@name="visibility"]')->length);
        }
    }

    public function test_the_menu_item_form_opens_for_an_existing_item(): void
    {
        $node = $this->item('welcome');

        $this->actingAs($this->user('admin'))->get('/navigation/' . $node->id . '/edit')->assertOk()
            ->assertSee('value="Welcome"', false);
    }

    public function test_the_content_form_has_everything_needed_to_write_and_assign_a_content(): void
    {
        $admin = $this->user('admin');
        $node = $this->item('team');
        $group = $this->item('heading', ['type' => 'group']);

        $html = $this->actingAs($admin)->get('/contents/create?navigation_id=' . $node->id)->assertOk()->getContent();
        $xpath = $this->dom($html);
        $form = '//form[contains(@action,"/contents")]';

        $this->assertSame(1, $xpath->query($form . '//select[@name="navigation_id"]/option[@selected][@value="' . $node->id . '"]')->length);
        // only the "contents" items can be chosen
        $this->assertSame(0, $xpath->query($form . '//select[@name="navigation_id"]/option[@value="' . $group->id . '"]')->length);
        $this->assertSame(1, $xpath->query($form . '//input[@name="members_only"][@type="checkbox"]')->length);
        $this->assertSame(1, $xpath->query($form . '//input[@name="publish_at"]')->length);
        $this->assertSame(1, $xpath->query($form . '//input[@name="archive_at"]')->length);
        $this->assertSame(1, $xpath->query($form . '//input[@name="publish_now"]')->length);
        foreach (['en', 'dk'] as $iso) {
            $this->assertSame(1, $xpath->query($form . '//input[@name="titles[' . $iso . ']"]')->length);
            $this->assertSame(1, $xpath->query($form . '//textarea[@name="bodies[' . $iso . ']"]')->length);
        }
        $this->assertSame(1, $xpath->query($form . '//button[@type="submit"]')->length);
        $this->assertSame(0, $xpath->query('//select[@name="visibility"]')->length);
    }

    public function test_the_content_form_opens_for_an_existing_content(): void
    {
        $content = $this->content($this->item('team'), title: 'Existing title', body: '<p>Existing body</p>');

        $this->actingAs($this->user('admin'))->get('/contents/' . $content->id . '/edit')->assertOk()
            ->assertSee('value="Existing title"', false)
            ->assertSee('Existing body');
    }

    // ---- contents belong to an item --------------------------------------------------------

    public function test_a_new_content_starts_as_a_draft_and_keeps_its_html(): void
    {
        $node = $this->item('team');
        $admin = $this->user('admin');

        $this->actingAs($admin)->post('/contents', [
            'navigation_id' => $node->id, 'members_only' => 1,
            'titles' => ['en' => '<b>Meet</b> the team', 'dk' => 'Mød holdet'],
            'bodies' => ['en' => '<p>We are <b>many</b></p>', 'dk' => ''],
        ])->assertRedirect('/contents');

        $content = ContentItem::latest('id')->firstOrFail();
        $this->assertSame($node->id, (int) $content->navigation_id);
        $this->assertNull($content->publish_at);
        $this->assertTrue($content->members_only);
        $this->assertSame($admin->id, (int) $content->created_by);

        $texts = app(ContentTexts::class);
        $this->assertSame('Meet the team', $texts->exact('content_title_' . $content->id, 'en'));
        $this->assertStringContainsString('<b>many</b>', $texts->exact('content_body_' . $content->id, 'en'));

        // a draft: not for visitors, open to staff
        Auth::logout();
        $this->get('/team')->assertNotFound();
        $this->actingAs($this->user('root'))->get('/team')->assertOk();
    }

    public function test_a_content_must_be_placed_under_a_contents_item(): void
    {
        $admin = $this->user('admin');
        $group = $this->item('heading', ['type' => 'group']);

        // there is no such thing as a content without a place
        $this->actingAs($admin)->post('/contents', ['titles' => ['en' => 'T']])->assertSessionHasErrors('navigation_id');
        $this->actingAs($admin)->post('/contents', ['navigation_id' => $group->id, 'titles' => ['en' => 'T']])->assertSessionHasErrors('navigation_id');
        $this->assertSame(ContentItem::count(), ContentItem::whereIn('navigation_id', Navigation::pluck('id'))->count());
    }

    public function test_a_content_needs_a_title_and_sensible_dates(): void
    {
        $admin = $this->user('admin');
        $node = $this->item('team');
        $before = ContentItem::count();

        $this->actingAs($admin)->post('/contents', ['navigation_id' => $node->id, 'titles' => ['en' => '', 'dk' => '']])->assertSessionHasErrors('titles');
        $this->actingAs($admin)->post('/contents', [
            'navigation_id' => $node->id, 'titles' => ['en' => 'T'], 'publish_at' => '2030-06-01T10:00', 'archive_at' => '2030-05-01T10:00',
        ])->assertSessionHasErrors('archive_at');
        $this->assertSame($before, ContentItem::count());
    }

    public function test_a_content_can_be_moved_to_another_item_by_editing_it(): void
    {
        $admin = $this->user('admin');
        $one = $this->item('one');
        $two = $this->item('two');
        $content = $this->content($one, title: 'Moving');

        $this->actingAs($admin)->put('/contents/' . $content->id, [
            'navigation_id' => $two->id, 'publish_at' => $content->publish_at->format('Y-m-d\TH:i'), 'titles' => ['en' => 'Moving'],
        ])->assertRedirect('/contents');

        $this->assertSame($two->id, (int) $content->fresh()->navigation_id);
    }

    public function test_deleting_a_content_removes_its_texts(): void
    {
        $content = $this->content($this->item('team'), title: 'Gone');

        $this->actingAs($this->user('admin'))->delete('/contents/' . $content->id)->assertRedirect('/contents');

        $this->assertNull(ContentItem::find($content->id));
        $this->assertSame('', app(ContentTexts::class)->exact('content_title_' . $content->id, 'en'));
    }

    public function test_an_item_that_still_holds_contents_cannot_be_deleted(): void
    {
        $admin = $this->user('admin');
        $g = $this->item('doomed', ['type' => 'group']);
        $c = $this->page('doomed-child', parent: $g, title: 'Kept text');
        $contentId = $c->contents()->first()->id;

        // nothing is deleted, and the admin is told why
        $this->actingAs($admin)->delete('/navigation/' . $g->id)->assertRedirect('/navigation')->assertSessionHas('error');
        $this->assertNotNull(Navigation::find($g->id));
        $this->assertNotNull(Navigation::find($c->id));
        $this->assertNotNull(ContentItem::find($contentId));

        // once the contents are gone, the item and what is under it can go, with their names
        $this->actingAs($admin)->delete('/contents/' . $contentId);
        $this->actingAs($admin)->delete('/navigation/' . $g->id)->assertRedirect('/navigation')->assertSessionHas('success');
        $this->assertNull(Navigation::find($g->id));
        $this->assertNull(Navigation::find($c->id));
        $this->assertSame('', app(ContentTexts::class)->exact('navigation_name_' . $c->id, 'en'));
        $this->assertNull(app(SiteNavigation::class)->findByPath('doomed/doomed-child'));
    }

    // ---- adding contents from the page itself ----------------------------------------------

    public function test_staff_can_add_a_content_from_the_page_of_an_item_and_come_back_to_it(): void
    {
        $node = $this->page('about-us', title: 'Existing');
        $admin = $this->user('admin');

        $page = $this->actingAs($admin)->get('/about-us')->assertOk();
        $link = 'contents/create?navigation_id=' . $node->id . '&amp;back=page';
        $page->assertSee($link, false);
        $this->actingAs($this->user())->get('/about-us')->assertDontSee('contents/create', false);

        // the form arrives with the item chosen and remembers where to go back to
        $html = $this->actingAs($admin)->get('/contents/create?navigation_id=' . $node->id . '&back=page')->assertOk()->getContent();
        $xpath = $this->dom($html);
        $this->assertSame('page', $xpath->query('//form[contains(@action,"/contents")]//input[@name="back"]')->item(0)->getAttribute('value'));
        $this->assertSame(1, $xpath->query('//select[@name="navigation_id"]/option[@selected][@value="' . $node->id . '"]')->length);
        $this->assertSame(1, $xpath->query('//a[@href="' . url('about-us') . '"][contains(.,"Cancel")]')->length);

        // saving, and deleting, bring the admin back to the page
        $this->actingAs($admin)->post('/contents', [
            'navigation_id' => $node->id, 'back' => 'page', 'publish_now' => 1, 'titles' => ['en' => 'Second text'],
        ])->assertRedirect(url('about-us'));

        $second = ContentItem::latest('id')->firstOrFail();
        $this->actingAs($admin)->delete('/contents/' . $second->id, ['back' => 'page'])->assertRedirect(url('about-us'));
        // from the Contents screen they stay there
        $this->actingAs($admin)->post('/contents', ['navigation_id' => $node->id, 'titles' => ['en' => 'Third']])->assertRedirect('/contents');
    }

    public function test_the_edit_button_on_a_page_leads_back_to_the_page(): void
    {
        $node = $this->page('about-us');
        $content = $node->contents()->first();

        $this->actingAs($this->user('admin'))->get('/about-us')->assertSee('contents/' . $content->id . '/edit?back=page', false);
    }

    public function test_the_content_form_offers_to_delete_only_an_existing_content(): void
    {
        $admin = $this->user('admin');
        $node = $this->item('team');
        $content = $this->content($node);

        $existing = $this->dom($this->actingAs($admin)->get('/contents/' . $content->id . '/edit')->getContent());
        $this->assertSame(1, $existing->query('//form[@action="' . url('contents/' . $content->id) . '"][.//input[@name="_method"][@value="DELETE"]]')->length);

        $new = $this->dom($this->actingAs($admin)->get('/contents/create')->getContent());
        $this->assertSame(0, $new->query('//input[@name="_method"][@value="DELETE"][ancestor::main]')->length);
    }

    // ---- the order of the contents ---------------------------------------------------------

    private function orderedPage(string $mode): array
    {
        $node = $this->item('ordered', ['sort_mode' => $mode]);
        // by hand: b, c, a. By date: a is the oldest, c the newest. By title: a, b, c
        $b = $this->content($node, ['publish_at' => now()->subDays(5)], title: 'B text');
        $c = $this->content($node, ['publish_at' => now()->subDay()], title: 'C text');
        $a = $this->content($node, ['publish_at' => now()->subDays(9)], title: 'A text');

        return [$node, $a, $b, $c];
    }

    private function setSortMode(string $mode): void
    {
        $node = Navigation::where('slug', 'ordered')->firstOrFail();
        $node->sort_mode = $mode;
        $node->save();
    }

    private function titlesOnThePage(): array
    {
        $this->freshRequest();
        preg_match_all('/<h2>(.*?)<\/h2>/', $this->get('/ordered')->assertOk()->getContent(), $m);

        return $m[1];
    }

    public function test_by_hand_the_contents_follow_their_position(): void
    {
        [, $a, $b, $c] = $this->orderedPage('manual');

        $this->assertSame(['B text', 'C text', 'A text'], $this->titlesOnThePage());
    }

    public function test_an_item_can_order_its_contents_automatically(): void
    {
        $this->orderedPage('newest');
        $this->assertSame(['C text', 'B text', 'A text'], $this->titlesOnThePage());

        $this->setSortMode('oldest');
        $this->assertSame(['A text', 'B text', 'C text'], $this->titlesOnThePage());

        $this->setSortMode('title');
        $this->assertSame(['A text', 'B text', 'C text'], $this->titlesOnThePage());
    }

    public function test_unpublished_contents_come_last_whatever_the_automatic_order(): void
    {
        $node = $this->item('ordered', ['sort_mode' => 'newest']);
        $draft = $this->content($node, ['publish_at' => null], title: 'Draft text');
        $this->content($node, ['publish_at' => now()->subDays(30)], title: 'Old text');
        $this->content($node, ['publish_at' => now()->subDay()], title: 'New text');
        $this->freshRequest();

        $this->actingAs($this->user('admin'));   // staff are the only ones who see a draft
        preg_match_all('/<h2>(.*?)<\/h2>/', $this->get('/ordered')->getContent(), $m);
        // an administrator's titles carry the pencil of the inline editor: only the words are compared
        $this->assertSame(['New text', 'Old text', 'Draft text'], array_map(fn($t) => trim(strip_tags($t)), $m[1]));
    }

    public function test_the_order_is_chosen_on_the_item_and_only_for_contents_items(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post('/navigation', ['type' => 'contents', 'sort_mode' => 'title', 'labels' => ['en' => 'Sorted']])->assertRedirect('/navigation');
        $this->assertSame('title', Navigation::where('slug', 'sorted')->value('sort_mode'));

        $this->actingAs($admin)->post('/navigation', ['type' => 'contents', 'sort_mode' => 'sideways', 'labels' => ['en' => 'Bad']])->assertSessionHasErrors('sort_mode');

        foreach (['contents' => 1, 'group' => 0, 'link' => 0] as $type => $expected) {
            $xpath = $this->dom($this->actingAs($admin)->get('/navigation/create?type=' . $type)->getContent());
            $this->assertSame($expected, $xpath->query('//select[@name="sort_mode"]/option')->length > 0 ? 1 : 0, $type);
        }
    }

    // ---- the Contents screen ---------------------------------------------------------------

    public function test_the_contents_screen_lists_every_contents_item_with_its_contents(): void
    {
        $team = $this->item('team');
        $this->item('empty-one', ['sort_mode' => 'title']);
        $this->item('heading', ['type' => 'group']);
        $this->content($team, ['publish_at' => null], title: 'A draft text');

        $xpath = $this->dom($this->actingAs($this->user('admin'))->get('/contents')->assertOk()
            ->assertSee('A draft text')->assertSee('draft')->getContent());

        $this->assertSame(1, $xpath->query('//ul[contains(@class,"content-list")][@data-navigation-id="' . $team->id . '"][@data-manual="1"]//li[@data-id]')->length);
        $emptyId = Navigation::where('slug', 'empty-one')->value('id');
        $this->assertSame(1, $xpath->query('//ul[contains(@class,"content-list")][@data-navigation-id="' . $emptyId . '"][@data-manual="0"]')->length);
        // groups and links cannot hold contents, so they are not there
        $this->assertSame(0, $xpath->query('//ul[@data-navigation-id="' . Navigation::where('slug', 'heading')->value('id') . '"]')->length);
        // and it offers to add one, per item
        $this->assertGreaterThan(0, $xpath->query('//a[contains(@href,"contents/create?navigation_id=' . $team->id . '")]')->length);
    }

    public function test_the_menu_screen_only_counts_the_contents_and_points_to_their_screen(): void
    {
        $team = $this->item('team');
        $this->content($team, title: 'Counted text');
        $this->content($team, title: 'Another text');

        $this->actingAs($this->user('admin'))->get('/navigation')->assertOk()
            ->assertDontSee('Counted text')
            ->assertSee('contents#node-' . $team->id, false);
    }

    public function test_dragging_a_content_moves_it_between_items_and_reorders_it(): void
    {
        $admin = $this->user('admin');
        $one = $this->item('one');
        $two = $this->item('two');
        $first = $this->content($one, title: 'First');
        $second = $this->content($one, title: 'Second');

        $this->actingAs($admin)->postJson('/contents/sort', ['items' => [
            ['id' => $second->id, 'navigation_id' => $one->id, 'position' => 0],
            ['id' => $first->id, 'navigation_id' => $one->id, 'position' => 1],
        ]])->assertOk();
        $this->freshRequest();
        Auth::logout();
        $this->get('/one')->assertSeeInOrder(['Second', 'First']);

        $this->actingAs($admin)->postJson('/contents/sort', ['items' => [
            ['id' => $second->id, 'navigation_id' => $two->id, 'position' => 0],
        ]])->assertOk();
        $this->assertSame($two->id, (int) $second->fresh()->navigation_id);
    }

    public function test_a_content_cannot_be_dragged_to_nowhere(): void
    {
        $admin = $this->user('admin');
        $content = $this->content($this->item('team'));

        $this->actingAs($admin)->postJson('/contents/sort', ['items' => [['id' => $content->id, 'navigation_id' => null, 'position' => 0]]])->assertStatus(422);
        $this->assertNotNull($content->fresh()->navigation_id);
    }

    // ---- the admin menus -------------------------------------------------------------------

    public function test_the_content_tasks_have_their_own_menu_apart_from_the_transcription_ones(): void
    {
        $xpath = $this->dom($this->actingAs($this->user('admin'))->get('/projects')->getContent());

        $content = '//li[contains(@class,"dropdown")][.//a[contains(@class,"dropdown-toggle")][contains(normalize-space(.),"Content")]]';
        foreach (['contents', 'navigation', 'footer'] as $path) {
            $this->assertSame(1, $xpath->query($content . '//a[@href="' . url($path) . '"]')->length, $path);
        }

        // the transcription ones are in the Admin menu, not in the Content one
        $admin = '//li[contains(@class,"dropdown")][.//a[contains(@class,"dropdown-toggle")][contains(normalize-space(.),"Admin")]]';
        foreach (['projects', 'fields', 'validate/manual', 'validated', 'all_submissions', 'users', 'settings'] as $path) {
            $this->assertSame(1, $xpath->query($admin . '//a[@href="' . url($path) . '"]')->length, $path);
            $this->assertSame(0, $xpath->query($content . '//a[@href="' . url($path) . '"]')->length, $path);
        }
        $this->assertSame(0, $xpath->query($admin . '//a[@href="' . url('navigation') . '"]')->length);
    }

    public function test_the_content_menu_is_for_root_and_admin_only(): void
    {
        $xpath = $this->dom($this->actingAs($this->user('project_manager'))->get('/projects')->getContent());

        $this->assertSame(0, $xpath->query('//li[contains(@class,"dropdown")]//a[@href="' . url('footer') . '"]')->length);
        $this->assertSame(0, $xpath->query('//li[contains(@class,"dropdown")]//a[@href="' . url('contents') . '"]')->length);
    }
}
