<?php

namespace Ahshiri\Bookable\Tests\Feature;

use Ahshiri\Bookable\Exceptions\NoRateException;
use Ahshiri\Bookable\Models\Rate;
use Ahshiri\Bookable\Tests\Room;
use Ahshiri\Bookable\Tests\TestCase;

class PricingTest extends TestCase
{
    private function rate(Room $room, int $price, array $attrs = []): Rate
    {
        return Rate::create(array_merge([
            'bookable_type' => $room->getMorphClass(),
            'bookable_id' => $room->id,
            'price' => $price,
        ], $attrs));
    }

    public function test_flat_rate_times_nights_times_quantity(): void
    {
        $room = Room::create(['name' => 'A', 'capacity' => 5]);
        $this->rate($room, 1000);

        $quote = $room->quote('2026-05-01', '2026-05-04', quantity: 2);

        $this->assertSame(3, $quote->nights);
        $this->assertSame(6000, $quote->total);
        $this->assertCount(3, $quote->lines);
        $this->assertSame(2000, $quote->lines[0]->amount);
    }

    public function test_seasonal_rate_overrides_base_by_priority(): void
    {
        $room = Room::create(['name' => 'A', 'capacity' => 1]);
        $this->rate($room, 1000);
        $this->rate($room, 1500, ['starts_on' => '2026-05-02', 'ends_on' => '2026-05-03', 'priority' => 10]);

        $quote = $room->quote('2026-05-01', '2026-05-05'); // nights: 1,2,3,4

        $this->assertSame([1000, 1500, 1500, 1000], array_map(fn ($l) => $l->unitPrice, $quote->lines));
        $this->assertSame(5000, $quote->total);
    }

    public function test_weekday_rate_applies_only_on_those_days(): void
    {
        $room = Room::create(['name' => 'A', 'capacity' => 1]);
        $this->rate($room, 1000);
        // 2026-05-01 is a Friday (5), 2026-05-02 is a Saturday (6)
        $this->rate($room, 1400, ['weekdays' => [5, 6], 'priority' => 5]);

        $quote = $room->quote('2026-04-30', '2026-05-03'); // Thu, Fri, Sat

        $this->assertSame([1000, 1400, 1400], array_map(fn ($l) => $l->unitPrice, $quote->lines));
    }

    public function test_equal_priority_prefers_the_narrowest_window(): void
    {
        $room = Room::create(['name' => 'A', 'capacity' => 1]);
        $this->rate($room, 1200, ['starts_on' => '2026-05-01', 'ends_on' => '2026-05-31']);
        $narrow = $this->rate($room, 1800, ['starts_on' => '2026-05-10', 'ends_on' => '2026-05-12']);

        $quote = $room->quote('2026-05-10', '2026-05-11');

        $this->assertSame(1800, $quote->lines[0]->unitPrice);
        $this->assertSame($narrow->id, $quote->lines[0]->rateId);
    }

    public function test_missing_rate_throws_unless_default_price_is_configured(): void
    {
        $room = Room::create(['name' => 'A', 'capacity' => 1]);

        try {
            $room->quote('2026-05-01', '2026-05-02');
            $this->fail('Expected NoRateException');
        } catch (NoRateException) {
            // expected
        }

        config(['bookable.default_price' => 700]);
        $this->assertSame(1400, $room->quote('2026-05-01', '2026-05-03')->total);
    }

    public function test_hold_snapshots_the_quoted_total(): void
    {
        $room = Room::create(['name' => 'A', 'capacity' => 2]);
        $this->rate($room, 1000);

        $booking = $room->hold('2026-05-01', '2026-05-03', quantity: 2);

        $this->assertSame(4000, $booking->total);
    }

    public function test_hold_without_any_rate_leaves_total_null(): void
    {
        $room = Room::create(['name' => 'A', 'capacity' => 1]);

        $this->assertNull($room->hold('2026-05-01', '2026-05-02')->total);
    }
}
