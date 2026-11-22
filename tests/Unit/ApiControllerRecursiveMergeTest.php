<?php

namespace Tests\Unit;

use App\Http\Controllers\ApiController;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for ApiController::recursiveMerge()'s handling of
 * dependent to-many relations (collectors, preparations, ...): merging a
 * line for an ordernumber that already exists in Specify's data must
 * overwrite that entry in place, not create a duplicate sibling.
 *
 * This is what broke when editing "Collector 2" on a specimen that already
 * had collectors at ordernumber 1 and 2 — the existing ordernumber=2 entry
 * sat at array index 1 (Specify's lists are 0-indexed), but the merge used
 * $position (1-indexed, =2) directly as the array index, landing one slot
 * past it and creating a brand new collector instead of replacing the old one.
 */
class ApiControllerRecursiveMergeTest extends TestCase
{
    public function test_merging_an_existing_ordernumber_overwrites_that_entry_in_place(): void
    {
        $controller = new ApiController();

        $exist = [
            'collectingevent' => [
                'collectors' => [
                    ['id' => 1, 'resource_uri' => '/api/specify/collector/1/', 'agent' => '/api/specify/agent/100/', 'ordernumber' => 1],
                    ['id' => 2, 'resource_uri' => '/api/specify/collector/2/', 'agent' => '/api/specify/agent/200/', 'ordernumber' => 2],
                ],
            ],
        ];

        $delta = [
            'collectingevent' => [
                'collectors' => [
                    ['agent' => '/api/specify/agent/300/', 'ordernumber' => 2],
                ],
            ],
        ];

        $controller->recursiveMerge($exist, $delta, 2);

        $collectors = $exist['collectingevent']['collectors'];

        $this->assertCount(2, $collectors, 'editing an existing line must not add a new collector');

        $this->assertSame(1, $collectors[0]['ordernumber']);
        $this->assertSame('/api/specify/agent/100/', $collectors[0]['agent']);
        $this->assertSame(1, $collectors[0]['id'], 'the untouched line must keep its original Specify id');

        $this->assertSame(2, $collectors[1]['ordernumber']);
        $this->assertSame('/api/specify/agent/300/', $collectors[1]['agent'], 'the new agent must replace the old one');
        $this->assertSame(2, $collectors[1]['id'], 'the edited line must update the EXISTING Specify row, not create a new one');
    }

    public function test_merging_a_new_ordernumber_appends_without_disturbing_existing_entries(): void
    {
        $controller = new ApiController();

        $exist = [
            'collectingevent' => [
                'collectors' => [
                    ['id' => 1, 'resource_uri' => '/api/specify/collector/1/', 'agent' => '/api/specify/agent/100/', 'ordernumber' => 1],
                ],
            ],
        ];

        $delta = [
            'collectingevent' => [
                'collectors' => [
                    ['agent' => '/api/specify/agent/200/', 'ordernumber' => 2],
                ],
            ],
        ];

        $controller->recursiveMerge($exist, $delta, 2);

        $collectors = $exist['collectingevent']['collectors'];

        $this->assertCount(2, $collectors);
        $this->assertSame(1, $collectors[0]['ordernumber']);
        $this->assertSame(1, $collectors[0]['id']);
        $this->assertSame(2, $collectors[1]['ordernumber']);
        $this->assertSame('/api/specify/agent/200/', $collectors[1]['agent']);
        $this->assertArrayNotHasKey('id', $collectors[1], 'a genuinely new line has no existing Specify id to preserve');
    }
}
