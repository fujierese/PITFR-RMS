<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\Holiday;
use App\Models\MaintenanceSchedule;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{
    public function checkFacilityRequest(FacilityRequest $facilityRequest, ?int $excludeRequestId = null): ?string
    {
        $requestedStart = $facilityRequest->getRequestedStartDateTime();
        $requestedEnd = $facilityRequest->getRequestedEndDateTime();

        foreach ($facilityRequest->getVenueNames() as $venueName) {
            $availability = $this->checkVenueAvailability($venueName, $requestedStart, $requestedEnd, $excludeRequestId);
            if (!$availability['available']) {
                return $availability['message'] ?? 'Venue booking conflict detected.';
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

        $requests = FacilityRequest::with(['requestVenues', 'reservationSchedule'])
            ->when($excludeRequestId !== null, fn ($query) => $query->whereKeyNot($excludeRequestId))
            ->get();

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
        $requests = FacilityRequest::with(['requestEquipment', 'reservationSchedule'])
            ->when($excludeRequestId !== null, fn ($query) => $query->whereKeyNot($excludeRequestId))
            ->get();

        $outstanding = 0;
        foreach ($requests as $request) {
            if ($request->status !== 'approved' || in_array($request->equipment_returned_status, ['returned', 'fulfilled'], true)) {
                continue;
            }

            if (!$request->overlapsTimeRange($requestedStart, $requestedEnd)) {
                continue;
            }

            $quantities = $request->getEquipmentQuantities();
            if (isset($quantities[$itemName])) {
                $outstanding += (int) $quantities[$itemName];
            }
        }

        return $outstanding;
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
