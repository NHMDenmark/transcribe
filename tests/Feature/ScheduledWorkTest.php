<?php

namespace Tests\Feature;

use App\Models\Collaborative_fields;
use App\Models\Language;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Models\User_categories;
use App\Models\Week_report;
use App\Services\WeeklyReport;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Recurring work belongs to the scheduler (cron), not to whoever opens a page.
 */
class ScheduledWorkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $language = new Language();
        $language->iso2 = 'en';
        $language->master = 1;
        $language->save();
        $cat = new User_categories();
        $cat->id = 5;
        $cat->category = 'level 5';
        $cat->points = 20;
        $cat->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(string $role = 'user'): User
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

    private function submission(User $user, string $when, int $status): void
    {
        static $n = 0;
        $row = new Collaborative_fields();
        $row->user_id = $user->id;
        $row->specimen_id = 1000 + (++$n);
        $row->field_id = 1;
        $row->position = 1;
        $row->value = 'x';
        $row->status = $status;
        $row->created_at = $when;
        $row->updated_at = $when;
        $row->save();
    }

    // ---- the week -----------------------------------------------------------------------------------------

    public function test_a_week_runs_from_sunday_to_sunday_and_is_named_after_its_saturday(): void
    {
        $report = new WeeklyReport();

        // Monday 9 Nov 2026: the last week that ended is Sunday 1 to Saturday 7 November
        [$start, $end, $label] = $report->lastWeek(Carbon::parse('2026-11-09 10:30:00'));
        $this->assertSame('2026-11-01 00:00:00', $start->toDateTimeString());
        $this->assertSame('2026-11-08 00:00:00', $end->toDateTimeString());
        $this->assertSame('2026_45', $label);

        // on the Sunday that closes a week, that week is the last one
        [$start, , $label] = $report->lastWeek(Carbon::parse('2026-11-15 00:10:00'));
        $this->assertSame('2026-11-08', $start->toDateString());
        $this->assertSame('2026_46', $label);

        // the Saturday before it closes, the week still running is not the last one
        [$start] = $report->lastWeek(Carbon::parse('2026-11-14 23:59:00'));
        $this->assertSame('2026-11-01', $start->toDateString());
    }

    public function test_the_report_counts_only_that_week_and_is_written_once(): void
    {
        $ada = $this->person();
        $bob = $this->person();
        $this->submission($ada, '2026-11-03 12:00:00', 2);       // inside
        $this->submission($ada, '2026-11-07 23:59:00', 1);       // inside
        $this->submission($ada, '2026-11-08 00:00:00', 2);       // the next week
        $this->submission($ada, '2026-10-31 23:59:00', 2);       // the week before

        $report = new WeeklyReport();
        $now = Carbon::parse('2026-11-09 10:30:00');
        $this->assertFalse($report->isDone($now));

        $this->assertSame(2, $report->generate($now));
        $row = Week_report::where('user_id', $ada->id)->where('year_week', '2026_45')->firstOrFail();
        $this->assertSame([1, 1, 0, 2], [(int) $row->submitted, (int) $row->validated, (int) $row->rejected, (int) $row->total]);
        $this->assertSame(0, (int) Week_report::where('user_id', $bob->id)->first()->total);

        // again: nothing changes, nothing is duplicated
        $this->submission($ada, '2026-11-04 12:00:00', 3);
        $this->assertSame(0, $report->generate($now));
        $this->assertSame(2, Week_report::where('year_week', '2026_45')->count());
        $this->assertSame(2, (int) Week_report::where('user_id', $ada->id)->first()->total);
        $this->assertTrue($report->isDone($now));
    }

    public function test_the_table_refuses_a_second_row_for_the_same_person_and_week(): void
    {
        $ada = $this->person();
        foreach ([1, 2] as $try) {
            $row = new Week_report();
            $row->user_id = $ada->id;
            $row->year_week = '2026_45';
            if ($try === 2) {
                $this->expectException(\Illuminate\Database\QueryException::class);
            }
            $row->save();
        }
    }

    public function test_visiting_the_home_page_no_longer_calculates_the_week(): void
    {
        $this->person();
        $this->actingAs($this->person('admin'))->get('/')->assertOk();

        $this->assertSame(0, Week_report::count());
    }

    public function test_the_command_writes_the_week_and_then_says_it_is_done(): void
    {
        $this->person();
        Carbon::setTestNow('2026-11-09 10:30:00');

        $this->artisan('platform:weekly-report')->expectsOutputToContain('1 contributors written')->assertSuccessful();
        $this->artisan('platform:weekly-report')->expectsOutputToContain('already written')->assertSuccessful();
    }

    // ---- the figures --------------------------------------------------------------------------------------

    public function test_the_nightly_commands_exist_and_run(): void
    {
        $this->person();

        $this->artisan('platform:update-project-stats')->assertSuccessful();
        $this->artisan('platform:recount-users')->assertSuccessful();
    }

    public function test_the_recount_gives_each_person_their_figures(): void
    {
        $ada = $this->person();
        $this->submission($ada, '2026-11-03 12:00:00', 2);
        $this->submission($ada, '2026-11-03 13:00:00', 1);

        Artisan::call('platform:recount-users');

        $ada->refresh();
        $this->assertSame([1, 0, 1], [(int) $ada->validated, (int) $ada->rejected, (int) $ada->submitted]);
    }

    public function test_the_project_figures_are_recalculated_for_the_projects_asked_for_only(): void
    {
        foreach (['A', 'B'] as $name) {
            $project = new Project();
            $project->name = $name;
            $project->user_id = 1;
            $project->active = 1;
            $project->specimens_count = 99;
            $project->save();
        }
        $a = Project::where('name', 'A')->first();

        app(\App\Services\ProjectStats::class)->update([$a->id]);

        $this->assertSame(0, (int) $a->fresh()->specimens_count, 'recalculated');
        $this->assertSame(99, (int) Project::where('name', 'B')->first()->specimens_count, 'left alone');
    }

    public function test_the_full_recalculation_by_url_is_for_those_who_manage_users(): void
    {
        $this->actingAs($this->person('user'))->get('/update_project_stats')->assertForbidden();

        Auth::logout();
        $this->actingAs($this->person('admin'))->get('/update_project_stats')->assertOk();
    }

    // ---- the scheduler ------------------------------------------------------------------------------------

    public function test_the_scheduler_has_the_recurring_work_and_a_heartbeat(): void
    {
        $scheduled = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command . '|' . $event->description);

        foreach (['platform:weekly-report', 'platform:update-project-stats', 'platform:recount-users', 'scheduler-heartbeat'] as $name) {
            $this->assertTrue($scheduled->contains(fn ($c) => str_contains($c, $name)), "$name is scheduled");
        }
    }

    public function test_the_settings_page_says_whether_the_scheduler_runs(): void
    {
        $admin = $this->person('admin');

        $html = $this->actingAs($admin)->get('/settings')->assertOk()->getContent();
        $this->assertStringContainsString('Not running', $html);
        $this->assertStringContainsString('has never run', $html);

        Setting::markSchedulerSeen();
        Auth::logout();
        $html = $this->actingAs($admin)->get('/settings')->getContent();
        $this->assertStringContainsString('Running', $html);
        $this->assertStringNotContainsString('Not running', $html);

        // a heartbeat older than a few minutes means it stopped
        Setting::current()->forceFill(['scheduler_seen_at' => now()->subMinutes(30)])->save();
        $this->assertFalse(Setting::current()->schedulerRunning());
    }
}
