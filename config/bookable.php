<?php

return [
    // How long (minutes) a hold lasts when no ttl is given.
    'hold_ttl' => 15,

    // Column on the bookable model that stores its unit capacity.
    'capacity_column' => 'capacity',

    // Price (minor units, per unit per night) used when no rate matches a night.
    // null = throw NoRateException instead.
    'default_price' => null,

    // Lock driver used to serialize writers per bookable: "database" (row lock) or "cache" (Cache::lock, e.g. Redis).
    'lock' => 'database',
];
