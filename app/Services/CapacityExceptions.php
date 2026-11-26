<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\NewFacilityRequestNotification;
use App\Notifications\RequestStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Part 2: capacity exceptions are decided by the assigned Venue Custodian, per venue.
 *
 * - Approve: the venue can hold the participant count. Not a reservation approval.
 * - Do not approve: a reason is required and shown to the requestor; the request goes
 *   back to the requestor, who either chooses another venue (edit form) or accepts the
 *   custodian's suggested alternative venue. The original venue is never replaced
 *   without the requestor's confirmation.
 *
 * The Supply Office, IGP and other custodians cannot decide; the Supply Office cannot
 * confirm availability and the reservation cannot be approved until every exception is
 * approved.
 */
class CapacityExceptions
{
    public function __construct(private readonly AvailabilityService $availability)
    {
    }

    public static function enabled(): bool
    {
        return FacilityRequest::capacityRulesEnabled() && FacilityRequest::capacityDecisionsEnabled();
    }

    /** Venues with an exception that this custodian may decide (their own venues, undecided). */
    public function decidableVenuesFor(FacilityRequest $request, User $user): array
    {
        if (! self::enabled() || ! $user->isCustodianVenue() || $request->status !== 'pending'
            || $request->supply_office_review_status !== 'pending') {
            return [];
        }
        $own = $user->venues()->pluck('name')->map(fn ($n) => mb_strtolower(trim((string) $n)))->all();

        return array_values(array_filter(
            $request->undecidedCapacityVenues(),
            fn ($venue) => in_array(mb_strtolower(trim((string) $venue)), $own, true)
        ));
    }

    /**
     * Venues that can replace $venueName: active, large enough, free (no approved booking
     * or closure) for that venue's requested schedule, open to this requestor.
     *
     * @return list<array{name: string, capacity: ?int}>
     */
    public function alternativeVenues(FacilityRequest $request, string $venueName): array
    {
        $participants = $request->participantsForVenue($venueName);
        $inRequest = array_map(fn ($n) => mb_strtolower(trim($n)), $request->getVenueNames());
        $ranges = $request->getVenueScheduleRanges($venueName);
        $closed = array_map('mb_strtolower', \App\Support\VenueRules::closedVenuesFor(
            $request->requester,
            Venue::query()->where('is_active', true)->pluck('name')->all()
        ));

        return Venue::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('capacity')->orWhere('capacity', '>=', $participants))
            ->orderBy('name')
            ->get()
            ->filter(function (Venue $venue) use ($inRequest, $ranges, $closed): bool {
                $name = mb_strtolower(trim((string) $venue->name));
                if (in_array($name, $inRequest, true) || in_array($name, $closed, true) || $ranges === []) {
                    return false;
                }
                foreach ($ranges as $range) {
                    if ($this->availability->checkVenueTimeRangeForReview($venue->name, $range['start'], $range['end'], null) !== null) {
                        return false;
                    }
                }

                return true;
            })
            ->map(fn (Venue $venue) => ['name' => (string) $venue->name, 'capacity' => $venue->capacity !== null ? (int) $venue->capacity : null])
            ->values()
            ->all();
    }

    /**
     * Record the custodian's decision for one venue.
     *
     * @param array{decision: string, remarks?: ?string, reason?: ?string, next_step?: ?string, suggested_venue?: ?string, recommendation?: ?string} $input
     */
    public function decide(FacilityRequest $request, string $venueName, User $custodian, array $input): FacilityRequest
    {
        return DB::transaction(function () use ($request, $venueName, $custodian, $input): FacilityRequest {
            $request = FacilityRequest::query()->lockForUpdate()->findOrFail($request->id);
            $venue = collect($request->undecidedCapacityVenues())
                ->first(fn ($v) => mb_strtolower(trim((string) $v)) === mb_strtolower(trim($venueName)));
            if ($venue === null) {
                throw ValidationException::withMessages(['capacity' => 'This capacity exception was already decided.']);
            }
            if (! in_array($venue, $this->decidableVenuesFor($request, $custodian), true)) {
                abort(403, 'Only the custodian assigned to ' . $venue . ' can decide its capacity exception.');
            }

            $participants = $request->participantsForVenue($venue);
            $capacity = Venue::query()->where('name', $venue)->value('capacity');
            $approved = $input['decision'] === 'approved';
            $record = [
                'decision' => $approved ? 'approved' : 'not_approved',
                'participants' => $participants,
                'capacity' => $capacity !== null ? (int) $capacity : null,
                'remarks' => $approved ? (trim((string) ($input['remarks'] ?? '')) ?: null) : null,
                'reason' => $approved ? null : trim((string) $input['reason']),
                'next_step' => $approved ? null : ($input['next_step'] ?? 'requestor_choose'),
                'suggested_venue' => null,
                'recommendation' => null,
                'by' => $custodian->id,
                'by_name' => $custodian->name,
                'at' => now()->toIso8601String(),
                'requestor_response' => null,
            ];

            if (! $approved && $record['next_step'] === 'suggest_venue') {
                $suggested = (string) ($input['suggested_venue'] ?? '');
                $valid = collect($this->alternativeVenues($request, $venue))->pluck('name')->all();
                if (! in_array($suggested, $valid, true)) {
                    throw ValidationException::withMessages(['suggested_venue' => 'That venue cannot take ' . $participants . ' participants at this time, or is not available. Choose one from the list.']);
                }
                $record['suggested_venue'] = $suggested;
                $record['recommendation'] = trim((string) $input['recommendation']);
            }

            $decisions = (array) ($request->capacity_exception_decisions ?? []);
            $decisions[$venue] = $record;
            $request->capacity_exception_decisions = $decisions;
            $request->recomputeCapacityExceptionStatus();
            $request->capacity_exception_reviewed_by_id = $custodian->id;
            $request->capacity_exception_reviewed_at = now();
            if (! $approved) {
                // Back to the requestor: choose another venue or accept the suggestion.
                $request->venue_status = 'needs_reschedule';
                $request->venue_notes = 'Capacity exception not approved for ' . $venue . ': ' . $record['reason'];
            }
            $request->save();

            $detail = $approved
                ? "Capacity exception approved for {$venue} ({$participants} participants, capacity " . ($capacity ?? 'not set') . ') by ' . $custodian->name . ($record['remarks'] ? '. Remarks: ' . $record['remarks'] : '.')
                : "Capacity exception not approved for {$venue} ({$participants} participants, capacity " . ($capacity ?? 'not set') . ') by ' . $custodian->name . '. Reason: ' . $record['reason']
                    . ($record['suggested_venue'] ? ' Suggested alternative venue: ' . $record['suggested_venue'] . '. ' . $record['recommendation'] : ' The requestor will choose another venue.');
            $request->addHistory($approved ? 'capacity_exception_approved' : 'capacity_exception_not_approved', $detail, $custodian->id);
            AuditLog::create([
                'actor_id' => $custodian->id,
                'action' => $approved ? 'capacity_exception_approved' : 'capacity_exception_not_approved',
                'details' => $request->control_number . ': ' . $detail,
                'old_values' => [],
                'new_values' => ['venue' => $venue] + $record,
            ]);

            if (! $approved) {
                DB::afterCommit(fn () => $this->notify($request->requester, new RequestStatusChanged(
                    $request, 'needs_revision',
                    'Capacity exception not approved for ' . $venue . '. ' . ($record['suggested_venue'] ? 'Suggested venue: ' . $record['suggested_venue'] . '. ' : '') . 'Open the request to choose a venue.',
                    $custodian->name
                )));
            }

            return $request;
        });
    }

    /** The requestor accepts the custodian's suggested venue; it then needs its own verification. */
    public function acceptSuggestion(FacilityRequest $request, string $venueName, User $requestor): FacilityRequest
    {
        return DB::transaction(function () use ($request, $venueName, $requestor): FacilityRequest {
            $request = FacilityRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless((int) $request->requested_by_id === (int) $requestor->id, 403);
            $decision = $request->capacityDecision($venueName);
            if (! $decision || ($decision['decision'] ?? null) !== 'not_approved' || empty($decision['suggested_venue'])
                || ($decision['requestor_response'] ?? null) !== null || $request->status !== 'pending') {
                throw ValidationException::withMessages(['capacity' => 'There is no suggested venue waiting for your answer.']);
            }
            $from = collect($request->getVenueNames())->first(fn ($n) => mb_strtolower(trim($n)) === mb_strtolower(trim($venueName)));
            $to = $decision['suggested_venue'];
            // Revalidate: capacity, closures, approved bookings, still active.
            if (! in_array($to, collect($this->alternativeVenues($request, $from))->pluck('name')->all(), true)) {
                throw ValidationException::withMessages(['capacity' => $to . ' is no longer available for this schedule or cannot hold your participants. Choose another venue.']);
            }

            $participants = $request->participantsForVenue($from);
            $names = array_map(fn ($n) => mb_strtolower(trim($n)) === mb_strtolower(trim($from)) ? $to : $n, $request->getVenueNames());
            $schedules = [];
            foreach ($request->getVenueNames() as $name) {
                $schedules[mb_strtolower(trim($name)) === mb_strtolower(trim($from)) ? $to : $name] = $request->getVenueSchedule($name);
            }
            $allocations = [];
            foreach ($request->getVenueEquipmentAllocations() as $venue => $items) {
                $allocations[mb_strtolower(trim($venue)) === mb_strtolower(trim($from)) ? $to : $venue] = $items;
            }
            $perVenue = (array) ($request->venue_participants ?? []);
            unset($perVenue[$from]);
            $perVenue[$to] = $participants;
            $exceptions = (array) ($request->capacity_exceptions ?? []);
            unset($exceptions[$from]);
            $decisions = (array) ($request->capacity_exception_decisions ?? []);
            $decisions[$from]['requestor_response'] = 'accepted';
            $decisions[$from]['responded_at'] = now()->toIso8601String();
            $verifications = (array) ($request->venue_verifications ?? []);
            unset($verifications[$from]);

            $request->forceFill([
                'venue' => $names,
                'venue_participants' => $perVenue,
                'capacity_exceptions' => $exceptions ?: null,
                'capacity_exception_decisions' => $decisions,
                'venue_verifications' => $verifications ?: null,
                'venue_status' => 'pending',
                'supply_office_review_status' => 'pending',
            ]);
            $request->recomputeCapacityExceptionStatus();
            $request->save();
            $request->syncRelationalItems($schedules, $allocations === [] ? null : $allocations);

            $detail = $requestor->name . ' accepted the suggested venue ' . $to . ' instead of ' . $from . '. The custodian of ' . $to . ' must verify it.';
            $request->addHistory('capacity_alternative_accepted', $detail, $requestor->id);
            AuditLog::create([
                'actor_id' => $requestor->id,
                'action' => 'capacity_alternative_accepted',
                'details' => $request->control_number . ': ' . $detail,
                'old_values' => ['venue' => $from],
                'new_values' => ['venue' => $to],
            ]);

            $fresh = $request->fresh();
            DB::afterCommit(function () use ($fresh, $to, $from, $requestor): void {
                $newCustodian = Venue::query()->where('name', $to)->first()?->custodian;
                if ($newCustodian) {
                    $this->notify($newCustodian, new NewFacilityRequestNotification($fresh));
                }
                $oldCustodian = Venue::query()->where('name', $from)->first()?->custodian;
                if ($oldCustodian && (! $newCustodian || $oldCustodian->id !== $newCustodian->id)) {
                    $this->notify($oldCustodian, new RequestStatusChanged($fresh, 'needs_revision', 'The requestor moved this reservation from ' . $from . ' to ' . $to . '.', $requestor->name));
                }
            });

            return $fresh;
        });
    }

    /** Requestor chose another venue through the edit form: close any open suggestion. */
    public static function markChoseOther(FacilityRequest $request): void
    {
        $decisions = (array) ($request->capacity_exception_decisions ?? []);
        foreach ($decisions as $venue => $decision) {
            if (($decision['decision'] ?? null) === 'not_approved' && ($decision['requestor_response'] ?? null) === null) {
                $decisions[$venue]['requestor_response'] = 'chose_other';
                $decisions[$venue]['responded_at'] = now()->toIso8601String();
            }
        }
        $request->capacity_exception_decisions = $decisions ?: null;
    }

    private function notify(?User $user, $notification): void
    {
        if (! $user) {
            return;
        }
        try {
            $user->notify($notification);
        } catch (\Throwable $e) {
            Log::warning('Capacity exception notification failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
