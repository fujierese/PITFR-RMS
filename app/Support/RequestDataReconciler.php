<?php

namespace App\Support;

use App\Models\FacilityRequest;
use App\Models\RequestEquipment;
use App\Models\RequestVenue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-18: the per-venue schedule rows (request_venues), the item rows
 * (request_equipment) and the reservation_schedules row are the source of truth. The
 * old columns on facility_requests (venue, equipment, equipment_quantities and the
 * start/end date and time) are kept only as a copy for older screens and exports.
 *
 * This finds requests where the two disagree, or where an old request has no rows
 * yet, and repairs them: missing rows are built from the old columns (nothing else
 * exists to lose), and an out-of-date copy is rewritten from the rows.
 */
final class RequestDataReconciler
{
    /**
     * @return array<string, string> issue key => plain-language description
     */
    public function issues(FacilityRequest $request): array
    {
        $request->loadMissing(['requestVenues.venue', 'requestEquipment.equipment', 'reservationSchedule']);
        $issues = [];

        $legacyVenues = $this->legacyVenueNames($request);
        $rowVenues = $this->rowVenueNames($request);

        if ($rowVenues === []) {
            if ($legacyVenues !== []) {
                $issues['missing_venue_rows'] = 'Has no per-venue schedule rows; it is read from the old venue and date columns (' . implode(', ', $legacyVenues) . ').';
            }
        } elseif ($this->normalizedSet($legacyVenues) !== $this->normalizedSet($rowVenues)) {
            $issues['venue_copy'] = 'Old venue column lists ' . $this->listOrNone($legacyVenues) . '; venue rows list ' . implode(', ', $rowVenues) . '.';
        }

        $legacyEquipment = $this->legacyEquipmentQuantities($request);
        $rowEquipment = $this->rowEquipmentQuantities($request);
        if ($rowEquipment === [] && $legacyEquipment !== [] && $rowVenues !== []) {
            $issues['missing_equipment_rows'] = 'Has no item rows; equipment is read from the old columns (' . $this->quantityList($legacyEquipment) . ').';
        } elseif ($rowEquipment !== [] && $this->normalizedQuantities($legacyEquipment) !== $this->normalizedQuantities($rowEquipment)) {
            $issues['equipment_copy'] = 'Old equipment columns list ' . $this->quantityList($legacyEquipment) . '; item rows list ' . $this->quantityList($rowEquipment) . '.';
        }

        $span = $rowVenues !== [] ? $this->rowSpan($request, $rowVenues) : null;
        if ($span !== null) {
            $legacy = FacilityRequest::normalizeScheduleRange(
                $request->start_date?->toDateString(),
                $request->start_time,
                ($request->end_date ?? $request->start_date)?->toDateString(),
                $request->end_time ?? $request->start_time
            );
            if (! $this->sameMinute($legacy['start'], $span['start']) || ! $this->sameMinute($legacy['end'], $span['end'])) {
                $issues['date_copy'] = 'Old date columns say ' . $this->rangeLabel($legacy) . '; venue rows run ' . $this->rangeLabel($span) . '.';
            }
        }

        $expectedRow = $span ?? ($request->start_date ? FacilityRequest::normalizeScheduleRange(
            $request->start_date->toDateString(),
            $request->start_time,
            ($request->end_date ?? $request->start_date)->toDateString(),
            $request->end_time ?? $request->start_time
        ) : null);
        if ($expectedRow !== null && $expectedRow['start'] && $expectedRow['end'] && Schema::hasTable('reservation_schedules')) {
            $row = $request->reservationSchedule;
            if (! $row) {
                $issues['missing_schedule_row'] = 'Has no reservation schedule row.';
            } elseif (! $this->sameMinute($row->start_datetime, $expectedRow['start']) || ! $this->sameMinute($row->end_datetime, $expectedRow['end'])) {
                $issues['schedule_row'] = 'Reservation schedule row says ' . $this->rangeLabel(['start' => $row->start_datetime, 'end' => $row->end_datetime])
                    . '; it should be ' . $this->rangeLabel($expectedRow) . '.';
            }
        }

        return $issues;
    }

    /**
     * Repairs what issues() found. Returns the keys that were fixed.
     *
     * @return array<int, string>
     */
    public function fix(FacilityRequest $request): array
    {
        $issues = $this->issues($request);
        if ($issues === []) {
            return [];
        }

        return DB::transaction(function () use ($request, $issues): array {
            // An old request with no venue rows: build every row from the old columns.
            if (isset($issues['missing_venue_rows'])) {
                $request->syncRelationalItems();

                return array_keys($issues);
            }

            $rowVenues = $this->rowVenueNames($request);
            $copy = [];
            if (isset($issues['venue_copy'])) {
                $copy['venue'] = json_encode(array_values($rowVenues));
            }
            if (isset($issues['equipment_copy'])) {
                $rowEquipment = $this->rowEquipmentQuantities($request);
                $copy['equipment'] = json_encode(array_keys($rowEquipment));
                $copy['equipment_quantities'] = json_encode($rowEquipment);
            }
            if (isset($issues['missing_equipment_rows'])) {
                $this->createEquipmentRows($request, $rowVenues);
            }

            $span = $this->rowSpan($request, $rowVenues);
            if ($span !== null && isset($issues['date_copy'])) {
                $copy['start_date'] = $span['start']->toDateString();
                $copy['end_date'] = $span['end']->toDateString();
                $copy['start_time'] = $span['start']->format('H:i');
                $copy['end_time'] = $span['end']->format('H:i');
            }

            // Written without model events so no notification, history or timestamp
            // change is triggered: only the copy is brought up to date.
            if ($copy !== []) {
                DB::table('facility_requests')->where('id', $request->getKey())->update($copy);
            }

            if ($span !== null && (isset($issues['missing_schedule_row']) || isset($issues['schedule_row']))) {
                DB::table('reservation_schedules')->where('facility_request_id', $request->getKey())->delete();
                DB::table('reservation_schedules')->insert([
                    'facility_request_id' => $request->getKey(),
                    'start_datetime' => $span['start']->format('Y-m-d H:i:s'),
                    'end_datetime' => $span['end']->format('Y-m-d H:i:s'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return array_keys($issues);
        });
    }

    private function createEquipmentRows(FacilityRequest $request, array $rowVenues): void
    {
        $requestVenue = count($rowVenues) === 1 ? $request->requestVenues->first() : null;
        foreach ($this->legacyEquipmentQuantities($request) as $name => $quantity) {
            $payload = ['name' => $name, 'quantity' => $quantity];
            if ($requestVenue && Schema::hasColumn('request_equipment', 'request_venue_id')) {
                $payload['request_venue_id'] = $requestVenue->id;
            }
            if (Schema::hasColumn('request_equipment', 'equipment_id')) {
                $equipmentId = DB::table('equipment')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id');
                if ($equipmentId) {
                    $payload['equipment_id'] = $equipmentId;
                }
            }
            $request->requestEquipment()->create($payload);
        }
    }

    /** @return array{start: Carbon, end: Carbon}|null */
    private function rowSpan(FacilityRequest $request, array $rowVenues): ?array
    {
        $ranges = collect($rowVenues)
            ->map(fn (string $venue): array => $request->getVenueScheduleRange($venue))
            ->filter(fn (array $range): bool => $range['start'] !== null && $range['end'] !== null);
        if ($ranges->isEmpty()) {
            return null;
        }

        return [
            'start' => $ranges->pluck('start')->sort()->first(),
            'end' => $ranges->pluck('end')->sort()->last(),
        ];
    }

    private function legacyVenueNames(FacilityRequest $request): array
    {
        $venues = $request->getAttribute('venue');
        if (is_string($venues)) {
            $venues = [$venues];
        }

        return collect(is_array($venues) ? $venues : [])
            ->map(fn ($name): string => trim((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function rowVenueNames(FacilityRequest $request): array
    {
        return $request->requestVenues
            ->map(fn (RequestVenue $venue): string => trim((string) $venue->resolvedName()))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, int> */
    private function legacyEquipmentQuantities(FacilityRequest $request): array
    {
        $items = $request->getAttribute('equipment');
        $quantities = $request->getAttribute('equipment_quantities');
        $quantities = is_array($quantities) ? $quantities : [];
        $result = [];
        foreach (is_array($items) ? $items : [] as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $quantity = (int) ($quantities[$name] ?? 1);
            if ($quantity > 0) {
                $result[$name] = ($result[$name] ?? 0) + $quantity;
            }
        }

        return $result;
    }

    /** @return array<string, int> */
    private function rowEquipmentQuantities(FacilityRequest $request): array
    {
        return $request->requestEquipment
            ->groupBy(fn (RequestEquipment $item): string => trim((string) $item->resolvedName()))
            ->map(fn ($items): int => (int) $items->sum('quantity'))
            ->filter(fn (int $quantity, string $name): bool => $name !== '' && $quantity > 0)
            ->all();
    }

    private function normalizedSet(array $names): array
    {
        $set = array_values(array_unique(array_map(fn (string $name): string => mb_strtolower(trim($name)), $names)));
        sort($set);

        return $set;
    }

    private function normalizedQuantities(array $quantities): array
    {
        $result = [];
        foreach ($quantities as $name => $quantity) {
            $key = mb_strtolower(trim((string) $name));
            $result[$key] = ($result[$key] ?? 0) + (int) $quantity;
        }
        ksort($result);

        return $result;
    }

    private function sameMinute($a, $b): bool
    {
        if (! $a || ! $b) {
            return $a == $b;
        }

        return Carbon::parse($a)->format('Y-m-d H:i') === Carbon::parse($b)->format('Y-m-d H:i');
    }

    private function rangeLabel(array $range): string
    {
        $format = fn ($value): string => $value ? Carbon::parse($value)->format('M j, Y g:i A') : 'nothing';

        return $format($range['start']) . ' to ' . $format($range['end']);
    }

    private function listOrNone(array $names): string
    {
        return $names === [] ? 'nothing' : implode(', ', $names);
    }

    private function quantityList(array $quantities): string
    {
        return $quantities === []
            ? 'nothing'
            : collect($quantities)->map(fn (int $quantity, string $name): string => "{$name} x{$quantity}")->implode(', ');
    }
}
