<?php

namespace Ahshiri\Bookable\Tests\Feature;

use Ahshiri\Bookable\Enums\BookingStatus;
use Ahshiri\Bookable\Events\BookingCancelled;
use Ahshiri\Bookable\Events\BookingConfirmed;
use Ahshiri\Bookable\Events\BookingExpired;
use Ahshiri\Bookable\Events\BookingHeld;
use Ahshiri\Bookable\Exceptions\BookingStateException;
use Ahshiri\Bookable\Exceptions\NotAvailableException;
use Ahshiri\Bookable\Models\Booking;
use Ahshiri\Bookable\Tests\Room;
use Ahshiri\Bookable\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class HoldTest extends TestCase
{
    public function test_hold_creates_a_held_booking_and_consumes_capacity(): void
    {
        Event::fake();
        $room = Room::create(['name' => 'A', 'capacity' => 2]);

        $booking = $room->hold('2026-05-01', '2026-05-03', quantity: 1);

        $this->assertSame(BookingStatus::Held, $booking->status);
        $this->assertNotNull($booking->expires_at);
        $this->assertSame(1, $room->availableUnits('2026-05-01', '2026-05-03'));
        Event::assertDispatched(BookingHeld::class);
    }

    public function test_cannot_hold_the_last_unit_twice(): void
    {
        $room = Room::create(['name' => 'A', 'capacity' => 1]);
        $room->hold('2026-05-01', '2026-05-03');

        $this->expectException(NotAvailableException::class);
        $room->hold('2026-05-02', '2026-05-04');
    }

    public function test_cannot_hold_more_than_capacity(): void
    {
        $room = Room::create(['name' => 'A', 'capacity' => 2]);

        $this->expectException(NotAvailableException::class);
        $room->hold('2026-05-01', '2026-05-03', quantity: 3);
    }

    public function test_back_to_back_holds_are_allowed(): void
    {
        $room = Room::create(['name' => 'A', 'capacity' => 1]);
        $room->hold('2026-05-01', '2026-05-03');

        $second = $room->hold('2026-05-03', '2026-05-05');

        $this->assertSame(BookingStatus::Held, $second->status);
    }

    public function test_expired_hold_frees_the_unit_for_a_new_hold(): void
    {
        $room = Room::create(['name' => 'A', 'capacity' => 1]);
        $room->hold('2026-05-01', '2026-05-03', ttl: 10);

        $this->travel(11)->minutes();

        $this->assertTrue($room->isAvailable('2026-05-01', '2026-05-03'));
        $this->assertSame(BookingStatus::Held, $room->hold('2026-05-01', '2026-05-03')->status);
    }

    public function test_confirm_keeps_capacity_consumed_without_expiry(): void
    {
        Event::fake();
        $room = Room::create(['name' => 'A', 'capacity' => 1]);
        $booking = $room->hold('2026-05-01', '2026-05-03');

        $booking->confirm();

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertNull($booking->expires_at);
        $this->travel(2)->hours();
        $this->assertFalse($room->isAvailable('2026-05-01', '2026-05-03'));
        Event::assertDispatched(BookingConfirmed::class);
    }

    public function test_cannot_confirm_an_expired_hold(): void
    {
        $room = Room::create(['name' => 'A', 'capacity' => 1]);
        $booking = $room->hold('2026-05-01', '2026-05-03', ttl: 5);

        $this->travel(6)->minutes();

        $this->expectException(BookingStateException::class);
        $booking->confirm();
    }

    public function test_cancel_frees_capacity(): void
    {
        Event::fake();
        $room = Room::create(['name' => 'A', 'capacity' => 1]);
        $booking = $room->hold('2026-05-01', '2026-05-03');
        $booking->confirm();

        $booking->cancel();

        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertTrue($room->isAvailable('2026-05-01', '2026-05-03'));
        Event::assertDispatched(BookingCancelled::class);
    }

    public function test_release_expired_command_marks_only_lapsed_holds(): void
    {
        Event::fake();
        $room = Room::create(['name' => 'A', 'capacity' => 5]);
        $lapsed = $room->hold('2026-05-01', '2026-05-03', ttl: 5);
        $confirmed = $room->hold('2026-05-01', '2026-05-03', ttl: 5)->confirm();
        $alive = $room->hold('2026-05-01', '2026-05-03', ttl: 60);

        $this->travel(10)->minutes();
        $this->artisan('bookable:release-expired')->expectsOutput('Released 1 expired hold(s).')->assertExitCode(0);

        $this->assertSame(BookingStatus::Expired, $lapsed->refresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $confirmed->refresh()->status);
        $this->assertSame(BookingStatus::Held, $alive->refresh()->status);
        Event::assertDispatched(BookingExpired::class, 1);
    }

    public function test_hold_stores_owner_and_meta(): void
    {
        $room = Room::create(['name' => 'A', 'capacity' => 1]);
        $owner = Room::create(['name' => 'Owner', 'capacity' => 1]); // any model can be an owner

        $booking = $room->hold('2026-05-01', '2026-05-02', owner: $owner, meta: ['note' => 'late arrival']);

        $this->assertSame($owner->getKey(), $booking->owner_id);
        $this->assertSame(['note' => 'late arrival'], $booking->meta);
        $this->assertSame(1, Booking::count());
    }
}
