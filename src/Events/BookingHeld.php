<?php

namespace Ahshiri\Bookable\Events;

use Ahshiri\Bookable\Models\Booking;

class BookingHeld
{
    public function __construct(public readonly Booking $booking) {}
}
