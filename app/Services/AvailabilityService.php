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
    public function getVenueCapacity(string $venueName): ?int
    {
        $venue = Venue::where('name', $venueName)->first();

        if ($venue && $venue->capacity !== null) {
            return (int) $venue->capacity;
        }

        $defaultCapacities = [
            'Conference Hall & Interaction Center (CHIC)' => 150,
            'Gymnasium' => 500,
            'Balay Alumni' => 200,
            'Covered Court' => 300,
            'Oval Grounds' => 1000,
            'Volleyball Court' => 100,
        ];

        return $defaultCapacities[$venueName] ?? null;
    }

    public function checkEquipmentAvailability(string $itemName, int $quantity, ?Carbon $requestedStart = null, ?Carbon $requestedEnd = null): array
    {
        $equipment = Equipment::whereRaw('LOWER(name) = ?', [strtolower($itemName)])->first();

        if (!$equipment) {
            return ['available' => false, 'message' => "Equipment '{$itemName}' not found.", 'total' => 0];
        }

        $requestedStart = $requestedStart ?? now();
        $requestedEnd = $requestedEnd ?? $requestedStart->copy()->addHour();

        $outstanding = $this->getOutstandingEquipmentQuantity($itemName, $requestedStart, $requestedEnd);
        $available = max(0, (int) $equipment->quantity - $outstanding);

        return [
            'available' => $available >= $quantity,
            'message' => $available >= $quantity ? null : "Sorry, only {$available} unit(s) of '{$itemName}' available for the selected window.",
            'available_qty' => $available,
            'total' => (int) $equipment->quantity,
        ];
    }

    public function checkVenueAvailability(string $venueName, Carbon $requestedStart, Carbon $requestedEnd): array
    {
        $venue = Venue::where('name', $venueName)->first();
        $venueRecord = $venue ?: null;
        $capacity = $this->getVenueCapacity($venueName);

        if ($venueRecord && $venueRecord->capacity !== null && $venueRecord->capacity <= 0) {
            return ['available' => false, 'message' => 'The selected venue is unavailable.', 'capacity' => $venueRecord->capacity];
        }

        $requests = FacilityRequest::with(['requestVenues', 'reservationSchedule'])->get();

        $conflicts = $requests->contains(function (FacilityRequest $request) use ($venueName, $requestedStart, $requestedEnd): bool {
                $isActiveApproved = $request->status === 'approved' && $request->equipment_returned_status !== 'returned' && $request->equipment_returned_status !== 'overdue';
                $isActivePending = $request->status === 'pending'
                    && $request->venue_status !== 'rejected'
                    && $request->equipment_status !== 'rejected';

                if (!$isActiveApproved && !$isActivePending) {
                    return false;
                }
                $venueNames = array_map('strtolower', $request->getVenueNames());
                $matchesVenue = collect($venueNames)->contains(function (string $name) use ($venueName): bool {
                    return str_contains($name, strtolower($venueName));
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

        $isHoliday = Holiday::whereDate('holiday_date', $requestedStart->toDateString())->exists();

        return [
            'available' => !$conflicts && !$maintenanceConflict && !$isHoliday,
            'message' => $this->buildVenueMessage($conflicts, $maintenanceConflict, $isHoliday),
            'capacity' => $capacity,
        ];
    }

    public function getOutstandingEquipmentQuantity(string $itemName, Carbon $requestedStart, Carbon $requestedEnd): int
    {
        $requests = FacilityRequest::with(['requestEquipment', 'reservationSchedule'])->get();

        $outstanding = 0;
        foreach ($requests as $request) {
            $isActiveApproved = $request->status === 'approved' && $request->equipment_returned_status !== 'returned' && $request->equipment_returned_status !== 'overdue';
            $isActivePending = $request->status === 'pending'
                && $request->venue_status !== 'rejected'
                && $request->equipment_status !== 'rejected';

            if (!$isActiveApproved && !$isActivePending) {
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
