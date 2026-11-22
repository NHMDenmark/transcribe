<?php

namespace Tests\Feature;

use App\Http\Controllers\ApiController;
use App\Models\Project;
use App\Models\Validate_fields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * The conflict check must be strictly opt-in: a project on the default
 * 'overwrite' (and any project created before the setting existed) has to
 * sync exactly as it always did.
 */
class ApiControllerOverwritePolicyTest extends TestCase
{
    use RefreshDatabase;

    private function gate(Validate_fields $record, ?Project $project): bool
    {
        $controller = new ApiController();
        $method = (new ReflectionClass($controller))->getMethod('shouldCheckForConflicts');
        $method->setAccessible(true);

        return $method->invoke($controller, $record, $project);
    }

    private function project(array $attrs = []): Project
    {
        $project = new Project();
        $project->name = 'P';
        $project->user_id = 1;
        $project->active = 1;
        foreach ($attrs as $k => $v) {
            $project->$k = $v;
        }
        $project->save();

        return $project->fresh();
    }

    private function record(int $status): Validate_fields
    {
        $record = new Validate_fields();
        $record->status = $status;

        return $record;
    }

    public function test_a_project_with_the_default_setting_never_checks(): void
    {
        $project = $this->project();

        $this->assertSame('overwrite', $project->sync_overwrite);
        $this->assertFalse($this->gate($this->record(1), $project));
        $this->assertFalse($this->gate($this->record(4), $project));
    }

    public function test_no_project_means_no_check(): void
    {
        $this->assertFalse($this->gate($this->record(1), null));
    }

    public function test_keep_existing_checks_pending_records_but_not_approved_overwrites(): void
    {
        $project = $this->project(['sync_overwrite' => 'keep_existing']);

        $this->assertTrue($this->gate($this->record(1), $project));
        $this->assertFalse($this->gate($this->record(6), $project));
    }
}
