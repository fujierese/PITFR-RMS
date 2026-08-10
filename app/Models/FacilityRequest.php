<?php
namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FacilityRequest extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'facility_requests';

    protected $fillable = [
        'control_number', 'date_requested', 'department', 'name_of_activity',
        'expected_participants', 'start_date', 'end_date', 'start_time', 'end_time', 'venue', 'equipment',
        'equipment_quantities', 'other_venue', 'equipment_custodian_statuses',
        'requested_by_id', 'status', 'venue_status', 'equipment_status',
        'venue_approval_signature', 'equipment_approval_signature', 'approval_signature_meta',
        'approved_by', 'approved_by_id', 'approved_date', 'notes', 'venue_notes', 'equipment_notes',
        'equipment_returned_status', 'equipment_returned_by', 'equipment_returned_date', 'equipment_return_notes',
        'equipment_returned_items', 'priority', 'is_emergency', 'proposal_file',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $request): void {
            if ($request->wasRecentlyCreated && $request->exists && $request->getAttribute('venue') !== null) {
                $request->syncRelationalItems();
            }
        });

        static::deleting(function (self $request): void {
            $request->syncRelationalItems();
        });
    }

    protected $casts = [
        'venue'                        => 'array',
        'equipment'                    => 'array',
        'equipment_quantities'         => 'array',
        'approved_date'                => 'datetime',
        'equipment_returned_date'      => 'datetime',
        'date_requested'               => 'date',
        'start_date'                   => 'date',
        'end_date'                     => 'date',
        'end_time'                     => 'string',
        'equipment_custodian_statuses' => 'array',
        'equipment_returned_items'     => 'array',
        'is_emergency'                 => 'boolean',
        'approval_signature_meta'      => 'array',
    ];

    public function getRequestingDateAttribute()
    {
        return $this->start_date;
    }

    public function setRequestingDateAttribute($value): void
    {
        $this->attributes['start_date'] = $value;
    }

    public function getRequestingEndDateAttribute()
    {
        return $this->end_date;
    }

    public function setRequestingEndDateAttribute($value): void
    {
        $this->attributes['end_date'] = $value;
    }

    public function getTimeAttribute()
    {
        return $this->start_time;
    }

    public function setTimeAttribute($value): void
    {
        $this->attributes['start_time'] = $value;
    }

    public function getRequestedByAttribute()
    {
        return $this->requester?->name ?? $this->attributes['requested_by'] ?? null;
    }

    public function getRequestedByPositionAttribute()
    {
        return $this->requester?->position ?? $this->attributes['requested_by_position'] ?? null;
    }

    public function getOtherEquipmentAttribute()
    {
        return null;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function histories()
    {
        return $this->hasMany(RequestHistory::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function reservationSchedule()
    {
        return $this->hasOne(ReservationSchedule::class);
    }

    public function requestVenues()
    {
        return $this->hasMany(RequestVenue::class);
    }

    public function requestEquipment()
    {
        return $this->hasMany(RequestEquipment::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(RequestStatusHistory::class);
    }

    public function addHistory(string $action, ?string $detail = null, ?int $userId = null)
    {
        return $this->histories()->create([
            'facility_request_id' => $this->getKey(),
            'action'              => $action,
            'detail'              => $detail,
            'user_id'             => $userId,
            'occurred_at'         => now(),
        ]);
    }

    public function getStageApproverName(string $stage): ?string
    {
        if ($stage === 'final') {
            $approvedBy = $this->relationLoaded('approvedBy')
                ? $this->getRelation('approvedBy')
                : $this->approvedBy()->first();

            if ($approvedBy instanceof User && !empty($approvedBy->name)) {
                return (string) $approvedBy->name;
            }

            if (!empty($this->approved_by)) {
                return (string) $this->approved_by;
            }

            $history = $this->histories()->where('action', 'final_approved')->latest('occurred_at')->first();
            return $history?->user?->name ?? null;
        }

        if ($stage === 'venue') {
            if (Schema::hasColumn('facility_requests', 'venue_approved_by') && !empty($this->getAttribute('venue_approved_by'))) {
                return (string) $this->getAttribute('venue_approved_by');
            }

            $history = $this->histories()
                ->where('action', 'custodian_endorsed')
                ->where('detail', 'like', '%Venue request%')
                ->latest('occurred_at')
                ->first();

            return $history?->user?->name ?? null;
        }

        if ($stage === 'equipment') {
            if (Schema::hasColumn('facility_requests', 'equipment_approved_by') && !empty($this->getAttribute('equipment_approved_by'))) {
                return (string) $this->getAttribute('equipment_approved_by');
            }

            $history = $this->histories()
                ->where('action', 'custodian_endorsed')
                ->where('detail', 'like', '%Equipment request%')
                ->latest('occurred_at')
                ->first();

            return $history?->user?->name ?? null;
        }

        return null;
    }

    public function getStageApprovalDate(string $stage): ?Carbon
    {
        if ($stage === 'final') {
            if ($this->approved_date instanceof Carbon) {
                return $this->approved_date;
            }

            if (!empty($this->approved_date)) {
                return Carbon::parse($this->approved_date);
            }

            $history = $this->histories()->where('action', 'final_approved')->latest('occurred_at')->first();
            return $history?->occurred_at ? Carbon::parse($history->occurred_at) : null;
        }

        if ($stage === 'venue') {
            $column = 'venue_approved_date';
            if (Schema::hasColumn('facility_requests', $column) && !empty($this->getAttribute($column))) {
                return Carbon::parse($this->getAttribute($column));
            }

            $history = $this->histories()
                ->where('action', 'custodian_endorsed')
                ->where('detail', 'like', '%Venue request%')
                ->latest('occurred_at')
                ->first();

            return $history?->occurred_at ? Carbon::parse($history->occurred_at) : null;
        }

        if ($stage === 'equipment') {
            $column = 'equipment_approved_date';
            if (Schema::hasColumn('facility_requests', $column) && !empty($this->getAttribute($column))) {
                return Carbon::parse($this->getAttribute($column));
            }

            $history = $this->histories()
                ->where('action', 'custodian_endorsed')
                ->where('detail', 'like', '%Equipment request%')
                ->latest('occurred_at')
                ->first();

            return $history?->occurred_at ? Carbon::parse($history->occurred_at) : null;
        }

        return null;
    }

    public static function generateControlNumber(): string
    {
        $year = now()->year;
        $num  = str_pad(rand(1, 9999), 3, '0', STR_PAD_LEFT);
        return "FER-{$year}-{$num}";
    }

    public function scopeMatchesVenue(Builder $query, string $venueName): Builder
    {
        return $query->where(function (Builder $resourceQuery) use ($venueName): void {
            $resourceQuery->whereHas('requestVenues', function (Builder $venueQuery) use ($venueName): void {
                $venueQuery->whereHas('venue', function (Builder $venueNameQuery) use ($venueName): void {
                    $venueNameQuery->whereRaw('LOWER(name) = ?', [strtolower($venueName)]);
                })->orWhereRaw('LOWER(name) = ?', [strtolower($venueName)]);
            })->orWhere(function (Builder $legacyQuery) use ($venueName): void {
                $legacyQuery->whereJsonContains('venue', $venueName)
                    ->orWhere('venue', 'LIKE', '%' . $venueName . '%');
            });
        });
    }

    public function scopeMatchesEquipment(Builder $query, string $equipmentName): Builder
    {
        return $query->where(function (Builder $resourceQuery) use ($equipmentName): void {
            $resourceQuery->whereHas('requestEquipment', function (Builder $equipmentQuery) use ($equipmentName): void {
                $equipmentQuery->whereHas('equipment', function (Builder $equipmentNameQuery) use ($equipmentName): void {
                    $equipmentNameQuery->whereRaw('LOWER(name) = ?', [strtolower($equipmentName)]);
                })->orWhereRaw('LOWER(name) = ?', [strtolower($equipmentName)]);
            })->orWhere(function (Builder $legacyQuery) use ($equipmentName): void {
                $legacyQuery->whereJsonContains('equipment', $equipmentName)
                    ->orWhere('equipment', 'LIKE', '%' . $equipmentName . '%');
            });
        });
    }

    public function syncRelationalItems(): void
    {
        if (Schema::hasTable('request_venues')) {
            DB::table('request_venues')->where('facility_request_id', $this->getKey())->delete();
        }

        if (Schema::hasTable('request_equipment')) {
            DB::table('request_equipment')->where('facility_request_id', $this->getKey())->delete();
        }

        if (Schema::hasTable('reservation_schedules')) {
            DB::table('reservation_schedules')->where('facility_request_id', $this->getKey())->delete();
        }

        $startDate = $this->start_date;
        $startTime = $this->start_time;
        $endDate = $this->end_date ?? $this->start_date;
        $endTime = $this->end_time ?? $this->start_time;

        $startDatetime = $this->normalizeScheduleValue($startDate, $startTime);
        $endDatetime = $this->normalizeScheduleValue($endDate, $endTime, $startDatetime);

        if ($startDatetime && $endDatetime && $endDatetime->lte($startDatetime)) {
            $endDatetime = $startDatetime->copy()->addDay();
        }

        if ($startDatetime && $endDatetime && Schema::hasTable('reservation_schedules')) {
            $this->reservationSchedule()->create([
                'start_datetime' => $startDatetime,
                'end_datetime' => $endDatetime,
            ]);
        }

        $venues = $this->getVenueNamesFromLegacyData();
        if (Schema::hasTable('request_venues')) {
            foreach ($venues as $venueName) {
                if (empty($venueName)) {
                    continue;
                }

                $payload = ['name' => $venueName];
                if (Schema::hasColumn('request_venues', 'venue_id')) {
                    $venue = Venue::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($venueName)])->first();
                    if ($venue) {
                        $payload['venue_id'] = $venue->id;
                    }
                }

                $this->requestVenues()->create($payload);
            }
        }

        $equipment = $this->getEquipmentItemsFromLegacyData();
        $quantities = $this->getEquipmentQuantitiesFromLegacyData();

        if (Schema::hasTable('request_equipment')) {
            foreach ($equipment as $equipmentName) {
                if (empty($equipmentName)) {
                    continue;
                }

                $payload = [
                    'name' => $equipmentName,
                    'quantity' => (int) ($quantities[$equipmentName] ?? 1),
                ];

                if (Schema::hasColumn('request_equipment', 'equipment_id')) {
                    $equipmentRecord = Equipment::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($equipmentName)])->first();
                    if ($equipmentRecord) {
                        $payload['equipment_id'] = $equipmentRecord->id;
                    }
                }

                $this->requestEquipment()->create($payload);
            }
        }
    }

    public function getVenueNames(): array
    {
        if (!Schema::hasTable('request_venues')) {
            return $this->getVenueNamesFromLegacyData();
        }

        if ($this->relationLoaded('requestVenues')) {
            $relationalVenues = $this->resolveVenueRelationNames();
            if (!empty($relationalVenues)) {
                return $this->preferRelationValuesOverLegacy($relationalVenues, $this->getVenueNamesFromLegacyData());
            }
        }

        $this->loadMissing('requestVenues');
        $this->loadMissing('requestVenues.venue');
        $relationalVenues = $this->resolveVenueRelationNames();
        if (!empty($relationalVenues)) {
            return $this->preferRelationValuesOverLegacy($relationalVenues, $this->getVenueNamesFromLegacyData());
        }

        $relationalVenues = $this->requestVenues()->get()->map(fn (RequestVenue $item) => $item->resolvedName())->filter()->values()->all();
        if (!empty($relationalVenues)) {
            return $this->preferRelationValuesOverLegacy($relationalVenues, $this->getVenueNamesFromLegacyData());
        }

        return $this->getVenueNamesFromLegacyData();
    }

    public function getEquipmentItems(): array
    {
        if (!Schema::hasTable('request_equipment')) {
            return $this->getEquipmentItemsFromLegacyData();
        }

        if ($this->relationLoaded('requestEquipment')) {
            $relationalEquipment = $this->resolveEquipmentRelationNames();
            if (!empty($relationalEquipment)) {
                return $this->preferRelationValuesOverLegacy($relationalEquipment, $this->getEquipmentItemsFromLegacyData());
            }
        }

        $this->loadMissing('requestEquipment');
        $this->loadMissing('requestEquipment.equipment');
        $relationalEquipment = $this->resolveEquipmentRelationNames();
        if (!empty($relationalEquipment)) {
            return $this->preferRelationValuesOverLegacy($relationalEquipment, $this->getEquipmentItemsFromLegacyData());
        }

        $relationalEquipment = $this->requestEquipment()->get()->map(fn (RequestEquipment $item) => $item->resolvedName())->filter()->values()->all();
        if (!empty($relationalEquipment)) {
            return $this->preferRelationValuesOverLegacy($relationalEquipment, $this->getEquipmentItemsFromLegacyData());
        }

        return $this->getEquipmentItemsFromLegacyData();
    }

    public function getEquipmentQuantities(): array
    {
        if ($this->relationLoaded('requestEquipment')) {
            $relationalQuantities = $this->requestEquipment->mapWithKeys(function (RequestEquipment $item) {
                return [$item->resolvedName() => (int) $item->quantity];
            })->all();

            if (!empty($relationalQuantities)) {
                return $this->preferRelationQuantitiesOverLegacy($relationalQuantities, $this->getEquipmentQuantitiesFromLegacyData());
            }
        }

        if (Schema::hasTable('request_equipment')) {
            $this->loadMissing('requestEquipment');
            $this->loadMissing('requestEquipment.equipment');
            $relationalQuantities = $this->requestEquipment->mapWithKeys(function (RequestEquipment $item) {
                return [$item->resolvedName() => (int) $item->quantity];
            })->all();

            if (!empty($relationalQuantities)) {
                return $this->preferRelationQuantitiesOverLegacy($relationalQuantities, $this->getEquipmentQuantitiesFromLegacyData());
            }

            $relationalQuantities = $this->requestEquipment()->get()
                ->mapWithKeys(function (RequestEquipment $item) {
                    return [$item->resolvedName() => (int) $item->quantity];
                })->all();

            if (!empty($relationalQuantities)) {
                return $this->preferRelationQuantitiesOverLegacy($relationalQuantities, $this->getEquipmentQuantitiesFromLegacyData());
            }
        }

        return $this->getEquipmentQuantitiesFromLegacyData();
    }

    public function getVenueIds(): array
    {
        return $this->getLoadedRelationIds('requestVenues');
    }

    public function getEquipmentIds(): array
    {
        return $this->getLoadedRelationIds('requestEquipment');
    }

    private function resolveVenueRelationNames(): array
    {
        if ($this->relationLoaded('requestVenues')) {
            $items = $this->getRelation('requestVenues');

            if (is_iterable($items)) {
                return collect($items)
                    ->map(fn (RequestVenue $item) => $item->resolvedName())
                    ->filter()
                    ->values()
                    ->all();
            }
        }

        return [];
    }

    private function resolveEquipmentRelationNames(): array
    {
        if ($this->relationLoaded('requestEquipment')) {
            $items = $this->getRelation('requestEquipment');

            if (is_iterable($items)) {
                return collect($items)
                    ->map(fn (RequestEquipment $item) => $item->resolvedName())
                    ->filter()
                    ->values()
                    ->all();
            }
        }

        return [];
    }

    private function preferRelationValuesOverLegacy(array $relationValues, array $legacyValues): array
    {
        $filteredRelationValues = array_values(array_filter($relationValues, static function ($value) use ($legacyValues): bool {
            return !in_array($value, $legacyValues, true);
        }));

        if (!empty($filteredRelationValues)) {
            return array_values(array_unique(array_filter($filteredRelationValues)));
        }

        return array_values(array_unique(array_filter($relationValues)));
    }

    private function preferRelationQuantitiesOverLegacy(array $relationQuantities, array $legacyQuantities): array
    {
        $relationKeys = array_keys($relationQuantities);
        $legacyKeys = array_keys($legacyQuantities);
        $filteredRelationQuantities = array_filter($relationQuantities, static function ($value, $key) use ($legacyKeys): bool {
            return !in_array($key, $legacyKeys, true);
        }, ARRAY_FILTER_USE_BOTH);

        if (!empty($filteredRelationQuantities)) {
            return $filteredRelationQuantities;
        }

        return $relationQuantities;
    }

    private function getLoadedRelationIds(string $relationName): array
    {
        if ($this->relationLoaded($relationName)) {
            $items = $this->getRelation($relationName);

            if (is_iterable($items)) {
                return collect($items)
                    ->map(function ($item) {
                        if ($item instanceof Model) {
                            return $item->getAttribute('id') ?? $item->id ?? null;
                        }

                        if (is_array($item)) {
                            return $item['id'] ?? null;
                        }

                        return null;
                    })
                    ->filter()
                    ->values()
                    ->all();
            }
        }

        return [];
    }

    private function getVenueNamesFromLegacyData(): array
    {
        $venueData = $this->getAttribute('venue');

        if (is_array($venueData)) {
            return array_values(array_filter($venueData));
        }

        if (is_string($venueData)) {
            return array_values(array_filter([$venueData]));
        }

        return [];
    }

    private function getEquipmentItemsFromLegacyData(): array
    {
        $equipmentData = $this->getAttribute('equipment');

        if (is_array($equipmentData)) {
            return array_values(array_filter($equipmentData));
        }

        if (is_string($equipmentData)) {
            return array_values(array_filter([$equipmentData]));
        }

        return [];
    }

    private function getEquipmentQuantitiesFromLegacyData(): array
    {
        $quantities = $this->getAttribute('equipment_quantities');

        if (!is_array($quantities)) {
            return [];
        }

        return array_filter($quantities, static function ($value): bool {
            return $value !== null;
        });
    }

    public function syncFromJsonPayload(array $payload): void
    {
        $this->fill([
            'venue' => $payload['venue'] ?? $this->venue,
            'equipment' => $payload['equipment'] ?? $this->equipment,
            'equipment_quantities' => $payload['equipment_quantities'] ?? $this->equipment_quantities,
        ]);

        $this->save();
        $this->syncRelationalItems();
    }

    private function normalizeScheduleValue($dateValue, $timeValue, ?\Carbon\Carbon $fallback = null): ?\Carbon\Carbon
    {
        if (!$dateValue && !$timeValue) {
            return null;
        }

        $timezone = config('app.timezone', 'Asia/Manila');
        $datePart = $dateValue instanceof \Carbon\Carbon ? $dateValue->toDateString() : trim((string) $dateValue);
        $timePart = $timeValue instanceof \Carbon\Carbon ? $timeValue->toTimeString() : trim((string) $timeValue);

        if ($datePart === '' && $timePart === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(?::\d{2})?$/', $datePart)) {
            return Carbon::parse($datePart, $timezone)->setTimezone($timezone);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(?::\d{2})?$/', $timePart)) {
            return Carbon::parse($timePart, $timezone)->setTimezone($timezone);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datePart) && preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $timePart)) {
            return Carbon::parse($datePart . ' ' . substr($timePart, 0, 5), $timezone)->setTimezone($timezone);
        }

        if (preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $timePart)) {
            return Carbon::parse(($fallback?->toDateString() ?? $datePart) . ' ' . substr($timePart, 0, 5), $timezone)->setTimezone($timezone);
        }

        return Carbon::parse($datePart . ' ' . $timePart, $timezone)->setTimezone($timezone);
    }

    public function formatTimeForDisplay(?string $time): string
    {
        if (blank($time)) {
            return '—';
        }

        $trimmed = trim((string) $time);

        foreach (['H:i', 'H:i:s', 'g:i A', 'g:i a', 'h:i A', 'h:i a'] as $format) {
            try {
                return Carbon::createFromFormat($format, $trimmed)->format('g:i A');
            } catch (\Exception $e) {
                // try the next format
            }
        }

        try {
            return Carbon::parse($trimmed)->format('g:i A');
        } catch (\Exception $e) {
            return $trimmed;
        }
    }

    public function formatDateForDisplay($date): string
    {
        if (blank($date)) {
            return '—';
        }

        try {
            return Carbon::parse($date)->translatedFormat('M j, Y');
        } catch (\Exception $e) {
            return (string) $date;
        }
    }

    // ─── HELPER: resolve quantities safely ───────────────────────────────────
    private function resolvedQuantities(): array
    {
        $quantities = $this->getEquipmentQuantities();
        if (!empty($quantities)) {
            return $quantities;
        }

        $equipmentItems = $this->getEquipmentItems();
        if (!empty($equipmentItems)) {
            return array_fill_keys($equipmentItems, 1);
        }

        return [];
    }

    public function getRequestedStartDateTime(): \Carbon\Carbon
    {
        if (!Schema::hasTable('reservation_schedules')) {
            return $this->normalizeScheduleValue($this->start_date, $this->start_time) ?? \Carbon\Carbon::parse('00:00');
        }

        $this->loadMissing('reservationSchedule');

        if ($this->relationLoaded('reservationSchedule') && $this->reservationSchedule) {
            return $this->reservationSchedule->start_datetime;
        }

        return $this->reservationSchedule?->start_datetime
            ?? $this->normalizeScheduleValue($this->start_date, $this->start_time) ?? \Carbon\Carbon::parse('00:00');
    }

    public function getRequestedEndDateTime(): \Carbon\Carbon
    {
        if (!Schema::hasTable('reservation_schedules')) {
            return $this->normalizeScheduleValue($this->end_date ?? $this->start_date, $this->end_time ?? $this->start_time ?? '00:00') ?? \Carbon\Carbon::parse('00:00');
        }

        $this->loadMissing('reservationSchedule');

        if ($this->relationLoaded('reservationSchedule') && $this->reservationSchedule) {
            return $this->reservationSchedule->end_datetime;
        }

        return $this->reservationSchedule?->end_datetime
            ?? $this->normalizeScheduleValue($this->end_date ?? $this->start_date, $this->end_time ?? $this->start_time ?? '00:00') ?? \Carbon\Carbon::parse('00:00');
    }

    public function overlapsTimeRange(\Carbon\Carbon $requestedStart, \Carbon\Carbon $requestedEnd): bool
    {
        return $this->getRequestedStartDateTime()->lt($requestedEnd)
            && $this->getRequestedEndDateTime()->gt($requestedStart);
    }

    public function overlapsRequest(FacilityRequest $other): bool
    {
        return $this->getRequestedStartDateTime()->lt($other->getRequestedEndDateTime())
            && $other->getRequestedStartDateTime()->lt($this->getRequestedEndDateTime());
    }

    // ─── HELPER: find equipment by name (case-insensitive) ───────────────────
    private function findEquipment(string $name): ?\App\Models\Equipment
    {
        return \App\Models\Equipment::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
    }

    // ─── HELPER: find equipment by name + custodian (case-insensitive) ────────
    private function findEquipmentForCustodian(string $name, int|string $custodianId): ?\App\Models\Equipment
    {
        $equipment = \App\Models\Equipment::whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();

        if (! $equipment) {
            return null;
        }

        return $equipment->isAuthorizedCustodian((int) $custodianId) ? $equipment : null;
    }

    public function getAuthorizedCustodianIdsForEquipment(string $itemName): array
    {
        $equipment = $this->findEquipment($itemName);

        if (! $equipment) {
            return [];
        }

        return $equipment->getAuthorizedCustodianIds();
    }

    // ─── GET ALL CUSTODIAN IDs ASSIGNED TO EQUIPMENT IN THIS REQUEST ──────────
    public function getAssignedEquipmentCustodianIds(): array
    {
        $quantities = $this->resolvedQuantities();

        if (empty($quantities)) return [];

        $custodianIds = [];

        foreach (array_keys($quantities) as $itemName) {
            $custodianIds = array_merge($custodianIds, $this->getAuthorizedCustodianIdsForEquipment($itemName));
        }

        return array_values(array_unique(array_map('intval', $custodianIds)));
    }

    // ─── GET EQUIPMENT ASSIGNED TO A SPECIFIC CUSTODIAN IN THIS REQUEST ───────
    public function getAssignedEquipmentForCustodian(int|string $custodianId): array
    {
        $quantities = $this->resolvedQuantities();

        if (empty($quantities)) return [];

        $result = [];

        foreach ($quantities as $itemName => $qty) {
            if ((int) $qty <= 0) continue;

            $eq = $this->findEquipmentForCustodian($itemName, (int) $custodianId);

            if ($eq) {
                $result[$itemName] = $qty;
            }
        }

        return $result;
    }

    // ─── ALIAS (used in return action) ───────────────────────────────────────
    public function equipmentForCustodian(int $custodianId): array
    {
        return $this->getAssignedEquipmentForCustodian($custodianId);
    }

    // ─── GET / SET INDIVIDUAL CUSTODIAN STATUS ────────────────────────────────
    public function getCustodianEquipmentStatus(int $custodianId): string
    {
        $statuses = $this->equipment_custodian_statuses ?? [];
        return $statuses[$custodianId] ?? 'pending';
    }

    public function setCustodianEquipmentStatus(int $custodianId, string $status): void
    {
        $statuses               = $this->equipment_custodian_statuses ?? [];
        $statuses[$custodianId] = $status;
        $this->equipment_custodian_statuses = $statuses;
        $this->save();
        $this->refresh();
    }

    // ─── RECOMPUTE GLOBAL EQUIPMENT STATUS BASED ON ALL CUSTODIANS ───────────
    public function recomputeEquipmentStatus(): void
    {
        $itemNames = $this->getEquipmentItems();
        $statuses = $this->equipment_custodian_statuses ?? [];

        if (empty($itemNames)) {
            $this->equipment_status = 'pending';
            $this->save();
            return;
        }

        $itemResults = [];

        foreach ($itemNames as $itemName) {
            $authorizedIds = $this->getAuthorizedCustodianIdsForEquipment($itemName);
            if (empty($authorizedIds)) {
                $itemResults[] = 'approved';
                continue;
            }

            $itemStatuses = array_values(array_intersect_key($statuses, array_flip(array_map('intval', $authorizedIds))));

            if ($itemStatuses === []) {
                $itemResults[] = 'pending';
                continue;
            }

            if (in_array('approved', $itemStatuses, true)) {
                $itemResults[] = 'approved';
                continue;
            }

            if (in_array('rejected', $itemStatuses, true)) {
                $itemResults[] = 'rejected';
                continue;
            }

            $itemResults[] = 'pending';
        }

        if (in_array('rejected', $itemResults, true) && ! in_array('approved', $itemResults, true)) {
            $this->equipment_status = 'rejected';
        } elseif (count($itemResults) > 0 && ! in_array('pending', $itemResults, true) && ! in_array('rejected', $itemResults, true)) {
            $this->equipment_status = 'approved';
        } else {
            $this->equipment_status = 'pending';
        }

        $this->save();
    }

    // ─── MARK EQUIPMENT AS RETURNED ──────────────────────────────────────────
    public function markEquipmentReturned(int $custodianId, array $returnedEquipment, ?string $notes = null): void
    {
        // ❗ Ensure event is finished, defaulting to end_date when start_date is not set
        $eventEndDate = $this->end_date ?? $this->start_date;
        if ($eventEndDate && now()->lt($eventEndDate)) {
            throw new \Exception('Event is not yet finished.');
        }

        $requestedQuantities = $this->resolvedQuantities();

        $returnedItems       = $this->equipment_returned_items ?? [];
        $prevCustodianReturn = $returnedItems[$custodianId]['equipment'] ?? [];

        // Initialize custodian entry
        $returnedItems[$custodianId] = [
            'equipment'   => [],
            'returned_at' => now()->toISOString(),
            'notes'       => $notes,
        ];

        foreach ($returnedEquipment as $itemName => $qty) {
            $qty = (int) $qty;
            if ($qty <= 0) continue;

            // ❗ Ensure this equipment belongs to this custodian
            $eq = $this->findEquipmentForCustodian($itemName, $custodianId);
            if (!$eq) continue;

            $alreadyReturnedByCustodian = (int) ($prevCustodianReturn[$itemName] ?? 0);
            $delta = $qty - $alreadyReturnedByCustodian;

            if ($delta <= 0) {
                $returnedItems[$custodianId]['equipment'][$itemName] = $alreadyReturnedByCustodian;
                continue;
            }

            $requestedQty = (int) ($requestedQuantities[$itemName] ?? 0);

            // ❗ Compute total returned across ALL custodians
            $totalReturnedSoFar = 0;
            foreach ($returnedItems as $cId => $custodianData) {
                if ($cId == $custodianId) continue;

                $totalReturnedSoFar += (int) ($custodianData['equipment'][$itemName] ?? 0);
            }

            $totalReturnedSoFar += $alreadyReturnedByCustodian;

            // Remaining needed
            $remainingNeeded = max(0, $requestedQty - $totalReturnedSoFar);

            // Final quantity to release
            $toRelease = min($delta, $remainingNeeded);

            // New total for this custodian
            $newTotalByCustodian = $alreadyReturnedByCustodian + $toRelease;

            $returnedItems[$custodianId]['equipment'][$itemName] = $newTotalByCustodian;

            // Release stock
            if ($toRelease > 0) {
                $eq->release($toRelease);
            }
        }

        // Check if all returned
        $allReturned = $this->isAllEquipmentReturned($returnedItems);

        $this->update([
            'equipment_returned_items'  => $returnedItems,
            'equipment_returned_status' => $allReturned ? 'returned' : 'partial',
            'equipment_returned_date'   => $allReturned ? now() : $this->equipment_returned_date,
            'equipment_return_notes'    => $notes,
        ]);

        // Optional: add history log
        $this->addHistory(
            'equipment_returned',
            'Equipment marked as returned by custodian ID: ' . $custodianId,
            $custodianId
        );
    }

    private function isAllEquipmentReturned(array $returnedItems): bool
    {
        $totalRequested = $this->resolvedQuantities();

        if (empty($totalRequested)) return true;

        $totalReturned = [];

        foreach ($returnedItems as $custodianData) {
            foreach ($custodianData['equipment'] ?? [] as $itemName => $qty) {
                $totalReturned[$itemName] = ($totalReturned[$itemName] ?? 0) + (int) $qty;
            }
        }

        foreach ($totalRequested as $itemName => $requestedQty) {
            if (($totalReturned[$itemName] ?? 0) < (int) $requestedQty) {
                return false;
            }
        }

        return true;
    }

    public function isOverdue(): bool
    {
        $eventEndDate = $this->end_date ?? $this->start_date;
        return $eventEndDate && now()->gt($eventEndDate)
            && $this->equipment_returned_status !== 'returned';
    }
}