<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\Holiday;
use App\Models\MaintenanceSchedule;
use App\Models\Venue;
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
        $requestedStart = $facilityRequest->getRequestedStartDateTime();
        $requestedEnd = $facilityRequest->getRequestedEndDateTime();

        if (! $ignoreVenueConflicts) {
            foreach ($facilityRequest->getVenueNames() as $venueName) {
                $venueRange = $facilityRequest->getVenueScheduleRange($venueName);
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

        $venueEquipment = $facilityRequest->getVenueEquipmentAllocations();
        if ($venueEquipment !== []) {
            $equipmentRanges = [];
            foreach ($venueEquipment as $venueName => $items) {
                if (! is_array($items) || ! in_array($venueName, $facilityRequest->getVenueNames(), true)) {
                    continue;
                }
                $venueRange = $facilityRequest->getVenueScheduleRange($venueName);
                foreach ($items as $itemName => $quantity) {
                    $equipmentRanges[$itemName][] = [
                        ...$venueRange,
                        'quantity' => (int) $quantity,
                    ];
                }
            }

            foreach ($equipmentRanges as $itemName => $ranges) {
                if (! Equipment::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($itemName)])->exists()) {
                    continue;
                }

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
            ->map(fn (string $venueName): array => $facilityRequest->getVenueScheduleRange($venueName))
            ->whenEmpty(fn (Collection $ranges): Collection => collect([[
                'start' => $requestedStart,
                'end' => $requestedEnd,
            ]]));

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

    public function checkVenueScheduleForReview(FacilityRequest $facilityRequest, string $venueName): ?string
    {
        $canonicalVenueName = collect($facilityRequest->getVenueNames())
            ->first(fn (string $name): bool => mb_strtolower(trim($name)) === mb_strtolower(trim($venueName)));

        if ($canonicalVenueName === null) {
            return 'The selected venue is not part of this request.';
        }

        $range = $facilityRequest->getVenueScheduleRange($canonicalVenueName);
        $availability = $this->checkVenueAvailability(
            $canonicalVenueName,
            $range['start'],
            $range['end'],
            $facilityRequest->id
        );
        if (! $availability['available']) {
            return $availability['message'] ?? 'The selected venue schedule is unavailable.';
        }

        $pendingConflict = FacilityRequest::query()
            ->where('status', 'pending')
            ->whereKeyNot($facilityRequest->id)
            ->where(fn ($query) => $query->matchesVenue($canonicalVenueName))
            ->with(['requestVenues', 'reservationSchedule'])
            ->get()
            ->contains(function (FacilityRequest $otherRequest) use ($canonicalVenueName, $range): bool {
                $otherRange = $otherRequest->getVenueScheduleRange($canonicalVenueName);

                return $otherRange['start']->lt($range['end'])
                    && $otherRange['end']->gt($range['start']);
            });

        return $pendingConflict
            ? 'Another request for this venue and time is still awaiting review. Resolve that request before confirming availability.'
            : null;
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
        $venue = Venue::where('name', $venueName)->first();
        $venueRecord = $venue ?: null;
        $capacity = $this->getVenueCapacity($venueName);

        if ($venueRecord && (! $venueRecord->is_active || ($venueRecord->capacity !== null && $venueRecord->capacity <= 0))) {
            return ['available' => false, 'message' => 'The selected venue is unavailable.', 'capacity' => $venueRecord->capacity];
        }

        $requests = FacilityRequest::query()
            ->where('status', 'approved')
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
                if ($request->status !== 'approved') {
                    return false;
                }
                $venueNames = array_map('strtolower', $request->getVenueNames());
                $matchesVenue = collect($venueNames)->contains(function (string $name) use ($venueName): bool {
                    return trim($name) === strtolower(trim($venueName));
                });

                if (! $matchesVenue) {
                    return false;
                }

                $range = $request->getVenueScheduleRange($venueName);

                return $range['start']->lt($requestedEnd) && $range['end']->gt($requestedStart);
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
            $range = $request->getVenueScheduleRange($venueName);
            if ($range['start']->lt($requestedEnd) && $range['end']->gt($requestedStart)) {
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
