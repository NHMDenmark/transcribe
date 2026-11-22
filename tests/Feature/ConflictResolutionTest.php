<?php

namespace Tests\Feature;

use App\Http\Controllers\ApiController;
use App\Models\Fields;
use App\Models\Fields_mapping;
use App\Models\Project;
use App\Models\Specimen;
use App\Models\User;
use App\Models\User_categories;
use App\Models\Validate_fields;
use App\Services\SpecifyClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ConflictResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function user(int $category): User
    {
        $cat = new User_categories();
        $cat->id = $category;
        $cat->category = 'c' . $category;
        $cat->points = 0;
        $cat->save();

        $user = new User();
        $user->category = $category;
        $user->role = $category <= 1 ? 'admin' : 'user'; // the old convention (level 0/1 = admin) only decides the test user's role now
        $user->name = 'U' . $category;
        $user->email = uniqid() . '@example.test';
        $user->password = bcrypt('password');
        $user->email_verified_at = now();
        $user->field_view = 'all';
        $user->save();

        return $user;
    }

    /** A validated entoLocality 'Boserup|55.9|12.3' mapped to collectingevent.locality.{localityname,latitude1,longitude1}. */
    private function conflictRecord(int $status = 4, array $projectAttrs = []): Validate_fields
    {
        $project = new Project();
        $project->name = 'Conflict Project';
        $project->user_id = 1;
        $project->active = 1;
        foreach ($projectAttrs as $k => $v) {
            $project->$k = $v;
        }
        $project->save();

        $field = new Fields();
        $field->name = 'entoLocality';
        $field->type = 'text';
        $field->position = 1;
        $field->explode_char = '|';
        $field->explode_fields = 'Locality|Latitude|Longitude';
        $field->save();

        foreach (['localityname', 'latitude1', 'longitude1'] as $part => $leaf) {
            $root = Fields_mapping::create(['field_id' => $field->id, 'part' => (string) $part, 'api_table_field' => 'CollectionObject.collectingevent', 'type' => 'node']);
            $locality = Fields_mapping::create(['field_id' => $field->id, 'part' => (string) $part, 'api_table_field' => 'CollectingEvent.locality', 'type' => 'node', 'parent' => $root->id]);
            Fields_mapping::create(['field_id' => $field->id, 'part' => (string) $part, 'api_table_field' => 'Locality.' . $leaf, 'type' => 'pick', 'parent' => $locality->id]);
        }

        Specimen::create(['specimen_id' => '999040', 'project_id' => $project->id, 'images' => 'a.jpg|b.jpg']);

        $record = new Validate_fields();
        $record->value = 'Boserup|55.9|12.3';
        $record->position = 1;
        $record->specimen_id = '999040';
        $record->field_id = $field->id;
        $record->user_id = 1;
        $record->project_id = $project->id;
        $record->status = $status;
        $record->save();

        return $record;
    }

    /** Stands in for Specify: the specimen's locality is "Silkebjerg" at 55.9, with no longitude. */
    private function fakeSpecify(bool $reachable = true, string $localityName = 'Silkebjerg'): void
    {
        $this->app->instance(SpecifyClient::class, new class($reachable, $localityName) extends SpecifyClient {
            public function __construct(private bool $reachable, private string $name)
            {
            }

            public function baseUrl(): string
            {
                return 'https://specify.test';
            }

            public function get(string $path): ?array
            {
                if (!$this->reachable) {
                    return null;
                }

                return match ($path) {
                    '/api/specify/collectionobject/?' . \App\Services\RootLookup::query('catalognumber', '999040') => ['objects' => [[
                        'id' => 77,
                        'resource_uri' => '/api/specify/collectionobject/77/',
                        'collectingevent' => '/api/specify/collectingevent/5/',
                    ]]],
                    '/api/specify/collectingevent/5/' => ['locality' => '/api/specify/locality/9/'],
                    '/api/specify/locality/9/' => ['localityname' => $this->name, 'latitude1' => '55.9000', 'longitude1' => null],
                    default => null,
                };
            }
        });
    }

    /** Stands in for the real sync, which talks to Specify. */
    private function fakeSync(int $resultingStatus, ?string $error = null): void
    {
        $this->app->instance(ApiController::class, new class($resultingStatus, $error) extends ApiController {
            public function __construct(private int $status, private ?string $err)
            {
            }

            public function ingest_validated_nested(Request $request, $id = null)
            {
                $r = Validate_fields::find($id);
                $r->status = $this->status;
                $r->error = $this->err;
                $r->save();
            }
        });
    }

    public function test_the_page_lines_the_validated_value_up_against_what_specify_holds_now(): void
    {
        $record = $this->conflictRecord();
        $this->fakeSpecify();

        $response = $this->actingAs($this->user(1))->get('/conflicts/' . $record->id);

        $response->assertOk()
            ->assertSee('Locality')       // labels from the field definition
            ->assertSee('Boserup')        // validated
            ->assertSee('Silkebjerg')     // live from Specify
            ->assertSee('55.9000')        // equal after numeric comparison, shown as Specify has it
            ->assertSee('https://specify.test/specify/view/collectionobject/77/', false)
            ->assertSee('/conflicts/' . $record->id . '/overwrite', false)
            ->assertSee('/conflicts/' . $record->id . '/keep', false);

        // only the locality name differs; latitude is the same value and longitude is empty in Specify
        $this->assertSame(1, substr_count($response->getContent(), 'table-warning'));
    }

    public function test_what_specify_holds_is_read_on_every_visit_not_stored(): void
    {
        $record = $this->conflictRecord();
        $admin = $this->user(1);

        $this->fakeSpecify(true, 'Silkebjerg');
        $this->actingAs($admin)->get('/conflicts/' . $record->id)->assertSee('Silkebjerg');

        // Specify changed in the meantime: the page must show the new value.
        $this->fakeSpecify(true, 'Boserup');
        $second = $this->actingAs($admin)->get('/conflicts/' . $record->id);
        $second->assertDontSee('Silkebjerg');
        $this->assertSame(0, substr_count($second->getContent(), 'table-warning'));
    }

    public function test_the_projects_comparison_rules_apply_to_the_live_comparison(): void
    {
        $record = $this->conflictRecord(4, ['sync_compare_case_insensitive' => 1]);
        $this->fakeSpecify(true, 'BOSERUP');

        $response = $this->actingAs($this->user(1))->get('/conflicts/' . $record->id);

        $this->assertSame(0, substr_count($response->getContent(), 'table-warning'));
    }

    public function test_a_project_rooted_at_another_table_reads_that_table_not_the_collection_object(): void
    {
        $project = new Project();
        $project->name = 'Preparation project';
        $project->user_id = 1;
        $project->active = 1;
        $project->sync_root_table = 'preparation';
        $project->sync_root_field = 'text1';
        $project->save();

        $field = new Fields();
        $field->name = 'sampleNumber';
        $field->type = 'text';
        $field->position = 1;
        $field->save();
        Fields_mapping::create(['field_id' => $field->id, 'part' => '0', 'api_table_field' => 'Preparation.samplenumber', 'type' => 'write']);

        Specimen::create(['specimen_id' => 'P-17', 'project_id' => $project->id, 'images' => 'a.jpg']);

        $record = new Validate_fields();
        $record->value = 'S1';
        $record->position = 1;
        $record->specimen_id = 'P-17';
        $record->field_id = $field->id;
        $record->user_id = 1;
        $record->project_id = $project->id;
        $record->status = 4;
        $record->save();

        $this->app->instance(SpecifyClient::class, new class extends SpecifyClient {
            public array $asked = [];

            public function baseUrl(): string
            {
                return 'https://specify.test';
            }

            public function get(string $path): ?array
            {
                $this->asked[] = $path;

                // raw specimen id (not zero-padded like a catalog number), on the project's own table/field
                return $path === '/api/specify/preparation/?text1=P-17'
                    ? ['objects' => [['id' => 55, 'resource_uri' => '/api/specify/preparation/55/', 'samplenumber' => 'S2']]]
                    : null;
            }
        });

        $response = $this->actingAs($this->user(1))->get('/conflicts/' . $record->id);

        $response->assertOk()
            ->assertSee('S1')
            ->assertSee('S2')
            ->assertSee('https://specify.test/specify/view/preparation/55/', false);
        $this->assertSame(1, substr_count($response->getContent(), 'table-warning'));
    }

    public function test_the_page_still_opens_and_says_so_when_specify_cannot_be_reached(): void
    {
        $record = $this->conflictRecord();
        $this->fakeSpecify(false);

        $this->actingAs($this->user(1))->get('/conflicts/' . $record->id)
            ->assertOk()
            ->assertSee('Boserup')
            ->assertSee('Could not read the record from Specify');
    }

    public function test_only_admins_may_open_or_decide(): void
    {
        $record = $this->conflictRecord();
        $regular = $this->user(5);

        $this->actingAs($regular)->get('/conflicts/' . $record->id)->assertForbidden();
        $this->actingAs($regular)->post('/conflicts/' . $record->id . '/overwrite')->assertForbidden();
        $this->actingAs($regular)->post('/conflicts/' . $record->id . '/keep')->assertForbidden();

        $this->assertSame(4, (int) $record->fresh()->status);
    }

    public function test_overwrite_syncs_straight_away_and_reports_success(): void
    {
        $record = $this->conflictRecord();
        $this->fakeSync(2);

        $response = $this->actingAs($this->user(1))->post('/conflicts/' . $record->id . '/overwrite');

        $response->assertRedirect('/validated?status=4')->assertSessionHas('success');
        $this->assertSame(2, (int) $record->fresh()->status);
    }

    public function test_overwrite_reports_a_failed_sync_with_its_error_escaped(): void
    {
        $record = $this->conflictRecord();
        $this->fakeSync(3, '<b>boom</b>');

        $response = $this->actingAs($this->user(1))->post('/conflicts/' . $record->id . '/overwrite');

        $response->assertRedirect('/validated?status=3');
        $this->assertStringContainsString('&lt;b&gt;boom&lt;/b&gt;', session('error'));
        $this->assertStringNotContainsString('<b>', session('error'));
    }

    public function test_keep_resolves_in_favour_of_specify_without_syncing(): void
    {
        $record = $this->conflictRecord();

        $response = $this->actingAs($this->user(1))->post('/conflicts/' . $record->id . '/keep');

        $response->assertRedirect('/validated?status=4');
        $this->assertSame(5, (int) $record->fresh()->status);
    }

    public function test_a_record_that_is_not_in_conflict_cannot_be_decided(): void
    {
        $record = $this->conflictRecord(2);
        $admin = $this->user(1);

        $this->actingAs($admin)->post('/conflicts/' . $record->id . '/overwrite')->assertStatus(409);
        $this->actingAs($admin)->post('/conflicts/' . $record->id . '/keep')->assertStatus(409);
        $this->assertSame(2, (int) $record->fresh()->status);
    }

    public function test_the_validated_list_links_conflicts_to_the_resolution_page(): void
    {
        $record = $this->conflictRecord();

        $this->actingAs($this->user(1))->get('/validated?status=4')
            ->assertOk()
            ->assertSee('/conflicts/' . $record->id, false);
    }
}
