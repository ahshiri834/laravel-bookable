<?php

namespace Ahshiri\Bookable\Tests\Feature;

use Ahshiri\Bookable\Models\Booking;
use Ahshiri\Bookable\Tests\Room;
use Ahshiri\Bookable\Tests\Support\ConfiguresDatabase;
use Ahshiri\Bookable\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;

/**
 * Real race: several separate PHP processes, each with its own MySQL connection, try to take
 * units at the same instant. Row locking must make exactly `capacity` of them succeed.
 *
 * Runs only with DB_CONNECTION=mysql; SQLite cannot lock rows, so the test would prove nothing.
 */
#[Group('concurrency')]
class ConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        if (! ConfiguresDatabase::usingMysql()) {
            $this->markTestSkipped('Concurrency test needs MySQL (set DB_CONNECTION=mysql).');
        }

        parent::setUp();
    }

    /** @return array<string, array{int, int}> */
    public static function scenarios(): array
    {
        return [
            'last unit, 8 racers' => [1, 8],
            '3 units, 10 racers' => [3, 10],
        ];
    }

    #[DataProvider('scenarios')]
    public function test_never_overbooks_under_parallel_holds(int $capacity, int $racers): void
    {
        $room = Room::create(['name' => 'Race', 'capacity' => $capacity]);
        $startAt = microtime(true) + 6; // generous: booting N PHP processes takes a while

        $workers = [];
        for ($i = 0; $i < $racers; $i++) {
            $process = new Process([PHP_BINARY, __DIR__.'/../Concurrency/worker.php', (string) $room->id, (string) $startAt, '1']);
            $process->start();
            $workers[] = $process;
        }

        $results = [];
        foreach ($workers as $process) {
            $process->wait();
            $decoded = json_decode(trim($process->getOutput()), true);
            $this->assertIsArray($decoded, 'Worker failed: '.$process->getOutput().$process->getErrorOutput());
            $results[] = $decoded;
        }

        $errors = array_filter($results, fn ($r) => $r['result'] === 'error');
        $this->assertSame([], array_values($errors), 'Workers hit unexpected errors');

        $won = count(array_filter($results, fn ($r) => $r['result'] === 'ok'));
        $this->assertSame($capacity, $won, 'Exactly `capacity` holds may succeed');

        $this->assertSame($capacity, (int) Booking::query()->consuming()->sum('quantity'));
        $this->assertSame(0, $room->availableUnits('2026-06-01', '2026-06-03'));
    }
}
