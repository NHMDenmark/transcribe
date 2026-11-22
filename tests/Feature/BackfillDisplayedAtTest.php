<?php

namespace Tests\Feature;

use App\Models\Collaborative_fields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillDisplayedAtTest extends TestCase
{
    use RefreshDatabase;

    private function row(string $user, string $specimen, string $createdAt, ?string $displayedAt = null): Collaborative_fields
    {
        $r = new Collaborative_fields();
        $r->value = 'x';
        $r->position = 1;
        $r->specimen_id = $specimen;
        $r->field_id = 1;
        $r->user_id = $user;
        $r->points = 0;
        $r->created_at = $createdAt;
        $r->displayed_at = $displayedAt;
        $r->save();

        return $r->fresh();
    }

    public function test_displayed_at_is_estimated_from_the_users_previous_submission(): void
    {
        // Submission A (two fields saved a second apart), then B 90s later, then C after a 2h break.
        $a1 = $this->row('1', '100', '2026-01-01 10:00:00');
        $a2 = $this->row('1', '100', '2026-01-01 10:00:01');
        $b = $this->row('1', '101', '2026-01-01 10:01:31');
        $c = $this->row('1', '102', '2026-01-01 12:01:31');
        // Another user's timeline must not leak into user 1's.
        $other = $this->row('2', '103', '2026-01-01 10:01:00');

        $this->artisan('app:backfill-displayed-at')->assertSuccessful();

        $this->assertNull($a1->fresh()->displayed_at);
        $this->assertNull($a2->fresh()->displayed_at);
        $this->assertSame('2026-01-01 10:00:01', $b->fresh()->displayed_at);
        $this->assertNull($c->fresh()->displayed_at);
        $this->assertNull($other->fresh()->displayed_at);
    }

    public function test_measured_values_are_never_overwritten_and_dry_run_writes_nothing(): void
    {
        $this->row('1', '100', '2026-01-01 10:00:00');
        $measured = $this->row('1', '101', '2026-01-01 10:01:00', '2026-01-01 10:00:20');
        $pending = $this->row('1', '102', '2026-01-01 10:03:00');

        $this->artisan('app:backfill-displayed-at --dry-run')->assertSuccessful();
        $this->assertNull($pending->fresh()->displayed_at);

        $this->artisan('app:backfill-displayed-at')->assertSuccessful();
        $this->assertSame('2026-01-01 10:00:20', $measured->fresh()->displayed_at);
        $this->assertSame('2026-01-01 10:01:00', $pending->fresh()->displayed_at);
    }
}
