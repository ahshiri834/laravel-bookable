<?php

namespace Ahshiri\Bookable\Pricing;

use Ahshiri\Bookable\Exceptions\NoRateException;
use Ahshiri\Bookable\Models\Rate;
use Ahshiri\Bookable\Support\DateRange;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class PriceCalculator
{
    /**
     * For each night pick the matching rate with the highest priority; ties go to the
     * narrowest date window, then to the most recently created rule.
     */
    public function quote(Model $bookable, DateRange $range, int $quantity = 1): Quote
    {
        $rates = Rate::query()
            ->where('bookable_type', $bookable->getMorphClass())
            ->where('bookable_id', $bookable->getKey())
            ->get();

        $default = config('bookable.default_price');
        $lines = [];
        $total = 0;

        foreach ($range->eachNight() as $night) {
            $rate = $this->bestRate($rates, $night);

            if ($rate === null && $default === null) {
                throw new NoRateException("No rate covers the night of {$night->toDateString()}.");
            }

            $unit = $rate !== null ? $rate->price : (int) $default;
            $amount = $unit * $quantity;
            $total += $amount;

            $lines[] = new QuoteLine($night->toDateString(), $unit, $amount, $rate?->getKey());
        }

        return new Quote($total, $range->nights(), $quantity, $lines);
    }

    /** @param  Collection<int, Rate>  $rates */
    private function bestRate(Collection $rates, CarbonInterface $night): ?Rate
    {
        return $rates
            ->filter(fn (Rate $rate) => $rate->appliesTo($night))
            ->sort(fn (Rate $a, Rate $b) => [$b->priority, $a->span(), $b->getKey()] <=> [$a->priority, $b->span(), $a->getKey()])
            ->first();
    }
}
