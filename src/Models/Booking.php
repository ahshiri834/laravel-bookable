<?php

namespace Ahshiri\Bookable\Models;

use Ahshiri\Bookable\BookingManager;
use Ahshiri\Bookable\Enums\BookingStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $bookable_type
 * @property int $bookable_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property int $quantity
 * @property BookingStatus $status
 * @property CarbonImmutable|null $expires_at
 * @property int|null $total
 * @property array<string, mixed>|null $meta
 */
class Booking extends Model
{
    protected $table = 'bookings';

    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'immutable_date',
        'ends_at' => 'immutable_date',
        'expires_at' => 'immutable_datetime',
        'status' => BookingStatus::class,
        'meta' => 'array',
    ];

    /** @return MorphTo<Model, $this> */
    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function confirm(): self
    {
        return app(BookingManager::class)->confirm($this);
    }

    public function cancel(): self
    {
        return app(BookingManager::class)->cancel($this);
    }

    /**
     * Bookings that currently consume capacity: confirmed, or held and not yet expired.
     *
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function scopeConsuming(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', BookingStatus::Confirmed->value)
                ->orWhere(function (Builder $q) {
                    $q->where('status', BookingStatus::Held->value)
                        ->where('expires_at', '>', now());
                });
        });
    }

    /**
     * Bookings overlapping the half-open range [from, to).
     *
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function scopeOverlapping(Builder $query, CarbonInterface|string $from, CarbonInterface|string $to): Builder
    {
        return $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }
}
