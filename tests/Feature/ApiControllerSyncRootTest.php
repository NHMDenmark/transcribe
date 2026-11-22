<?php

namespace Tests\Feature;

use App\Http\Controllers\ApiController;
use App\Models\Fields;
use App\Models\Fields_mapping;
use App\Models\Project;
use App\Models\Validate_fields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * Regression coverage for ApiController::resolveSyncRoot() — the piece that
 * replaced 4 places hardcoding "sync always means collectionobject/
 * catalognumber" with a per-project setting (sync_root_table/sync_root_field),
 * so a project can instead be rooted at, say, preparation/text1.
 *
 * Tested via reflection rather than through ingest_validated_nested() itself,
 * since that method makes real HTTP calls to Specify — appropriate for the
 * live browser verification this feature got, not for an automated,
 * network-free test run.
 */
class ApiControllerSyncRootTest extends TestCase
{
    use RefreshDatabase;

    private function callResolveSyncRoot(Validate_fields $record): ?array
    {
        $controller = new ApiController();
        $method = (new ReflectionClass($controller))->getMethod('resolveSyncRoot');
        $method->setAccessible(true);

        return $method->invoke($controller, $record);
    }

    public function test_defaults_to_collectionobject_catalognumber_when_project_does_not_override(): void
    {
        $project = new Project();
        $project->name = 'Default Project';
        $project->user_id = 1;
        $project->save();

        $record = new Validate_fields();
        $record->specimen_id = '212530';
        $record->field_id = 1;
        $record->position = 1;
        $record->value = 'x';
        $record->status = 1;
        $record->project_id = $project->id;
        $record->save();

        [$table, $field, $value] = $this->callResolveSyncRoot($record);

        $this->assertSame('collectionobject', $table);
        $this->assertSame('catalognumber', $field);
        $this->assertSame('212530', $value, 'as the platform holds it: the zeros are the lookup\'s business');
    }

    public function test_uses_the_projects_configured_root_when_overridden(): void
    {
        $project = new Project();
        $project->name = 'Preparation-rooted Project';
        $project->user_id = 1;
        $project->sync_root_table = 'preparation';
        $project->sync_root_field = 'text1';
        $project->save();

        $record = new Validate_fields();
        $record->specimen_id = 'PREP-42';
        $record->field_id = 1;
        $record->position = 1;
        $record->value = 'x';
        $record->status = 1;
        $record->project_id = $project->id;
        $record->save();

        [$table, $field, $value] = $this->callResolveSyncRoot($record);

        $this->assertSame('preparation', $table);
        $this->assertSame('text1', $field);
        // non-catalognumber fields are matched on the raw value, unpadded
        $this->assertSame('PREP-42', $value);
    }

    public function test_returns_null_when_the_record_has_no_resolvable_project(): void
    {
        $record = new Validate_fields();
        $record->specimen_id = '1';
        $record->field_id = 1;
        $record->position = 1;
        $record->value = 'x';
        $record->status = 1;
        $record->project_id = null;
        $record->save();

        $this->assertNull($this->callResolveSyncRoot($record));
    }

    private function callCheckMappingRootMatches(Validate_fields $record, string $rootTable): ?string
    {
        $controller = new ApiController();
        $method = (new ReflectionClass($controller))->getMethod('checkMappingRootMatches');
        $method->setAccessible(true);

        return $method->invoke($controller, $record, $rootTable);
    }

    public function test_mapping_root_matches_project_root_passes(): void
    {
        $field = new Fields();
        $field->name = 'Preparation sample number';
        $field->type = 'text';
        $field->save();

        Fields_mapping::create([
            'field_id'        => $field->id,
            'part'            => 0,
            'api_table_field' => 'Preparation.samplenumber',
            'type'            => 'write',
            'parent'          => null,
        ]);

        $record = new Validate_fields();
        $record->specimen_id = '28601';
        $record->field_id = $field->id;
        $record->position = 1;
        $record->value = 'prep123';
        $record->status = 1;
        $record->save();

        $this->assertNull($this->callCheckMappingRootMatches($record, 'preparation'));
    }

    public function test_mapping_rooted_elsewhere_than_project_root_fails_loudly(): void
    {
        // Mirrors the real "Collectors" field: rooted at Collectionobject
        // (collectingevent.collectors.agent.lastname), used from a project
        // whose sync root is 'preparation' — a Preparation record has no
        // 'collectingevent' relation to write into at all.
        $field = new Fields();
        $field->name = 'Collectors';
        $field->type = 'text';
        $field->save();

        $root = Fields_mapping::create([
            'field_id'        => $field->id,
            'part'            => 0,
            'api_table_field' => 'Collectionobject.collectingevent',
            'type'            => 'node',
            'parent'          => null,
        ]);
        $events = Fields_mapping::create([
            'field_id'        => $field->id,
            'part'            => 0,
            'api_table_field' => 'Collectingevent.collectors',
            'type'            => 'node',
            'parent'          => $root->id,
        ]);
        $collector = Fields_mapping::create([
            'field_id'        => $field->id,
            'part'            => 0,
            'api_table_field' => 'Collector.agent',
            'type'            => 'node',
            'parent'          => $events->id,
        ]);
        Fields_mapping::create([
            'field_id'        => $field->id,
            'part'            => 0,
            'api_table_field' => 'Agent.lastname',
            'type'            => 'pick',
            'parent'          => $collector->id,
        ]);

        $record = new Validate_fields();
        $record->specimen_id = '28601';
        $record->field_id = $field->id;
        $record->position = 1;
        $record->value = 'Santos';
        $record->status = 1;
        $record->save();

        $error = $this->callCheckMappingRootMatches($record, 'preparation');

        $this->assertNotNull($error);
        $this->assertStringContainsString('Collectors', $error);
        $this->assertStringContainsString('collectionobject', $error);
        $this->assertStringContainsString('preparation', $error);
    }
}
