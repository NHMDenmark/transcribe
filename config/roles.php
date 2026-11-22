<?php

/*
 * What each role may do. Roles are fixed (they are code, not data); the level a user
 * holds (users.category / user_categories) is separate and only drives points and
 * progression.
 *
 * root             the instance owner: everything, including settings, languages and roles
 * admin            day-to-day administration
 * project_manager  the projects they created or were given (see project_user)
 * user             regular transcriber
 */
return [
    'roles' => [
        'root' => 'Root',
        'admin' => 'Administrator',
        'project_manager' => 'Project manager',
        'user' => 'User',
    ],

    // capability => roles that have it
    'capabilities' => [
        'manage-settings' => ['root'],
        'manage-access' => ['root', 'admin'],
        'manage-content' => ['root', 'admin'],
        'manage-languages' => ['root'],
        'manage-roles' => ['root'],
        'manage-users' => ['root', 'admin'],
        'edit-dialogs' => ['root', 'admin'],
        'manage-fields' => ['root', 'admin'],
        'run-sync' => ['root', 'admin'],
        'view-all-submissions' => ['root', 'admin'],
        'view-project-submissions' => ['root', 'admin', 'project_manager'],
        'manage-projects' => ['root', 'admin', 'project_manager'],
        'see-others-submissions' => ['root', 'admin', 'project_manager'],
        'resolve-conflicts' => ['root', 'admin', 'project_manager'],
    ],

    // a project_manager only has these on the projects they manage
    'project_scoped' => ['manage-projects', 'see-others-submissions', 'resolve-conflicts', 'view-project-submissions'],

    // these roles are not held back by a field's or specimen's minimum level
    'ignore_level' => ['root', 'admin'],
];
