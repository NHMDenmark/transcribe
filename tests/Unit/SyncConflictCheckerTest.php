<?php

namespace Tests\Unit;

use App\Services\SyncConflictChecker;
use PHPUnit\Framework\TestCase;

class SyncConflictCheckerTest extends TestCase
{
    private function paths(array $entries): array
    {
        return array_column($entries, 'path');
    }

    public function test_empty_and_equal_parts_are_written_and_a_different_one_is_a_conflict(): void
    {
        // The locality name matches, the latitude is missing, the longitude differs.
        $delta = ['collectingevent' => ['locality' => [
            'localityname' => 'Boserup',
            'latitude1' => '55.7',
            'longitude1' => '12.1',
        ]]];
        $existing = ['collectingevent' => ['locality' => [
            'localityname' => 'Boserup',
            'latitude1' => null,
            'longitude1' => '12.9',
        ]]];

        $r = (new SyncConflictChecker())->check($delta, $existing, 1);

        $this->assertSame('55.7', $r['delta']['collectingevent']['locality']['latitude1']);
        $this->assertSame('Boserup', $r['delta']['collectingevent']['locality']['localityname']);
        $this->assertArrayNotHasKey('longitude1', $r['delta']['collectingevent']['locality']);
        $this->assertSame(['collectingevent.locality.longitude1'], $this->paths($r['conflicts']));
        $this->assertSame('12.9', $r['conflicts'][0]['existing']);
        $this->assertSame('12.1', $r['conflicts'][0]['proposed']);
    }

    public function test_when_everything_conflicts_nothing_is_left_to_write(): void
    {
        $r = (new SyncConflictChecker())->check(
            ['remarks' => 'new'],
            ['remarks' => 'old'],
            1
        );

        $this->assertSame([], $r['delta']);
        $this->assertCount(1, $r['conflicts']);
    }

    public function test_a_date_and_its_precision_companion_stand_or_fall_together(): void
    {
        $delta = ['startdate' => '2009-07-27', 'startdateprecision' => 1, 'enddate' => '2009-07-31', 'enddateprecision' => 1];
        $existing = ['startdate' => null, 'enddate' => '2009-08-01'];

        $r = (new SyncConflictChecker())->check($delta, $existing, 1);

        $this->assertSame(['startdate' => '2009-07-27', 'startdateprecision' => 1], $r['delta']);
        $this->assertSame(['enddate'], $this->paths($r['conflicts']));
    }

    public function test_a_pick_linking_to_a_different_record_is_a_conflict_but_the_same_link_is_not(): void
    {
        $delta = ['collectingevent' => '/api/specify/collectingevent/9/', 'accession' => '/api/specify/accession/3/'];
        $existing = ['collectingevent' => '/api/specify/collectingevent/5/', 'accession' => ['resource_uri' => '/api/specify/accession/3/']];

        $r = (new SyncConflictChecker())->check($delta, $existing, 1);

        $this->assertSame(['collectingevent'], $this->paths($r['conflicts']));
        $this->assertSame('pick', $r['conflicts'][0]['kind']);
        $this->assertArrayHasKey('accession', $r['delta']);
        $this->assertArrayNotHasKey('collectingevent', $r['delta']);
    }

    public function test_a_dependent_line_is_matched_by_ordernumber_and_a_different_agent_is_a_conflict(): void
    {
        $delta = ['collectingevent' => ['collectors' => [['agent' => '/api/specify/agent/20/', 'ordernumber' => 2]]]];
        $existing = ['collectingevent' => ['collectors' => [
            ['agent' => '/api/specify/agent/10/', 'ordernumber' => 1],
            ['agent' => '/api/specify/agent/11/', 'ordernumber' => 2],
        ]]];

        $r = (new SyncConflictChecker())->check($delta, $existing, 2);

        $this->assertSame([], $r['delta']);
        $this->assertSame(['collectingevent.collectors[2].agent'], $this->paths($r['conflicts']));
    }

    public function test_a_line_with_no_existing_entry_at_that_ordernumber_is_new(): void
    {
        $delta = ['collectingevent' => ['collectors' => [['agent' => '/api/specify/agent/20/', 'ordernumber' => 3]]]];
        $existing = ['collectingevent' => ['collectors' => [['agent' => '/api/specify/agent/10/', 'ordernumber' => 1]]]];

        $r = (new SyncConflictChecker())->check($delta, $existing, 3);

        $this->assertSame($delta, $r['delta']);
        $this->assertSame([], $r['conflicts']);
    }

    public function test_a_related_record_that_came_back_as_a_uri_is_fetched_to_compare(): void
    {
        $fetch = fn(string $uri) => $uri === '/api/specify/locality/7/' ? ['localityname' => 'Boserup', 'latitude1' => '55.7'] : null;

        $r = (new SyncConflictChecker(false, null, $fetch))->check(
            ['locality' => ['localityname' => 'Boserup', 'latitude1' => '56.0']],
            ['locality' => '/api/specify/locality/7/'],
            1
        );

        $this->assertSame(['locality.latitude1'], $this->paths($r['conflicts']));
        $this->assertSame(['localityname' => 'Boserup'], $r['delta']['locality']);
    }

    public function test_comparison_rules(): void
    {
        $strict = new SyncConflictChecker();
        $loose = new SyncConflictChecker(true, 2);

        // Plain text: exact by default, case-insensitive when the project says so.
        $this->assertCount(1, $strict->check(['a' => 'Boserup'], ['a' => 'boserup'], 1)['conflicts']);
        $this->assertCount(0, $loose->check(['a' => 'Boserup'], ['a' => 'boserup'], 1)['conflicts']);

        // Decimals compare by value; integers stay textual.
        $this->assertCount(0, $strict->check(['lat' => '55.1234'], ['lat' => '55.1234000000'], 1)['conflicts']);
        $this->assertCount(1, $strict->check(['n' => '7'], ['n' => '007'], 1)['conflicts']);

        // Rounding to N decimals, when configured.
        $this->assertCount(1, $strict->check(['lat' => '55.1234'], ['lat' => '55.1239'], 1)['conflicts']);
        $this->assertCount(0, $loose->check(['lat' => '55.1234'], ['lat' => '55.1239'], 1)['conflicts']);

        // Surrounding whitespace is ignored.
        $this->assertCount(0, $strict->check(['a' => 'x'], ['a' => ' x '], 1)['conflicts']);
    }
}
