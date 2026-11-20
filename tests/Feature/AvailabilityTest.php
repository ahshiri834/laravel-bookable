<?php

namespace Ahshiri\Bookable\Tests\Feature;

use Ahshiri\Bookable\Enums\BookingStatus;
use Ahshiri\Bookable\Models\Booking;
use Ahshiri\Bookable\Tests\Room;
use Ahshiri\Bookable\Tests\TestCase;

class AvailabilityTest extends TestCase
{
    private function book(Room $room, string $from, string $to, int $qty = 1, BookingStatus $status = BookingStatus::Confirmed, $expires = null): Booking
    {
        return Booking::create([
            'bookable_type' => $room->getMorphClass(),
            'bookable_id' => $room->id,
            'starts_at' => $from,
            'ends_at' => $to,
            'quantity' => $qty,
            'status' => $status,
            'expires_at' => $expires,
        ]);
    }

    public function test_empty_room_has_full_capacity(): void
    {
        $room = Room::create(['name' => 'A', 'capacity' => 3]);

        $this->assertSame(3, $room->availableUnits('2026-05-01', '2026-05-05'));
    }

    public function test_confirmed_booking_reduces_availability(): void
    {
        $room = Room::create(['name' => 'A', 'capacity' => 3]);
        $this->book($room, '2026-05-02', '2026-05-04', 2);

        $this->assertSame(1, $room->availableUnits('2026-05-01', '2026-05-05'));
    }

    public function test_back_to_back_bookings_do_not_conflict(): void
    {
        $room = Room::create(['name' => 'A', 'capacity' => 1]);
        $this->book($room, '2026-05-01', '2026-05-10');

        $this->assertTrue($room->isAvailable('2026-05-10', '2026-05-12'));
        $this->assertFalse($room->isAvailable('2026-05-09', '2026-05-12'));
    }

    public function test_peak_usage_is_per_night_not_sum_of_overlaps(): void
    {
        $room = Room::create(['name' => 'A', 'capacity' => 1]);
        $this->book($room, '2026-05-01', '2026-05-03');
        $this->book($room, '2026-05-03', '2026-05-05');

        // Both bookings overlap the request, one unit used each night: 0 free with capacity 1.
        $this->assertSame(0, $room->availableUnits('2026-05-01', '2026-05-05'));

        // Capacity 2: only 1 unit used on any night, so 1 stays free for the whole range.
        $room2 = Room::create(['name' => 'B', 'capacity' => 2]);
        $this->book($room2, '2026-05-01', '2026-05-03');
        $this->book($room2, '2026-05-03', '2026-05-05');
        $this->assertSame(1, $room2->availableUnits('2026-05-01', '2026-05-05'));
    }

    public function test_active_hold_counts_but_expired_hold_does_not(): void
    {
        $room = Room::create(['name' => 'A', 'capacity' => 1]);
        $this->book($room, '2026-05-01', '2026-05-03', 1, BookingStatus::Held, now()->addMinutes(10));
        $this->assertFalse($room->isAvailable('2026-05-01', '2026-05-03'));

        $room2 = Room::create(['name' => 'B', 'capacity' => 1]);
        $this->book($room2, '2026-05-01', '2026-05-03', 1, BookingStatus::Held, now()->subMinute());
        $this->assertTrue($room2->isAvailable('2026-05-01', '2026-05-03'));
    }

    public function test_cancelled_booking_frees_capacity(): void
    {
        $room = Room::create(['name' => 'A', 'capacity' => 1]);
        $this->book($room, '2026-05-01', '2026-05-03', 1, BookingStatus::Cancelled);

        $this->assertTrue($room->isAvailable('2026-05-01', '2026-05-03'));
    }
}
