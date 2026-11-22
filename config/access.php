<?php

/*
 * What visitors who are not logged in may reach, depending on the "anonymous access" setting
 * (Settings page; Setting::anonymousAccess()):
 *
 *   login_required  nothing: every page asks for a login
 *   browse          the routes under 'browse' (read-only pages)
 *   submit          'browse' plus the routes under 'submit': an anonymous visitor may transcribe, and
 *                   what they submit is tied to their browser session instead of a user id
 *
 * Entries are "METHOD uri" exactly as the router reports them. Everything not listed always
 * needs a login, whatever the setting.
 */
return [
    'modes' => ['login_required', 'browse', 'submit'],

    'public_routes' => [
        'browse' => [
            'GET /',
            'GET projects',
            'GET projects/{project}',
            'GET projects/{project_id}/start',
            'GET projects/{project_id}/{id}/{field?}',
            'GET comments',
        ],
        'submit' => [
            'GET autocomplete',
            'POST store_user_response',
            'POST choose_value',
        ],
    ],
];
