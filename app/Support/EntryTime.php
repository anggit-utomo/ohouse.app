<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class EntryTime
{
    /** Today keeps the real time; other days are stored at noon. */
    public static function at(?string $date): CarbonImmutable
    {
        if ($date === null) {
            return CarbonImmutable::now();
        }

        $day = CarbonImmutable::parse($date);

        return $day->isToday() ? CarbonImmutable::now() : $day->setTime(12, 0);
    }
}
