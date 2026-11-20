<?php

namespace Ahshiri\Bookable\Console;

use Ahshiri\Bookable\BookingManager;
use Illuminate\Console\Command;

class ReleaseExpiredCommand extends Command
{
    protected $signature = 'bookable:release-expired';

    protected $description = 'Release booking holds whose time has run out';

    public function handle(BookingManager $manager): int
    {
        $this->info("Released {$manager->releaseExpired()} expired hold(s).");

        return self::SUCCESS;
    }
}
