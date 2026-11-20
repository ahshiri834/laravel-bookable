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

Date ranges are half-open: check-in day inclusive, check-out day exclusive, so a stay ending on 10 May does not conflict with one starting on 10 May.

## Roadmap

- [x] **M1** Availability engine (peak usage per night), migrations, `IsBookable` trait
- [ ] **M2** `hold` / `confirm` / `cancel`, per-bookable locking, events, expiry command
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
