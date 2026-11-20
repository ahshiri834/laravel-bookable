<?php

namespace Ahshiri\Bookable\Pricing;

/**
 * Price breakdown for a stay. All amounts are integers in minor units (no floats).
 */
final class Quote
{
    /** @param  array<int, QuoteLine>  $lines */
    public function __construct(
        public readonly int $total,
        public readonly int $nights,
        public readonly int $quantity,
        public readonly array $lines,
    ) {}
}
