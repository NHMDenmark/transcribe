<?php

namespace Tests\Feature;

use App\Models\Fields;
use App\Models\Fields_mapping;
use App\Models\Language;
use App\Models\Project;
use App\Models\Setting;
use App\Models\SyncRun;
use App\Models\User;
use App\Models\User_categories;
use App\Models\Validate_fields;
use App\Notifications\SyncRunFailed;
use App\Services\SyncRunner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The sync with the collection database without a browser, against a small fake Specify server
 * (tests/Support/fake_specify.php, started with PHP's built-in web server).
 */
class AutomaticSyncTest extends TestCase
{
    use RefreshDatabase;

    /** @var resource|null */
    private $server = null;
    private string $stateFile = '';
    private Fields $field;
    private Project $project;

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

        $this->project = new Project();
        $this->project->name = 'Moths';
        $this->project->user_id = 1;
        $this->project->active = 1;
        $this->project->specify_collection = '4';
        $this->project->save();

        $this->field = new Fields();
        $this->field->name = 'Remarks';
        $this->field->type = 'text';
        $this->field->save();
        Fields_mapping::create(['field_id' => $this->field->id, 'part' => 0, 'api_table_field' => 'Collectionobject.remarks', 'type' => 'write', 'parent' => null]);
    }

    protected function tearDown(): void
    {
        if ($this->server) {
            proc_terminate($this->server);
            proc_close($this->server);
            $this->server = null;
        }
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- the fake Specify -------------------------------------------------------------------------------

    private function specify(array $state = []): void
    {
        $this->stateFile = tempnam(sys_get_temp_dir(), 'specify');
        $objects = [];
        foreach ($state['specimens'] ?? [12] as $number) {
            // the catalog number as the collection writes it: 9 digits unless a width is given
            $catalog = str_pad((string) $number, $state['widths'][$number] ?? 9, '0', STR_PAD_LEFT);
            $objects[$catalog] = array_merge(['id' => $number, 'resource_uri' => "/api/specify/collectionobject/$number/", 'collection' => '/api/specify/collection/4/', 'remarks' => $state['existing'] ?? '', 'catalognumber' => $catalog], $state['extra'] ?? []);
            if (isset($state['twin'][$number])) {
                $objects[$catalog . '-twin'] = array_merge($objects[$catalog], $state['twin'][$number]);
            }
        }
        unset($state['specimens'], $state['existing'], $state['extra'], $state['widths'], $state['twin']);
        file_put_contents($this->stateFile, json_encode($state + ['objects' => $objects]));

        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);

        $this->server = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$port", base_path('tests/Support/fake_specify.php')],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            base_path(),
            array_merge(getenv(), ['FAKE_SPECIFY_STATE' => $this->stateFile])
        );
        for ($try = 0; $try < 50; $try++) {
            if (@fsockopen('127.0.0.1', $port)) {
                break;
            }
            usleep(100000);
        }

        config(['services.specify.url' => "http://127.0.0.1:$port", 'services.specify.username' => 'robot', 'services.specify.password' => 'secret', 'services.specify.collection' => '9']);
    }

    private function state(): array
    {
        return json_decode(file_get_contents($this->stateFile), true);
    }

    private function waiting(int $specimen, string $value = 'hello', int $status = 1): Validate_fields
    {
        $record = new Validate_fields();
        $record->specimen_id = $specimen;
        $record->field_id = $this->field->id;
        $record->position = 1;
        $record->value = $value;
        $record->status = $status;
        $record->project_id = $this->project->id;
        $record->save();

        return $record;
    }

    private function person(string $role): User
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

    // ---- the runner -------------------------------------------------------------------------------------

    public function test_a_run_sends_what_is_waiting_logs_in_to_the_projects_collection_and_keeps_a_log(): void
    {
        $this->specify();
        $record = $this->waiting(12);

        $run = app(SyncRunner::class)->run($this->project);

        $this->assertSame('ok', $run->status);
        $this->assertSame([1, 0, 0, 0], [$run->sent, $run->conflicts, $run->errors, $run->pending]);
        $this->assertSame(2, (int) $record->fresh()->status);
        $this->assertSame('auto', $run->trigger);
        $this->assertStringContainsString('SUCCESS: Record updated.', $run->log);
        $this->assertStringNotContainsString('<', $run->log, 'the log is text');

        $state = $this->state();
        $this->assertSame(4, $state['logins'][0]['collection'], "the project's collection, not the default");
        $this->assertSame('hello', $state['puts'][0]['body']['remarks']);
        $this->assertSame('/api/specify/collectionobject/12/', $state['puts'][0]['uri']);
    }

    public function test_without_a_collection_of_its_own_a_project_uses_the_default_of_the_installation(): void
    {
        $this->specify();
        $this->project->specify_collection = null;
        $this->project->save();
        $this->waiting(12);

        app(SyncRunner::class)->run($this->project);

        $this->assertSame(9, $this->state()['logins'][0]['collection']);
    }

    public function test_the_runner_sends_only_this_projects_waiting_values_and_everything_that_waits(): void
    {
        $this->specify(['specimens' => [1, 2, 3, 4, 5]]);
        config(['sync.auto_chunk' => 2]);
        $ids = collect([1, 2, 3, 4, 5])->map(fn ($n) => $this->waiting($n)->id);
        $done = $this->waiting(1, 'already', 2);                  // already sent
        $failed = $this->waiting(2, 'broken', 3);                 // failed before: not tried again by itself
        $approved = $this->waiting(3, 'approved', 6);             // an administrator approved overwriting: goes with the rest
        $other = new Project();
        $other->name = 'Beetles';
        $other->user_id = 1;
        $other->save();
        $elsewhere = $this->waiting(4, 'elsewhere');
        $elsewhere->project_id = $other->id;
        $elsewhere->save();

        $run = app(SyncRunner::class)->run($this->project);

        $this->assertSame(6, $run->sent, '5 waiting + 1 approved, in chunks of two');
        $this->assertSame(0, $run->pending);
        foreach ($ids as $id) {
            $this->assertSame(2, (int) Validate_fields::find($id)->status);
        }
        $this->assertSame(2, (int) $approved->fresh()->status);
        $this->assertSame(3, (int) $failed->fresh()->status, 'left alone');
        $this->assertSame(1, (int) $elsewhere->fresh()->status, 'another project');
    }

    // ---- objects that Specify has to create from the nested value --------------------------------------------

    /** a field that fills the start date of the record's collecting event, as in the real mapping */
    private function startDateField(): Fields
    {
        $field = new Fields();
        $field->name = 'startDate';
        $field->type = 'text';
        $field->concatenate_parts = 1;
        $field->save();
        $root = Fields_mapping::create(['field_id' => $field->id, 'part' => 0, 'api_table_field' => 'Collectionobject.collectingevent', 'type' => 'node', 'parent' => null]);
        Fields_mapping::create(['field_id' => $field->id, 'part' => 0, 'api_table_field' => 'Collectingevent.startdate', 'type' => 'write', 'parent' => $root->id]);

        return $field;
    }

    private function dataModel(): array
    {
        return [
            ['table' => 'CollectionObject', 'relationships' => [['name' => 'collectingEvent', 'type' => 'many-to-one', 'relatedModelName' => 'CollectingEvent']]],
            ['table' => 'CollectingEvent', 'relationships' => [['name' => 'discipline', 'type' => 'many-to-one', 'relatedModelName' => 'Discipline']]],
        ];
    }

    private function startDate(int $specimen): Validate_fields
    {
        $record = $this->waiting($specimen, '1896-04-16');
        $record->field_id = $this->startDateField()->id;
        $record->save();

        return $record;
    }

    public function test_a_collecting_event_that_does_not_exist_yet_is_created_with_the_discipline_of_the_collection(): void
    {
        // the record has no collecting event: Specify creates one from the nested value, and refuses it without a discipline
        $this->specify(['datamodel' => $this->dataModel(), 'require_discipline' => true, 'extra' => ['collectingevent' => null]]);
        $record = $this->startDate(12);

        $run = app(SyncRunner::class)->run($this->project);

        $this->assertSame(2, (int) $record->fresh()->status, (string) $record->fresh()->error);
        $this->assertSame(1, $run->sent);
        $put = $this->state()['puts'][0]['body']['collectingevent'];
        $this->assertSame('1896-04-16', $put['startdate']);
        $this->assertSame('/api/specify/discipline/1/', $put['discipline']);
    }

    public function test_a_collecting_event_that_already_exists_is_left_as_it_is(): void
    {
        $event = ['id' => 5, 'resource_uri' => '/api/specify/collectingevent/5/', 'startdate' => null, 'discipline' => '/api/specify/discipline/9/'];
        $this->specify(['datamodel' => $this->dataModel(), 'require_discipline' => true, 'extra' => ['collectingevent' => $event]]);
        $record = $this->startDate(12);

        app(SyncRunner::class)->run($this->project);

        $this->assertSame(2, (int) $record->fresh()->status);
        $put = $this->state()['puts'][0]['body']['collectingevent'];
        $this->assertSame('/api/specify/collectingevent/5/', $put['resource_uri']);
        $this->assertSame('/api/specify/discipline/9/', $put['discipline'], 'its own discipline, not replaced');
    }

    public function test_a_collecting_event_linked_by_its_address_is_updated_in_place_not_replaced_by_a_new_one(): void
    {
        // an independent collecting event: the record carries only its address, as on an instance where it is not embedded
        $address = '/api/specify/collectingevent/5/';
        $event = ['id' => 5, 'resource_uri' => $address, 'version' => 3, 'startdate' => null, 'discipline' => '/api/specify/discipline/9/'];
        $this->specify(['datamodel' => $this->dataModel(), 'require_discipline' => true, 'extra' => ['collectingevent' => $address], 'resources' => [$address => $event]]);
        $record = $this->startDate(12);

        $run = app(SyncRunner::class)->run($this->project);

        $this->assertSame(2, (int) $record->fresh()->status, (string) $record->fresh()->error);
        $this->assertSame(1, $run->sent);
        $put = $this->state()['puts'][0]['body']['collectingevent'];
        $this->assertSame($address, $put['resource_uri'], 'the object that is there is updated, so Specify does not create another one');
        $this->assertSame(3, $put['version']);
        $this->assertSame('1896-04-16', $put['startdate']);
        $this->assertSame('/api/specify/discipline/9/', $put['discipline'], 'its own discipline, untouched');
    }
    public function test_the_discipline_of_a_record_of_another_collection_is_asked_for_when_the_session_does_not_list_it(): void
    {
        // the session is logged in to collection 4 and lists only that one; the record belongs to collection 7
        $this->specify(['datamodel' => $this->dataModel(), 'require_discipline' => true, 'collections' => ['7' => '/api/specify/discipline/3/'],
            'extra' => ['collectingevent' => null, 'collection' => '/api/specify/collection/7/']]);
        $record = $this->startDate(12);

        app(SyncRunner::class)->run($this->project);

        $this->assertSame(2, (int) $record->fresh()->status, (string) $record->fresh()->error);
        $this->assertSame('/api/specify/discipline/3/', $this->state()['puts'][0]['body']['collectingevent']['discipline']);
    }

    public function test_when_the_discipline_cannot_be_found_anywhere_the_value_fails_with_what_specify_says(): void
    {
        $this->specify(['datamodel' => $this->dataModel(), 'require_discipline' => true,
            'extra' => ['collectingevent' => null, 'collection' => '/api/specify/collection/7/']]);   // not listed, and not readable
        $record = $this->startDate(12);

        app(SyncRunner::class)->run($this->project);

        $this->assertSame(3, (int) $record->fresh()->status);
        $this->assertSame("IntegrityError: Column 'DisciplineID' cannot be null (1048)", $record->fresh()->error);
    }

    public function test_a_value_specify_refuses_is_marked_as_an_error_and_not_retried(): void
    {
        $this->specify(['put_code' => 500]);
        $record = $this->waiting(12);

        $run = app(SyncRunner::class)->run($this->project);

        $this->assertSame('ok', $run->status, 'the run worked; the value did not');
        $this->assertSame([0, 0, 1, 0], [$run->sent, $run->conflicts, $run->errors, $run->pending]);
        $this->assertSame(3, (int) $record->fresh()->status);
        $this->assertSame('Specify says no', $record->fresh()->error);

        // the next run finds nothing waiting
        $this->assertCount(1, $this->state()['puts']);
        $again = app(SyncRunner::class)->run($this->project);
        $this->assertSame([0, 0, 0, 0], [$again->sent, $again->conflicts, $again->errors, $again->pending]);
        $this->assertCount(1, $this->state()['puts']);
    }

    public function test_a_specimen_missing_in_specify_is_an_error_of_that_value_only(): void
    {
        $this->specify(['specimens' => [12]]);
        $missing = $this->waiting(99);
        $present = $this->waiting(12);

        $run = app(SyncRunner::class)->run($this->project);

        $this->assertSame(3, (int) $missing->fresh()->status);
        $this->assertStringContainsString('not found in Specify', $missing->fresh()->error);
        $this->assertSame(2, (int) $present->fresh()->status);
        $this->assertSame([1, 0, 1], [$run->sent, $run->conflicts, $run->errors]);
    }

    public function test_a_project_that_keeps_existing_values_flags_a_conflict_for_a_person(): void
    {
        $this->specify(['existing' => 'what Specify holds']);
        $this->project->sync_overwrite = 'keep_existing';
        $this->project->save();
        $record = $this->waiting(12, 'what we transcribed');

        $run = app(SyncRunner::class)->run($this->project);

        $this->assertSame(4, (int) $record->fresh()->status);
        $this->assertSame([0, 1, 0], [$run->sent, $run->conflicts, $run->errors]);
        $this->assertArrayNotHasKey('puts', $this->state());
    }

    public function test_a_project_that_overwrites_overwrites_automatically_too(): void
    {
        $this->specify(['existing' => 'what Specify holds']);
        $record = $this->waiting(12, 'what we transcribed');

        app(SyncRunner::class)->run($this->project);

        $this->assertSame(2, (int) $record->fresh()->status);
        $this->assertSame('what we transcribed', $this->state()['puts'][0]['body']['remarks']);
    }

    public function test_a_refused_login_fails_the_run_and_leaves_the_values_waiting(): void
    {
        Notification::fake();
        $this->specify(['reject_login' => true]);
        $this->person('admin');
        $record = $this->waiting(12);

        $run = app(SyncRunner::class)->run($this->project);

        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('could not authenticate', $run->message);
        $this->assertSame(1, (int) $record->fresh()->status, 'still waiting');
        $this->assertSame(1, $run->pending);
        $this->assertStringContainsString('The run stopped', $run->log);
    }

    // ---- the catalog number, with the zeros the collection uses -------------------------------------------

    public function test_a_catalog_number_is_found_whatever_the_zeros_the_collection_writes(): void
    {
        // the same kind of number written with 1, 8, 9 and 12 digits by four different collections
        $this->specify(['specimens' => [11, 12, 13, 14], 'widths' => [11 => 1, 12 => 8, 13 => 9, 14 => 12]]);
        $records = collect([11, 12, 13, 14])->map(fn ($n) => $this->waiting($n));

        $run = app(SyncRunner::class)->run($this->project);

        $this->assertSame(4, $run->sent, $run->log);
        $this->assertSame([2, 2, 2, 2], $records->map(fn ($r) => (int) $r->fresh()->status)->all());
        $this->assertSame(['/api/specify/collectionobject/11/', '/api/specify/collectionobject/12/', '/api/specify/collectionobject/13/', '/api/specify/collectionobject/14/'],
            array_column($this->state()['puts'], 'uri'));
    }

    public function test_a_number_that_is_not_all_digits_is_looked_up_as_it_is(): void
    {
        $this->assertSame('catalognumber=E0012', \App\Services\RootLookup::query('catalognumber', 'E0012'));
        $this->assertSame('text1=000123', \App\Services\RootLookup::query('text1', '000123'), 'only the catalog number gets the zeros treatment');
        $this->assertSame(['264', '0264', '00264', '000264', '0000264', '00000264', '000000264', '0000000264', '00000000264', '000000000264'], \App\Services\RootLookup::candidates('264'));
        $this->assertSame(['00264', '264', '0264', '000264', '0000264', '00000264', '000000264', '0000000264', '00000000264', '000000000264'], \App\Services\RootLookup::candidates('00264'), 'what the platform holds comes first');
    }

    public function test_two_records_with_the_same_number_are_told_apart_by_the_projects_collection_or_refused(): void
    {
        // the number exists in collection 4 (9 digits) and in collection 7 (8 digits)
        $twin = ['id' => 112, 'resource_uri' => '/api/specify/collectionobject/112/', 'collection' => '/api/specify/collection/7/', 'catalognumber' => '00000012'];
        $this->specify(['twin' => [12 => $twin]]);
        $record = $this->waiting(12);

        // the project has no collection of its own and the default is 9: nothing says which one is meant
        $this->project->specify_collection = null;
        $this->project->save();
        $run = app(SyncRunner::class)->run($this->project);
        $this->assertSame(3, (int) $record->fresh()->status);
        $this->assertStringContainsString('2 records have that catalog number', (string) $record->fresh()->error);
        $this->assertArrayNotHasKey('puts', $this->state(), 'nothing is written when it cannot be told where');

        // with the collection of the project the right one is chosen
        Validate_fields::where('id', $record->id)->update(['status' => 1, 'error' => null]);   // waiting again
        $this->project->specify_collection = '7';
        $this->project->save();
        $run = app(SyncRunner::class)->run($this->project);
        $this->assertSame(2, (int) $record->fresh()->status, (string) $record->fresh()->error . ' | ' . $run->message);
        $this->assertSame('/api/specify/collectionobject/112/', $this->state()['puts'][0]['uri']);
    }

    public function test_each_specify_address_has_its_own_login_file(): void
    {
        config(['services.specify.url' => 'http://specify-one.example.org']);
        $one = specify_cookie_file();
        config(['services.specify.url' => 'https://specify-two.example.org']);
        $two = specify_cookie_file();

        $this->assertNotSame($one, $two, 'the cookies of two servers must not meet in one file');
        $this->assertStringStartsWith(storage_path('framework/sessions/' . session()->getId()), $one);
    }

    public function test_the_csrf_token_sent_is_the_one_of_the_instance_even_if_the_jar_holds_another_hosts(): void
    {
        // the jar of a session that was used with a local Specify and then with the real one
        $jar = "172.22.0.172\tFALSE\t/\tFALSE\t0\tcsrftoken\tLOCALTOKEN\n"
            . "specify.example.org\tFALSE\t/\tFALSE\t0\tcsrftoken\tREALTOKEN\n"
            . "#HttpOnly_specify.example.org\tFALSE\t/\tTRUE\t0\tsessionid\tabc\n"
            . "10.0.0.9\tFALSE\t/\tFALSE\t0\tcsrftoken\tOTHERTOKEN\n";

        $controller = new \App\Http\Controllers\ApiController();
        $extract = new \ReflectionMethod($controller, 'extractCookies');
        $extract->setAccessible(true);
        $pick = new \ReflectionMethod($controller, 'csrfToken');
        $pick->setAccessible(true);
        $cookies = $extract->invoke($controller, $jar);

        $this->assertSame('REALTOKEN', $pick->invoke($controller, $cookies, 'https://specify.example.org'), 'not the last one in the file');
        $this->assertSame('LOCALTOKEN', $pick->invoke($controller, $cookies, 'http://172.22.0.172'));
        $this->assertSame('OTHERTOKEN', $pick->invoke($controller, $cookies, 'http://unknown.example.org'), 'no cookie names the host: the last token, as before');
    }

    public function test_with_no_collection_anywhere_the_run_says_so_before_calling_specify(): void
    {
        $this->specify();
        config(['services.specify.collection' => '']);
        $this->project->specify_collection = null;
        $this->project->save();
        $record = $this->waiting(12);

        $run = app(SyncRunner::class)->run($this->project);

        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('API_COLLECTION', $run->message);
        $this->assertSame(1, (int) $record->fresh()->status);
        $this->assertArrayNotHasKey('logins', $this->state(), 'Specify was not even asked');
    }

    public function test_a_collection_the_user_cannot_use_fails_the_run_instead_of_syncing_into_the_previous_one(): void
    {
        $this->specify(['reject_collections' => [4]]);
        $record = $this->waiting(12);

        // the session is already open in the installation's default collection (9), as after a lookup of the schema
        $this->assertIsArray(API_login());
        $this->assertSame(9, $this->state()['logged']);

        $run = app(SyncRunner::class)->run($this->project);       // the project wants collection 4, which Specify refuses

        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('could not authenticate', $run->message);
        $this->assertSame(1, (int) $record->fresh()->status, 'still waiting');
        $this->assertArrayNotHasKey('puts', $this->state(), 'nothing was sent into the other collection');
    }
    public function test_the_administrators_hear_about_the_first_failure_and_not_about_each_repetition(): void
    {
        Notification::fake();
        $this->specify(['reject_login' => true]);
        $admin = $this->person('admin');
        $this->person('user');
        $this->waiting(12);

        $first = app(SyncRunner::class)->run($this->project);
        Notification::assertSentTo($admin, SyncRunFailed::class);
        Notification::assertCount(1);

        app(SyncRunner::class)->run($this->project);
        Notification::assertCount(1);   // still failing: no second message

        // it works again, then fails again: a new message
        $state = $this->state();
        $state['reject_login'] = false;
        file_put_contents($this->stateFile, json_encode($state));
        app(SyncRunner::class)->run($this->project);
        $state['reject_login'] = true;
        $state['logged'] = null;
        file_put_contents($this->stateFile, json_encode($state));
        $this->waiting(13);
        app(SyncRunner::class)->run($this->project);
        Notification::assertCount(2);
        $this->assertStringContainsString('/sync-runs/' . $first->id, (new SyncRunFailed($first, $this->project))->toMail($admin)->actionUrl);
    }

    public function test_without_a_connection_configured_the_run_fails_clearly(): void
    {
        config(['services.specify.url' => '']);
        $this->waiting(12);

        $run = app(SyncRunner::class)->run($this->project);

        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('not configured', $run->message);
    }

    public function test_no_login_file_is_left_behind(): void
    {
        $this->specify();
        $this->waiting(12);

        app(SyncRunner::class)->run($this->project);

        $this->assertFileDoesNotExist(specify_cookie_file());
    }

    // ---- the command and the schedule -------------------------------------------------------------------

    private function scheduled(array $attributes = []): void
    {
        $setting = Setting::current();
        $setting->auto_sync = true;
        $setting->schedule_timezone = 'UTC';
        $setting->save();

        $this->project->forceFill(array_merge(['auto_sync' => true, 'auto_sync_frequency' => 'daily', 'auto_sync_time' => '02:00', 'auto_sync_last_slot' => null], $attributes))->save();
    }

    public function test_the_master_switch_off_means_nothing_is_sent(): void
    {
        $this->specify();
        $this->scheduled();
        $setting = Setting::current();
        $setting->auto_sync = false;
        $setting->save();
        $record = $this->waiting(12);

        $this->artisan('platform:sync-due')->assertSuccessful();

        $this->assertSame(1, (int) $record->fresh()->status);
        $this->assertSame(0, SyncRun::count());
    }

    public function test_a_project_that_is_due_is_synced_once_per_moment(): void
    {
        $this->specify();
        $this->scheduled();
        Carbon::setTestNow('2026-11-09 15:00:00');
        $record = $this->waiting(12);

        $this->artisan('platform:sync-due')->expectsOutputToContain('1 sent')->assertSuccessful();
        $this->assertSame(2, (int) $record->fresh()->status);
        $this->assertSame(1, SyncRun::where('trigger', 'auto')->count());
        $this->assertSame('2026-11-09 02:00:00', $this->project->fresh()->auto_sync_last_slot->format('Y-m-d H:i:s'));

        // the next minute: the moment was handled
        $this->waiting(13);
        Carbon::setTestNow('2026-11-09 15:01:00');
        $this->artisan('platform:sync-due')->assertSuccessful();
        $this->assertSame(1, SyncRun::count());

        // tomorrow after 02:00: due again
        Carbon::setTestNow('2026-11-10 02:00:30');
        $this->artisan('platform:sync-due')->assertSuccessful();
        $this->assertSame(2, SyncRun::count());
    }

    public function test_a_project_with_its_switch_off_is_not_synced_even_if_the_global_one_is_on(): void
    {
        $this->specify();
        $this->scheduled(['auto_sync' => false]);
        $this->waiting(12);

        $this->artisan('platform:sync-due')->assertSuccessful();

        $this->assertSame(0, SyncRun::count());
    }

    public function test_a_project_can_be_synced_by_hand_from_the_command_line_whatever_its_schedule(): void
    {
        $this->specify();
        $record = $this->waiting(12);

        $this->artisan('platform:sync-due', ['--project' => $this->project->id])->expectsOutputToContain('1 sent')->assertSuccessful();

        $this->assertSame(2, (int) $record->fresh()->status);
        $this->assertSame('command', SyncRun::first()->trigger);
        $this->artisan('platform:sync-due', ['--project' => 999])->assertFailed();
    }

    public function test_the_scheduler_runs_the_sync_every_minute_without_overlapping(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'platform:sync-due'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    // ---- the manual page and the history ----------------------------------------------------------------

    /** The manual page prints as it goes (echo), so what it shows is read from the output, not from the response body. */
    private function manualPage(User $user, string $url): string
    {
        $level = ob_get_level();
        ob_start();
        $this->actingAs($user)->get($url)->assertOk();
        $page = '';
        while (ob_get_level() > $level) {
            $page = ob_get_clean() . $page;
        }

        return $page;
    }

    public function test_a_manual_sync_is_kept_in_the_history_with_who_did_it(): void
    {
        $this->specify();
        $admin = $this->person('admin');
        $record = $this->waiting(12);

        $page = $this->manualPage($admin, '/validate/manual/' . $record->id);

        $this->assertStringContainsString('SUCCESS: Record updated.', $page);

        $run = SyncRun::firstOrFail();
        $this->assertSame(['manual', $admin->id, 'ok', 1], [$run->trigger, $run->user_id, $run->status, $run->sent]);
        $this->assertStringContainsString('SUCCESS', $run->log);
    }

    public function test_a_dry_run_changes_nothing_and_is_not_in_the_history(): void
    {
        $this->specify();
        $record = $this->waiting(12);

        $page = $this->manualPage($this->person('admin'), '/validate/manual/' . $record->id . '?dryrun=1');

        $this->assertStringContainsString('DRY RUN', $page);

        $this->assertSame(0, SyncRun::count());
        $this->assertSame(1, (int) $record->fresh()->status);
        $this->assertArrayNotHasKey('puts', $this->state());
    }

    /** A value that needs an object Specify does not hold yet: collectingevent.locality.localityname, a pick. */
    private function localityRecord(int $specimen): Validate_fields
    {
        $field = new Fields();
        $field->name = 'Locality';
        $field->type = 'text';
        $field->save();
        $event = Fields_mapping::create(['field_id' => $field->id, 'part' => 0, 'api_table_field' => 'Collectionobject.collectingevent', 'type' => 'node', 'parent' => null]);
        $locality = Fields_mapping::create(['field_id' => $field->id, 'part' => 0, 'api_table_field' => 'Collectingevent.locality', 'type' => 'node', 'parent' => $event->id]);
        Fields_mapping::create(['field_id' => $field->id, 'part' => 0, 'api_table_field' => 'Locality.localityname', 'type' => 'pick', 'parent' => $locality->id]);

        $record = $this->waiting($specimen, 'Boserup');
        $record->field_id = $field->id;
        $record->save();

        return $record;
    }

    public function test_a_dry_run_creates_nothing_not_even_the_objects_a_value_needs(): void
    {
        $this->specify();
        $record = $this->localityRecord(12);

        $page = $this->manualPage($this->person('admin'), '/validate/manual/' . $record->id . '?dryrun=1');

        $this->assertStringContainsString('not created (it would be)', $page);
        $this->assertStringContainsString('DRY RUN — PUT skipped', $page, 'the rest of the run still shows what would be written');
        $this->assertArrayNotHasKey('posts', $this->state(), 'no locality was created');
        $this->assertArrayNotHasKey('puts', $this->state());
        $this->assertSame(1, (int) $record->fresh()->status);
    }

    public function test_the_same_value_creates_the_locality_when_it_is_not_a_dry_run(): void
    {
        $this->specify();
        $record = $this->localityRecord(12);

        $this->manualPage($this->person('admin'), '/validate/manual/' . $record->id);

        $this->assertSame(['locality'], array_column($this->state()['posts'], 'table'));
        $this->assertSame('Boserup', $this->state()['posts'][0]['body']['localityname']);
        $this->assertSame(2, (int) $record->fresh()->status);
    }

    public function test_a_mapping_to_a_name_that_does_not_exist_in_specify_is_refused_and_not_reported_as_synced(): void
    {
        $model = [['table' => 'CollectionObject', 'fields' => [['name' => 'remarks'], ['name' => 'catalogNumber']], 'relationships' => []]];
        $this->specify(['datamodel' => $model]);
        $field = new Fields();
        $field->name = 'Typo';
        $field->type = 'text';
        $field->save();
        Fields_mapping::create(['field_id' => $field->id, 'part' => 0, 'api_table_field' => 'Collectionobject.remarkz', 'type' => 'write', 'parent' => null]);
        $record = $this->waiting(12, 'x');
        $record->field_id = $field->id;
        $record->save();
        $good = $this->waiting(12, 'fine');

        app(SyncRunner::class)->run($this->project);

        $this->assertSame(3, (int) $record->fresh()->status);
        $this->assertStringContainsString('collectionobject.remarkz', (string) $record->fresh()->error);
        $this->assertSame(2, (int) $good->fresh()->status, 'a correct mapping is still written');
        $this->assertCount(1, $this->state()['puts']);
    }

    public function test_the_page_warns_when_the_record_is_in_another_collection_than_the_session(): void
    {
        // the records are in collection 4, the project logs in to 9
        $this->project->specify_collection = '9';
        $this->project->save();
        $this->specify();
        $record = $this->waiting(12);

        $page = $this->manualPage($this->person('admin'), '/validate/manual/' . $record->id);

        $this->assertStringContainsString('belongs to collection 4 but the session is logged in to collection 9', $page);
        $this->assertSame(2, (int) $record->fresh()->status);
    }

    public function test_a_rank_is_searched_in_the_tree_of_the_discipline_of_the_record(): void
    {
        // the mapping says tree 5 (one discipline's); the discipline of the record's collection uses tree 9
        $this->specify(['extra' => ['collectingevent' => null], 'resources' => ['/api/specify/discipline/1/' => ['id' => 1, 'geographytreedef' => '/api/specify/geographytreedef/9/']]]);
        $field = new Fields();
        $field->name = 'Country';
        $field->type = 'text';
        $field->save();
        $event = Fields_mapping::create(['field_id' => $field->id, 'part' => 0, 'api_table_field' => 'Collectionobject.collectingevent', 'type' => 'node', 'parent' => null]);
        $locality = Fields_mapping::create(['field_id' => $field->id, 'part' => 0, 'api_table_field' => 'Collectingevent.locality', 'type' => 'node', 'parent' => $event->id]);
        $geography = Fields_mapping::create(['field_id' => $field->id, 'part' => 0, 'api_table_field' => 'Locality.geography', 'type' => 'node', 'parent' => $locality->id]);
        Fields_mapping::create(['field_id' => $field->id, 'part' => 0, 'api_table_field' => 'Geography.name', 'type' => 'pick', 'parent' => $geography->id, 'rank_id' => '5_200']);
        $record = $this->waiting(12, 'Denmark');
        $record->field_id = $field->id;
        $record->save();

        app(SyncRunner::class)->run($this->project);

        $searches = implode(' ', $this->state()['gets']);
        $this->assertStringContainsString('definitionitem__treedef=9', $searches);
        $this->assertStringNotContainsString('definitionitem__treedef=5', $searches);
    }

    // ---- the required relations of a new object ---------------------------------------------------------

    private function preparationModel(): array
    {
        return [
            ['table' => 'collectionobject', 'fields' => [['name' => 'remarks']], 'relationships' => [['name' => 'preparations', 'type' => 'one-to-many', 'dependent' => true, 'relatedModelName' => 'Preparation']]],
            ['table' => 'preparation', 'fields' => [['name' => 'remarks'], ['name' => 'collectionMemberId', 'required' => true], ['name' => 'yesNo1']], 'relationships' => [
                ['name' => 'collectionObject', 'type' => 'many-to-one', 'required' => true, 'relatedModelName' => 'CollectionObject'],
                ['name' => 'prepType', 'type' => 'many-to-one', 'required' => true, 'relatedModelName' => 'PrepType'],
                ['name' => 'storage', 'type' => 'many-to-one', 'required' => false, 'relatedModelName' => 'Storage'],
            ]],
            ['table' => 'preptype', 'fields' => [], 'relationships' => []],
        ];
    }

    public function test_the_form_asks_for_the_required_relations_but_not_the_link_to_the_parent(): void
    {
        $this->specify(['datamodel' => $this->preparationModel()]);

        $asked = $this->actingAs($this->person('admin'))->getJson('/api_get-table-required-fields/preparation')->assertOk()->json();

        $this->assertSame(['prepType'], array_column($asked, 'name'), 'the type is asked; the collection object is set by Specify from where the preparation is written');
        $this->assertSame(['relation', 'PrepType'], [$asked[0]['type'], $asked[0]['relatedModel']]);
    }

    public function test_a_new_line_gets_the_required_defaults_of_its_table_and_an_existing_one_is_left_alone(): void
    {
        $this->specify([
            'datamodel' => $this->preparationModel(),
            'schema' => ['Preparation' => ['properties' => ['yesno1' => ['type' => 'boolean']]]],
            'extra' => ['preparations' => [['id' => 7, 'resource_uri' => '/api/specify/preparation/7/', 'remarks' => '', 'preptype' => '/api/specify/preptype/1/']]],
        ]);
        $field = new Fields();
        $field->name = 'Preparation remarks';
        $field->type = 'text';
        $field->required_defaults = ['preparation.preptype' => '/api/specify/preptype/5/', 'preparation.yesno1' => 'true', 'preparation.remarks' => 'never replaces what was transcribed'];
        $field->save();
        $node = Fields_mapping::create(['field_id' => $field->id, 'part' => 0, 'api_table_field' => 'Collectionobject.preparations', 'type' => 'node', 'parent' => null]);
        Fields_mapping::create(['field_id' => $field->id, 'part' => 0, 'api_table_field' => 'Preparation.remarks', 'type' => 'write', 'parent' => $node->id]);
        $first = $this->waiting(12, 'line one');
        $second = $this->waiting(12, 'line two');
        foreach ([$first, $second] as $i => $record) {
            $record->field_id = $field->id;
            $record->position = $i + 1;
            $record->save();
        }

        app(SyncRunner::class)->run($this->project);

        $puts = $this->state()['puts'];
        $existing = $puts[0]['body']['preparations'][0];
        $this->assertSame('/api/specify/preptype/1/', $existing['preptype'], 'line 1 is the preparation that is there: its type stays');
        $created = $puts[1]['body']['preparations'][1];
        $this->assertSame('line two', $created['remarks'], 'what was transcribed is not replaced by a default');
        $this->assertSame('/api/specify/preptype/5/', $created['preptype'], 'a new preparation gets the type that was chosen in the form');
        $this->assertTrue($created['yesno1'], 'a yes/no default goes as a boolean');
    }

    public function test_the_manual_page_still_ends_with_the_message_when_the_login_is_refused(): void
    {
        $this->specify(['reject_login' => true]);
        $record = $this->waiting(12);

        $page = $this->manualPage($this->person('admin'), '/validate/manual/' . $record->id);

        $this->assertStringContainsString('could not authenticate with the API', $page);

        $this->assertSame('failed', SyncRun::firstOrFail()->status);
    }

    public function test_the_history_lists_the_runs_and_opens_one(): void
    {
        $this->specify();
        $this->waiting(12);
        $run = app(SyncRunner::class)->run($this->project);
        $admin = $this->person('admin');

        $this->actingAs($admin)->get('/sync-runs')->assertOk()->assertSee('Moths');
        $this->get('/sync-runs?project=' . $this->project->id)->assertOk()->assertSee('Moths');
        $this->get('/sync-runs?status=failed')->assertOk()->assertDontSee('sync-runs/' . $run->id);
        $this->get('/sync-runs/' . $run->id)->assertOk()->assertSee('SUCCESS: Record updated.');

        Auth::logout();
        $this->actingAs($this->person('user'))->get('/sync-runs')->assertForbidden();
    }

    // ---- the settings -----------------------------------------------------------------------------------

    public function test_the_master_switch_and_the_time_zone_are_set_by_administrators(): void
    {
        $admin = $this->person('admin');

        $this->actingAs($admin)->post('/settings/scheduling', ['auto_sync' => '1', 'schedule_timezone' => 'Europe/Copenhagen'])->assertRedirect('/settings');
        $this->assertTrue(Setting::current()->auto_sync);
        $this->assertSame('Europe/Copenhagen', Setting::current()->schedule_timezone);

        $this->post('/settings/scheduling', ['schedule_timezone' => 'Europe/Copenhagen']);
        $this->assertFalse(Setting::current()->auto_sync, 'an unchecked switch sends nothing');

        $this->post('/settings/scheduling', ['schedule_timezone' => 'Not/AZone'])->assertSessionHasErrors('schedule_timezone');
        $this->assertSame('Europe/Copenhagen', Setting::current()->schedule_timezone);

        Auth::logout();
        $this->actingAs($this->person('user'))->post('/settings/scheduling', ['auto_sync' => '1', 'schedule_timezone' => 'UTC'])->assertForbidden();
    }

    public function test_the_project_form_saves_the_schedule_and_waits_for_the_next_moment(): void
    {
        $admin = $this->person('admin');
        Carbon::setTestNow('2026-11-09 15:00:00');

        $this->actingAs($admin)->post('/projects', [
            'name' => 'Moths', 'project_id' => $this->project->id, 'auto_sync_present' => '1', 'auto_sync' => '1',
            'auto_sync_frequency' => 'weekly', 'auto_sync_weekday' => '5', 'auto_sync_time' => '18:15',
        ])->assertSessionHasNoErrors();

        $project = $this->project->fresh();
        $this->assertTrue($project->auto_sync);
        $this->assertSame(['weekly', '18:15', 5], [$project->auto_sync_frequency, $project->auto_sync_time, (int) $project->auto_sync_weekday]);
        $this->assertSame('2026-11-06 18:15:00', $project->auto_sync_last_slot->format('Y-m-d H:i:s'), 'the Friday that has passed is handled: it waits for the next');

        // the form shows it, and when the next run is
        $html = $this->get('/projects/' . $project->id . '/edit')->assertOk()->getContent();
        $this->assertStringContainsString('name="auto_sync"', $html);
        $this->assertStringContainsString('2026-11-13 18:15', $html);
    }

    public function test_the_project_form_refuses_a_schedule_that_is_not_one(): void
    {
        $this->actingAs($this->person('admin'))->post('/projects', [
            'name' => 'Moths', 'project_id' => $this->project->id, 'auto_sync_present' => '1', 'auto_sync' => '1',
            'auto_sync_frequency' => 'every_second', 'auto_sync_time' => '25:99',
        ])->assertSessionHasErrors(['auto_sync_frequency', 'auto_sync_time']);

        $this->assertFalse($this->project->fresh()->auto_sync);
    }

    public function test_a_project_manager_cannot_switch_the_automatic_sync_on(): void
    {
        $manager = $this->person('project_manager');
        $this->project->user_id = $manager->id;
        $this->project->save();

        $this->actingAs($manager)->post('/projects', [
            'name' => 'Moths', 'project_id' => $this->project->id, 'auto_sync_present' => '1', 'auto_sync' => '1', 'auto_sync_frequency' => 'hourly',
        ]);

        $this->assertFalse($this->project->fresh()->auto_sync);
        $this->assertStringNotContainsString('name="auto_sync"', $this->get('/projects/' . $this->project->id . '/edit')->getContent());
    }

    public function test_the_settings_page_has_the_switch_the_zone_and_the_link_to_the_history(): void
    {
        $html = $this->actingAs($this->person('admin'))->get('/settings')->assertOk()->getContent();

        $this->assertStringContainsString('name="auto_sync"', $html);
        $this->assertStringContainsString('name="schedule_timezone"', $html);
        $this->assertStringContainsString(url('sync-runs'), $html);
    }
}
