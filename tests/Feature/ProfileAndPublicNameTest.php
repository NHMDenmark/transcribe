<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\User_categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileAndPublicNameTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, string $role = 'user', bool $public = true): User
    {
        $cat = User_categories::find(5) ?? new User_categories();
        $cat->id = 5;
        $cat->category = 'level 5';
        $cat->points = 20;
        $cat->save();

        $user = new User();
        $user->category = 5;
        $user->role = $role;
        $user->name = $name;
        $user->public_name = $public;
        $user->email = uniqid() . '@example.test';
        $user->password = bcrypt('old-password');
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    // ---- the rule -------------------------------------------------------------------------

    public function test_a_public_name_is_shown_and_a_hidden_one_becomes_hidden_user(): void
    {
        $public = $this->user('Visible Vera');
        $hidden = $this->user('Secret Sam', 'user', false);
        $viewer = $this->user('Viewer');

        $this->assertSame('Visible Vera', display_name($public, $viewer));
        $this->assertSame('hidden user', display_name($hidden, $viewer));
        $this->assertSame('hidden user', display_name($hidden, null));   // e.g. an e-mail with no particular reader
    }

    public function test_a_hidden_user_still_sees_their_own_name(): void
    {
        $hidden = $this->user('Secret Sam', 'user', false);

        $this->assertSame('Secret Sam', display_name($hidden, $hidden));
    }

    public function test_root_and_admin_can_always_tell_who_it_is(): void
    {
        $hidden = $this->user('Secret Sam', 'user', false);

        $this->assertSame('Secret Sam (hidden)', display_name($hidden, $this->user('A', 'admin')));
        $this->assertSame('Secret Sam (hidden)', display_name($hidden, $this->user('R', 'root')));
    }

    public function test_a_project_manager_knows_who_it_is_only_inside_their_own_project(): void
    {
        $hidden = $this->user('Secret Sam', 'user', false);
        $pm = $this->user('Pat', 'project_manager');
        $mine = new Project();
        $mine->name = 'Mine';
        $mine->user_id = $pm->id;
        $mine->active = 1;
        $mine->save();
        $other = new Project();
        $other->name = 'Other';
        $other->user_id = 0;
        $other->active = 1;
        $other->save();

        $this->assertSame('Secret Sam (hidden)', display_name($hidden, $pm, $mine));
        $this->assertSame('hidden user', display_name($hidden, $pm, $other));
        $this->assertSame('hidden user', display_name($hidden, $pm));
    }

    public function test_the_leaderboard_lists_a_hidden_user_with_their_score_but_not_their_name(): void
    {
        $hidden = $this->user('Secret Sam', 'user', false);
        $hidden->validated = 5;
        $hidden->rejected = 0;
        $hidden->submitted = 0;
        $hidden->save();
        $shown = $this->user('Visible Vera');
        $shown->validated = 3;
        $shown->rejected = 0;
        $shown->submitted = 0;
        $shown->save();

        $this->actingAs($this->user('Viewer'))->get('/')->assertOk()
            ->assertSee('hidden user')
            ->assertSee('Visible Vera')
            ->assertDontSee('Secret Sam');
    }

    // ---- the profile page -----------------------------------------------------------------

    public function test_the_profile_needs_a_login(): void
    {
        $this->get('/profile')->assertRedirect();
    }

    public function test_the_profile_shows_the_name_and_the_choice(): void
    {
        $user = $this->user('Pseudo Pat', 'user', false);

        $this->actingAs($user)->get('/profile')->assertOk()
            ->assertSee('Name or pseudonym')
            ->assertSee('Pseudo Pat')
            ->assertSee('Show my name publicly');
    }

    public function test_the_name_and_the_choice_can_be_changed(): void
    {
        $user = $this->user('Old Name');

        $this->actingAs($user)->patch('/profile', ['name' => 'A Pseudonym'])->assertRedirect('/profile');   // box unticked: no public_name sent

        $this->assertSame('A Pseudonym', $user->fresh()->name);
        $this->assertFalse($user->fresh()->public_name);

        $this->actingAs($user)->patch('/profile', ['name' => 'A Pseudonym', 'public_name' => 1]);
        $this->assertTrue($user->fresh()->public_name);
    }

    public function test_a_name_is_required(): void
    {
        $user = $this->user('Keep Me');

        $this->actingAs($user)->patch('/profile', ['name' => ''])->assertSessionHasErrors('name');
        $this->assertSame('Keep Me', $user->fresh()->name);
    }

    public function test_the_email_cannot_be_changed_from_the_profile(): void
    {
        $user = $this->user('Pat');
        $email = $user->email;

        $this->actingAs($user)->patch('/profile', ['name' => 'Pat', 'email' => 'hijack@example.test']);

        $this->assertSame($email, $user->fresh()->email);
    }

    public function test_the_password_changes_with_the_right_current_one(): void
    {
        $user = $this->user('Pat');

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertRedirect('/profile')->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password-1', $user->fresh()->password));
    }

    public function test_a_wrong_current_password_a_mismatch_or_a_short_one_changes_nothing(): void
    {
        $user = $this->user('Pat');

        $this->actingAs($user)->put('/profile/password', ['current_password' => 'nope', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put('/profile/password', ['current_password' => 'old-password', 'password' => 'new-password-1', 'password_confirmation' => 'different'])
            ->assertSessionHasErrors('password');
        $this->actingAs($user)->put('/profile/password', ['current_password' => 'old-password', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_the_users_menu_links_to_the_profile(): void
    {
        $this->actingAs($this->user('Pat'))->get('/dashboard')->assertOk()->assertSee('/profile');
    }
}
