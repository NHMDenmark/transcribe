<?php

/*
 * The "?" hints that sit next to a setting, where a decision is taken: a short explanation and, for the people who
 * may read that guide, a link to the section of the guide in docs/ that explains the logic (shown by HelpController).
 *
 *   key => [
 *     'text'   the hint, in English: it is also the identifier of its translations (edit it in place with the pencils,
 *              like any other text), so at most 190 characters
 *     'guide'  administration | configuration
 *     'anchor' the id of a heading of that guide (HelpTest checks that it exists)
 *   ]
 *
 * Used in the views as help_hint('key', $active_language).
 */
return [
    'hints' => [
        // ---- the form of a field ----
        'field_type' => [
            'text' => 'The type decides how the transcriber answers: a line, a longer text, a choice from a list, several choices, or a date.',
            'guide' => 'administration', 'anchor' => 'field-types',
        ],
        'field_options' => [
            'text' => 'Each option has a value (what is saved and sent on) and a label per language (what the transcriber sees). Translating the labels does not change the data.',
            'guide' => 'administration', 'anchor' => 'options',
        ],
        'field_choices_separator' => [
            'text' => 'Empty: each ticked box is its own value. With a separator, the boxes ticked for a specimen become one value, joined with it in list order.',
            'guide' => 'administration', 'anchor' => 'checkboxes-and-the-separator',
        ],
        'field_explode' => [
            'text' => 'Splits what is typed into parts at a separator (for example day / month / year), so that each part can be mapped to its own column.',
            'guide' => 'administration', 'anchor' => 'other-field-settings',
        ],
        'field_na_defaults' => [
            'text' => 'What is sent to the collection database when the transcriber marked the field as having no information. Leave it empty to skip those records.',
            'guide' => 'administration', 'anchor' => 'other-field-settings',
        ],
        'field_autocomplete' => [
            'text' => 'Suggests values while the person types. It speeds up typing and makes spellings identical, which helps answers agree.',
            'guide' => 'administration', 'anchor' => 'other-field-settings',
        ],
        'field_deducted' => [
            'text' => 'Adds a box where the transcriber marks a value that is not written on the label but was worked out. It is stored with the answer.',
            'guide' => 'administration', 'anchor' => 'other-field-settings',
        ],
        'field_have_doubts' => [
            'text' => 'Adds a box where the transcriber marks a value they are not sure of. It is stored with the answer, so that whoever reviews it looks more carefully.',
            'guide' => 'administration', 'anchor' => 'other-field-settings',
        ],
        'field_multiple' => [
            'text' => 'Lets the transcriber add several values for one specimen (several collectors, several determinations). Each line is validated on its own.',
            'guide' => 'administration', 'anchor' => 'other-field-settings',
        ],
        'field_min_level' => [
            'text' => 'Only people at this level or higher are offered the field. Use it to keep difficult fields for experienced transcribers.',
            'guide' => 'administration', 'anchor' => 'other-field-settings',
        ],

        // ---- the form of a project ----
        'project_visible' => [
            'text' => 'An inactive project is hidden from visitors and transcribers. Administrators still see it.',
            'guide' => 'administration', 'anchor' => 'projects',
        ],
        'project_field_view' => [
            'text' => 'All fields of a specimen on one screen, or one field at a time. Each person can switch on the transcription screen, and their choice wins over the project\'s.',
            'guide' => 'administration', 'anchor' => 'how-the-form-is-shown',
        ],
        'project_sync_identifier' => [
            'text' => 'What the specimen identifier means in the collection database. By default it is the catalog number of a collection object. One project has one identifier.',
            'guide' => 'administration', 'anchor' => 'sync-identifier',
        ],
        'project_sync_collection' => [
            'text' => 'The Specify collection this project sends its validated values to. Empty means the default of the installation. Use it when projects belong to different collections.',
            'guide' => 'administration', 'anchor' => 'collection-to-sync-into',
        ],
        'project_auto_sync' => [
            'text' => 'Sends this project\'s validated values to the collection database on a schedule, with a log of every run. Conflicts still wait for a person; a value that fails is not retried by itself.',
            'guide' => 'administration', 'anchor' => 'automatic-sync',
        ],
        'project_managers' => [
            'text' => 'People who can manage this project and resolve its conflicts, besides its creator. They get that power only on this project, and any of them can add or remove the others.',
            'guide' => 'administration', 'anchor' => 'project-managers',
        ],
        'project_others_panel' => [
            'text' => 'A panel with other people\'s answers helps experts, but makes answers less independent, so agreement means less. Choose who gets it.',
            'guide' => 'administration', 'anchor' => 'who-sees-what-others-submitted',
        ],
        'project_sync_overwrite' => [
            'text' => 'What to do when the collection already holds a different value: overwrite it, or keep it and flag a conflict for someone to decide.',
            'guide' => 'administration', 'anchor' => 'when-the-collection-already-has-a-value',
        ],

        // ---- specimens ----
        'upload_file' => [
            'text' => 'A spreadsheet with the columns specimen_id and images. Several images of one specimen go in the same cell, separated by |.',
            'guide' => 'administration', 'anchor' => 'specimens-uploading-the-list',
        ],
        'upload_min_level' => [
            'text' => 'The level a person needs to transcribe the specimens of this upload. Use it to keep a hard batch for experienced people.',
            'guide' => 'administration', 'anchor' => 'specimens-uploading-the-list',
        ],

        // ---- validation ----
        'validated_status' => [
            'text' => 'Where each value stands: waiting, sent, error, conflict, resolved, approved to overwrite, or ignored.',
            'guide' => 'administration', 'anchor' => 'validated-values-conflicts-and-export',
        ],
        'category_points' => [
            'text' => 'The weight of this level\'s answers. Identical answers add their points, and at 60 the value is valid.',
            'guide' => 'administration', 'anchor' => 'how-a-value-becomes-valid',
        ],

        // ---- platform ----
        'category_validations' => [
            'text' => 'How many of a person\'s contributions must have been validated to reach this level. Empty: only an administrator can give it.',
            'guide' => 'configuration', 'anchor' => 'user-levels',
        ],
        'settings_access' => [
            'text' => 'What people without an account can do: nothing, browse, or also transcribe. What an anonymous visitor submits is tied to the browser session.',
            'guide' => 'configuration', 'anchor' => 'access-for-visitors',
        ],
        'settings_registration' => [
            'text' => 'Let people create their own account, or keep it by invitation. Self-registered people confirm their e-mail and start at the lowest level.',
            'guide' => 'configuration', 'anchor' => 'creating-accounts',
        ],
        'settings_scheduler' => [
            'text' => 'Some work runs by itself: the weekly ranking, nightly figures and the automatic sync. It needs one cron line on the server; this tells you whether it is running.',
            'guide' => 'configuration', 'anchor' => 'scheduled-tasks',
        ],
        'settings_auto_sync' => [
            'text' => 'The master switch of the automatic sync. Off, no project sends anything by itself, whatever its own schedule says. The time zone is the one the schedules are written in.',
            'guide' => 'administration', 'anchor' => 'automatic-sync',
        ],
        'settings_logo' => [
            'text' => 'Shown in the menu bar of every page. Until one is uploaded, the name of the application is shown instead.',
            'guide' => 'configuration', 'anchor' => 'settings-page',
        ],
        'language_code' => [
            'text' => 'It must match a flag in public/flags/4x3 (dk, pt, de…), and it is stored with every translated text: choose it once and keep it.',
            'guide' => 'configuration', 'anchor' => 'languages',
        ],
        'user_role' => [
            'text' => 'Roles are fixed and decide what a person may do. The level is something else: it only drives points and progress.',
            'guide' => 'configuration', 'anchor' => 'roles',
        ],
        'user_level' => [
            'text' => 'The level sets the weight of a person\'s answers and which fields they are offered. It rises with validated contributions; an administrator can also set it.',
            'guide' => 'configuration', 'anchor' => 'user-levels',
        ],
    ],
];
