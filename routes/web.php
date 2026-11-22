<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\SpecimensController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

//Using HTTPS over a reverse proxy in Laravel https://cylab.be/blog/122/using-https-over-a-reverse-proxy-in-laravel
//This will retrieve the application URL from the .env file and force all the incoming requests to use HTTP or HTTPS depending on the APP_URL written in your .env file. Speaking of the devil, you must now precise in APP_URL which port you're using if you use a port different than the default ones (80 for HTTP and 443 for HTTPS)
$app_url = config("app.url");
if (app()->environment('production') && !empty($app_url)) {
    URL::forceRootUrl($app_url);
    $schema = explode(':', $app_url)[0];
    URL::forceScheme($schema);
}


//Auth::routes();
// the registration routes exist always; RegisterController answers 404 unless an administrator switched self-registration on
Auth::routes(['register' => true, 'verify' => true]);

Route::get('/email/verify', function () {
    return view('auth.verify');
})->middleware('auth')->name('verification.notice');

// Agrupamos as rotas de autenticação para manter o ficheiro organizado
Route::prefix('auth')->group(function () {
    
    // 1. Rota para redirecionar o utilizador para o provider (ex: /auth/google/redirect)
    Route::get('/{provider}/redirect', [SocialiteController::class, 'redirect'])
        ->name('social.redirect');

    // 2. Rota de callback onde o provider devolve os dados (ex: /auth/google/callback)
    Route::get('/{provider}/callback', [SocialiteController::class, 'callback'])
        ->name('social.callback');
});

Route::group(['middleware' => ['auth.or.public']], function() //login required, except on the pages the "anonymous access" setting opens (config/access.php)
{

Route::middleware('can:manage-users')->group(function () {
    Route::get('/update_user_counts', [App\Http\Controllers\UsersController::class, 'update_user_counts']);
    Route::get('/users', [App\Http\Controllers\UsersController::class, 'index']);
    Route::get('/users_weekly_report', [App\Http\Controllers\UsersController::class, 'weekly_report']);
    Route::post('/user_edit', [App\Http\Controllers\UsersController::class, 'save']);
    Route::post('/user_anonymize', [App\Http\Controllers\UsersController::class, 'anonymize']);
    Route::resource('user_categories', App\Http\Controllers\User_categoriesController::class);
});
Route::post('/set_field_view', [App\Http\Controllers\UsersController::class, 'setFieldView']);
Route::post('/toggle_field_ban', [App\Http\Controllers\UsersController::class, 'toggleFieldBan']);


Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'edit']);
Route::patch('/profile', [App\Http\Controllers\ProfileController::class, 'update']);
Route::put('/profile/password', [App\Http\Controllers\ProfileController::class, 'updatePassword']);
Route::post('/profile/avatar', [App\Http\Controllers\ProfileController::class, 'updateAvatar']);
Route::delete('/profile/avatar', [App\Http\Controllers\ProfileController::class, 'removeAvatar']);
Route::delete('/profile', [App\Http\Controllers\ProfileController::class, 'destroy']);

Route::get('/dashboard', [App\Http\Controllers\UsersController::class, 'dashboard']);
Route::get('/dashboard/csv', [App\Http\Controllers\UsersController::class, 'my_submissions_csv']);
Route::get('/dashboard/json', [App\Http\Controllers\UsersController::class, 'my_submissions_json']);
Route::get('/all_submissions', [App\Http\Controllers\UsersController::class, 'all_submissions'])->middleware('can:view-project-submissions');
Route::get('/all_submissions/csv', [App\Http\Controllers\UsersController::class, 'all_submissions_csv'])->middleware('can:view-project-submissions');
Route::get('/all_submissions/json', [App\Http\Controllers\UsersController::class, 'all_submissions_json'])->middleware('can:view-project-submissions');

Route::get('/my_stats', [App\Http\Controllers\UsersController::class, 'stats']);
Route::get('/all_stats', [App\Http\Controllers\UsersController::class, 'all_stats'])->middleware('can:view-all-submissions');


Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get('/', [App\Http\Controllers\PagesController::class, 'index']);


// the menu tree and the pages in it
Route::middleware('can:manage-content')->group(function () {
    Route::post('navigation/sort', [App\Http\Controllers\NavigationController::class, 'sort']);
    Route::resource('navigation', App\Http\Controllers\NavigationController::class)->except(['show']);
    Route::post('contents/sort', [App\Http\Controllers\ContentItemsController::class, 'sort']);
    Route::resource('contents', App\Http\Controllers\ContentItemsController::class)->except(['show']);
    // the editor's "browse the server" popup: images and files under public/uploads
    Route::get('editor-files', [App\Http\Controllers\EditorFilesController::class, 'browse']);
    Route::post('editor-files/upload', [App\Http\Controllers\EditorFilesController::class, 'upload']);
    Route::post('editor-files/delete', [App\Http\Controllers\EditorFilesController::class, 'delete']);
    Route::post('editor-files/create-folder', [App\Http\Controllers\EditorFilesController::class, 'createFolder']);
    Route::post('editor-files/delete-folder', [App\Http\Controllers\EditorFilesController::class, 'deleteFolder']);
    Route::post('footer/sort', [App\Http\Controllers\FooterBlocksController::class, 'sort']);
    Route::resource('footer', App\Http\Controllers\FooterBlocksController::class)->except(['show']);
});

Route::resource('projects', App\Http\Controllers\ProjectsController::class);
Route::get('projects/{project_id}/start', [App\Http\Controllers\ProjectsController::class, 'start']);
Route::post('update_specimens_submit', [App\Http\Controllers\ProjectsController::class, 'update_specimens_submit']);
Route::get('project_update_specimens/{project_id}', [App\Http\Controllers\ProjectsController::class, 'update_specimens']);
Route::get('/projects/{project_id}/upload', [App\Http\Controllers\ProjectsController::class, 'data_upload_form'])->name('upload');
Route::post('/import', [App\Http\Controllers\ProjectsController::class, 'import'])->name('import');
Route::get('/projects/{project_id}/specimens', [App\Http\Controllers\ProjectsController::class, 'specimens'])->name('specimens');
Route::get('/projects/{project_id}/fields', [App\Http\Controllers\ProjectsController::class, 'fields'])->name('fields');
Route::post('/project_update_fields', [App\Http\Controllers\ProjectsController::class, 'update_fields'])->name('update_fields');
Route::get('/update_project_stats', [App\Http\Controllers\ProjectsController::class, 'update_project_stats'])->middleware('can:manage-users');



Route::get('projects/{project_id}/{id}/{field?}/', [App\Http\Controllers\SpecimensController::class, 'show'])->name('show');
Route::post('store_user_response', [App\Http\Controllers\SpecimensController::class, 'store'])->name('store');
Route::post('choose_value', [App\Http\Controllers\SpecimensController::class, 'choose_value'])->name('choose_value');


Route::resource('languages', App\Http\Controllers\LanguagesController::class)->middleware('can:manage-languages');
Route::middleware('can:edit-dialogs')->group(function () {
    Route::get('dialogs/{language_id}', [App\Http\Controllers\DialogsController::class, 'show']);
    Route::post('editdialog', [App\Http\Controllers\DialogsController::class, 'update']);
    Route::post('editdialog_inline', [App\Http\Controllers\DialogsController::class, 'store']);
    Route::get('dialog_values/{identifier}', [App\Http\Controllers\DialogsController::class, 'values']);
    Route::get('build_all_missing_dialogs', [App\Http\Controllers\DialogsController::class, 'build_all_missing_dialogs']);
});


Route::middleware('can:manage-fields')->group(function () {
    Route::get('/fields', [App\Http\Controllers\FieldsController::class, 'show']);
    Route::post('/field_edit', [App\Http\Controllers\FieldsController::class, 'save']);
    Route::post('/api_field_edit', [App\Http\Controllers\FieldsController::class, 'api_field_edit']);
    Route::post('/fields_sort', [App\Http\Controllers\FieldsController::class, 'save_order']);
    Route::get('/field_submitted_values/{id}', [App\Http\Controllers\FieldsController::class, 'submitted_values']);
    Route::post('/field_delete', [App\Http\Controllers\FieldsController::class, 'delete_field']);
    Route::get('/update_tree_options', [App\Http\Controllers\FieldsController::class, 'update_tree_options']);
    Route::get('/fill_options_from_current', [App\Http\Controllers\FieldsController::class, 'fill_options_from_current']);

    Route::get('/api_get-table-ranks/{table}', [App\Http\Controllers\FieldsController::class, 'getTableRanks']);
    Route::get('/api_get-table-required-fields/{table}',
        [App\Http\Controllers\FieldsController::class, 'getTableRequiredFields']);
    Route::get('/api_get-related-options/{table}',
        [App\Http\Controllers\FieldsController::class, 'getRelatedOptions']);
});

// the page opens for root (branding) and for admins (access); each form is saved under its own capability
Route::get('/settings', [App\Http\Controllers\SettingsController::class, 'edit']);
Route::post('/settings', [App\Http\Controllers\SettingsController::class, 'update'])->middleware('can:manage-settings');
Route::post('/settings/access', [App\Http\Controllers\SettingsController::class, 'updateAccess'])->middleware('can:manage-access');
Route::post('/settings/scheduling', [App\Http\Controllers\SettingsController::class, 'updateScheduling'])->middleware('can:manage-access');
Route::post('/settings/registration', [App\Http\Controllers\SettingsController::class, 'updateRegistration'])->middleware('can:manage-access');


Route::get('autocomplete', [App\Http\Controllers\SpecimensController::class, 'autocomplete'])->name('autocomplete');
Route::get('autocomplete_comments',  [SpecimensController::class, 'autocomplete_comments'])->name('autocomplete_comments');
Route::post('submit_comment', [App\Http\Controllers\SpecimensController::class, 'submit_comment']);
Route::post('delete_comment', [App\Http\Controllers\SpecimensController::class, 'delete_comment']);
Route::get('comments', [App\Http\Controllers\CommentsController::class, 'index']);


Route::middleware('can:run-sync')->group(function () {
    Route::get('/sync-runs', [App\Http\Controllers\SyncRunsController::class, 'index']);
    Route::get('/sync-runs/{run}', [App\Http\Controllers\SyncRunsController::class, 'show']);
    Route::get('/validate/autovalidate', [App\Http\Controllers\ValidationController::class, 'autovalidate']);
    Route::get('/validate/manual/{id?}', [App\Http\Controllers\ApiController::class, 'ingest_validated_nested']);
    Route::get('/validate/reevaulate_validation', [App\Http\Controllers\ValidationController::class, 'reevaulate_validation']);

    Route::get('/validated/json', [App\Http\Controllers\ValidationController::class, 'validated_json']);
    Route::get('/validated/csv', [App\Http\Controllers\ValidationController::class, 'validated_csv']);
    Route::post('/validated/{id}/ignore', [App\Http\Controllers\ValidationController::class, 'ignore']);
});
// the validated list: everything for root/admin, only their own projects for a project manager resolving conflicts (checked in the controller)
Route::get('/validated', [App\Http\Controllers\ValidationController::class, 'index']);
Route::get('/conflicts/{id}', [App\Http\Controllers\ConflictsController::class, 'show']);
Route::post('/conflicts/{id}/overwrite', [App\Http\Controllers\ConflictsController::class, 'overwrite']);
Route::post('/conflicts/{id}/keep', [App\Http\Controllers\ConflictsController::class, 'keep']);

Route::middleware('can:run-sync')->group(function () {
    Route::get('/promote_users', [App\Http\Controllers\ValidationController::class, 'promote_users']);

    Route::get('/ingest_validated', [App\Http\Controllers\ApiController::class, 'ingest_validated']);
    Route::get('/ingest_validated_nested', [App\Http\Controllers\ApiController::class, 'ingest_validated_nested']);
});



});

// Pages of the navigation tree: only reached by addresses nothing above answers.
Route::fallback([App\Http\Controllers\ContentController::class, 'show']);

// the guides of docs/ inside the application: each person sees the ones meant for their role
Route::get('/help/{page?}', [App\Http\Controllers\HelpController::class, 'show']);
