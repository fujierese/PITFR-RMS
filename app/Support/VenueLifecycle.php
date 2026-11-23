<?php

namespace App\Support;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\ResourceStatusChanged;
use App\Notifications\VenueCustodianAssigned;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * REQ-20: what happens to bookings when a venue changes while it has them.
 * - Bookings are always kept. Pending, and approved ones that have not ended, get a
 *   history entry; their requestors and the Supply Office are told.
 * - Deactivating: pending requests cannot be approved while it is inactive (REQ-13);
 *   approved upcoming bookings stay for the Supply Office to confirm or reschedule.
 * - Reassigning: the new custodian is told which bookings are now theirs; endorsements
 *   given by the old custodian are reopened (APR-10).
 * - Deleting: refused while any request, past ones included, refers to the venue, so
 *   history and reports keep it. Deactivate it instead.
 * - Renaming and lowering capacity: ResourceRename (REQ-13) and REQ-02.
 */
final class VenueLifecycle
{
    /** Pending requests, and approved ones that have not ended, at this venue. */
    public static function liveBookings(Venue $venue, ?string $name = null): Collection
    {
        return ResourceRename::liveRequests()
            ->where(fn ($query) => $query->matchesVenue((string) ($name ?? $venue->name)))
            ->orderBy('start_date')
            ->get();
    }

    public static function controlNumberList(Collection $requests, int $limit = 5): string
    {
        $numbers = $requests->pluck('control_number')->filter()->values();

        return $numbers->take($limit)->implode(', ') . ($numbers->count() > $limit ? ', …' : '');
    }

    /** Message for the person who made the change, or null when no booking is affected. */
    public static function warningFor(Venue $venue, Collection $bookings, string $change): ?string
    {
        if ($bookings->isEmpty()) {
            return null;
        }

        $approved = $bookings->where('status', 'approved')->count();

        return match ($change) {
            'deactivated' => "{$venue->name} has {$bookings->count()} pending or upcoming booking(s) (" . self::controlNumberList($bookings) . '). '
                . 'They were kept and noted; pending ones cannot be approved while it is disabled'
                . ($approved ? ", and {$approved} approved booking(s) need the Supply Office to confirm or reschedule them." : '.'),
            'reassigned' => "{$venue->name} has {$bookings->count()} pending or upcoming booking(s) (" . self::controlNumberList($bookings) . '). '
                . 'The new custodian was told; venue confirmations given by the previous custodian were reopened.',
            default => null,
        };
    }

    public static function activeChanged(Venue $venue): void
    {
        $bookings = self::liveBookings($venue);
        if ($bookings->isEmpty()) {
            return;
        }
        $actor = auth()->user();
        $by = $actor ? ' by ' . $actor->name : '';

        foreach ($bookings as $booking) {
            $booking->addHistory(
                $venue->is_active ? 'venue_reactivated' : 'venue_deactivated',
                $venue->is_active
                    ? "{$venue->name} was re-enabled{$by}."
                    : "{$venue->name} was disabled{$by}. This booking was kept. "
                        . ($booking->status === 'approved'
                            ? 'The Supply Office should confirm it or propose another venue or schedule.'
                            : 'It cannot be approved while the venue is disabled.'),
                $actor?->id,
            );
        }

        self::notify($venue, $bookings, new ResourceStatusChanged($venue, $venue->is_active ? 'enabled' : 'disabled', 'venue', $actor?->name ?? 'the Supply Office'));
    }

    public static function custodianChanged(Venue $venue, ?int $previousCustodianId): void
    {
        $bookings = self::liveBookings($venue);
        if ($bookings->isEmpty()) {
            return;
        }
        $actor = auth()->user();
        $previous = $previousCustodianId ? User::find($previousCustodianId)?->name : null;
        $current = $venue->custodian?->name ?? 'no one';

        foreach ($bookings as $booking) {
            $booking->addHistory(
                'venue_custodian_changed',
                "The custodian of {$venue->name} changed from " . ($previous ?? 'no one') . " to {$current}" . ($actor ? ' (by ' . $actor->name . ')' : '') . '.',
                $actor?->id,
            );
        }

        try {
            $venue->custodian?->notify(new VenueCustodianAssigned($venue, $bookings->count(), self::controlNumberList($bookings, 10)));
        } catch (\Throwable $exception) {
            Log::warning('REQ-20: could not notify the new venue custodian: ' . $exception->getMessage());
        }
    }

    /** Error when the venue cannot be deleted, or null. */
    public static function deleteError(Venue $venue): ?string
    {
        $referencing = FacilityRequest::withTrashed()
            ->where(function ($query) use ($venue): void {
                $query->matchesVenue((string) $venue->name)
                    ->orWhereHas('requestVenues', fn ($rows) => $rows->where('venue_id', $venue->getKey()));
            });
        $count = (clone $referencing)->count();
        if ($count === 0) {
            return null;
        }

        $live = self::liveBookings($venue);
        if ($live->isNotEmpty()) {
            return "{$venue->name} can't be deleted while it has pending or upcoming bookings (" . self::controlNumberList($live) . '). '
                . 'Disable it instead; the bookings are kept.';
        }

        return "{$venue->name} can't be deleted because {$count} past request(s) refer to it, and deleting it would remove it from their history and reports. "
            . 'Disable it instead so it can no longer be booked.';
    }

    private static function notify(Venue $venue, Collection $bookings, $notification): void
    {
        try {
            $recipients = User::query()->whereIn('id', $bookings->pluck('requested_by_id')->filter()->unique())->get()
                ->merge(User::query()->whereIn('role', ['admin', 'supply_office'])->get())
                ->unique('id');
            foreach ($recipients as $recipient) {
                $recipient->notify($notification);
            }
        } catch (\Throwable $exception) {
            Log::warning('REQ-20: could not notify users of the venue change: ' . $exception->getMessage());
        }
    }
}
