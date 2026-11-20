# Changelog

## v0.1.0 - 2026-11-20

First public release.

- Availability engine based on peak usage per night (not the sum of overlapping bookings)
- `IsBookable` trait for any Eloquent model
- `hold()` / `confirm()` / `cancel()` with per-bookable locking (`database` row lock or `cache` lock)
- Expiring holds and `bookable:release-expired` command
- Events: `BookingHeld`, `BookingConfirmed`, `BookingCancelled`, `BookingExpired`
- Pricing: seasonal and weekday rates, priorities, per-night quote breakdown, price snapshot on hold
- Tests on SQLite plus a multi-process concurrency test on MySQL, run in GitHub Actions
- Requires PHP 8.2+ and Laravel 12
