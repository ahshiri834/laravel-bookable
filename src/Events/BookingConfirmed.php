<?php

namespace Ahshiri\Bookable\Events;

use Ahshiri\Bookable\Models\Booking;

class BookingConfirmed
{
    public function __construct(public readonly Booking $booking) {}
}
