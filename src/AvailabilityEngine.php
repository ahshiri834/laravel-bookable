<?php

namespace Ahshiri\Bookable;

use Ahshiri\Bookable\Models\Booking;
use Ahshiri\Bookable\Support\DateRange;
use Illuminate\Database\Eloquent\Model;

class AvailabilityEngine
{
    /**
     * Peak number of units in use on any single night of the range.
     *
     * Summing overlapping bookings would over-count: Mon-Tue and Wed-Thu both overlap a
     * Mon-Thu request but never use more than one unit at the same time.
     */
    public function peakUsage(Model $bookable, DateRange $range): int
    {
        $bookings = Booking::query()
            ->where('bookable_type', $bookable->getMorphClass())
            ->where('bookable_id', $bookable->getKey())
            ->consuming()
            ->overlapping($range->from, $range->to)
            ->get(['starts_at', 'ends_at', 'quantity']);

        $peak = 0;

        foreach ($range->eachNight() as $night) {
            $used = $bookings
                ->filter(fn (Booking $b) => $b->starts_at->lte($night) && $b->ends_at->gt($night))
                ->sum('quantity');

            $peak = max($peak, (int) $used);
        }

        return $peak;
    }

    public function availableUnits(Model $bookable, int $capacity, DateRange $range): int
    {
        return max(0, $capacity - $this->peakUsage($bookable, $range));
    }
}
