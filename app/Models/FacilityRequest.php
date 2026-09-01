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
        'control_number', 'date_requested', 'department', 'organization_name', 'request_context', 'student_organization_id', 'name_of_activity',
        'expected_participants', 'start_date', 'end_date', 'start_time', 'end_time', 'venue', 'equipment', 'purpose',
        'equipment_quantities', 'other_venue', 'equipment_custodian_statuses',
        'requested_by_id', 'status', 'venue_status', 'equipment_status',
        'venue_approval_signature', 'equipment_approval_signature', 'approval_signature_meta',
        'approved_by', 'approved_by_id', 'approved_date', 'notes', 'venue_notes', 'equipment_notes',
        'equipment_returned_status', 'equipment_returned_by', 'equipment_returned_date', 'equipment_return_notes',
        'equipment_returned_items', 'equipment_return_damaged_quantity', 'equipment_return_missing_quantity',
        'equipment_return_damage_remarks', 'equipment_return_missing_remarks', 'priority', 'requested_priority', 'requested_is_emergency', 'is_emergency', 'emergency_justification', 'proposal_file',
        'activity_proposal_file', 'igp_receipt_file', 'e_signature_file', 'document_metadata',
        'venue_approval_signature_file', 'equipment_approval_signature_file', 'final_approval_signature_file',
        'final_approval_signature',
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
        'requested_is_emergency'       => 'boolean',
        'approval_signature_meta'      => 'array',
        'document_metadata'            => 'array',
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

    public function studentOrganization()
    {
        return $this->belongsTo(StudentOrganization::class);
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

    public function revisionHistories()
    {
        return $this->hasMany(RevisionHistory::class)->orderByDesc('created_at');
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

    public function recordApprovalSignature(string $approvalType, User $approver): void
    {
        $payload = json_encode([
            'request_id' => $this->id,
            'approver_id' => $approver->id,
            'type' => $approvalType,
            'time' => now()->toISOString(),
        ]);

        $this->{$approvalType . '_approval_signature'} = hash_hmac('sha256', $payload, config('app.key'));
        $meta = $this->approval_signature_meta ?? [];

        if ($approvalType === 'equipment') {
            $equipmentMeta = $meta[$approvalType] ?? [];
            if (! is_array($equipmentMeta) || array_is_list($equipmentMeta) === false) {
                $equipmentMeta = $equipmentMeta ? [$equipmentMeta] : [];
            }
            $equipmentMeta[] = $payload;
            $meta[$approvalType] = $equipmentMeta;
        } else {
            $meta[$approvalType] = $payload;
        }

        if ($approvalType === 'final') {
            $this->final_approval_signature = $this->{$approvalType . '_approval_signature'};
            $meta['final'] = $payload;
            $signedFile = $this->snapshotSignatureForRecord($approver, 'final');
            if ($signedFile) {
                $this->final_approval_signature_file = $signedFile;
            }
        } else {
            $signedFile = $this->snapshotSignatureForRecord($approver, $approvalType);
            if ($signedFile) {
                $this->{$approvalType . '_approval_signature_file'} = $signedFile;
            }
        }

        $this->approval_signature_meta = $meta;
        $this->save();
    }

    public function snapshotSignatureForRecord(User $user, string $recordType): ?string
    {
        if (! $user->e_signature_file) {
            return null;
        }

        $sourcePath = 'documents/e_signature/users/' . $user->e_signature_file;
        if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($sourcePath)) {
            return null;
        }

        $extension = pathinfo($user->e_signature_file, PATHINFO_EXTENSION) ?: 'png';
        $targetDirectory = $recordType === 'requestor' ? 'documents/e_signature/requests' : 'documents/e_signature/approvals';
        $targetFilename = $recordType . '_' . $this->id . '_' . now()->format('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($extension);

        \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory($targetDirectory);
        \Illuminate\Support\Facades\Storage::disk('local')->copy($sourcePath, $targetDirectory . '/' . $targetFilename);

        return $targetFilename;
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

        $range = self::normalizeScheduleRange($this->start_date, $this->start_time, $this->end_date ?? $this->start_date, $this->end_time ?? $this->start_time);
        $startDatetime = $range['start'];
        $endDatetime = $range['end'];

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
        // If the relationship is already loaded, trust it first.
        if ($this->relationLoaded('requestVenues')) {
            $relationalVenues = $this->resolveVenueRelationNames();

            if (!empty($relationalVenues)) {
                return $this->preferRelationValuesOverLegacy(
                    $relationalVenues,
                    $this->getVenueNamesFromLegacyData()
                );
            }
        }

        // No relational table available → legacy fallback.
        if (!Schema::hasTable('request_venues')) {
            return $this->getVenueNamesFromLegacyData();
        }

        $this->loadMissing('requestVenues.venue');

        $relationalVenues = $this->resolveVenueRelationNames();

        if (!empty($relationalVenues)) {
            return $this->preferRelationValuesOverLegacy(
                $relationalVenues,
                $this->getVenueNamesFromLegacyData()
            );
        }

        return $this->getVenueNamesFromLegacyData();
    }

    public function getEquipmentItems(): array
    {
        // Prefer explicitly loaded relational data first.
        if ($this->relationLoaded('requestEquipment')) {
            $relationalEquipment = $this->resolveEquipmentRelationNames();

            if (!empty($relationalEquipment)) {
                return $this->preferRelationValuesOverLegacy(
                    $relationalEquipment,
                    $this->getEquipmentItemsFromLegacyData()
                );
            }
        }

        if (!Schema::hasTable('request_equipment')) {
            return $this->getEquipmentItemsFromLegacyData();
        }

        $this->loadMissing('requestEquipment.equipment');

        $relationalEquipment = $this->resolveEquipmentRelationNames();

        if (!empty($relationalEquipment)) {
            return $this->preferRelationValuesOverLegacy(
                $relationalEquipment,
                $this->getEquipmentItemsFromLegacyData()
            );
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

    public static function normalizeScheduleRange($startDateValue, $startTimeValue, $endDateValue = null, $endTimeValue = null): array
    {
        $start = self::normalizeScheduleValue($startDateValue, $startTimeValue);
        $effectiveEndDate = $endDateValue ?? $startDateValue;
        $effectiveEndTime = $endTimeValue ?? $startTimeValue ?? '00:00';
        $end = self::normalizeScheduleValue($effectiveEndDate, $effectiveEndTime, $start);

        if ($start && $end && $end->lte($start)) {
            $end = $start->copy()->addDay()->setTime(0, 0, 0);
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    public static function resolveReservationDuration(string $duration, $startDateValue, $startTimeValue = null, $endDateValue = null, $endTimeValue = null): array
    {
        $normalizedDuration = strtolower(trim($duration));
        $effectiveStartDate = $startDateValue ?? now()->toDateString();
        $effectiveEndDate = $endDateValue ?? $effectiveStartDate;

        if (in_array($normalizedDuration, ['whole_day', 'whole-day', 'whole day'], true)) {
            return self::normalizeScheduleRange(
                $effectiveStartDate,
                '08:00',
                $effectiveEndDate,
                '23:59'
            );
        }

        $effectiveStartTime = $startTimeValue ?? '08:00';
        $effectiveEndTime = $endTimeValue ?? $effectiveStartTime;

        return self::normalizeScheduleRange(
            $effectiveStartDate,
            $effectiveStartTime,
            $effectiveEndDate,
            $effectiveEndTime
        );
    }

    protected static function normalizeScheduleValue($dateValue, $timeValue, ?\Carbon\Carbon $fallback = null): ?\Carbon\Carbon
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
            return self::normalizeScheduleRange($this->start_date, $this->start_time, $this->end_date ?? $this->start_date, $this->end_time ?? $this->start_time ?? '00:00')['start'] ?? \Carbon\Carbon::parse('00:00');
        }

        $this->loadMissing('reservationSchedule');

        if ($this->relationLoaded('reservationSchedule') && $this->reservationSchedule) {
            return $this->reservationSchedule->start_datetime;
        }

        return $this->reservationSchedule?->start_datetime
            ?? self::normalizeScheduleRange($this->start_date, $this->start_time, $this->end_date ?? $this->start_date, $this->end_time ?? $this->start_time ?? '00:00')['start'] ?? \Carbon\Carbon::parse('00:00');
    }

    public function getRequestedEndDateTime(): \Carbon\Carbon
    {
        if (!Schema::hasTable('reservation_schedules')) {
            return self::normalizeScheduleRange($this->start_date, $this->start_time, $this->end_date ?? $this->start_date, $this->end_time ?? $this->start_time ?? '00:00')['end'] ?? \Carbon\Carbon::parse('00:00');
        }

        $this->loadMissing('reservationSchedule');

        if ($this->relationLoaded('reservationSchedule') && $this->reservationSchedule) {
            return $this->reservationSchedule->end_datetime;
        }

        return $this->reservationSchedule?->end_datetime
            ?? self::normalizeScheduleRange($this->start_date, $this->start_time, $this->end_date ?? $this->start_date, $this->end_time ?? $this->start_time ?? '00:00')['end'] ?? \Carbon\Carbon::parse('00:00');
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
    public function markEquipmentReturned(int $custodianId, array $returnedEquipment, ?string $notes = null, array $damageDetails = [], array $missingDetails = [], array $damageRemarks = [], array $missingRemarks = []): void
    {
        // ❗ Ensure event is finished, defaulting to end_date when start_date is not set
        $eventEndDate = $this->end_date ?? $this->start_date;
        if ($eventEndDate && now()->lt($eventEndDate)) {
            throw new \Exception('Event is not yet finished.');
        }

        if ($this->equipment_returned_status === 'fulfilled') {
            throw new \Exception('Equipment has already been recorded as fulfilled for this request.');
        }

        $requestedQuantities = $this->resolvedQuantities();
        $returnedItems = $this->equipment_returned_items ?? [];
        $prevCustodianReturn = $returnedItems[$custodianId]['equipment'] ?? [];

        $returnedItems[$custodianId] = [
            'equipment'   => [],
            'returned_at' => now()->toISOString(),
            'notes'       => $notes,
        ];

        $damagedTotals = [];
        $missingTotals = [];
        $damagedTotal = 0;
        $missingTotal = 0;

        foreach ($damageDetails as $itemName => $qty) {
            $damagedTotals[$itemName] = max(0, (int) $qty);
        }

        foreach ($missingDetails as $itemName => $qty) {
            $missingTotals[$itemName] = max(0, (int) $qty);
        }

        $damagedTotal = array_sum($damagedTotals);
        $missingTotal = array_sum($missingTotals);
        $shouldRestoreInventory = $damagedTotal === 0 && $missingTotal === 0;

        foreach ($returnedEquipment as $itemName => $qty) {
            $qty = (int) $qty;
            if ($qty <= 0) continue;

            $eq = $this->findEquipmentForCustodian($itemName, $custodianId);
            if (!$eq) continue;

            $alreadyReturnedByCustodian = (int) ($prevCustodianReturn[$itemName] ?? 0);
            $delta = $qty - $alreadyReturnedByCustodian;

            if ($delta <= 0) {
                $returnedItems[$custodianId]['equipment'][$itemName] = $alreadyReturnedByCustodian;
                continue;
            }

            $requestedQty = (int) ($requestedQuantities[$itemName] ?? 0);

            $totalReturnedSoFar = 0;
            foreach ($returnedItems as $cId => $custodianData) {
                if ($cId == $custodianId) continue;
                $totalReturnedSoFar += (int) ($custodianData['equipment'][$itemName] ?? 0);
            }

            $totalReturnedSoFar += $alreadyReturnedByCustodian;
            $remainingNeeded = max(0, $requestedQty - $totalReturnedSoFar);
            $toRelease = min($delta, $remainingNeeded);
            $newTotalByCustodian = $alreadyReturnedByCustodian + $toRelease;

            $returnedItems[$custodianId]['equipment'][$itemName] = $newTotalByCustodian;

            if ($toRelease > 0 && $shouldRestoreInventory) {
                $locked = \App\Models\Equipment::whereKey($eq->id)->lockForUpdate()->first();
                if ($locked) {
                    $locked->quantity_available = min($locked->quantity, $locked->quantity_available + $toRelease);
                    $locked->save();
                }
            }
        }

        $allReturned = $this->isAllEquipmentReturned($returnedItems);
        $damageRemarkText = $this->flattenReturnRemarks($damageRemarks);
        $missingRemarkText = $this->flattenReturnRemarks($missingRemarks);

        $this->update([
            'equipment_returned_items' => $returnedItems,
            'equipment_returned_status' => $allReturned ? 'fulfilled' : 'partial',
            'equipment_returned_date' => $allReturned ? now() : $this->equipment_returned_date,
            'equipment_return_notes' => $notes,
            'equipment_return_damaged_quantity' => $damagedTotal,
            'equipment_return_missing_quantity' => $missingTotal,
            'equipment_return_damage_remarks' => $damageRemarkText,
            'equipment_return_missing_remarks' => $missingRemarkText,
        ]);

        $this->addHistory(
            'equipment_returned',
            'Equipment recorded as returned by custodian ID: ' . $custodianId . ($damagedTotal > 0 ? ' Damaged units: ' . $damagedTotal : '') . ($missingTotal > 0 ? ' Missing units: ' . $missingTotal : ''),
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

    private function flattenReturnRemarks(array $remarks): ?string
    {
        if (empty($remarks)) {
            return null;
        }

        $values = [];
        foreach ($remarks as $itemName => $remark) {
            if (is_array($remark)) {
                $values[] = implode('; ', array_filter(array_map('strval', $remark)));
                continue;
            }

            $text = trim((string) $remark);
            if ($text !== '') {
                $values[] = $text;
            }
        }

        $combined = implode('; ', array_filter($values, fn ($value) => $value !== ''));

        return $combined !== '' ? $combined : null;
    }

    public function isOverdue(): bool
    {
        $eventEndDate = $this->end_date ?? $this->start_date;
        return $eventEndDate && now()->gt($eventEndDate)
            && $this->equipment_returned_status !== 'returned';
    }

    /**
     * Get consolidated custodian approval details for notification
     * Returns: [venue_custodian, equipment_custodians[], supply_office]
     */
    public function getConsolidatedApprovalDetails(): array
    {
        $venueCustodian = null;
        $equipmentCustodians = [];
        $supplyOffice = null;

        // Extract venue custodian from history
        if ($this->venue_status === 'approved') {
            $venueHistory = $this->histories()
                ->where('action', 'like', '%venue%')
                ->whereIn('action', ['venue_status_approved', 'custodian_endorsed'])
                ->latest('occurred_at')
                ->first();
            $venueCustodian = $venueHistory?->user?->name;
        }

        // Extract equipment custodians from statuses and history
        if ($this->equipment_status === 'approved' && !empty($this->equipment_custodian_statuses)) {
            $custodianIds = array_keys($this->equipment_custodian_statuses ?? []);
            foreach ($custodianIds as $custodianId) {
                $user = User::find($custodianId);
                if ($user && $this->getCustodianEquipmentStatus($custodianId) === 'approved') {
                    $equipmentCustodians[] = $user->name;
                }
            }
        }

        // Extract supply office from approved_by
        if ($this->status === 'approved' && !empty($this->approved_by)) {
            $supplyOffice = $this->approved_by;
        }

        return [
            'venue_custodian' => $venueCustodian,
            'equipment_custodians' => array_values(array_filter($equipmentCustodians)),
            'supply_office' => $supplyOffice,
        ];
    }

    /**
     * Check if this request can be revised (not locked by equipment return)
     */
    public function canBeRevised(): bool
    {
        // Locked if equipment return has started (partial or fully returned)
        if (in_array($this->equipment_returned_status, ['partial', 'returned', 'fulfilled', 'overdue'], true)) {
            return false;
        }

        // Can revise if status is approved, pending, or needs_reschedule
        return in_array($this->status, ['approved', 'pending', 'needs_reschedule'], true);
    }

    /**
     * Get reason why revision is locked
     */
    public function getRevisionLockReason(): ?string
    {
        if (in_array($this->equipment_returned_status, ['partial', 'returned', 'fulfilled', 'overdue'], true)) {
            return 'Equipment fulfillment has started. Cannot revise reservation.';
        }

        return null;
    }

    /**
     * Capture current state for revision history
     */
    public function getCurrentState(): array
    {
        return [
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'venue' => $this->getVenueNames(),
            'equipment' => $this->getEquipmentItems(),
            'equipment_quantities' => $this->getEquipmentQuantities(),
        ];
    }
}
