<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\User_categories;
use App\Services\AvatarStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] photo files this test created, removed again afterwards (and nothing else) */
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $name) {
            @unlink(public_path('avatars/' . $name));
        }
        parent::tearDown();
    }

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
        $user->password = bcrypt('password');
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function upload(User $user, string $file = 'me.png'): void
    {
        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image($file, 600, 400)])
            ->assertSessionHasNoErrors();
        if ($user->fresh()->avatar) {
            $this->created[] = $user->fresh()->avatar;
        }
    }

    // ---- what is shown ---------------------------------------------------------------------

    public function test_without_a_photo_a_visible_user_gets_their_initials(): void
    {
        $html = (string) display_avatar($this->user('Visible Vera'), 28, null);

        $this->assertStringContainsString('>VV<', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('>S<', (string) display_avatar($this->user('sam'), 28, null));
    }

    public function test_the_initials_are_escaped(): void
    {
        $html = (string) display_avatar($this->user('<script>x</script>'), 28, null);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;', $html);
    }

    public function test_the_colour_is_stable_and_people_with_the_same_initial_do_not_all_look_alike(): void
    {
        $users = [];
        for ($i = 0; $i < 12; $i++) {
            $users[] = $this->user('Anna ' . $i);   // all start with "A"
        }

        $again = array_map(fn($u) => (string) display_avatar($u, 28, null), $users);
        $first = array_map(fn($u) => (string) display_avatar($u, 28, null), $users);
        $this->assertSame($first, $again);   // stable from one view to the next

        preg_match_all('/background:(#[0-9a-f]{6})/', implode('', $first), $m);
        $this->assertGreaterThan(3, count(array_unique($m[1])));   // not one colour for every "A"
    }

    public function test_a_hidden_user_is_a_pattern_with_neither_initials_nor_photo(): void
    {
        $hidden = $this->user('Secret Sam', 'user', false);
        $hidden->avatar = 'u1_1.jpg';
        $hidden->save();
        $stranger = $this->user('Stranger');

        $html = (string) display_avatar($hidden, 28, $stranger);

        $this->assertStringContainsString('<svg', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('SS', $html);
        $this->assertStringNotContainsString('u1_1.jpg', $html);
    }

    public function test_hidden_users_can_still_be_told_apart_from_each_other(): void
    {
        $viewer = $this->user('Viewer');
        $patterns = [];
        for ($i = 0; $i < 8; $i++) {
            $patterns[] = (string) display_avatar($this->user('Hidden ' . $i, 'user', false), 28, $viewer);
        }

        $this->assertGreaterThan(3, count(array_unique($patterns)));
    }

    public function test_the_hidden_user_themselves_and_admins_see_the_real_one(): void
    {
        $hidden = $this->user('Secret Sam', 'user', false);
        $hidden->avatar = 'u1_1.jpg';
        $hidden->save();

        $this->assertStringContainsString('<img', (string) display_avatar($hidden, 28, $hidden));
        $this->assertStringContainsString('<img', (string) display_avatar($hidden, 28, $this->user('Admin', 'admin')));
    }

    public function test_the_home_page_leaderboard_shows_avatars_but_keeps_hidden_users_anonymous(): void
    {
        $shown = $this->user('Visible Vera');
        $shown->validated = 3;
        $shown->rejected = 0;
        $shown->submitted = 0;
        $shown->save();
        $hidden = $this->user('Secret Sam', 'user', false);
        $hidden->validated = 5;
        $hidden->rejected = 0;
        $hidden->submitted = 0;
        $hidden->save();

        $html = $this->actingAs($this->user('Viewer'))->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('>VV<', $html);       // Vera's initials
        $this->assertStringNotContainsString('>SS<', $html);    // not Sam's
        $this->assertStringNotContainsString('Secret Sam', $html);
    }

    // ---- the upload ------------------------------------------------------------------------

    public function test_this_server_can_handle_the_common_formats(): void
    {
        $this->assertTrue(AvatarStore::available());
        $this->assertContains('jpg', AvatarStore::formats());
        $this->assertContains('png', AvatarStore::formats());
    }

    public function test_an_uploaded_photo_becomes_a_square_jpeg_the_user_can_see(): void
    {
        $user = $this->user('Pat');

        $this->upload($user);

        $name = $user->fresh()->avatar;
        $this->assertMatchesRegularExpression('/^u' . $user->id . '_\d+\.jpg$/', $name);
        $path = public_path('avatars/' . $name);
        $this->assertFileExists($path);
        $this->assertSame([256, 256], array_slice(getimagesize($path), 0, 2));   // cropped to a square, whatever went in
        $this->assertSame('image/jpeg', getimagesize($path)['mime']);
        $this->actingAs($user)->get('/profile')->assertSee('avatars/' . $name);
    }

    public function test_a_new_photo_replaces_the_old_file(): void
    {
        $user = $this->user('Pat');
        $this->upload($user);
        $old = $user->fresh()->avatar;
        $this->assertFileExists(public_path('avatars/' . $old));

        sleep(1);   // file names carry the time
        $this->upload($user, 'second.png');

        $this->assertNotSame($old, $user->fresh()->avatar);
        $this->assertFileDoesNotExist(public_path('avatars/' . $old));
    }

    public function test_only_images_up_to_2mb_are_accepted(): void
    {
        $user = $this->user('Pat');

        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('avatar');
        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')])->assertSessionHasErrors('avatar');
        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('big.jpg', 600, 600)->size(3000)])->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar);
    }

    public function test_the_photo_needs_a_login(): void
    {
        $this->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('a.png')])->assertRedirect();
    }

    public function test_removing_the_photo_deletes_the_file(): void
    {
        $user = $this->user('Pat');
        $this->upload($user);
        $name = $user->fresh()->avatar;

        $this->actingAs($user)->delete('/profile/avatar')->assertRedirect('/profile');

        $this->assertNull($user->fresh()->avatar);
        $this->assertFileDoesNotExist(public_path('avatars/' . $name));
    }

    // ---- root/admin changing somebody else's profile --------------------------------------

    public function test_an_admin_can_change_anyones_name_visibility_and_photo(): void
    {
        $admin = $this->user('Admin', 'admin');
        $target = $this->user('Old Name');

        $this->actingAs($admin)->post('/user_edit', [
            'user_id' => $target->id,
            'name' => 'New Pseudonym',
            'email' => $target->email,
            'category' => 5,
            'profile_fields' => 1,          // the form's marker; public_name left unticked
            'avatar' => UploadedFile::fake()->image('them.png', 300, 300),
        ])->assertRedirect('/users');

        $target = $target->fresh();
        $this->created[] = $target->avatar;
        $this->assertSame('New Pseudonym', $target->name);
        $this->assertFalse($target->public_name);
        $this->assertNotNull($target->avatar);
        $this->assertFileExists(public_path('avatars/' . $target->avatar));

        // and take the photo off again
        $name = $target->avatar;
        $this->actingAs($admin)->post('/user_edit', [
            'user_id' => $target->id, 'name' => 'New Pseudonym', 'email' => $target->email, 'category' => 5,
            'profile_fields' => 1, 'public_name' => 1, 'remove_avatar' => 1,
        ])->assertRedirect('/users');
        $this->assertNull($target->fresh()->avatar);
        $this->assertTrue($target->fresh()->public_name);
        $this->assertFileDoesNotExist(public_path('avatars/' . $name));
    }

    public function test_a_form_without_the_profile_marker_leaves_visibility_alone(): void
    {
        $admin = $this->user('Admin', 'admin');
        $target = $this->user('Hidden One', 'user', false);

        $this->actingAs($admin)->post('/user_edit', ['user_id' => $target->id, 'name' => 'Hidden One', 'email' => $target->email, 'category' => 5]);

        $this->assertFalse($target->fresh()->public_name);
    }

    public function test_an_ordinary_user_cannot_change_someone_elses_profile(): void
    {
        $target = $this->user('Target');

        $this->actingAs($this->user('Nosy'))->post('/user_edit', [
            'user_id' => $target->id, 'name' => 'Hijacked', 'email' => $target->email, 'category' => 5,
        ])->assertForbidden();

        $this->assertSame('Target', $target->fresh()->name);
    }

    public function test_the_users_screen_shows_each_users_avatar_and_the_profile_fields(): void
    {
        $admin = $this->user('Admin', 'admin');
        $this->user('Visible Vera');

        $this->actingAs($admin)->get('/users')->assertOk()
            ->assertSee('>VV<', false)
            ->assertSee('name="public_name"', false)
            ->assertSee('name="avatar"', false);
    }


    // ---- when the upload fails: whose fault is it? -------------------------------------------------------

    /** A server whose photo folder cannot be written (the usual cause is the folder belonging to another user). */
    private function serverWithAFolderThatCannotBeWritten(): void
    {
        $this->app->instance(AvatarStore::class, new class extends AvatarStore {
            public function directory(): string
            {
                // a "folder" under a path that does not exist and cannot be created
                return public_path('index.php') . DIRECTORY_SEPARATOR . 'avatars';
            }
        });
    }

    public function test_a_server_that_cannot_save_the_photo_says_so_and_does_not_blame_the_image(): void
    {
        $user = $this->user('Pat');
        $this->serverWithAFolderThatCannotBeWritten();
        \Illuminate\Support\Facades\Log::spy();

        $response = $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('me.png', 300, 300)]);

        $message = session('errors')->first('avatar');
        $this->assertStringContainsString('The server could not save the photo', $message);
        $this->assertStringContainsString('Nothing is wrong with the image', $message);
        $this->assertStringNotContainsString('Try another one', $message);
        $this->assertNull($user->fresh()->avatar);

        // and the reason is where whoever looks after the server can read it
        \Illuminate\Support\Facades\Log::shouldHaveReceived('error')->withArgs(function ($text, $context) {
            return str_contains($text, 'Profile photo failed') && array_key_exists('directory', $context) && array_key_exists('writable', $context) && array_key_exists('php_user', $context);
        })->once();
    }

    public function test_a_file_that_is_not_really_an_image_still_says_to_try_another_one(): void
    {
        $user = $this->user('Pat');
        \Illuminate\Support\Facades\Log::spy();

        // it passes for a png by its name and type, and GD cannot read it
        $broken = UploadedFile::fake()->createWithContent('broken.png', "\x89PNG\r\n\x1a\n" . str_repeat('x', 60));
        $this->actingAs($user)->post('/profile/avatar', ['avatar' => $broken]);

        $message = session('errors')->first('avatar');
        $this->assertStringContainsString('Try another one', $message);
        $this->assertStringNotContainsString('The server could not save', $message);
        \Illuminate\Support\Facades\Log::shouldNotHaveReceived('error');   // nothing the server did wrong: no alarm
    }

    public function test_the_admins_user_form_tells_a_server_problem_from_an_image_problem_too(): void
    {
        $admin = $this->user('Boss', 'admin');
        $target = $this->user('Pat');
        $this->serverWithAFolderThatCannotBeWritten();

        $this->actingAs($admin)->post('/user_edit', [
            'user_id' => $target->id, 'name' => 'Pat', 'email' => $target->email, 'role' => 'user', 'category' => 5,
            'avatar' => UploadedFile::fake()->image('me.png', 300, 300),
        ])->assertRedirect('/users')->assertSessionHas('error');

        $this->assertStringContainsString('the server could not save the photo', session('error'));
    }
}
