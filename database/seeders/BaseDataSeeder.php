<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * What a new installation needs in order to work at all, and nothing that belongs to an institution:
 * a master language, the default user levels, the English texts of the pages that are not plain labels, and
 * the branding row. Everything else (projects, fields, the website's menu and pages, other languages, users)
 * is created by the administrators.
 *
 * It only ever adds what is missing and never changes what is there, so it is safe to run again after an
 * update (php artisan db:seed --class=BaseDataSeeder --force) to receive texts added by a new version.
 *
 * It is a seeder and not a migration on purpose: a migration runs once and leaves its rows in every database
 * that is migrated (the test database included), whereas this has to be repeatable and optional.
 */
class BaseDataSeeder extends Seeder
{
    /**
     * Most of the interface is its own English label (a text without a stored translation is shown as its
     * identifier), so only the texts whose identifier is not a readable label are stored here.
     */
    public const DIALOGS = [
        'platform_title' => 'Transcription platform',
        'platform_message' => '<p>Welcome to the transcription platform.</p><p>To start transcribing, choose a project below or go to "Projects" and choose a project from there.</p>',
        'projects_active' => 'Active projects',
        'Projects_page_title' => 'Projects',
        'tutorial' => 'Tutorial',
        'participate' => 'Participate in the project',
        'read_more' => 'Read more',
        'best_contributors' => 'Best contributors',
        'best_contributors_week' => 'Best contributors of last week',
        'recent_comments' => 'Recent comments',
        'all_comments' => 'All comments',
        'no_information' => 'No information',
        'no_information_all' => 'No information (all fields)',
        'have_doubts' => 'Have doubts',
        'deducted' => 'Deducted',
        'guided_form' => 'Guided form',
        'full_form' => 'Full form',
        'indecipherable' => 'Indecipherable',
        'skip' => 'Skip',
        'submit' => 'Submit',
    ];

    /**
     * The levels of a transcriber: progression is by validated contributions. Level 0 is the one for people who
     * validate (it is not given automatically). The names and numbers can be changed in the administration.
     */
    private const LEVELS = [
        // id => [name, validations required to reach it (null: not reached by progression), points]
        0 => ['Validator', null, 60],
        1 => ['Basic', 0, 10],
        2 => ['Beginner', 10, 20],
        3 => ['Competent', 50, 30],
        4 => ['Advanced', 100, 40],
        5 => ['Expert', 500, 50],
    ];

    public function run(): void
    {
        $this->language();
        $this->levels();
        $this->dialogs();

        Setting::current();   // the branding row, empty: no logo, the name of the application in its place
    }

    private function language(): void
    {
        if (DB::table('languages')->exists()) {
            return;
        }

        DB::table('languages')->insert([
            'master' => 1,
            'iso2' => 'en',
            'text' => 'English',
            'active' => 1,
            'cover_image' => 'noimage.jpg',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function levels(): void
    {
        if (DB::table('user_categories')->exists()) {
            return;
        }

        foreach (self::LEVELS as $id => [$name, $required, $points]) {
            // an id of 0 is replaced by the next number when inserted, so the row is moved to 0 afterwards
            $new = DB::table('user_categories')->insertGetId([
                'category' => $name,
                'validations_required' => $required,
                'points' => $points,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($new !== $id) {
                DB::table('user_categories')->where('id', $new)->update(['id' => $id]);
            }
        }
    }

    private function dialogs(): void
    {
        $master = DB::table('languages')->where('master', 1)->value('iso2');
        if ($master === null) {
            return;
        }

        $present = DB::table('dialogs')->where('language', $master)->pluck('identifier')->all();
        foreach (self::DIALOGS as $identifier => $text) {
            if (in_array($identifier, $present, true)) {
                continue;
            }
            DB::table('dialogs')->insert([
                'identifier' => $identifier,
                'language' => $master,
                'text' => $text,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
