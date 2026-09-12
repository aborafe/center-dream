<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class DatePresenter
{
    public static function date(CarbonInterface|string|null $value): string
    {
        if ($value === null) {
            return '—';
        }

        $date = $value instanceof CarbonInterface ? $value : Carbon::parse($value);

        return $date->format('j/n/Y');
    }
}
