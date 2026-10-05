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
                $availability = $this->checkVenueAvailability($venueName, $requestedStart, $requestedEnd, $excludeRequestId);
                if (!$availability['available']) {
                    return $availability['message'] ?? 'Venue booking conflict detected.';
                }
            }
        }

        foreach ($facilityRequest->getEquipmentQuantities() as $itemName => $quantity) {
            $availability = $this->checkEquipmentAvailability(
                $itemName,
                (int) $quantity,
                $requestedStart,
                $requestedEnd,
                $excludeRequestId
            );
            if (!$availability['available']) {
                return $availability['message'] ?? 'Requested equipment is not available.';
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

        $requests = $this->getApprovedRequestsOverlapping($requestedStart, $requestedEnd, $excludeRequestId);

        $conflicts = $requests->contains(function (FacilityRequest $request) use ($venueName, $requestedStart, $requestedEnd): bool {
                if ($request->status !== 'approved') {
                    return false;
                }
                $venueNames = array_map('strtolower', $request->getVenueNames());
                $matchesVenue = collect($venueNames)->contains(function (string $name) use ($venueName): bool {
                    return trim($name) === strtolower(trim($venueName));
                });

                return $matchesVenue && $request->overlapsTimeRange($requestedStart, $requestedEnd);
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

            if (!$request->overlapsTimeRange($requestedStart, $requestedEnd)) {
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
