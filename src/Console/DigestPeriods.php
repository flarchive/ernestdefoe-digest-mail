<?php

namespace Resofire\DigestMail\Console;

use Carbon\Carbon;

/**
 * Period-window helpers shared by the digest commands. `periodStart()` is the
 * start of the content window for a frequency; `lastSentCutoff()` is the
 * "already sent recently?" guard so a digest isn't sent twice in one period.
 */
trait DigestPeriods
{
    private function periodStart(string $frequency): Carbon
    {
        return match ($frequency) {
            'daily'   => Carbon::now('UTC')->subDay(),
            'weekly'  => Carbon::now('UTC')->subWeek(),
            'monthly' => Carbon::now('UTC')->subMonth(),
        };
    }

    private function lastSentCutoff(string $frequency): Carbon
    {
        return match ($frequency) {
            'daily'   => Carbon::now('UTC')->subHours(23),
            'weekly'  => Carbon::now('UTC')->subDays(6),
            'monthly' => Carbon::now('UTC')->subDays(28),
        };
    }
}
