<?php

/**
 * Child process used by ConcurrencyTest. Boots the package in its own PHP process (its own DB
 * connection), waits for a shared start time so all workers fire together, then tries to hold
 * units of one room and prints a single JSON line with the outcome.
 *
 * Usage: php worker.php <roomId> <startAtMicrotime> <quantity>
 */

use Ahshiri\Bookable\BookableServiceProvider;
use Ahshiri\Bookable\Exceptions\NotAvailableException;
use Ahshiri\Bookable\Tests\Room;
use Ahshiri\Bookable\Tests\Support\ConfiguresDatabase;
use Orchestra\Testbench\Foundation\Application;

require __DIR__.'/../../vendor/autoload.php';

[, $roomId, $startAt, $quantity] = $argv;

$app = Application::create(options: [
    'extra' => ['providers' => [BookableServiceProvider::class], 'dont-discover' => ['*']],
]);
ConfiguresDatabase::apply($app);

// Warm up the connection and the model so only the contended call is timed.
$room = Room::findOrFail((int) $roomId);

while (microtime(true) < (float) $startAt) {
    usleep(200);
}

try {
    $room->hold('2026-06-01', '2026-06-03', (int) $quantity);
    echo json_encode(['result' => 'ok']);
} catch (NotAvailableException) {
    echo json_encode(['result' => 'full']);
} catch (Throwable $e) {
    echo json_encode(['result' => 'error', 'message' => $e::class.': '.$e->getMessage()]);
}
