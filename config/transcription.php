<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default transcription form workflow
    |--------------------------------------------------------------------------
    |
    | 'single' — present one field per screen for each specimen
    | 'all'    — present every field of a specimen on a single screen
    |
    | This is the lowest-priority default. A project may override it
    | (projects.field_view_default) and a user may override that
    | (users.field_view). Resolved by field_view_for().
    |
    */

    'field_view_default' => env('FIELD_VIEW_DEFAULT', 'all'),

];
