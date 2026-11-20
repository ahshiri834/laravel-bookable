<?php

return [
    // How long (minutes) a hold lasts when no ttl is given.
    'hold_ttl' => 15,

    // Column on the bookable model that stores its unit capacity.
    'capacity_column' => 'capacity',

    // Lock driver used to serialize writers per bookable: "database" (row lock) or "cache" (Cache::lock, e.g. Redis).
    'lock' => 'database',
];
