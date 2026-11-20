<?php

namespace Ahshiri\Bookable\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Rate extends Model
{
    protected $table = 'booking_rates';

    protected $guarded = [];

    protected $casts = [
        'price' => 'integer',
        'priority' => 'integer',
        'starts_on' => 'immutable_date',
        'ends_on' => 'immutable_date',
        'weekdays' => 'array',
    ];

    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Whether this rule applies to the given night (dates inclusive, weekday 0 = Sunday). */
    public function appliesTo(CarbonInterface $night): bool
    {
        $day = $night->startOfDay();

        if ($this->starts_on !== null && $day->lt($this->starts_on)) {
            return false;
        }

        if ($this->ends_on !== null && $day->gt($this->ends_on)) {
            return false;
        }

        if (! empty($this->weekdays) && ! in_array($day->dayOfWeek, $this->weekdays, true)) {
            return false;
        }

        return true;
    }

    /** Length of the date window in days; open-ended rules count as the widest. */
    public function span(): int
    {
        if ($this->starts_on === null || $this->ends_on === null) {
            return PHP_INT_MAX;
        }

        return (int) $this->starts_on->diffInDays($this->ends_on) + 1;
    }
}
