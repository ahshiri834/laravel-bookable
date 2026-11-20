<?php

namespace Ahshiri\Bookable\Enums;

enum BookingStatus: string
{
    case Held = 'held';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
