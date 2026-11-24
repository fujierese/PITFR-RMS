<?php

namespace App\Support;

use App\Models\FacilityRequest;
use Carbon\Carbon;

/**
 * Reservation approval cutoff (Additional Part 2).
 *
 * Every approval step (venue custodian, equipment custodian, Supply Office
 * availability and final approval) must happen before the reservation starts:
 * - a start date before today cannot be approved;
 * - a same-day reservation can be approved until its start time;
 * - an expired request can never be approved.
 * Times are compared in the application timezone (Asia/Manila). Urgent requests
 * follow the same rule.
 */
final class ApprovalCutoff
{
    public static function startOf(FacilityRequest $request): ?Carbon
    {
        $starts = collect($request->getVenueNames())
            ->map(fn (string $venue) => $request->getVenueSchedule($venue))
            ->filter(fn ($schedule) => ! empty($schedule['start_date']))
            ->map(fn ($schedule) => Carbon::parse($schedule['start_date'] . ' ' . ($schedule['start_time'] ?: '00:00'), config('app.timezone')));

        if ($starts->isNotEmpty()) {
            return $starts->min();
        }
        if (! $request->start_date) {
            return null;
        }

        return Carbon::parse($request->start_date->toDateString() . ' ' . ($request->start_time ?: '00:00'), config('app.timezone'));
    }

    /** Why the request can no longer be approved, or null while approval is still possible. */
    public static function reason(FacilityRequest $request, ?Carbon $now = null): ?string
    {
        if ($request->status === 'expired') {
            return 'This request has expired: its start time passed before it was approved. It can no longer be approved.';
        }

        $start = self::startOf($request);
        $now = ($now ?? Carbon::now())->copy()->setTimezone(config('app.timezone'));
        if ($start && $start->lessThanOrEqualTo($now)) {
            return 'Approval is closed: this reservation was scheduled to start on ' . $start->format('M j, Y g:i A')
                . ', which has passed. Approvals must be completed before the activity starts.';
        }

        return null;
    }

    public static function isOpen(FacilityRequest $request, ?Carbon $now = null): bool
    {
        return self::reason($request, $now) === null;
    }
}
