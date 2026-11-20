<?php

namespace Ahshiri\Bookable\Tests\Unit;

use Ahshiri\Bookable\Exceptions\InvalidRangeException;
use Ahshiri\Bookable\Support\DateRange;
use PHPUnit\Framework\TestCase;

class DateRangeTest extends TestCase
{
    public function test_counts_nights(): void
    {
        $this->assertSame(3, (new DateRange('2026-05-01', '2026-05-04'))->nights());
    }

    public function test_back_to_back_ranges_do_not_overlap(): void
    {
        $a = new DateRange('2026-05-01', '2026-05-10');
        $b = new DateRange('2026-05-10', '2026-05-12');

        $this->assertFalse($a->overlaps($b));
        $this->assertFalse($b->overlaps($a));
    }

    public function test_overlapping_ranges_are_detected(): void
    {
        $a = new DateRange('2026-05-01', '2026-05-10');
        $b = new DateRange('2026-05-09', '2026-05-12');

        $this->assertTrue($a->overlaps($b));
    }

    public function test_rejects_empty_or_reversed_range(): void
    {
        $this->expectException(InvalidRangeException::class);
        new DateRange('2026-05-10', '2026-05-10');
    }
}
