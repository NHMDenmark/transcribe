<?php

namespace Tests\Feature;

use App\Models\Collaborative_fields;
use App\Models\Comments;
use App\Models\Project;
use App\Models\User;
use App\Models\User_categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Deleting an account: the person's data goes (name, e-mail, photo, password, roles), their work stays as
 * "Deleted user". An administrator can do it for anybody, and each person for themselves.
 */
class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] photo files created here, removed afterwards */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        $cat = new User_categories();
        $cat->id = 5;
        $cat->category = 'level 5';
        $cat->points = 20;
        $cat->save();
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function user(string $name, string $role = 'user', string $password = 'secret-pass'): User
    {
        $user = new User();
        $user->category = 5;
        $user->role = $role;
        $user->name = $name;
        $user->email = strtolower(str_replace(' ', '.', $name)) . '@example.test';
        $user->password = bcrypt($password);
        $user->email_verified_at = now();
        $user->public_name = false;
        $user->banned_fields = '3|4';
        $user->comment_notifications = 1;
        $user->save();

        return $user;
    }

    private function submission(User $user, string $value = 'Boserup'): Collaborative_fields
    {
        $c = new Collaborative_fields();
        $c->value = $value;
        $c->position = 0;
        $c->specimen_id = '999333';
        $c->field_id = 1;
        $c->user_id = (string) $user->id;
        $c->project_id = 1;
        $c->status = 2;
        $c->points = 20;
        $c->save();

        return $c;
    }

    // ---- what is removed, what stays ---------------------------------------------------------------

    public function test_an_administrator_deletes_an_account_and_the_persons_data_goes(): void
    {
        $admin = $this->user('Boss', 'admin');
        $person = $this->user('Pat Smith', 'project_manager');
        $oldEmail = $person->email;

        // everything that could lead back to them or act as them
        $photo = public_path('avatars/u' . $person->id . '_1700000000.jpg');
        @mkdir(dirname($photo), 0755, true);
        file_put_contents($photo, 'x');
        $this->files[] = $photo;
        $person->avatar = basename($photo);
        $person->save();

        $project = new Project();
        $project->name = 'P';
        $project->user_id = 0;
        $project->active = 1;
        $project->save();
        DB::table('project_user')->insert(['project_id' => $project->id, 'user_id' => $person->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('password_reset_tokens')->insert(['email' => $oldEmail, 'token' => 'abc', 'created_at' => now()]);
        DB::table('personal_access_tokens')->insert(['tokenable_type' => User::class, 'tokenable_id' => $person->id, 'name' => 'x', 'token' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($admin)->post('/user_anonymize', ['user_id' => $person->id])->assertRedirect('/users')->assertSessionHas('success');

        $person->refresh();
        $this->assertSame('Deleted user', $person->name);
        $this->assertSame('deleted-' . $person->id . '@anonymous.invalid', $person->email);
        $this->assertNotNull($person->anonymized_at);
        $this->assertNull($person->avatar);
        $this->assertFileDoesNotExist($photo);
        $this->assertSame('user', $person->role);               // no privileges left
        $this->assertNull($person->banned_fields);
        $this->assertSame(0, (int) $person->comment_notifications);
        $this->assertFalse(Hash::check('secret-pass', $person->password));
        $this->assertSame(0, DB::table('project_user')->where('user_id', $person->id)->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', $oldEmail)->count());
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $person->id)->count());
    }

    public function test_their_work_stays_and_is_shown_as_deleted_user_to_everybody_even_administrators(): void
    {
        $admin = $this->user('Boss', 'admin');
        $person = $this->user('Pat Smith');
        $this->submission($person, 'Boserup, Fyn');
        $comment = new Comments();
        $comment->value = 'a remark';
        $comment->specimen_id = '999333';
        $comment->field_id = 1;
        $comment->user_id = $person->id;
        $comment->status = 1;
        $comment->save();

        $this->actingAs($admin)->post('/user_anonymize', ['user_id' => $person->id]);

        $this->assertSame(1, Collaborative_fields::where('user_id', (string) $person->id)->count());
        $this->assertSame(1, Comments::where('user_id', $person->id)->count());
        $this->assertTrue(User::whereKey($person->id)->exists());   // the row stays: the work belongs to it

        $person = $person->fresh();
        $this->assertSame('Deleted user', display_name($person, $admin));
        $this->assertSame('Deleted user', display_name($person, $person));
        $this->assertFalse(can_see_identity($person, $admin));   // no initials, no photo: the pattern
        $this->actingAs($admin)->get('/all_submissions')->assertOk()->assertSee('Boserup, Fyn')->assertSee('Deleted user')->assertDontSee('Pat Smith');
    }

    public function test_the_account_can_no_longer_be_used(): void
    {
        $admin = $this->user('Boss', 'admin');
        $person = $this->user('Pat Smith');
        $oldEmail = $person->email;

        $this->actingAs($admin)->post('/user_anonymize', ['user_id' => $person->id]);
        \Illuminate\Support\Facades\Auth::logout();

        $this->post('/login', ['email' => $oldEmail, 'password' => 'secret-pass'])->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_a_session_that_was_already_open_ends_on_the_next_request(): void
    {
        $admin = $this->user('Boss', 'admin');
        $person = $this->user('Pat Smith');
        $this->actingAs($admin)->post('/user_anonymize', ['user_id' => $person->id]);

        $this->actingAs($person->fresh())->get('/projects')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_two_deleted_accounts_do_not_collide_on_the_unique_e_mail_address(): void
    {
        $admin = $this->user('Boss', 'admin');
        $one = $this->user('Pat One');
        $two = $this->user('Pat Two');

        $this->actingAs($admin)->post('/user_anonymize', ['user_id' => $one->id])->assertSessionHas('success');
        $this->actingAs($admin)->post('/user_anonymize', ['user_id' => $two->id])->assertSessionHas('success');

        $this->assertNotSame($one->fresh()->email, $two->fresh()->email);
    }

    // ---- who may delete whom ------------------------------------------------------------------------

    public function test_an_ordinary_user_and_a_project_manager_cannot_delete_others(): void
    {
        $victim = $this->user('Pat Smith');

        foreach (['user', 'project_manager'] as $role) {
            $this->actingAs($this->user('Someone ' . $role, $role))->post('/user_anonymize', ['user_id' => $victim->id])->assertForbidden();
        }

        $this->assertNull($victim->fresh()->anonymized_at);
    }

    public function test_only_root_can_delete_a_root_account_and_never_the_only_one(): void
    {
        $root = $this->user('Root One', 'root');
        $admin = $this->user('Boss', 'admin');

        // an administrator cannot
        $this->actingAs($admin)->post('/user_anonymize', ['user_id' => $root->id])->assertSessionHas('error');
        $this->assertNull($root->fresh()->anonymized_at);

        // root cannot delete the only root (here: the other root does not exist yet)
        $second = $this->user('Root Two', 'root');
        $this->actingAs($second)->post('/user_anonymize', ['user_id' => $root->id])->assertSessionHas('success');   // two roots: allowed
        $this->assertNotNull($root->fresh()->anonymized_at);

        $this->actingAs($admin)->post('/user_anonymize', ['user_id' => $second->id])->assertSessionHas('error');
        $third = $this->user('Root Three', 'root');
        $this->actingAs($third)->post('/user_anonymize', ['user_id' => $second->id])->assertSessionHas('success');
        // and now the last one left cannot be deleted by anybody
        $this->assertSame(1, User::where('role', 'root')->whereNull('anonymized_at')->count());
    }

    public function test_an_account_cannot_be_deleted_twice_or_by_its_own_row_in_the_list(): void
    {
        $admin = $this->user('Boss', 'admin');
        $person = $this->user('Pat Smith');

        $this->actingAs($admin)->post('/user_anonymize', ['user_id' => $person->id])->assertSessionHas('success');
        $this->actingAs($admin)->post('/user_anonymize', ['user_id' => $person->id])->assertSessionHas('error');
        $this->actingAs($admin)->post('/user_anonymize', ['user_id' => $admin->id])->assertSessionHas('error');
        $this->assertNull($admin->fresh()->anonymized_at);
    }

    public function test_the_old_delete_route_that_only_renamed_the_user_is_gone(): void
    {
        $admin = $this->user('Boss', 'admin');
        $person = $this->user('Pat Smith');

        $this->actingAs($admin)->post('/user_delete', ['user_id' => $person->id])->assertStatus(405);
        $this->assertSame('Pat Smith', $person->fresh()->name);
    }

    // ---- the list of users --------------------------------------------------------------------------

    private function xpath(User $as): \DOMXPath
    {
        $html = $this->actingAs($as)->get('/users')->assertOk()->getContent();
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);

        return new \DOMXPath($doc);
    }

    public function test_the_list_offers_to_delete_others_but_not_oneself_or_a_deleted_account_and_puts_those_last(): void
    {
        $admin = $this->user('Boss', 'admin');
        $other = $this->user('Zed Other');
        $gone = $this->user('Ann Gone');
        $this->actingAs($admin)->post('/user_anonymize', ['user_id' => $gone->id]);

        $x = $this->xpath($admin);
        $form = fn(User $u) => '//form[contains(@action,"user_anonymize")][.//input[@name="user_id"][@value="' . $u->id . '"]]';

        $this->assertSame(1, $x->query($form($other))->length);
        $this->assertSame(0, $x->query($form($admin))->length);   // not on one's own row
        $this->assertSame(0, $x->query($form($gone))->length);

        // the deleted account is the last row, with a badge instead of buttons
        $rows = $x->query('//table[@id="example"]/tbody/tr');
        $last = $rows->item($rows->length - 1);
        $this->assertStringContainsString('Deleted user', $last->textContent);
        $this->assertSame(0, (new \DOMXPath($last->ownerDocument))->query('.//button', $last)->length);
    }

    // ---- one's own account ----------------------------------------------------------------------------

    public function test_everybody_has_the_delete_my_account_section_with_their_address(): void
    {
        $person = $this->user('Pat Smith');

        $html = $this->actingAs($person)->get('/profile')->assertOk()->getContent();

        $this->assertStringContainsString('Delete my account', $html);
        $this->assertStringContainsString($person->email, $html);
        $this->assertStringContainsString('name="confirm_email"', $html);
    }

    public function test_a_wrong_address_deletes_nothing(): void
    {
        $person = $this->user('Pat Smith');

        $this->actingAs($person)->delete('/profile', ['confirm_email' => 'someone.else@example.test'])->assertSessionHasErrors('confirm_email');
        $this->actingAs($person)->delete('/profile', [])->assertSessionHasErrors('confirm_email');

        $this->assertNull($person->fresh()->anonymized_at);
        $this->assertSame('Pat Smith', $person->fresh()->name);
    }

    public function test_typing_the_address_deletes_the_account_signs_out_and_goes_to_the_login(): void
    {
        $person = $this->user('Pat Smith');
        $this->submission($person);

        $this->actingAs($person)->delete('/profile', ['confirm_email' => '  ' . strtoupper($person->email) . ' '])
            ->assertRedirect('/login')->assertSessionHas('success');

        $this->assertGuest();
        $this->assertNotNull($person->fresh()->anonymized_at);
        $this->assertSame('Deleted user', $person->fresh()->name);
        $this->assertSame(1, Collaborative_fields::where('user_id', (string) $person->id)->count());   // the work stays
    }

    public function test_the_only_root_cannot_delete_their_own_account(): void
    {
        $root = $this->user('Root One', 'root');

        $this->actingAs($root)->delete('/profile', ['confirm_email' => $root->email])->assertSessionHasErrors('confirm_email');

        $this->assertNull($root->fresh()->anonymized_at);
    }

    public function test_a_visitor_cannot_use_it(): void
    {
        $this->delete('/profile', ['confirm_email' => 'a@b.test'])->assertRedirect('/login');
    }
}
