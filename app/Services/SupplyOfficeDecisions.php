<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\ReservationConflict;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\ReservationDecision;
use App\Support\ApprovalCutoff;
use App\Support\VenueRules;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Additional Part 2: the Supply Office's decisions, offered only when they apply.
 *
 * - Approve Reservation: every review done, no unresolved conflict.
 * - Request Revision: the requestor has something to correct.
 * - Assign Alternative Venue: a real venue concern exists AND a suitable free venue exists.
 * - Reschedule: a real scheduling concern exists AND a free schedule exists.
 * - Decline Reservation: always possible, with a reason and a written explanation.
 *
 * Urgent conflicts: an approved urgent reservation that overlaps another reservation
 * opens a ReservationConflict. Nothing is changed automatically; the Supply Office
 * decides which reservation to adjust (either one) and every change is recorded.
 */
class SupplyOfficeDecisions
{
    public const DECLINE_REASONS = [
        'venue_unavailable' => 'The venue is not available for this schedule',
        'no_alternative' => 'No suitable alternative venue or schedule',
        'institutional_priority' => 'Needed for a higher-priority institutional activity',
        'requirements_not_met' => 'Requirements were not met',
        'policy' => 'Not allowed under the reservation policy',
        'other' => 'Other administrative reason',
    ];

    public function __construct(private readonly AvailabilityService $availability)
    {
    }

    /** @return Collection<int, ReservationConflict> */
    public function openConflicts(FacilityRequest $request): Collection
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('reservation_conflicts')) {
            return collect();
        }

        return ReservationConflict::query()
            ->where('status', 'open')
            ->where(fn ($q) => $q->where('urgent_request_id', $request->id)->orWhere('affected_request_id', $request->id))
            ->with(['urgentRequest', 'affectedRequest'])
            ->get();
    }

    /** A real problem with the requested venue, or null. */
    public function venueConcern(FacilityRequest $request): ?string
    {
        if ($this->openConflicts($request)->isNotEmpty()) {
            return 'An approved urgent reservation needs the same venue and time.';
        }
        if (! in_array($request->status, ['pending', 'approved'], true)) {
            return null;
        }
        if ($request->status === 'pending' && ($request->supply_office_review_status === 'unavailable' || $request->venue_status === 'needs_reschedule')) {
            return 'The requested venue was marked unavailable for this schedule.';
        }
        if ($message = $this->availability->inactiveResourceMessage($request)) {
            return $message;
        }
        if ($message = VenueRules::requestMessage($request)) {
            return $message;
        }
        foreach ($request->getVenueNames() as $venueName) {
            $capacity = $this->availability->getVenueCapacity($venueName);
            if ($capacity !== null && (int) $request->participantsForVenue($venueName) > $capacity && ! $request->hasPendingCapacityException()) {
                return "{$venueName} holds {$capacity}; the request expects " . $request->participantsForVenue($venueName) . ' participants.';
            }
        }

        return null;
    }

    /** A real problem with the requested schedule, or null. */
    public function scheduleConcern(FacilityRequest $request): ?string
    {
        if ($this->openConflicts($request)->isNotEmpty()) {
            return 'An approved urgent reservation needs the same venue and time.';
        }
        if (! in_array($request->status, ['pending', 'approved'], true)) {
            return null;
        }
        if ($request->status === 'pending' && ($request->supply_office_review_status === 'unavailable' || $request->venue_status === 'needs_reschedule')) {
            return 'The requested schedule was marked unavailable.';
        }
        if ($request->requestChangeRequests()->where('status', 'pending')->exists()) {
            return 'The requestor asked for a change.';
        }
        foreach ($request->getVenueNames() as $venueName) {
            foreach ($request->getVenueScheduleRanges($venueName) as $range) {
                $check = $request->status === 'pending'
                    ? $this->availability->checkVenueTimeRangeForReview($venueName, $range['start'], $range['end'], $request->id)
                    : (($this->availability->checkVenueAvailability($venueName, $range['start'], $range['end'], $request->id)['available'] ?? true) ? null : 'Another approved reservation uses this venue and time.');
                if ($check !== null) {
                    return $check;
                }
            }
        }

        return null;
    }

    /** What the requestor must correct, if anything. @return list<string> */
    public function revisionReasons(FacilityRequest $request): array
    {
        if ($request->status !== 'pending') {
            return [];
        }
        $reasons = [];
        if ($request->is_emergency && ! $request->proposal_file && ! $request->activity_proposal_file) {
            $reasons[] = 'The urgent request has no proposal or memorandum order attached.';
        }
        if ($request->hasPendingCapacityException()) {
            $reasons[] = 'Expected participants exceed the venue capacity.';
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('activity_proposal_versions')
            && method_exists($request, 'activityProposalDocumentStatus')
            && $request->activityProposalDocumentStatus() === 'requires_correction') {
            $reasons[] = 'The activity proposal needs correction.';
        }

        return $reasons;
    }

    /**
     * Free, suitable venues that could replace $replaceVenue (or the request's first
     * venue) for the same schedule.
     *
     * @return list<string>
     */
    public function alternativeVenues(FacilityRequest $request, ?string $replaceVenue = null): array
    {
        $names = $request->getVenueNames();
        $replaceVenue ??= $this->conflictVenue($request) ?? ($names[0] ?? null);
        if ($replaceVenue === null) {
            return [];
        }
        $ranges = $request->getVenueScheduleRanges($replaceVenue);
        $taken = array_map(fn ($n) => mb_strtolower(trim($n)), $names);
        $participants = (int) $request->participantsForVenue($replaceVenue) ?: (int) $request->expected_participants;
        $urgentIds = $this->openConflicts($request)->flatMap(fn (ReservationConflict $c) => [$c->urgent_request_id, $c->affected_request_id])->unique()->all();

        return Venue::query()->where('is_active', true)->orderBy('name')->get()
            ->filter(function (Venue $venue) use ($taken, $participants, $ranges, $request, $urgentIds): bool {
                if (in_array(mb_strtolower(trim($venue->name)), $taken, true)) {
                    return false;
                }
                if ($venue->capacity !== null && $participants > (int) $venue->capacity) {
                    return false;
                }
                if (VenueRules::closedVenuesFor($request->requester, [$venue->name]) !== []) {
                    return false;
                }
                if (VenueRules::requiresActivityType($venue->name)) {
                    return false; // needs the requestor's activity type; not assigned administratively
                }
                foreach ($ranges as $range) {
                    if ($this->availability->checkVenueTimeRangeForReview($venue->name, $range['start'], $range['end'], $request->id) !== null) {
                        return false;
                    }
                }

                return true;
            })
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * Free dates for the same venues and times (the whole booking shifted), within
     * the next three weeks.
     *
     * @return list<array{start_date: string, label: string}>
     */
    public function feasibleSchedules(FacilityRequest $request, int $limit = 5, int $days = 21): array
    {
        $venues = $request->getVenueNames();
        if ($venues === [] || ! $request->start_date) {
            return [];
        }
        $now = Carbon::now();
        $original = $request->start_date->copy()->startOfDay();
        $options = [];
        for ($offset = 1; $offset <= $days && count($options) < $limit; $offset++) {
            $candidateStart = $now->copy()->startOfDay()->addDays($offset);
            $shift = (int) $original->diffInDays($candidateStart, false);
            if ($shift === 0) {
                continue;
            }
            if ($this->shiftIsFree($request, $shift)) {
                $options[] = ['start_date' => $candidateStart->toDateString(), 'label' => $candidateStart->format('l, M j, Y')];
            }
        }

        return $options;
    }

    private function shiftIsFree(FacilityRequest $request, int $shiftDays): bool
    {
        foreach ($request->getVenueNames() as $venueName) {
            foreach ($request->getVenueScheduleRanges($venueName) as $range) {
                $start = $range['start']->copy()->addDays($shiftDays);
                $end = $range['end']->copy()->addDays($shiftDays);
                if ($start->lessThanOrEqualTo(Carbon::now())) {
                    return false;
                }
                if ($this->availability->checkVenueTimeRangeForReview($venueName, $start, $end, $request->id) !== null) {
                    return false;
                }
            }
        }
        foreach ($request->getEquipmentQuantities() as $item => $quantity) {
            $start = $request->getRequestedStartDateTime()->copy()->addDays($shiftDays);
            $end = $request->getRequestedEndDateTime()->copy()->addDays($shiftDays);
            if (! ($this->availability->checkEquipmentAvailability($item, (int) $quantity, $start, $end, $request->id)['available'] ?? true)) {
                return false;
            }
        }

        return true;
    }

    private function conflictVenue(FacilityRequest $request): ?string
    {
        $conflict = $this->openConflicts($request)->first();

        return $conflict?->venue_name;
    }

    /**
     * The actions to offer the Supply Office for this request.
     *
     * @return array{approve: bool, revision: list<string>, alternative_venues: list<string>, venue_concern: ?string, schedules: list<array{start_date: string, label: string}>, schedule_concern: ?string, decline: bool, conflicts: Collection}
     */
    public function actionsFor(FacilityRequest $request): array
    {
        $conflicts = $this->openConflicts($request);
        $venueConcern = $this->venueConcern($request);
        $scheduleConcern = $this->scheduleConcern($request);
        $open = in_array($request->status, ['pending', 'approved'], true) && ApprovalCutoff::isOpen($request);

        return [
            'conflicts' => $conflicts,
            'revision' => $open ? $this->revisionReasons($request) : [],
            'venue_concern' => $venueConcern,
            'alternative_venues' => $open && $venueConcern ? $this->alternativeVenues($request) : [],
            'schedule_concern' => $scheduleConcern,
            'schedules' => $open && $scheduleConcern ? $this->feasibleSchedules($request) : [],
            'decline' => in_array($request->status, ['pending', 'approved', 'needs_reschedule'], true),
        ];
    }

    /** Record conflicts opened by an approved urgent reservation (idempotent). */
    public function recordUrgentConflicts(FacilityRequest $urgent, User $admin): Collection
    {
        if (! $urgent->is_emergency || $urgent->status !== 'approved') {
            return collect();
        }
        $created = collect();
        foreach ($this->availability->getApprovedVenueConflicts($urgent) as $affected) {
            $venue = collect($urgent->getVenueNames())->first(fn ($n) => $affected->booksVenue($n));
            $conflict = ReservationConflict::firstOrCreate(
                ['urgent_request_id' => $urgent->id, 'affected_request_id' => $affected->id],
                [
                    'venue_name' => $venue,
                    'status' => 'open',
                    'previous_details' => $this->snapshot($affected),
                    'detected_by_id' => $admin->id,
                    'detected_at' => now(),
                ]
            );
            if (! $conflict->wasRecentlyCreated) {
                continue;
            }
            $created->push($conflict);
            $affected->addHistory(
                'reservation_affected',
                'An approved urgent institutional reservation needs ' . ($venue ?? 'this venue') . ' at this time. The Supply Office will decide how to adjust one of the reservations; nothing has been changed yet.',
                $admin->id
            );
            $urgent->addHistory(
                'urgent_conflict_opened',
                'Approved while overlapping reservation ' . $affected->control_number . ' at ' . ($venue ?? 'the same venue') . '. The Supply Office must resolve the conflict.',
                $admin->id
            );
            AuditLog::create([
                'actor_id' => $admin->id,
                'action' => 'urgent_conflict_opened',
                'details' => "Urgent {$urgent->control_number} overlaps {$affected->control_number}",
                'new_values' => ['conflict_id' => $conflict->id, 'urgent' => $urgent->control_number, 'affected' => $affected->control_number, 'venue' => $venue, 'affected_details' => $conflict->previous_details],
            ]);
            $this->notifyDecision($affected, 'reservation_affected', $conflict->id, [], true);
        }

        return $created;
    }

    /**
     * Apply a Supply Office decision to $request.
     *
     * @param  array{venue?: string, start_date?: string, reason: string, explanation: string}  $input
     */
    public function resolve(FacilityRequest $request, string $action, array $input, User $admin): string
    {
        return DB::transaction(function () use ($request, $action, $input, $admin): string {
            $request = FacilityRequest::query()->lockForUpdate()->findOrFail($request->id);
            $before = $this->snapshot($request);
            $reason = trim($input['reason']);
            $explanation = trim($input['explanation']);
            $oldCustodians = $this->custodiansOf($request);

            if ($action === 'alternative_venue') {
                $from = $this->conflictVenue($request) ?? ($request->getVenueNames()[0] ?? null);
                $to = (string) ($input['venue'] ?? '');
                if ($from === null || ! in_array($to, $this->alternativeVenues($request, $from), true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['venue' => 'That venue is not available or not suitable for this reservation. Choose one from the list.']);
                }
                $this->replaceVenue($request, $from, $to);
                $summary = "Venue changed from {$from} to {$to}.";
                $history = 'alternative_venue_assigned';
            } elseif ($action === 'reschedule') {
                $date = (string) ($input['start_date'] ?? '');
                $allowed = collect($this->feasibleSchedules($request, 50))->pluck('start_date')->all();
                if (! in_array($date, $allowed, true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['start_date' => 'That date is not available for this reservation. Choose one from the list.']);
                }
                $shift = (int) $request->start_date->copy()->startOfDay()->diffInDays(Carbon::parse($date)->startOfDay(), false);
                $this->shiftSchedule($request, $shift);
                $summary = 'Schedule moved to ' . Carbon::parse($date)->format('M j, Y') . ' (same times).';
                $history = 'reservation_rescheduled';
            } elseif ($action === 'decline') {
                $request->update(['status' => 'rejected', 'approved_by_id' => null, 'approved_by' => null, 'approved_date' => null]);
                $summary = 'Reservation declined.';
                $history = 'final_rejected';
            } elseif ($action === 'revision') {
                $request->update(['status' => 'needs_reschedule']);
                $summary = 'Revision requested from the requestor.';
                $history = 'revision_requested';
            } else {
                throw new \InvalidArgumentException('Unknown decision.');
            }

            $request->refresh();
            $after = $this->snapshot($request);
            $fullMessage = trim($summary . ' Reason: ' . $reason . '. ' . $explanation);
            $request->addHistory($history, 'Supply Office decision by ' . $admin->name . ': ' . $fullMessage, $admin->id);

            AuditLog::create([
                'actor_id' => $admin->id,
                'action' => 'supply_office_' . $action,
                'details' => "{$request->control_number}: {$summary}",
                'old_values' => $before,
                'new_values' => $after + ['reason' => $reason, 'explanation' => $explanation],
            ]);

            $this->closeConflicts($request, $action, $reason, $explanation, $after, $admin);

            $kind = match ($action) {
                'alternative_venue' => 'alternative_venue_assigned',
                'reschedule' => 'reservation_rescheduled',
                'decline' => 'reservation_declined',
                'revision' => 'revision_requested',
            };
            $key = $request->id . ':' . $kind . ':' . md5(json_encode($after));
            DB::afterCommit(function () use ($request, $kind, $key, $oldCustodians): void {
                $this->notifyDecision($request->fresh(), $kind, $key, $oldCustodians);
            });

            return $summary;
        });
    }

    private function closeConflicts(FacilityRequest $request, string $action, string $reason, string $explanation, array $after, User $admin): void
    {
        foreach ($this->openConflicts($request) as $conflict) {
            $other = $conflict->otherSide($request)?->fresh();
            $stillOverlaps = $action !== 'decline' && $action !== 'revision' && $other && $other->status === 'approved'
                && $this->overlaps($request->fresh(), $other);
            if ($stillOverlaps) {
                continue;
            }
            $conflict->update([
                'status' => 'resolved',
                'resolution' => match ($action) {
                    'alternative_venue' => 'alternative_venue',
                    'reschedule' => 'rescheduled',
                    'decline' => 'declined',
                    default => 'revision_requested',
                },
                'resolved_request_id' => $request->id,
                'reason' => $reason,
                'explanation' => $explanation,
                'new_details' => $after,
                'resolved_by_id' => $admin->id,
                'resolved_at' => now(),
            ]);
        }
    }

    private function overlaps(FacilityRequest $a, FacilityRequest $b): bool
    {
        foreach ($a->getVenueNames() as $venueName) {
            if (! $b->booksVenue($venueName)) {
                continue;
            }
            foreach ($a->getVenueScheduleRanges($venueName) as $range) {
                if ($b->overlapsVenueScheduleRange($venueName, $range['start'], $range['end'])) {
                    return true;
                }
            }
        }

        return false;
    }

    private function replaceVenue(FacilityRequest $request, string $from, string $to): void
    {
        $names = array_map(fn ($n) => mb_strtolower(trim($n)) === mb_strtolower(trim($from)) ? $to : $n, $request->getVenueNames());
        $schedules = [];
        foreach ($request->getVenueNames() as $name) {
            $schedule = $request->getVenueSchedule($name);
            $schedules[mb_strtolower(trim($name)) === mb_strtolower(trim($from)) ? $to : $name] = $schedule;
        }
        $allocations = [];
        foreach ($request->getVenueEquipmentAllocations() as $venueName => $items) {
            $allocations[mb_strtolower(trim($venueName)) === mb_strtolower(trim($from)) ? $to : $venueName] = $items;
        }
        $updates = ['venue' => $names];
        if ($request->status === 'pending') {
            // A pending request goes back to the new venue's custodian and the Supply Office.
            $updates += ['venue_status' => 'pending', 'supply_office_review_status' => 'pending'];
        }
        $request->update($updates);
        $request->syncRelationalItems($schedules, $allocations === [] ? null : $allocations);
    }

    private function shiftSchedule(FacilityRequest $request, int $days): void
    {
        $schedules = [];
        foreach ($request->getVenueNames() as $name) {
            $schedule = $request->getVenueSchedule($name);
            foreach (['start_date', 'end_date'] as $field) {
                if (! empty($schedule[$field])) {
                    $schedule[$field] = Carbon::parse($schedule[$field])->addDays($days)->toDateString();
                }
            }
            $schedules[$name] = $schedule;
        }
        $updates = [
            'start_date' => $request->start_date->copy()->addDays($days)->toDateString(),
            'end_date' => ($request->end_date ?? $request->start_date)->copy()->addDays($days)->toDateString(),
        ];
        if ($request->status === 'pending') {
            $updates += ['venue_status' => 'pending', 'supply_office_review_status' => 'pending'];
        }
        $request->update($updates);
        $request->syncRelationalItems($schedules);
    }

    /** @return array<string, mixed> */
    public function snapshot(FacilityRequest $request): array
    {
        return [
            'control_number' => $request->control_number,
            'status' => $request->status,
            'venues' => collect($request->getVenueNames())->map(fn ($name) => ['venue' => $name] + $request->getVenueSchedule($name))->values()->all(),
        ];
    }

    /** @return Collection<int, User> */
    private function custodiansOf(FacilityRequest $request): Collection
    {
        $venueIds = Venue::query()->whereIn('name', $request->getVenueNames())->pluck('custodian_id');
        $equipmentIds = collect($request->getAssignedEquipmentCustodianIds());

        return User::query()->whereIn('id', $venueIds->merge($equipmentIds)->filter()->unique())->get();
    }

    /**
     * Short notice to the requestor (and the custodians whose work changes). The
     * full explanation is on the request page. Sent once per decision.
     *
     * @param  Collection<int, User>|array  $previousCustodians
     */
    public function notifyDecision(FacilityRequest $request, string $kind, string|int $key, Collection|array $previousCustodians = [], bool $requestorOnlyAndCustodians = false): void
    {
        try {
            $decisionKey = $kind . ':' . $key;
            $recipients = collect([$request->requester])->filter();
            $custodians = collect($previousCustodians)->merge($this->custodiansOf($request))->filter()->unique('id');
            foreach ($recipients as $user) {
                $this->sendOnce($user, new ReservationDecision($request, $kind, $decisionKey, 'requestor'));
            }
            foreach ($custodians as $user) {
                $this->sendOnce($user, new ReservationDecision($request, $kind, $decisionKey, 'custodian'));
            }
        } catch (\Throwable $e) {
            Log::warning('Reservation decision notification failed.', ['request_id' => $request->id, 'kind' => $kind, 'error' => $e->getMessage()]);
        }
    }

    private function sendOnce(User $user, ReservationDecision $notification): void
    {
        $exists = $user->notifications()
            ->where('type', ReservationDecision::class)
            ->get()
            ->contains(fn ($n) => ($n->data['decision_key'] ?? null) === $notification->decisionKey);
        if (! $exists) {
            $user->notify($notification);
        }
    }
}
