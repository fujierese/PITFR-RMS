<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\RequestEquipment;
use App\Models\RequestVenue;
use App\Models\Venue;
use Illuminate\Support\Facades\Log;

class FacilityRequestRelationBackfillService
{
    public function run(): array
    {
        $summary = [
            'total_requests_scanned' => 0,
            'request_venues_created' => 0,
            'request_equipment_created' => 0,
            'skipped_requests' => 0,
            'unmatched_venues' => 0,
            'unmatched_equipment' => 0,
            'missing_quantity_requests' => [],
            'missing_venue_names' => [],
            'missing_equipment_names' => [],
        ];

        $requests = FacilityRequest::query()->orderBy('id')->get();
        $summary['total_requests_scanned'] = $requests->count();

        foreach ($requests as $request) {
            $hasVenueRows = $request->requestVenues()->exists();
            $hasEquipmentRows = $request->requestEquipment()->exists();

            if ($hasVenueRows && $hasEquipmentRows) {
                $summary['skipped_requests']++;
                continue;
            }

            $venueNames = $this->normalizeStringList($request->getAttribute('venue'));
            foreach ($venueNames as $venueName) {
                $venue = Venue::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($venueName)])->first();

                if (!$venue) {
                    $summary['unmatched_venues']++;
                    $summary['missing_venue_names'][] = $venueName;
                    continue;
                }

                $existing = RequestVenue::query()
                    ->where('facility_request_id', $request->id)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($venue->name)])
                    ->first();

                if (!$existing) {
                    $request->requestVenues()->create([
                        'venue_id' => $venue->id,
                        'name' => $venue->name,
                    ]);
                    $summary['request_venues_created']++;
                }
            }

            $equipmentItems = $this->normalizeStringList($request->getAttribute('equipment'));
            $quantities = $this->normalizeQuantities($request->getAttribute('equipment_quantities'));

            foreach ($equipmentItems as $equipmentName) {
                $equipment = Equipment::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($equipmentName)])->first();

                if (!$equipment) {
                    $summary['unmatched_equipment']++;
                    $summary['missing_equipment_names'][] = $equipmentName;
                    continue;
                }

                $quantity = $quantities[$equipmentName] ?? 1;

                if (!array_key_exists($equipmentName, $quantities)) {
                    $summary['missing_quantity_requests'][] = $request->id;
                }

                $existing = RequestEquipment::query()
                    ->where('facility_request_id', $request->id)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($equipment->name)])
                    ->first();

                if (!$existing) {
                    $request->requestEquipment()->create([
                        'equipment_id' => $equipment->id,
                        'name' => $equipment->name,
                        'quantity' => (int) $quantity,
                    ]);
                    $summary['request_equipment_created']++;
                }
            }
        }

        $summary['missing_venue_names'] = array_values(array_unique($summary['missing_venue_names']));
        $summary['missing_equipment_names'] = array_values(array_unique($summary['missing_equipment_names']));
        $summary['missing_quantity_requests'] = array_values(array_unique($summary['missing_quantity_requests']));

        $this->logSummary($summary);

        return $summary;
    }

    private function normalizeStringList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value), static fn ($item) => $item !== ''));
        }

        if (is_string($value)) {
            return array_values(array_filter(array_map('trim', preg_split('/[,;]+/', $value) ?: []), static fn ($item) => $item !== ''));
        }

        return [];
    }

    private function normalizeQuantities(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_map(static fn ($item) => (int) $item, $value);
    }

    private function logSummary(array $summary): void
    {
        Log::info('Facility request relation backfill complete', [
            'total_requests_scanned' => $summary['total_requests_scanned'],
            'request_venues_created' => $summary['request_venues_created'],
            'request_equipment_created' => $summary['request_equipment_created'],
            'skipped_requests' => $summary['skipped_requests'],
            'unmatched_venues' => $summary['unmatched_venues'],
            'unmatched_equipment' => $summary['unmatched_equipment'],
            'missing_quantity_requests' => $summary['missing_quantity_requests'],
            'missing_venue_names' => $summary['missing_venue_names'],
            'missing_equipment_names' => $summary['missing_equipment_names'],
        ]);
    }
}
