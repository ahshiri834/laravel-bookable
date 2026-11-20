<?php

namespace Ahshiri\Bookable\Models;

use Ahshiri\Bookable\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property BookingStatus $status
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

    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /** Bookings that currently consume capacity: confirmed, or held and not yet expired. */
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

    /** Bookings overlapping the half-open range [from, to). */
    public function scopeOverlapping(Builder $query, $from, $to): Builder
    {
        return $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }
}
