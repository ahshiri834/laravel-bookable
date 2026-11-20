# laravel-bookable

Availability, safe reservations and seasonal pricing for **any Eloquent model**: hotel rooms, tours, rentals, tables.

> **Status: v0.1.1, early release.** The API may still change before 1.0. See the [specification](docs/SPEC.md) and the roadmap below.

## Why

Reservation code keeps getting rewritten, and the hard parts go wrong in production:

- **Overbooking**: two customers book the last unit at the same moment.
- **Wrong availability**: summing overlapping bookings over-counts; what matters is the peak usage on each night.
- **Abandoned checkouts** that block inventory forever.

This package solves those once, is domain-neutral, and is tested.

Requires PHP 8.2+ and Laravel 12. (Laravel 11 is not supported: every 11.x release is currently blocked by Composer's security-advisory check.)

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

### Pricing

```php
Rate::create(['bookable_type' => $room->getMorphClass(), 'bookable_id' => $room->id, 'price' => 1000]);                 // base
Rate::create([..., 'price' => 1500, 'starts_on' => '2026-07-01', 'ends_on' => '2026-08-31', 'priority' => 10]);         // season
Rate::create([..., 'price' => 1300, 'weekdays' => [4, 5], 'priority' => 5]);                                            // Thu/Fri

$quote = $room->quote('2026-07-10', '2026-07-13', quantity: 2);
$quote->total;   // integer minor units, never floats
$quote->lines;   // per-night breakdown: night, unitPrice, amount, rateId
```

Per night the highest `priority` rule wins; ties go to the narrowest date window, then the newest rule. `hold()` stores the quoted total on the booking. With no matching rate, `quote()` throws `NoRateException` unless `bookable.default_price` is set.

Date ranges are half-open: check-in day inclusive, check-out day exclusive, so a stay ending on 10 May does not conflict with one starting on 10 May.

## Roadmap

- [x] **M1** Availability engine (peak usage per night), migrations, `IsBookable` trait
- [x] **M2** `hold` / `confirm` / `cancel`, per-bookable locking, events, expiry command (`php artisan bookable:release-expired`)
- [x] **M3** Pricing engine (seasonal and weekday rates, price breakdown, price snapshot on hold)
- [x] **M4** Real multi-process concurrency test on MySQL, run in CI (GitHub Actions)
- [x] Static analysis (PHPStan level 6 via Larastan) and code style (Pint) enforced in CI
- [x] Listed on Packagist: `composer require ahshiri834/laravel-bookable`
- [ ] **M5** v0.1.0 on Packagist

## Testing

```bash
composer install
vendor/bin/phpunit
```

## License

MIT
