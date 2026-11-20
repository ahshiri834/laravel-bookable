<?php

namespace Ahshiri\Bookable\Pricing;

final class QuoteLine
{
    public function __construct(
        public readonly string $night,     // Y-m-d
        public readonly int $unitPrice,    // price per unit for this night
        public readonly int $amount,       // unitPrice * quantity
        public readonly ?int $rateId,      // null when the default price was used
    ) {}
}
