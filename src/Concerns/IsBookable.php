<?php

namespace Ahshiri\Bookable\Concerns;

use Ahshiri\Bookable\AvailabilityEngine;
use Ahshiri\Bookable\BookingManager;
use Ahshiri\Bookable\Models\Booking;
use Ahshiri\Bookable\Models\Rate;
use Ahshiri\Bookable\Pricing\PriceCalculator;
use Ahshiri\Bookable\Pricing\Quote;
use Ahshiri\Bookable\Support\DateRange;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Makes an Eloquent model bookable. Capacity is read from the configured column.
 */
trait IsBookable
{
    /** @return MorphMany<Booking, $this> */
    public function bookings(): MorphMany
    {
        return $this->morphMany(Booking::class, 'bookable');
    }

    /** @return MorphMany<Rate, $this> */
    public function rates(): MorphMany
    {
        return $this->morphMany(Rate::class, 'bookable');
    }

    public function quote(CarbonInterface|string $from, CarbonInterface|string $to, int $quantity = 1): Quote
    {
        return app(PriceCalculator::class)->quote($this, new DateRange($from, $to), $quantity);
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

    /** @param  array<string, mixed>|null  $meta */
    public function hold(
        CarbonInterface|string $from,
        CarbonInterface|string $to,
        int $quantity = 1,
        ?int $ttl = null,
        ?Model $owner = null,
        ?array $meta = null,
    ): Booking {
        return app(BookingManager::class)->hold($this, $from, $to, $quantity, $ttl, $owner, $meta);
    }

    public function isAvailable(CarbonInterface|string $from, CarbonInterface|string $to, int $quantity = 1): bool
    {
        return $this->availableUnits($from, $to) >= $quantity;
    }
}
