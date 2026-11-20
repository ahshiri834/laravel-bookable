<?php

namespace Ahshiri\Bookable;

use Ahshiri\Bookable\Enums\BookingStatus;
use Ahshiri\Bookable\Events\BookingCancelled;
use Ahshiri\Bookable\Events\BookingConfirmed;
use Ahshiri\Bookable\Events\BookingExpired;
use Ahshiri\Bookable\Events\BookingHeld;
use Ahshiri\Bookable\Exceptions\BookingStateException;
use Ahshiri\Bookable\Exceptions\NotAvailableException;
use Ahshiri\Bookable\Models\Booking;
use Ahshiri\Bookable\Support\DateRange;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BookingManager
{
    public function __construct(private readonly AvailabilityEngine $engine) {}

    /**
     * Place a temporary hold on `quantity` units. Writers are serialized per bookable so two
     * concurrent requests can never both take the last unit.
     */
    public function hold(
        Model $bookable,
        CarbonInterface|string $from,
        CarbonInterface|string $to,
        int $quantity = 1,
        ?int $ttlMinutes = null,
        ?Model $owner = null,
        ?array $meta = null,
    ): Booking {
        if ($quantity < 1) {
            throw new NotAvailableException('Quantity must be at least 1.');
        }

        $range = new DateRange($from, $to);
        $ttl = $ttlMinutes ?? (int) config('bookable.hold_ttl', 15);

        $booking = $this->serialized($bookable, function () use ($bookable, $range, $quantity, $ttl, $owner, $meta) {
            // Re-read capacity from the locked row, not from a possibly stale in-memory model.
            $capacity = (int) $bookable->newQuery()->whereKey($bookable->getKey())
                ->value(config('bookable.capacity_column', 'capacity'));

            if ($this->engine->availableUnits($bookable, $capacity, $range) < $quantity) {
                throw new NotAvailableException('Not enough units available for the requested dates.');
            }

            return Booking::create([
                'bookable_type' => $bookable->getMorphClass(),
                'bookable_id' => $bookable->getKey(),
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner?->getKey(),
                'starts_at' => $range->from,
                'ends_at' => $range->to,
                'quantity' => $quantity,
                'status' => BookingStatus::Held,
                'expires_at' => now()->addMinutes($ttl),
                'meta' => $meta,
            ]);
        });

        event(new BookingHeld($booking));

        return $booking;
    }

    public function confirm(Booking $booking): Booking
    {
        $booking->refresh();

        if ($booking->status === BookingStatus::Confirmed) {
            return $booking;
        }

        if ($booking->status !== BookingStatus::Held) {
            throw new BookingStateException("A {$booking->status->value} booking cannot be confirmed.");
        }

        if ($booking->expires_at === null || $booking->expires_at->lte(now())) {
            throw new BookingStateException('The hold has expired; place a new hold.');
        }

        $booking->update(['status' => BookingStatus::Confirmed, 'expires_at' => null]);

        event(new BookingConfirmed($booking));

        return $booking;
    }

    public function cancel(Booking $booking): Booking
    {
        $booking->refresh();

        if ($booking->status === BookingStatus::Cancelled) {
            return $booking;
        }

        if ($booking->status === BookingStatus::Expired) {
            throw new BookingStateException('An expired booking cannot be cancelled.');
        }

        $booking->update(['status' => BookingStatus::Cancelled, 'expires_at' => null]);

        event(new BookingCancelled($booking));

        return $booking;
    }

    /** Mark lapsed holds as expired. Returns how many were released. */
    public function releaseExpired(): int
    {
        $count = 0;

        Booking::query()
            ->where('status', BookingStatus::Held->value)
            ->where('expires_at', '<=', now())
            ->each(function (Booking $booking) use (&$count) {
                // Conditional update: skip rows confirmed/cancelled since the query ran.
                $changed = Booking::query()
                    ->whereKey($booking->getKey())
                    ->where('status', BookingStatus::Held->value)
                    ->update(['status' => BookingStatus::Expired->value]);

                if ($changed) {
                    $count++;
                    event(new BookingExpired($booking->refresh()));
                }
            });

        return $count;
    }

    /** Run `$callback` while holding an exclusive lock for this bookable. */
    private function serialized(Model $bookable, callable $callback): mixed
    {
        if (config('bookable.lock', 'database') === 'cache') {
            $key = 'bookable:'.$bookable->getMorphClass().':'.$bookable->getKey();

            return Cache::lock($key, 10)->block(5, fn () => DB::transaction($callback));
        }

        return DB::transaction(function () use ($bookable, $callback) {
            // Pessimistic row lock: concurrent holds on the same bookable queue here.
            $bookable->newQuery()->whereKey($bookable->getKey())->lockForUpdate()->first();

            return $callback();
        });
    }
}
