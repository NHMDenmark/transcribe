<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\User;
use App\Models\User_categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EditorFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');   // the real disk is public/: nothing of these tests may land there

        $language = new Language();
        $language->iso2 = 'en';
        $language->save();
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

    private function admin(): User
    {
        return $this->user('admin');
    }

    // ---- who may use it --------------------------------------------------------------------

    public function test_only_root_and_admin_may_use_the_file_browser(): void
    {
        foreach (['user', 'project_manager'] as $role) {
            $viewer = $this->user($role);
            $this->actingAs($viewer)->get('/editor-files')->assertForbidden();
            $this->actingAs($viewer)->post('/editor-files/upload', ['upload' => UploadedFile::fake()->image('a.png')])->assertForbidden();
            $this->actingAs($viewer)->post('/editor-files/delete', ['name' => 'a.png'])->assertForbidden();
            $this->actingAs($viewer)->post('/editor-files/create-folder', ['name' => 'x'])->assertForbidden();
            $this->actingAs($viewer)->post('/editor-files/delete-folder', ['path' => 'x'])->assertForbidden();
        }
        Storage::disk('local')->assertDirectoryEmpty('/');

        $this->actingAs($this->admin())->get('/editor-files')->assertOk();
        $this->actingAs($this->user('root'))->get('/editor-files')->assertOk();
        Auth::logout();
        $this->get('/editor-files')->assertRedirect(route('login'));
    }

    // ---- uploading -------------------------------------------------------------------------

    public function test_an_image_is_stored_under_uploads_and_its_address_comes_back(): void
    {
        $response = $this->actingAs($this->admin())
            ->post('/editor-files/upload', ['upload' => UploadedFile::fake()->image('My Photo!.png', 40, 30), 'type' => 'images'])
            ->assertOk();

        $name = $response->json('name');
        $this->assertMatchesRegularExpression('/^my-photo_\d+\.png$/', $name);
        Storage::disk('local')->assertExists('uploads/' . $name);
        $this->assertSame(parse_url(url('/'), PHP_URL_PATH) === '/' ? '' : rtrim(parse_url(url('/'), PHP_URL_PATH), '/'), substr($response->json('url'), 0, strlen(rtrim((string) parse_url(url('/'), PHP_URL_PATH), '/'))));
        $this->assertStringEndsWith('/uploads/' . $name, $response->json('url'));
        $this->assertStringStartsWith('/', $response->json('url'));   // from the site root, no domain in the text
    }

    public function test_documents_are_accepted_for_links_but_not_in_the_image_dialog(): void
    {
        $admin = $this->admin();
        $pdf = fn() => UploadedFile::fake()->create('Annual report.pdf', 100, 'application/pdf');

        $this->actingAs($admin)->post('/editor-files/upload', ['upload' => $pdf(), 'type' => 'images'])->assertSessionHasErrors('upload');
        $this->actingAs($admin)->postJson('/editor-files/upload', ['upload' => $pdf(), 'type' => 'images'])->assertStatus(422);
        $this->actingAs($admin)->postJson('/editor-files/upload', ['upload' => $pdf(), 'type' => 'files'])->assertOk();

        $this->assertCount(1, Storage::disk('local')->files('uploads'));
    }

    public function test_nothing_a_server_or_a_browser_could_run_is_accepted(): void
    {
        $admin = $this->admin();

        foreach (['shell.php', 'page.html', 'drawing.svg', 'run.exe', 'x.phtml', '.htaccess', 'a.js'] as $name) {
            $this->actingAs($admin)->postJson('/editor-files/upload', ['upload' => UploadedFile::fake()->create($name, 1, 'application/octet-stream'), 'type' => 'files'])
                ->assertStatus(422);
        }
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_a_file_is_judged_by_what_it_is_not_by_its_name(): void
    {
        // PHP code called "photo.png": the content gives it away
        $path = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($path, '<?php echo shell_exec($_GET["c"]);');
        $disguised = new UploadedFile($path, 'photo.png', 'image/png', null, true);

        $this->actingAs($this->admin())->postJson('/editor-files/upload', ['upload' => $disguised, 'type' => 'images'])->assertStatus(422);

        Storage::disk('local')->assertDirectoryEmpty('/');
        @unlink($path);
    }

    public function test_a_file_over_the_size_limit_is_refused(): void
    {
        $this->actingAs($this->admin())->postJson('/editor-files/upload', ['upload' => UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf'), 'type' => 'files'])->assertStatus(422);

        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_a_path_can_never_lead_out_of_the_uploads_folder(): void
    {
        $admin = $this->admin();
        Storage::disk('local')->put('secret.txt', 'private');
        Storage::disk('local')->put('app/config.txt', 'private');

        foreach (['../../app', '..\\..\\app', 'a/../../app', '/../app', './../app'] as $path) {
            $this->actingAs($admin)->postJson('/editor-files/upload', ['upload' => UploadedFile::fake()->image('x.png'), 'path' => $path, 'type' => 'images'])->assertOk();
        }

        // everything that was uploaded is under uploads/, and what was already there was left alone
        foreach (Storage::disk('local')->allFiles() as $file) {
            $this->assertTrue(str_starts_with($file, 'uploads/') || in_array($file, ['secret.txt', 'app/config.txt'], true), $file);
        }
        $this->assertSame('private', Storage::disk('local')->get('app/config.txt'));

        // nor can a delete reach out of the current folder
        $this->actingAs($admin)->postJson('/editor-files/delete', ['name' => '../secret.txt', 'path' => ''])->assertOk();
        $this->actingAs($admin)->postJson('/editor-files/delete', ['name' => '../../secret.txt', 'path' => 'a/b'])->assertOk();
        Storage::disk('local')->assertExists('secret.txt');
        $this->actingAs($admin)->postJson('/editor-files/delete-folder', ['path' => '../app'])->assertOk();   // read as uploads/app
        Storage::disk('local')->assertExists('app/config.txt');
    }

    // ---- browsing --------------------------------------------------------------------------

    public function test_the_popup_lists_folders_and_files_and_remembers_which_editor_asked(): void
    {
        $admin = $this->admin();
        Storage::disk('local')->put('uploads/logo.png', 'x');
        Storage::disk('local')->put('uploads/report.pdf', 'x');
        Storage::disk('local')->put('uploads/notes.php', 'x');          // never offered
        Storage::disk('local')->put('uploads/.hidden.png', 'x');         // nor this
        Storage::disk('local')->makeDirectory('uploads/tutorial');

        $html = $this->actingAs($admin)->get('/editor-files?type=files&CKEditorFuncNum=7')->assertOk()
            ->assertSee('logo.png')->assertSee('report.pdf')->assertSee('tutorial')
            ->assertDontSee('notes.php')->assertDontSee('.hidden.png')
            ->assertSee('const funcNum = 7;', false)->getContent();
        $this->assertStringContainsString('accept=".jpg,.jpeg,.png,.gif,.webp,.pdf', $html);

        // in the image dialog documents are not offered
        $this->actingAs($admin)->get('/editor-files?type=images&CKEditorFuncNum=7')->assertOk()
            ->assertSee('logo.png')->assertDontSee('report.pdf');
    }

    public function test_a_subfolder_is_browsed_with_a_way_back_up(): void
    {
        Storage::disk('local')->put('uploads/tutorial/step1.png', 'x');

        $this->actingAs($this->admin())->get('/editor-files?type=images&path=tutorial&CKEditorFuncNum=1')->assertOk()
            ->assertSee('step1.png')->assertSee('.. (up)');

        // a path trying to climb is read as a folder inside uploads: what is outside is never shown
        Storage::disk('local')->put('app/outside.png', 'x');
        $this->actingAs($this->admin())->get('/editor-files?type=images&path=../../app&CKEditorFuncNum=1')->assertOk()->assertDontSee('outside.png');
    }

    // ---- folders and deleting --------------------------------------------------------------

    public function test_folders_are_created_with_a_safe_name_and_deleted_with_what_is_in_them(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/editor-files/create-folder', ['name' => 'Tutorial Pictures', 'path' => ''])->assertOk()->assertJson(['path' => 'tutorial-pictures']);
        Storage::disk('local')->assertExists('uploads/tutorial-pictures');

        $this->actingAs($admin)->postJson('/editor-files/create-folder', ['name' => 'Tutorial Pictures', 'path' => ''])->assertStatus(422);
        $this->actingAs($admin)->postJson('/editor-files/create-folder', ['name' => '..', 'path' => ''])->assertStatus(422);
        $this->actingAs($admin)->postJson('/editor-files/create-folder', ['name' => '../../x', 'path' => ''])->assertOk()->assertJson(['path' => 'x']);

        Storage::disk('local')->put('uploads/tutorial-pictures/a.png', 'x');
        $this->actingAs($admin)->postJson('/editor-files/delete-folder', ['path' => 'tutorial-pictures'])->assertOk();
        Storage::disk('local')->assertMissing('uploads/tutorial-pictures/a.png');

        // the main folder itself is never deleted
        $this->actingAs($admin)->postJson('/editor-files/delete-folder', ['path' => ''])->assertStatus(422);
        Storage::disk('local')->assertExists('uploads');
    }

    public function test_a_file_is_deleted(): void
    {
        Storage::disk('local')->put('uploads/old.png', 'x');

        $this->actingAs($this->admin())->postJson('/editor-files/delete', ['name' => 'old.png', 'path' => ''])->assertOk();

        Storage::disk('local')->assertMissing('uploads/old.png');
    }

    // ---- the editor knows where it is ------------------------------------------------------

    public function test_the_editor_gets_the_popup_only_for_those_who_may_use_it(): void
    {
        $this->actingAs($this->admin())->get('/projects')->assertOk()->assertSee('window.EDITOR_FILES', false)->assertSee('editor-files?type=images', false);
    }

    public function test_the_editor_does_not_get_the_popup_for_anybody_else(): void
    {
        $this->actingAs($this->user())->get('/projects')->assertOk()->assertDontSee('window.EDITOR_FILES', false);
    }

    public function test_the_editor_configuration_uses_it_when_present(): void
    {
        $config = file_get_contents(public_path('js/ckeditor/config.js'));

        $this->assertStringContainsString('window.EDITOR_FILES', $config);
        $this->assertStringContainsString('filebrowserImageBrowseUrl', $config);
        $this->assertStringContainsString('filebrowserBrowseUrl', $config);
    }
}
