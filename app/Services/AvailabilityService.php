<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\Holiday;
use App\Models\MaintenanceSchedule;
use App\Models\RevisionHistory;
use App\Models\Venue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AvailabilityService
{
    public function lockResourcesForFacilityRequests(FacilityRequest ...$facilityRequests): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Reservation resources must be locked inside a database transaction.');
        }

        $resources = collect($facilityRequests)
            ->flatMap(function (FacilityRequest $request): array {
                $venues = collect($request->getVenueNames())
                    ->map(fn (string $name): array => ['venue', mb_strtolower(trim($name))]);
                $equipment = collect(array_keys($request->getEquipmentQuantities()))
                    ->map(fn (string $name): array => ['equipment', mb_strtolower(trim($name))]);

                return $venues->concat($equipment)->all();
            })
            ->filter(fn (array $resource): bool => $resource[1] !== '')
            ->unique(fn (array $resource): string => $resource[0] . ':' . $resource[1])
            ->sortBy(fn (array $resource): string => $resource[0] . ':' . $resource[1])
            ->values();

        if ($resources->isEmpty()) {
            return;
        }

        $lockRows = $resources
            ->map(fn (array $resource): array => [
                'resource_type' => $resource[0],
                'resource_hash' => hash('sha256', $resource[1]),
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        DB::table('reservation_resource_locks')->insertOrIgnore($lockRows);

        DB::table('reservation_resource_locks')
            ->where(function ($query) use ($lockRows): void {
                foreach ($lockRows as $lockRow) {
                    $query->orWhere(function ($resourceQuery) use ($lockRow): void {
                        $resourceQuery
                            ->where('resource_type', $lockRow['resource_type'])
                            ->where('resource_hash', $lockRow['resource_hash']);
                    });
                }
            })
            ->orderBy('resource_type')
            ->orderBy('resource_hash')
            ->lockForUpdate()
            ->get(['id']);
    }

    public function checkFacilityRequest(
        FacilityRequest $facilityRequest,
        ?int $excludeRequestId = null,
        bool $ignoreVenueConflicts = false
    ): ?string
    {
        // REQ-13: a deactivated venue or item is never bookable, urgent or not.
        $inactiveMessage = $this->inactiveResourceMessage($facilityRequest);
        if ($inactiveMessage !== null) {
            return $inactiveMessage;
        }

        $requestedStart = $facilityRequest->getRequestedStartDateTime();
        $requestedEnd = $facilityRequest->getRequestedEndDateTime();

        if (! $ignoreVenueConflicts) {
            foreach ($facilityRequest->getVenueNames() as $venueName) {
                foreach ($facilityRequest->getVenueScheduleRanges($venueName) as $venueRange) {
                    $availability = $this->checkVenueAvailability(
                        $venueName,
                        $venueRange['start'],
                        $venueRange['end'],
                        $excludeRequestId
                    );
                    if (!$availability['available']) {
                        return $availability['message'] ?? 'Venue booking conflict detected.';
                    }
                }
            }
        }

        $venueEquipment = $facilityRequest->getVenueEquipmentAllocations();
        if ($venueEquipment !== []) {
            // Total the need per equipment record (by ID), across every venue that uses it.
            $equipmentRanges = [];
            $equipmentNames = [];
            foreach ($venueEquipment as $venueName => $items) {
                if (! is_array($items) || ! in_array($venueName, $facilityRequest->getVenueNames(), true)) {
                    continue;
                }
                foreach ($items as $itemName => $quantity) {
                    $equipmentRecord = Equipment::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $itemName))])->first();
                    if (! $equipmentRecord) {
                        continue;
                    }
                    $equipmentNames[$equipmentRecord->id] = $equipmentRecord->name;
                    foreach ($facilityRequest->getVenueScheduleRanges($venueName) as $venueRange) {
                        $equipmentRanges[$equipmentRecord->id][] = [
                            ...$venueRange,
                            'quantity' => (int) $quantity,
                        ];
                    }
                }
            }

            foreach ($equipmentRanges as $equipmentId => $ranges) {
                $itemName = $equipmentNames[$equipmentId];

                $boundaries = collect($ranges)
                    ->flatMap(fn (array $range): array => [$range['start'], $range['end']])
                    ->unique(fn (Carbon $time): string => $time->toIso8601String())
                    ->sortBy(fn (Carbon $time): int => $time->getTimestamp())
                    ->values();

                for ($index = 0; $index < $boundaries->count() - 1; $index++) {
                    $segmentStart = $boundaries[$index];
                    $segmentEnd = $boundaries[$index + 1];
                    if ($segmentStart->equalTo($segmentEnd)) {
                        continue;
                    }

                    $requestedQuantity = collect($ranges)
                        ->filter(fn (array $range): bool => $range['start']->lt($segmentEnd) && $range['end']->gt($segmentStart))
                        ->sum('quantity');
                    if ($requestedQuantity === 0) {
                        continue;
                    }

                    $availability = $this->checkEquipmentAvailability(
                        $itemName,
                        (int) $requestedQuantity,
                        $segmentStart,
                        $segmentEnd,
                        $excludeRequestId
                    );
                    if (! $availability['available']) {
                        return $availability['message'] ?? 'Requested equipment is not available.';
                    }
                }
            }

            return null;
        }

        $equipmentRanges = collect($facilityRequest->getVenueNames())
            ->flatMap(fn (string $venueName): array => $facilityRequest->getVenueScheduleRanges($venueName))
            ->whenEmpty(fn (Collection $ranges): Collection => collect($facilityRequest->getScheduleRanges()));

        foreach ($facilityRequest->getEquipmentQuantities() as $itemName => $quantity) {
            foreach ($equipmentRanges as $range) {
                $availability = $this->checkEquipmentAvailability(
                    $itemName,
                    (int) $quantity,
                    $range['start'],
                    $range['end'],
                    $excludeRequestId
                );
                if (!$availability['available']) {
                    return $availability['message'] ?? 'Requested equipment is not available.';
                }
            }
        }

        return null;
    }

    /**
     * REQ-13: the first venue or equipment item of this request that has been
     * deactivated, checked by stable ID where the request has one and by name
     * otherwise. Null when everything is still active.
     */
    public function inactiveResourceMessage(FacilityRequest $facilityRequest): ?string
    {
        $venueNames = array_values(array_filter(array_map('strval', $facilityRequest->getVenueNames())));
        $venueIds = [];
        $equipmentIds = [];
        if ($facilityRequest->exists) {
            $venueIds = $facilityRequest->requestVenues()->whereNotNull('venue_id')->pluck('venue_id')->all();
            $equipmentIds = $facilityRequest->requestEquipment()->whereNotNull('equipment_id')->pluck('equipment_id')->all();
        }

        $lowerVenueNames = [];
        foreach ($venueNames as $name) {
            $lower = mb_strtolower(trim($name));
            $lowerVenueNames[] = $lower;
            if ($lower === 'balay alumni') {
                $lowerVenueNames[] = 'balay alumni hall';
            }
        }
        $inactiveVenue = Venue::query()
            ->where('is_active', false)
            ->where(function ($query) use ($lowerVenueNames, $venueIds): void {
                $query->whereRaw('1 = 0');
                if ($lowerVenueNames !== []) {
                    $query->orWhereIn(DB::raw('LOWER(TRIM(name))'), $lowerVenueNames);
                }
                if ($venueIds !== []) {
                    $query->orWhereIn('id', $venueIds);
                }
            })
            ->first();
        if ($inactiveVenue) {
            return "{$inactiveVenue->name} is no longer available for booking. Choose another venue.";
        }

        $equipmentNames = array_keys($facilityRequest->getEquipmentQuantities());
        foreach ($facilityRequest->getVenueEquipmentAllocations() as $items) {
            if (is_array($items)) {
                $equipmentNames = array_merge($equipmentNames, array_keys($items));
            }
        }
        $lowerEquipmentNames = array_values(array_unique(array_map(
            static fn ($name): string => mb_strtolower(trim((string) $name)),
            $equipmentNames
        )));
        $inactiveEquipment = Equipment::query()
            ->where('is_active', false)
            ->where(function ($query) use ($lowerEquipmentNames, $equipmentIds): void {
                $query->whereRaw('1 = 0');
                if ($lowerEquipmentNames !== []) {
                    $query->orWhereIn(DB::raw('LOWER(TRIM(name))'), $lowerEquipmentNames);
                }
                if ($equipmentIds !== []) {
                    $query->orWhereIn('id', $equipmentIds);
                }
            })
            ->first();
        if ($inactiveEquipment) {
            return "{$inactiveEquipment->name} is no longer available for booking. Remove it from the request.";
        }

        return null;
    }

    public function checkVenueScheduleForReview(FacilityRequest $facilityRequest, string $venueName): ?string
    {
        $canonicalVenueName = collect($facilityRequest->getVenueNames())
            ->first(fn (string $name): bool => mb_strtolower(trim($name)) === mb_strtolower(trim($venueName)));

        if ($canonicalVenueName === null) {
            return 'The selected venue is not part of this request.';
        }

        $ranges = $facilityRequest->getVenueScheduleRanges($canonicalVenueName);
        foreach ($ranges as $range) {
            $availability = $this->checkVenueAvailability(
                $canonicalVenueName,
                $range['start'],
                $range['end'],
                $facilityRequest->id
            );
            if (! $availability['available']) {
                return $availability['message'] ?? 'The selected venue schedule is unavailable.';
            }
        }

        $pendingConflict = FacilityRequest::query()
            ->where('status', 'pending')
            ->whereKeyNot($facilityRequest->id)
            ->where(fn ($query) => $query->matchesVenue($canonicalVenueName))
            ->with(['requestVenues', 'reservationSchedule'])
            ->get()
            ->contains(function (FacilityRequest $otherRequest) use ($canonicalVenueName, $ranges): bool {
                foreach ($otherRequest->getVenueScheduleRanges($canonicalVenueName) as $otherRange) {
                    foreach ($ranges as $range) {
                        if ($otherRange['start']->lt($range['end']) && $otherRange['end']->gt($range['start'])) {
                            return true;
                        }
                    }
                }

                return false;
            });

        return $pendingConflict
            ? 'Another request for this venue and time is still awaiting review. Resolve that request before confirming availability.'
            : null;
    }

    public function checkRevisionVenueAvailability(FacilityRequest $facilityRequest, RevisionHistory $revision, string $venueName): ?string
    {
        $canonicalVenueName = collect($revision->new_venue ?? [])
            ->first(fn (string $name): bool => mb_strtolower(trim($name)) === mb_strtolower(trim($venueName)));

        if ($canonicalVenueName === null) {
            return 'The selected venue is not part of the proposed schedule.';
        }

        $range = FacilityRequest::normalizeScheduleRange(
            $revision->new_start_date,
            $revision->new_start_time,
            $revision->new_end_date,
            $revision->new_end_time,
        );
        return $this->checkVenueTimeRangeForReview(
            $canonicalVenueName,
            $range['start'],
            $range['end'],
            $facilityRequest->id,
        );
    }

    public function checkVenueTimeRangeForReview(
        string $venueName,
        Carbon $requestedStart,
        Carbon $requestedEnd,
        ?int $excludeRequestId = null,
    ): ?string {
        $availability = $this->checkVenueAvailability($venueName, $requestedStart, $requestedEnd, $excludeRequestId);
        if (! $availability['available']) {
            return $availability['message'] ?? 'The proposed venue schedule is unavailable.';
        }
        $requestedRanges = FacilityRequest::dailyRanges($requestedStart, $requestedEnd);

        $pendingConflict = FacilityRequest::query()
            ->where('status', 'pending')
            ->when($excludeRequestId !== null, fn ($query) => $query->whereKeyNot($excludeRequestId))
            ->where(fn ($query) => $query->matchesVenue($venueName))
            ->with(['requestVenues', 'reservationSchedule'])
            ->get()
            ->contains(fn (FacilityRequest $otherRequest): bool => collect($requestedRanges)->contains(
                fn (array $range): bool => $otherRequest->overlapsVenueScheduleRange($venueName, $range['start'], $range['end'])
            ));

        return $pendingConflict
            ? 'Another request for this venue and time is still awaiting review. Resolve that request before confirming availability.'
            : null;
    }

    public function checkRevisionAvailabilityForCustodian(
        FacilityRequest $facilityRequest,
        RevisionHistory $revision,
        User $custodian
    ): ?string {
        $venueNames = $revision->new_venue ?? [];
        if ($custodian->isCustodianVenue()) {
            $assignedVenues = $custodian->venues()
                ->whereIn('name', $venueNames)
                ->pluck('name')
                ->all();

            foreach ($assignedVenues as $venueName) {
                $conflict = $this->checkRevisionVenueAvailability($facilityRequest, $revision, $venueName);
                if ($conflict !== null) {
                    return $conflict;
                }
            }
        }

        if ($custodian->isCustodianEquipment()) {
            $equipmentNames = $revision->new_equipment ?? [];
            $assignedEquipment = $custodian->equipmentItems()
                ->whereIn('name', $equipmentNames)
                ->pluck('name')
                ->all();
            $range = FacilityRequest::normalizeScheduleRange(
                $revision->new_start_date,
                $revision->new_start_time,
                $revision->new_end_date,
                $revision->new_end_time,
            );
            $quantities = $revision->new_equipment_quantities ?? [];

            foreach ($assignedEquipment as $equipmentName) {
                $availability = $this->checkEquipmentAvailability(
                    $equipmentName,
                    (int) ($quantities[$equipmentName] ?? 1),
                    $range['start'],
                    $range['end'],
                    $facilityRequest->id,
                );
                if (! $availability['available']) {
                    return $availability['message'] ?? 'The proposed equipment schedule is unavailable.';
                }
            }
        }

        return null;
    }

    public function checkFacilityRequestForReview(FacilityRequest $facilityRequest): ?string
    {
        $conflict = $this->checkFacilityRequest($facilityRequest, $facilityRequest->id);
        if ($conflict !== null) {
            return $conflict;
        }

        foreach ($facilityRequest->getVenueNames() as $venueName) {
            $conflict = $this->checkVenueScheduleForReview($facilityRequest, $venueName);
            if ($conflict !== null) {
                return $conflict;
            }
        }

        return null;
    }

    public function getApprovedVenueConflicts(FacilityRequest $facilityRequest, ?string $venueName = null): Collection
    {
        $venues = $venueName !== null ? [$venueName] : $facilityRequest->getVenueNames();
        $requestedRanges = [];
        foreach ($venues as $name) {
            $requestedRanges[mb_strtolower(trim($name))] = $facilityRequest->getVenueScheduleRanges($name);
        }

        return FacilityRequest::query()
            ->where('status', 'approved')
            ->whereKeyNot($facilityRequest->id)
            ->with(['requestVenues', 'reservationSchedule'])
            ->get()
            ->filter(function (FacilityRequest $request) use ($requestedRanges): bool {
                foreach ($requestedRanges as $normalizedName => $ranges) {
                    $name = $normalizedName;
                    if (! collect($request->getVenueNames())
                        ->contains(fn (string $requestVenue): bool => mb_strtolower(trim($requestVenue)) === $normalizedName)) {
                        continue;
                    }

                    foreach ($ranges as $range) {
                        if ($request->overlapsVenueScheduleRange($name, $range['start'], $range['end'])) {
                            return true;
                        }
                    }
                }

                return false;
            })
            ->values();
    }

    public function getVenueCapacity(string $venueName): ?int
    {
        $venue = Venue::where('name', $venueName)->first();

        if ($venue && $venue->capacity !== null) {
            return (int) $venue->capacity;
        }

        return null;
    }

    public function checkEquipmentAvailability(string $itemName, int $quantity, ?Carbon $requestedStart = null, ?Carbon $requestedEnd = null, ?int $excludeRequestId = null): array
    {
        $equipment = Equipment::whereRaw('LOWER(name) = ?', [strtolower($itemName)])->first();

        if (!$equipment) {
            return ['available' => false, 'message' => "Equipment '{$itemName}' not found.", 'total' => 0];
        }

        if (! $equipment->is_active) {
            return ['available' => false, 'message' => "Equipment '{$itemName}' is currently unavailable.", 'total' => (int) $equipment->quantity];
        }

        $requestedStart = $requestedStart ?? now();
        $requestedEnd = $requestedEnd ?? $requestedStart->copy()->addHour();

        // REQ-16: a schedule over several dates books the same hours each day (or each
        // night); the busiest of those days decides.
        $dailyRanges = FacilityRequest::dailyRanges($requestedStart, $requestedEnd);
        if (count($dailyRanges) > 1) {
            $busiest = null;
            foreach ($dailyRanges as $range) {
                $result = $this->checkEquipmentAvailability($itemName, $quantity, $range['start'], $range['end'], $excludeRequestId);
                if ($busiest === null || ($result['available_qty'] ?? 0) < ($busiest['available_qty'] ?? 0)) {
                    $busiest = $result;
                }
            }

            return $busiest;
        }

        $outstanding = $this->getOutstandingEquipmentQuantity($itemName, $requestedStart, $requestedEnd, $excludeRequestId);
        $available = max(0, (int) $equipment->quantity - $outstanding);

        return [
            'available' => $available >= $quantity,
            'message' => $available >= $quantity ? null : "Sorry, only {$available} unit(s) of '{$itemName}' available for the selected window.",
            'available_qty' => $available,
            'total' => (int) $equipment->quantity,
        ];
    }

    public function checkVenueAvailability(string $venueName, Carbon $requestedStart, Carbon $requestedEnd, ?int $excludeRequestId = null): array
    {
        // REQ-16: a schedule over several dates books the same hours each day (or each
        // night); every one of those days must be free.
        $dailyRanges = FacilityRequest::dailyRanges($requestedStart, $requestedEnd);
        if (count($dailyRanges) > 1) {
            $result = null;
            foreach ($dailyRanges as $range) {
                $result = $this->checkVenueAvailability($venueName, $range['start'], $range['end'], $excludeRequestId);
                if (! $result['available']) {
                    return $result;
                }
            }

            return $result;
        }

        $venue = Venue::where('name', $venueName)->first();
        $venueRecord = $venue ?: null;
        $capacity = $this->getVenueCapacity($venueName);

        if ($venueRecord && (! $venueRecord->is_active || ($venueRecord->capacity !== null && $venueRecord->capacity <= 0))) {
            return ['available' => false, 'message' => 'The selected venue is unavailable.', 'capacity' => $venueRecord->capacity];
        }

        $holdsEnabled = \Illuminate\Support\Facades\Schema::hasColumn('facility_requests', 'outsider_hold_until');
        $requests = FacilityRequest::query()
            ->where(function ($statusQuery) use ($holdsEnabled): void {
                $statusQuery->where('status', 'approved');
                // B.7: an approved outsider inquiry awaiting its requirements holds the schedule
                // until the Supply Office deadline.
                if ($holdsEnabled) {
                    $statusQuery->orWhere(function ($holdQuery): void {
                        $holdQuery->where('status', 'pending')
                            ->whereIn('outsider_stage', FacilityRequest::OUTSIDER_HOLD_STAGES)
                            ->where('outsider_hold_until', '>=', now());
                    });
                }
            })
            ->when($excludeRequestId !== null, fn ($query) => $query->whereKeyNot($excludeRequestId))
            ->where(function ($query) use ($requestedStart, $requestedEnd): void {
                $query->whereHas('reservationSchedule', function ($scheduleQuery) use ($requestedStart, $requestedEnd): void {
                    $scheduleQuery->where('start_datetime', '<', $requestedEnd)
                        ->where('end_datetime', '>', $requestedStart);
                })->orWhere(function ($legacyQuery) use ($requestedStart, $requestedEnd): void {
                    $legacyQuery->whereDoesntHave('reservationSchedule')
                        ->whereDate('start_date', '<=', $requestedEnd->toDateString())
                        ->whereDate('end_date', '>=', $requestedStart->toDateString());
                });
            })
            ->with(['requestVenues', 'reservationSchedule'])
            ->get();

        $conflicts = $requests->contains(function (FacilityRequest $request) use ($venueName, $requestedStart, $requestedEnd): bool {
                if ($request->status !== 'approved' && ! $request->hasActiveOutsiderHold()) {
                    return false;
                }
                $venueNames = array_map('strtolower', $request->getVenueNames());
                $matchesVenue = collect($venueNames)->contains(function (string $name) use ($venueName): bool {
                    return trim($name) === strtolower(trim($venueName));
                });

                if (! $matchesVenue) {
                    return false;
                }

                return $request->overlapsVenueScheduleRange($venueName, $requestedStart, $requestedEnd);
            });

        $maintenanceConflict = MaintenanceSchedule::where(function ($query) use ($venueName, $venueRecord) {
            $query->where('venue_id', optional($venueRecord)->id)
                ->orWhereNull('venue_id');
        })
            ->where('status', 'active')
            ->where('start_datetime', '<', $requestedEnd)
            ->where('end_datetime', '>', $requestedStart)
            ->exists();

        // Check if any holiday exists anywhere in the requested date range (inclusive)
        $isHoliday = Holiday::whereDate('holiday_date', '>=', $requestedStart->toDateString())
            ->whereDate('holiday_date', '<=', $requestedEnd->toDateString())
            ->exists();

        return [
            'available' => !$conflicts && !$maintenanceConflict && !$isHoliday,
            'message' => $this->buildVenueMessage($conflicts, $maintenanceConflict, $isHoliday),
            'capacity' => $capacity,
        ];
    }

    public function getOutstandingEquipmentQuantity(string $itemName, Carbon $requestedStart, Carbon $requestedEnd, ?int $excludeRequestId = null): int
    {
        $requests = $this->getApprovedRequestsOverlapping($requestedStart, $requestedEnd, $excludeRequestId);

        $outstanding = 0;
        foreach ($requests as $request) {
            if ($request->status !== 'approved') {
                continue;
            }

            if (! $this->requestHasScheduleOverlap($request, $requestedStart, $requestedEnd)) {
                continue;
            }

            $quantities = $request->getInventoryOutstandingQuantities();
            if (isset($quantities[$itemName])) {
                $outstanding += (int) $quantities[$itemName];
            }
        }

        return $outstanding;
    }

    private function getApprovedRequestsOverlapping(Carbon $requestedStart, Carbon $requestedEnd, ?int $excludeRequestId): Collection
    {
        return FacilityRequest::query()
            ->where('status', 'approved')
            ->when($excludeRequestId !== null, fn ($query) => $query->whereKeyNot($excludeRequestId))
            ->where(function ($query) use ($requestedStart, $requestedEnd): void {
                $query->whereHas('reservationSchedule', function ($scheduleQuery) use ($requestedStart, $requestedEnd): void {
                    $scheduleQuery
                        ->where('start_datetime', '<', $requestedEnd)
                        ->where('end_datetime', '>', $requestedStart);
                })->orWhere(function ($legacyQuery) use ($requestedStart, $requestedEnd): void {
                    $legacyQuery->whereDoesntHave('reservationSchedule')
                        ->whereDate('start_date', '<=', $requestedEnd->toDateString())
                        ->where(function ($endDateQuery) use ($requestedStart): void {
                            $endDateQuery->whereDate('end_date', '>=', $requestedStart->toDateString())
                                ->orWhere(function ($singleDateQuery) use ($requestedStart): void {
                                    $singleDateQuery->whereNull('end_date')
                                        ->whereDate('start_date', '>=', $requestedStart->toDateString());
                                });
                        });
                });
            })
            ->with(['requestVenues', 'requestEquipment', 'reservationSchedule'])
            ->get();
    }

    private function requestHasScheduleOverlap(FacilityRequest $request, Carbon $requestedStart, Carbon $requestedEnd): bool
    {
        $venueNames = $request->getVenueNames();
        if ($venueNames === []) {
            return $request->overlapsTimeRange($requestedStart, $requestedEnd);
        }

        foreach ($venueNames as $venueName) {
            if ($request->overlapsVenueScheduleRange($venueName, $requestedStart, $requestedEnd)) {
                return true;
            }
        }

        return false;
    }

    private function buildVenueMessage(bool $conflicts, bool $maintenance, bool $holiday): ?string
    {
        if ($conflicts) {
            return 'The selected venue conflicts with an existing reservation.';
        }

        if ($maintenance) {
            return 'The selected venue is under maintenance for the requested period.';
        }

        if ($holiday) {
            return 'The selected venue is unavailable on the requested holiday.';
        }

        return null;
    }
}
