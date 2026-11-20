<?php

namespace Ahshiri\Bookable\Support;

use Ahshiri\Bookable\Exceptions\InvalidRangeException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Generator;

/**
 * Half-open date range [from, to): check-in day inclusive, check-out day exclusive.
 */
final class DateRange
{
    public readonly CarbonImmutable $from;

    public readonly CarbonImmutable $to;

    public function __construct(CarbonInterface|string $from, CarbonInterface|string $to)
    {
        $this->from = CarbonImmutable::parse($from)->startOfDay();
        $this->to = CarbonImmutable::parse($to)->startOfDay();

        if ($this->to->lessThanOrEqualTo($this->from)) {
            throw new InvalidRangeException('The end date must be after the start date.');
        }
    }

    public function nights(): int
    {
        return (int) $this->from->diffInDays($this->to);
    }

    /** @return Generator<int, CarbonImmutable> every night in the range */
    public function eachNight(): Generator
    {
        for ($day = $this->from; $day->lt($this->to); $day = $day->addDay()) {
            yield $day;
        }
    }

    public function overlaps(self $other): bool
    {
        return $this->from->lt($other->to) && $this->to->gt($other->from);
    }
}
