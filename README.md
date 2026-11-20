# laravel-bookable

Availability, safe reservations and seasonal pricing for **any Eloquent model**: hotel rooms, tours, rentals, tables.

> **Status: early development (v0.x).** The API is not stable yet. See the [specification](docs/SPEC.md) and the roadmap below.

## Why

Reservation code keeps getting rewritten, and the hard parts go wrong in production:

- **Overbooking**: two customers book the last unit at the same moment.
- **Wrong availability**: summing overlapping bookings over-counts; what matters is the peak usage on each night.
- **Abandoned checkouts** that block inventory forever.

This package solves those once, is domain-neutral, and is tested.

## Install

```bash
composer require ahshiri834/laravel-bookable
php artisan migrate
```

## Usage

```php
use Ahshiri\Bookable\Concerns\IsBookable;

class Room extends Model
{
    use IsBookable;          // reads units from the `capacity` column
}

$room->availableUnits('2026-05-01', '2026-05-05');   // int
$room->isAvailable('2026-05-01', '2026-05-05', 2);   // bool
```

```php
$booking = $room->hold('2026-05-01', '2026-05-05', quantity: 1, ttl: 15, owner: $user);
$booking->confirm();   // keeps the units, clears the expiry
$booking->cancel();    // frees the units
```

Holds that are never confirmed lapse on their own; schedule `bookable:release-expired` every minute to mark them `expired` and fire `BookingExpired`. Events: `BookingHeld`, `BookingConfirmed`, `BookingCancelled`, `BookingExpired`.

Concurrent `hold()` calls for the same model are serialized with a row lock (`lock = database`) or a cache lock (`lock = cache`, e.g. Redis), so the last unit can only be taken once. *A multi-process MySQL test proving this is planned for M4.*

Date ranges are half-open: check-in day inclusive, check-out day exclusive, so a stay ending on 10 May does not conflict with one starting on 10 May.

## Roadmap

- [x] **M1** Availability engine (peak usage per night), migrations, `IsBookable` trait
- [x] **M2** `hold` / `confirm` / `cancel`, per-bookable locking, events, expiry command (`php artisan bookable:release-expired`)
- [ ] **M3** Pricing engine (seasonal and weekday rates, price breakdown)
- [ ] **M4** Real concurrency test on MySQL in CI, static analysis
- [ ] **M5** v0.1.0 on Packagist

## Testing

```bash
composer install
vendor/bin/phpunit
```

## License

MIT
