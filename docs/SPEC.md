# laravel-bookable: Specification

Status: draft v0.1 · Package: `ahshiri834/laravel-bookable` · Namespace: `Ahshiri\Bookable`

## 1. Problem

Reservation systems (hotels, tours, rentals, tables) all need the same three things, and most projects re-implement them badly:

1. **Availability**: how many units of a resource are free in a date range.
2. **Safe reservation**: two customers booking the last unit at the same moment must never both succeed.
3. **Pricing**: a price for a stay that can vary by season, weekday and length.

`laravel-bookable` provides these as a small, framework-native, well-tested package. It is **domain-neutral**: it knows nothing about hotels or any client; any Eloquent model can become bookable.

## 2. Goals / Non-goals

**Goals**
- Make any Eloquent model bookable with one trait.
- Correct under concurrency (no overbooking), verified by tests.
- Temporary holds that expire (checkout flow) and are released automatically.
- Seasonal and weekday pricing with a transparent price breakdown.
- Clean API, events, config, migrations, full test suite, CI.

**Non-goals (v1)**
- Payments, invoices, notifications, UI/admin panel.
- Time-of-day slots (v1 is date-range / nights; slot mode is a v2 candidate).
- Multi-currency conversion.

## 3. Concepts

| Term | Meaning |
|---|---|
| Bookable | Any model using the `IsBookable` trait. Has an integer `capacity` (units available, e.g. 5 identical rooms). |
| Booking | A claim on `quantity` units of a bookable over `[starts_at, ends_at)`. |
| Range semantics | Half-open: check-in day inclusive, check-out day exclusive. A booking ending 10 May does not conflict with one starting 10 May. |
| Hold | A booking with status `held` and an `expires_at`. Counts against availability until it expires or is confirmed/cancelled. |
| Rate | A price rule for a bookable: base price, plus optional date range, weekday and priority. |

### Booking status lifecycle

```
held --confirm--> confirmed --cancel--> cancelled
  |                                        ^
  +--------cancel / expire-----------------+
```

Statuses that **consume capacity**: `held` (not yet expired) and `confirmed`.

## 4. Public API (target)

```php
use Ahshiri\Bookable\Facades\Bookable;

// 1. Make a model bookable
class Room extends Model { use IsBookable; }   // capacity read from `capacity` column

// 2. Availability
$room->availableUnits($from, $to);             // int
$room->isAvailable($from, $to, quantity: 2);   // bool

// 3. Price quote
$quote = $room->quote($from, $to, quantity: 2);
$quote->total;        // integer, minor units (no floats)
$quote->nights;       // int
$quote->lines;        // per-night breakdown

// 4. Reserve (hold) and confirm
$booking = $room->hold($from, $to, quantity: 2, ttl: 15 /*minutes*/, owner: $user);
$booking->confirm();
$booking->cancel();

// 5. Housekeeping
php artisan bookable:release-expired            // schedule every minute
```

Exceptions: `NotAvailableException`, `InvalidRangeException`, `BookingStateException`.

Events: `BookingHeld`, `BookingConfirmed`, `BookingCancelled`, `BookingExpired`.

## 5. Data model

`bookings`
| column | type | notes |
|---|---|---|
| id | bigint | |
| bookable_type, bookable_id | morphs | indexed together with dates |
| owner_type, owner_id | nullable morphs | who booked |
| starts_at | date | inclusive |
| ends_at | date | exclusive, must be > starts_at |
| quantity | unsigned int | default 1 |
| status | string | held / confirmed / cancelled / expired |
| expires_at | timestamp null | only for `held` |
| total | unsigned bigint null | quoted minor units |
| meta | json null | free-form |
| timestamps | | |

`booking_rates`
| column | type | notes |
|---|---|---|
| id | bigint | |
| bookable_type, bookable_id | morphs | |
| price | unsigned bigint | minor units per unit per night |
| starts_on, ends_on | date null | null = always |
| weekdays | json null | e.g. [4,5] for Thu/Fri |
| priority | int | higher wins when rules overlap |

## 6. Concurrency strategy (the core of the package)

Overbooking happens when two requests both read "1 unit free" and both insert. v1 prevents this by **serializing writers per bookable**:

1. Open a DB transaction.
2. `SELECT ... FOR UPDATE` the bookable row (pessimistic row lock). Concurrent `hold()` calls for the same bookable queue here.
3. Recount consumed units for the range (overlap query below).
4. If `consumed + quantity > capacity` → throw `NotAvailableException`.
5. Insert the booking; commit (lock released).

Optional driver: `config('bookable.lock')` = `database` (default) or `redis` (atomic lock key per bookable), for setups where row locks are undesirable.

**Overlap query** (half-open ranges): a booking overlaps `[from, to)` when `starts_at < to AND ends_at > from`.
Consumed units = `SUM(quantity)` of overlapping bookings that are `confirmed`, or `held` with `expires_at > now()`. For per-night correctness the engine evaluates the **peak concurrent usage across each night** in the range, not just the sum of overlaps (a 1-room booking Mon-Tue and another Wed-Thu both overlap a Mon-Thu request but never use more than 1 unit at a time).

## 7. Pricing

For each night in `[from, to)`:
1. Collect rates that match the night (date range and weekday).
2. Pick the highest `priority`; ties broken by narrowest date range, then latest id.
3. Night price = rate price × quantity.
4. Total = sum of nights. All math in integer minor units.

If no rate matches, use `config('bookable.default_price')` or throw `NoRateException` (configurable).

## 8. Testing plan

- Unit: range overlap, per-night peak calculation, rate selection, weekday rules.
- Feature (Orchestra Testbench + SQLite/MySQL): hold/confirm/cancel/expire, back-to-back bookings, quantity > 1.
- **Concurrency test**: N parallel processes race for the last unit; exactly one must win (MySQL job in CI).
- Static analysis (PHPStan level 8) and code style (Pint) in CI.

## 9. Milestones

| # | Deliverable |
|---|---|
| M1 | Skeleton, migrations, trait, range + availability engine, tests |
| M2 | `hold` / `confirm` / `cancel` with locking, events, expiry command |
| M3 | Pricing engine and quotes |
| M4 | Concurrency test in CI, PHPStan, README with diagrams |
| M5 | Tag v0.1.0, publish to Packagist |

## 10. Security & privacy rules

- No client code, names, data, or credentials in this repository.
- Public repo from day one; secrets never committed (`.gitignore`, CI secret scanning).
