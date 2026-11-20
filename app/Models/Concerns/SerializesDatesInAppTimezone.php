<?php

namespace App\Models\Concerns;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * REQ-17: dates sent as JSON (API, calendar pop-ups) are written in Philippine time
 * with the offset, e.g. 2026-11-20T00:00:00+08:00, instead of Laravel's default UTC
 * form 2026-11-19T16:00:00.000000Z. It is the same moment either way, but the date
 * part now always matches the date people booked.
 */
trait SerializesDatesInAppTimezone
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)
            ->setTimezone(config('app.timezone', 'Asia/Manila'))
            ->toIso8601String();
    }
}
