<?php

namespace Tests\Unit;

use App\Http\Controllers\ApiController;
use App\Services\ListLine;
use PHPUnit\Framework\TestCase;

/** Lines of a list in tables without an ordernumber (determinations, preparations, other identifiers) go by position. */
class ListLineTest extends TestCase
{
    public function test_a_table_without_ordernumber_is_matched_by_the_position_of_the_line(): void
    {
        $list = [['id' => 10, 'remarks' => 'a'], ['id' => 11, 'remarks' => 'b']];

        $this->assertSame(0, ListLine::find($list, ['remarks' => 'x'], 1));
        $this->assertSame(1, ListLine::find($list, ['remarks' => 'x'], 2));
        $this->assertNull(ListLine::find($list, ['remarks' => 'x'], 3), 'a line past the end is a new entry');
    }

    public function test_a_table_with_ordernumber_is_still_matched_by_it(): void
    {
        $list = [['id' => 1, 'ordernumber' => 2], ['id' => 2, 'ordernumber' => 1]];

        $this->assertSame(1, ListLine::find($list, ['agent' => 'x', 'ordernumber' => 1], 1));
    }

    public function test_the_merge_updates_the_existing_entry_and_adds_the_next_without_an_ordernumber(): void
    {
        $exist = ['determinations' => [['id' => 10, 'remarks' => null]]];
        (new ApiController())->recursiveMerge($exist, ['determinations' => [['remarks' => 'one']]], 1);
        (new ApiController())->recursiveMerge($exist, ['determinations' => [['remarks' => 'two']]], 2);

        $this->assertSame([['id' => 10, 'remarks' => 'one'], ['remarks' => 'two']], $exist['determinations']);
    }
}
