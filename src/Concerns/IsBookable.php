<?php

namespace Ahshiri\Bookable\Concerns;

use Ahshiri\Bookable\AvailabilityEngine;
use Ahshiri\Bookable\Models\Booking;
use Ahshiri\Bookable\Support\DateRange;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Makes an Eloquent model bookable. Capacity is read from the configured column.
 */
trait IsBookable
{
    public function bookings(): MorphMany
    {
        return $this->morphMany(Booking::class, 'bookable');
    }

    public function bookableCapacity(): int
    {
        return (int) $this->getAttribute(config('bookable.capacity_column', 'capacity'));
    }

    public function availableUnits(CarbonInterface|string $from, CarbonInterface|string $to): int
    {
        return app(AvailabilityEngine::class)
            ->availableUnits($this, $this->bookableCapacity(), new DateRange($from, $to));
    }

    public function isAvailable(CarbonInterface|string $from, CarbonInterface|string $to, int $quantity = 1): bool
    {
        return $this->availableUnits($from, $to) >= $quantity;
    }
}
