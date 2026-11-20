<?php

namespace Ahshiri\Bookable\Tests;

use Ahshiri\Bookable\Concerns\IsBookable;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use IsBookable;

    protected $guarded = [];
}
