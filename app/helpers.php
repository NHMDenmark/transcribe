<?php

//namespace App;
use App\Models\Language;
use App\Models\Fields;
use App\Models\User;
use App\Models\User_categories;
use App\Models\Collaborative_fields;
use App\Models\Validate_fields;
use App\Models\Specify_fields;
use Illuminate\Support\Facades\Gate;


/**
 * Resolve the effective transcription form workflow.
 *
 * Priority: user preference > project default > global default.
 * Returns 'single' (one field per screen) or 'all' (every field on one screen).
 */
function field_view_for($project = null, $user = null): string
{
    $user = $user ?: (Auth::check() ? Auth::user() : null);

    if ($user && in_array($user->field_view ?? null, ['single', 'all'], true)) {
        return $user->field_view;
    }
    if ($project && in_array($project->field_view_default ?? null, ['single', 'all'], true)) {
        return $project->field_view_default;
    }

    return config('transcription.field_view_default', 'all');
}

/**
 * Ids of the fields a user may work on in a project, in project field order:
 * fields within the user's level (min_user_level) and, unless $include_banned,
 * not on the user's banned list. $user_id may be a numeric user id or a guest
 * session id.
 */
function project_fields_for_user($project, $user_id, $include_banned = false): array
{
    if (!$project || $project->id == 0) {
        return [];
    }

    if (is_numeric($user_id) && ($u = User::find($user_id))) {
        $user_points = $u->visibilityPoints();
        $banned = array_filter(explode('|', $u->banned_fields ?? ''));
    } else {
        $user_points = User_categories::min('points');
        $banned = [];
    }

    return $project->fields()
        ->whereHas('user_category', function ($query) use ($user_points) {
            $query->where('points', '<=', $user_points);
        })
        ->when(!$include_banned && $banned, function ($query) use ($banned) {
            $query->whereNotIn('fields.id', $banned);
        })
        ->orderBy('parent')->orderBy('position')
        ->pluck('fields.id')->toArray();
}

/**
 * Ids of the project fields still outstanding for this user on a given specimen,
 * in the project's field order. A field is outstanding when it is available to
 * the user (level / not banned) and is neither validated for the specimen nor
 * already submitted by the user.
 */
function outstanding_fields_for($specimen_id, $project, $user_id): array
{
    $field_ids = project_fields_for_user($project, $user_id);
    if (empty($field_ids)) {
        return [];
    }

    $done = Collaborative_fields::where('user_id', $user_id)->where('status', '>', 0)
        ->where('specimen_id', $specimen_id)->pluck('field_id')->toArray();
    $done = array_merge($done, Validate_fields::where('status', 1)
        ->where('specimen_id', $specimen_id)->pluck('field_id')->toArray());
    $done = array_merge($done, Specify_fields::where('status', '>', 0)
        ->where('specimen_id', $specimen_id)
        ->where('value', '!=', '')->where('value', '!=', '_')
        ->pluck('field_id')->toArray());

    return array_values(array_diff($field_ids, $done));
}


/**
 * All three get_dialogs*() helpers used to run their own one-row query per
 * call — on a page like the specimen form (44 call sites, several inside
 * per-field/per-line loops) that's 100+ near-identical queries per request,
 * on top of the one bulk query AppServiceProvider already runs for the same
 * table. They now share App\Services\DialogTextRepository's memoized,
 * per-language [plain, html] arrays instead — see that class for why it's a
 * container singleton rather than a static cache.
 */
function get_dialogs_simple($dialog, $active_language)
{
    [$plain, ] = app(\App\Services\DialogTextRepository::class)->forLanguage($active_language);

    return $plain[$dialog] ?? $dialog;
}


/**
 * Renders a dialog's text in the active language (tags stripped) and, for
 * admins, appends a small edit-trigger icon (see dialog_edit_trigger()).
 *
 * Call sites where the return value is NOT plain HTML content — a JS string
 * (e.g. inside alert(`...`)) or an HTML attribute value (data-bs-title="...")
 * — must use get_dialogs_simple() instead, which never carries the trigger;
 * splicing an <a> tag into either of those contexts corrupts the output
 * rather than rendering an icon.
 */
function get_dialogs($dialog, $active_language)
{
    [$plain, ] = app(\App\Services\DialogTextRepository::class)->forLanguage($active_language);
    $text = $plain[$dialog] ?? $dialog;

    return $text . dialog_edit_trigger($dialog, false);
}


/**
 * Same as get_dialogs(), but keeps the stored HTML instead of stripping it —
 * for dialogs whose text is allowed to contain markup. Same restriction on
 * call sites as get_dialogs() above. The edit trigger opens a rich-text
 * (CKEditor) field for these, since the whole point is that they may
 * contain markup — get_dialogs()'s trigger opens a plain one.
 */
function get_dialogs_html($dialog, $active_language)
{
    [, $html] = app(\App\Services\DialogTextRepository::class)->forLanguage($active_language);
    $text = $html[$dialog] ?? $dialog;

    return $text . dialog_edit_trigger($dialog, true);
}

/**
 * The small pencil-icon edit trigger for admins, appended by get_dialogs()/
 * get_dialogs_html(). Echoing it (via those two) requires {!! !!} in Blade,
 * not {{ }} — same as dialog text itself already requires wherever it may
 * contain markup.
 *
 * $html marks whether this identifier's text is allowed to contain markup
 * (true from get_dialogs_html(), false from get_dialogs()) — the modal uses
 * it to decide whether to edit each language as plain text or as CKEditor
 * rich text, matching how the text is actually rendered on save.
 *
 * Clicking it fetches every language's value on demand
 * (GET /dialog_values/{identifier}) and fills the single shared edit modal
 * (see layouts.app / dialogs.edit_modal), so this never has to embed a
 * <form> at the call site — that per-call embedded <form> is what broke
 * this feature originally when used inside the specimen transcription
 * form's own big <form>.
 */
function dialog_edit_trigger($dialog, $html = false)
{
    if (!Auth::check() || !Gate::allows('edit-dialogs')) {
        return '';
    }

    // A span, not a link: it is used inside links and buttons (menu items, button labels), and an <a> inside an <a>
    // is invalid HTML - the browser closes the outer one and the pencil ends up loose, wherever the layout puts it.
    return ' <span class="dialog-edit-trigger" role="button" tabindex="0" data-dialog="' . e($dialog) . '" data-html="' . ($html ? '1' : '0') . '" title="Edit this text"><i class="bi bi-pencil-square small"></i></span>';
}

/**
 * The small "?" next to a setting: a short explanation that opens on click and, for the people who may read that
 * guide, a link to the section that explains the logic (config/help.php lists the hints). Echo it with {!! !!},
 * inside the label of the setting it explains.
 *
 * The text is an ordinary dialog, so it is translated, and edited in place, like every other text. The assets (a
 * few lines of style and script) are written with the first hint of a page.
 */
function help_hint(string $key, $active_language): string
{
    $hint = config('help.hints.' . $key);
    $user = Auth::user();
    if (!$hint || !$user) {
        return '';
    }

    $out = '';
    if (!request()->attributes->get('help_hint_assets')) {
        request()->attributes->set('help_hint_assets', true);
        $out .= view('help.hint_assets')->render();
    }

    $more = '';
    if (\App\Http\Controllers\HelpController::canRead($hint['guide'], $user)) {
        $more = '<a class="help-hint-more" target="_blank" rel="noopener" href="' . e(url('help/' . $hint['guide']) . '#' . $hint['anchor']) . '">'
            . e(get_dialogs_simple('Read more', $active_language)) . ' <i class="bi bi-box-arrow-up-right"></i></a>';
    }

    return $out . '<span class="help-hint"><span class="help-hint-icon" role="button" tabindex="0" aria-expanded="false" aria-label="'
        . e(get_dialogs_simple('Help', $active_language)) . '"><i class="bi bi-question-circle"></i></span>'
        . '<span class="help-hint-body">' . get_dialogs($hint['text'], $active_language) . $more . '</span></span>';
}

/**
 * How a user is shown to others: the name they chose (it can be a pseudonym), or "hidden user"
 * when they switched their public name off in their profile. A hidden user still sees their own
 * name, and root/admin (and the managers of the project at hand) see the real name with a
 * "(hidden)" mark, so staff can always tell who someone is. Admin screens do not use this.
 *
 * $viewer: omitted = whoever is logged in; pass null for "no one in particular" (e.g. an e-mail).
 */
function display_name($user, $viewer = 'auto', $project = null): string
{
    $language = app()->getLocale();

    if (!$user) {
        return get_dialogs_simple('hidden user', $language);
    }

    // a deleted account has no name left: it is the same for everybody
    if (!empty($user->anonymized_at)) {
        return get_dialogs_simple('Deleted user', $language);
    }

    $viewer = $viewer === 'auto' ? auth()->user() : $viewer;
    $name = trim((string) ($user->username ?? '')) !== '' ? $user->username : $user->name;

    if ($user->public_name ?? true) {
        return (string) $name;
    }

    if ($viewer && (int) $viewer->id === (int) $user->id) {
        return (string) $name;
    }

    if ($viewer && ($viewer->isAdmin() || ($project && $viewer->managesProject($project)))) {
        return $name . ' (' . get_dialogs_simple('hidden', $language) . ')';
    }

    return get_dialogs_simple('hidden user', $language);
}

/**
 * Whether this viewer may know who the user really is: the same rule as display_name() -
 * a public name, themselves, root/admin, or a manager of the project at hand.
 */
function can_see_identity($user, $viewer = 'auto', $project = null): bool
{
    if (!$user) {
        return false;
    }

    // nobody knows who a deleted account was, not even an administrator: the pattern, not initials or a photo
    if (!empty($user->anonymized_at)) {
        return false;
    }

    $viewer = $viewer === 'auto' ? auth()->user() : $viewer;

    if ($user->public_name ?? true) {
        return true;
    }

    return $viewer && ((int) $viewer->id === (int) $user->id || $viewer->isAdmin() || ($project && $viewer->managesProject($project)));
}

/**
 * A small round avatar for a user, as HTML. Their photo if they have one, otherwise a circle with
 * their initials in a colour taken from their id. When the viewer may not know who the user is
 * (they hid their name) neither the photo nor the initials are shown, only an identicon: a pattern
 * generated from the id alone, so it tells hidden users apart without hinting at who they are.
 */
function display_avatar($user, int $size = 28, $viewer = 'auto', $project = null): \Illuminate\Support\HtmlString
{
    $palette = ['#054791', '#1b7f5c', '#a4322a', '#7a4fa0', '#b86e00', '#2a7f9e', '#6b6f1e', '#c24d86', '#4a5a6a', '#8a5a3c', '#2f6f3e', '#9a3a3a'];
    $id = (int) ($user->id ?? 0);
    $color = $palette[crc32('avatar-' . $id) % count($palette)];

    if (!can_see_identity($user, $viewer, $project)) {
        $bits = md5('identicon-' . $id);
        $cells = '';
        $filled = 0;
        for ($row = 0; $row < 5; $row++) {
            for ($col = 0; $col < 3; $col++) {
                if (hexdec($bits[$row * 3 + $col]) % 2 === 0) {
                    foreach (array_unique([$col, 4 - $col]) as $c) {
                        $cells .= '<rect x="' . $c . '" y="' . $row . '" width="1" height="1"/>';
                    }
                    $filled++;
                }
            }
        }
        if ($filled === 0) {
            $cells = '<rect x="2" y="2" width="1" height="1"/>';
        }

        return new \Illuminate\Support\HtmlString(
            '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 5 5" fill="' . $color . '" shape-rendering="crispEdges" aria-hidden="true" '
            . 'style="border-radius:50%;background:#e9ecef;vertical-align:middle;flex-shrink:0">' . $cells . '</svg>'
        );
    }

    if (!empty($user->avatar)) {
        return new \Illuminate\Support\HtmlString(
            '<img src="' . e(url('avatars/' . $user->avatar)) . '" width="' . $size . '" height="' . $size . '" alt="" '
            . 'style="border-radius:50%;object-fit:cover;vertical-align:middle;flex-shrink:0">'
        );
    }

    $parts = preg_split('/\s+/u', trim((string) ($user->name ?? '')), -1, PREG_SPLIT_NO_EMPTY);
    $initials = mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));

    return new \Illuminate\Support\HtmlString(
        '<span aria-hidden="true" style="display:inline-flex;align-items:center;justify-content:center;width:' . $size . 'px;height:' . $size . 'px;'
        . 'border-radius:50%;background:' . $color . ';color:#fff;font-size:' . round($size * 0.4) . 'px;font-weight:600;line-height:1;vertical-align:middle;flex-shrink:0">'
        . e($initials) . '</span>'
    );
}

/**
 * The file that keeps the login to the collection database for this browser session. There is one per Specify address:
 * the same session may be used with another installation (a test one, then the real one), and the cookies of two
 * servers in one file make the sync send the CSRF token of the wrong one ("CSRF verification failed").
 */
function specify_cookie_file(): string
{
    return storage_path('framework/sessions/' . session()->getId() . '-' . substr(md5((string) config('services.specify.url')), 0, 8) . '.txt');
}

/** Whether visitors may create their own account (the Settings page); the navbar shows its link only then. */
function registration_open(): bool
{
    return \App\Models\Setting::current()->allowsRegistration();
}

function API_is_loggedin()
{

    $session_id = session()->getId();
    $cookie_file = specify_cookie_file();

    $specify7URL = config('services.specify.url');

    //The currently logged in user can be queried
    $url = $specify7URL . '/context/user.json';
    //echo $url;

    //init curl
    $ch = curl_init();
    //Set the URL to work with
    curl_setopt($ch, CURLOPT_URL, $url);
    //return the transfer as a string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    //execute the request (the login)
    $response = curl_exec($ch);
    // close curl resource to free up system resources
    curl_close($ch);

    $response_data = json_decode($response, true);
    //print_r($response_data);
    //echo $response_data['name'];

    if (!isset($response_data['name'])) {
        return false;
    } else {
        return $response_data;
    }
}

function API_login($collection = null)
{
    $specify7URL = config('services.specify.url');
    $username = config('services.specify.username');
    $password = config('services.specify.password');
    // the collection to log in to: the one asked for (a project's), otherwise the installation's default
    $collection = $collection ?: config('services.specify.collection');
    session(['specify_collection' => (string) $collection]);

    $loginURL = $specify7URL . '/context/login/';

    $session_id = session()->getId();
    $cookie_file = specify_cookie_file();

    //call to get the csrf token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $loginURL);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    //curl_setopt($ch, CURLOPT_POSTFIELDS, $reqBody);
    //curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);

    $response = curl_exec($ch);

    // extract header
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $headerSize);

    //process headers to extract values
    $headerText = substr($header, 0, strpos($header, "\r\n\r\n"));

    // Matching the response to extract cookie value 
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $headerText,  $match_found);

    $cookiearray = array();
    foreach ($match_found[1] as $item) {
        parse_str($item,  $cookie);
        $cookiearray = array_merge($cookiearray,  $cookie);
    }

    // Printing cookie data
    //Log::info('Cookie array', $cookiearray);
    Log::debug('Specify login cookie received', [
    'has_csrf_token' => isset($cookiearray['csrftoken']),
    ]);

    $csrftoken = $cookiearray['csrftoken'] ?? null;

    if (!$csrftoken) {
    Log::error('CSRF token missing from Specify response', [
        'header' => $header,
        'cookiearray' => $cookiearray,
        'specify7URL' => $specify7URL ?? null,
        'loginURL' => $loginURL ?? null,
        ]);

        return false;
    }

    $headers = array();
    $headers[] = "X-CSRFToken: $csrftoken";
    $headers[] = "Referer: $specify7URL";
    $postinfo = array('username' => $username, 'password' => $password);
    if ($collection) {
        $postinfo['collection'] = (int) $collection;
    }
    //$postinfo = http_build_query($postinfo);
    $postinfo = json_encode($postinfo);
    //echo $postinfo;

    //echo $csrftoken; //debug info
    //curl_setopt($ch, CURLOPT_URL, $loginURL . '?username=' . $username . '&password=' . $password . '&collection=4');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_URL, $loginURL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postinfo);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);


    $html = curl_exec($ch);
    //print($html);
    if (curl_errno($ch))
        print curl_error($ch);
    $loginCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // A refused login (a wrong password, or a collection this user cannot use) must not be taken for a success: the
    // session may still be open from an earlier login, in another collection, and user.json would say so below.
    if ($loginCode >= 400) {
        Log::warning('The collection database refused the login', ['collection' => $collection, 'http' => $loginCode]);

        return false;
    }

    //The currently logged in user can be queried
    $url = $specify7URL . '/context/user.json';
    //echo $url;


    //init curl
    $ch = curl_init();
    //Set the URL to work with
    curl_setopt($ch, CURLOPT_URL, $url);
    //return the transfer as a string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);


    //execute the request (the login)
    $response = curl_exec($ch);
    // close curl resource to free up system resources
    curl_close($ch);

    //print_r($response);

    $response_data = json_decode($response, true);
    if (!isset($response_data['name'])) {
        Log::warning('Login to the collection database failed', ['collection' => $collection, 'response' => mb_substr((string) $html, 0, 300)]);

        return false;
    } else {
        return $response_data;
    }
}

/**
 * Fetches Specify's table/field schema (name, type, per-table properties) so
 * admin UIs can offer live dropdowns instead of free-text table/field names.
 * Used by the field mapping editor and the project sync-identifier picker.
 */
function API_get_schema()
{
    // no connection to a collection database configured: nothing to look up, and nothing to log as an error
    if (! config('services.specify.url')) {
        return [];
    }

    $API_auth_user = API_is_loggedin();
    if ($API_auth_user === false) {
        $API_auth_user = API_login();
    }

    $specify7URL = config('services.specify.url');
    $url = $specify7URL . '/api/specify_schema/openapi.json';

    $session_id = session()->getId();
    $cookie_file = specify_cookie_file();

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    $response_data = json_decode($response, true);

    return $response_data['components']['schemas'] ?? [];
}
