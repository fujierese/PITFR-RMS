<?php
namespace App\Http\Controllers;
 
use App\Models\FacilityRequest;
use App\Models\College;
use App\Models\Department;
use App\Models\User;
use App\Models\Venue;
use App\Services\AvailabilityService;
use App\Services\VenueEquipmentPolicy;
use App\Http\Controllers\Concerns\ManagesAccountSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class RequestorController extends Controller
{
    use ManagesAccountSettings;

    public function __construct(private readonly AvailabilityService $availabilityService)
    {
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            abort(403);
        }

        return $user;
    }

    private const VENUE_OPTIONS = [
        'Conference Hall & Interaction Center (CHIC)', 'Gymnasium', 'Balay Alumni',
        'Oval Grounds', 'Covered Court', 'Volleyball Court',
    ];
    private const EQUIPMENT_OPTIONS = [
        'Sound System', 'Canopies', 'Industrial Fans',
        'Iwata Cooler Fans', 'Tables', 'Wireless Microphones', 'Non-Wireless Microphones', 'Monobloc Chairs',
    ];

    private static function canonicalizeEquipmentName(string $name): string
    {
        $normalized = trim((string) $name);
        if ($normalized === '') {
            return '';
        }

        $lookup = [
            'wireless microphone' => 'Wireless Microphones',
            'wireless microphones' => 'Wireless Microphones',
            'non-wireless microphone' => 'Non-Wireless Microphones',
            'non-wireless microphones' => 'Non-Wireless Microphones',
            'non wireless microphone' => 'Non-Wireless Microphones',
            'non wireless microphones' => 'Non-Wireless Microphones',
            'chairs' => 'Monobloc Chairs',
            'monobloc chairs' => 'Monobloc Chairs',
            'monobloc chair' => 'Monobloc Chairs',
        ];

        $lower = mb_strtolower($normalized);
        return $lookup[$lower] ?? $normalized;
    }

    private static function normalizeEquipmentSelection(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            $expanded = self::canonicalizeEquipmentName((string) $item);
            if ($expanded === '') {
                continue;
            }
            $normalized[] = $expanded;
        }

        return array_values(array_unique($normalized));
    }

    public function index(Request $request)
    {
        $user = $this->currentUser();
        $search = trim((string) $request->query('search', ''));
        $statusFilter = strtolower((string) $request->query('status', ''));
        $venueFilter = trim((string) $request->query('venue', ''));
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $sort = $request->query('sort', 'latest');

        $query = FacilityRequest::with(['requestVenues', 'requestEquipment', 'reservationSchedule'])
            ->where('requested_by_id', $user->id);

        if ($search !== '') {
            $searchTerm = '%' . mb_strtolower($search) . '%';
            $query->where(function ($filterQuery) use ($searchTerm): void {
                $filterQuery->whereRaw('LOWER(control_number) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(name_of_activity) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(department) LIKE ?', [$searchTerm])
                    ->orWhereHas('requester', function ($requesterQuery) use ($searchTerm): void {
                        $requesterQuery->whereRaw('LOWER(office_or_organization) LIKE ?', [$searchTerm]);
                    })
                    ->orWhereRaw('LOWER(status) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(start_date) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(end_date) LIKE ?', [$searchTerm])
                    ->orWhereHas('requestVenues', function ($venueQuery) use ($searchTerm): void {
                        $venueQuery->whereRaw('LOWER(name) LIKE ?', [$searchTerm]);
                    })
                    ->orWhereHas('requestEquipment', function ($equipmentQuery) use ($searchTerm): void {
                        $equipmentQuery->whereRaw('LOWER(name) LIKE ?', [$searchTerm]);
                    })
                    ->orWhere(function ($legacyQuery) use ($searchTerm): void {
                        $legacyQuery->whereRaw('LOWER(venue) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(equipment) LIKE ?', [$searchTerm]);
                    });
            });
        }

        if ($statusFilter !== '') {
            $query->whereRaw('LOWER(status) = ?', [$statusFilter]);
        }

        if ($venueFilter !== '') {
            $query->where(function ($venueQuery) use ($venueFilter): void {
                $venueQuery->whereHas('requestVenues', function ($relatedVenueQuery) use ($venueFilter): void {
                    $relatedVenueQuery->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($venueFilter) . '%']);
                })->orWhere(function ($legacyVenueQuery) use ($venueFilter): void {
                    $legacyVenueQuery->whereRaw('LOWER(venue) LIKE ?', ['%' . mb_strtolower($venueFilter) . '%']);
                });
            });
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $query->whereDate('start_date', '>=', $dateFrom);
        }

        if ($dateTo !== null && $dateTo !== '') {
            $query->whereDate('start_date', '<=', $dateTo);
        }

        $query->orderBy($sort === 'oldest' ? 'start_date' : 'start_date', $sort === 'oldest' ? 'asc' : 'desc')
            ->orderBy($sort === 'oldest' ? 'created_at' : 'created_at', $sort === 'oldest' ? 'asc' : 'desc');

        $requests = $query->get();
        $equipment = \App\Models\Equipment::where('is_active', true)->whereNotIn('id', [2, 10])->get();

        // Separate by future/past dates and approval status
        $today = now()->toDateString();
        $upcomingRequests = $requests->filter(function ($request) use ($today) {
            $schedule = $request->reservationSchedule;
            $start = $schedule ? $schedule->start_datetime : $request->start_date;
            return $request->status === 'approved' && $start && $start->toDateString() >= $today;
        });
        $pastRequests = $requests->filter(function ($request) use ($today) {
            $schedule = $request->reservationSchedule;
            $start = $schedule ? $schedule->start_datetime : $request->start_date;
            return $start && $start->toDateString() < $today;
        });
 
        $activeRequests    = $requests->where('equipment_returned_status', '!=', 'returned');
        $completedRequests = $requests->where('equipment_returned_status', 'returned');
        $latestRequest     = $requests->sortByDesc('created_at')->first();
        $nextPendingRequest = $activeRequests->where('status', 'pending')->sortBy('start_date')->first();
        $currentSemester   = $this->determineSemester(now());
        $profileMeta       = [];

        if ($user->isStudent()) {
            if ($user->studentProgramLabel) {
                $profileMeta['Program / Course'] = $user->studentProgramLabel;
            }

            if ($user->studentYearLevelLabel) {
                $profileMeta['Year Level'] = $user->studentYearLevelLabel;
            }

            $profileMeta['Semester'] = $currentSemester;
        } elseif ($user->isFaculty()) {
            if ($user->department) {
                $profileMeta['Department'] = $user->department;
            }
        } elseif ($user->isOutsider()) {
            if ($user->office_or_organization) {
                $profileMeta['Organization'] = $user->office_or_organization;
            } else {
                $profileMeta['External Requestor'] = null;
            }
        }

        $venueCapacityMap = [];
        $venueRecords = Venue::query()->where('is_active', true)->orderBy('name')->get();
        $venueOptions = array_values(array_unique(array_merge(
            $venueRecords->pluck('name')->filter()->values()->all(),
            self::VENUE_OPTIONS
        )));

        foreach ($venueOptions as $venueName) {
            $venueCapacityMap[$venueName] = $this->availabilityService->getVenueCapacity($venueName);
        }

        $departments = Department::query()->orderBy('name')->get();
        $colleges = College::query()->with('departments')->orderBy('name')->get();
        $profileDepartment = $user->department_id
            ? $departments->firstWhere('id', (int) $user->department_id)
            : $departments->firstWhere('name', $user->department);
        $profileCollegeId = $user->college_id ?? $profileDepartment?->college_id;
        $profileDepartmentId = $user->department_id ?? $profileDepartment?->id;

        return view('requestor.index', [
            'user'              => $user,
            'requests'          => $requests,
            'upcomingRequests'  => $upcomingRequests,
            'pastRequests'      => $pastRequests,
            'activeRequests'    => $activeRequests,
            'completedRequests' => $completedRequests,
            'latestRequest'     => $latestRequest,
            'nextPendingRequest'=> $nextPendingRequest,
            'currentSemester'   => $currentSemester,
            'profileMeta'       => $profileMeta,
            'activeTab'         => $request->get('tab', 'calendar'),
            'venueOptions'      => $venueOptions,
            'venueRecords'      => $venueRecords,
            'colleges'          => $colleges,
            'departments'       => $departments,
            'profileCollegeId'  => $profileCollegeId,
            'profileDepartmentId' => $profileDepartmentId,
            'studentOrganizations' => $user->isStudent()
                ? $user->studentOrganizations()->orderBy('name')->get()
                : collect(),
            'equipOptions'      => self::EQUIPMENT_OPTIONS,
            'controlNumber'     => FacilityRequest::generateControlNumber(),
            'equipment'         => $equipment,
            'venueCapacityMap'  => $venueCapacityMap,
        ]);
    }

    public function settings(Request $request)
    {
        $user = $this->currentUser();

        return view('requestor.settings', [
            'user' => $user,
            'profileMeta' => $this->buildProfileMeta($user),
            'colleges' => \App\Models\College::with('departments')->orderBy('name')->get(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $this->currentUser();
        $validated = $request->validate([
            'surname' => ['nullable', 'string', 'max:100'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'contact_number' => ['nullable', 'string', 'max:255'],
        ]);

        if ($user->isStudent()) {
            $validated = array_merge($validated, $request->validate([
                    'school_id_number' => ['sometimes', 'nullable', 'string', 'regex:/^\d{2}-\d{4}-\d{3}$/'],
                    'college_id' => ['sometimes', 'nullable', 'exists:colleges,id'],
                    'department_id' => ['sometimes', 'nullable', 'exists:departments,id'],
            ]));
        } elseif ($user->isFaculty()) {
            $validated = array_merge($validated, $request->validate([
                'faculty_id' => ['sometimes', 'nullable', 'string', 'max:50', 'unique:users,faculty_id,' . $user->id],
                'position' => ['sometimes', 'nullable', 'string', 'max:100'],
                'college_id' => ['sometimes', 'nullable', 'exists:colleges,id'],
                'department_id' => ['sometimes', 'nullable', 'exists:departments,id'],
            ]));
        }

        if ($user->isOutsider() || $user->isStudentOrganization()) {
            $validated['office_or_organization'] = $request->validate([
                'office_or_organization' => ['nullable', 'string', 'max:255'],
            ])['office_or_organization'];
        }

        $user->fill($validated);
        $user->name = User::formatFullName($validated['surname'] ?? $user->surname, $validated['first_name'] ?? $user->first_name, $validated['middle_name'] ?? $user->middle_name, $validated['suffix'] ?? $user->suffix);
        if (isset($validated['department_id']) && isset($validated['college_id'])) {
            $department = \App\Models\Department::find($validated['department_id']);
            if ($department && (int) $department->college_id !== (int) $validated['college_id']) {
                return back()->withErrors(['department_id' => 'Please select a department under the selected college.'])->withInput();
            }
            $user->department = $department?->name;
        }
        $user->save();

        return redirect()->route('requestor.settings')->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = $this->currentUser();
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return redirect()->route('requestor.settings')->with('success', 'Password updated successfully.');
    }

    public function updateNotificationPreferences(Request $request)
    {
        return $this->saveNotificationPreferences($request, 'requestor.settings');
    }

    public function updateSignature(Request $request)
    {
        return $this->saveSignature($request, 'requestor.settings');
    }

    public function accountSignature(?User $user = null)
    {
        $targetUser = $user ?? Auth::user();

        if (! $targetUser instanceof User || ! $targetUser->e_signature_file) {
            abort(404);
        }

        if (Auth::id() !== $targetUser->id && ! Auth::user()?->isAdmin()) {
            abort(403);
        }

        $path = 'documents/e_signature/users/' . $targetUser->e_signature_file;

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($path));
    }

    public function approvalSignature(FacilityRequest $facilityRequest, string $type)
    {
        $this->authorize('view', $facilityRequest);

        $field = match ($type) {
            'venue' => 'venue_approval_signature_file',
            'equipment' => 'equipment_approval_signature_file',
            'final' => 'final_approval_signature_file',
            default => null,
        };

        if (! $field || ! $facilityRequest->{$field}) {
            abort(404);
        }

        $path = 'documents/e_signature/approvals/' . $facilityRequest->{$field};

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($path));
    }

    public function edit(FacilityRequest $facilityRequest)
    {
        $user = $this->currentUser();
        $shouldAllowReschedule = $facilityRequest->status === 'needs_reschedule'
            || $facilityRequest->venue_status === 'needs_reschedule'
            || $facilityRequest->equipment_status === 'needs_reschedule';

        if ($facilityRequest->requested_by_id !== $user->id || !$shouldAllowReschedule) {
            abort(403);
        }

        $equipmentQuantityLimits = [];
        foreach ($facilityRequest->getEquipmentItems() as $itemName) {
            $equipmentQuantityLimits[$itemName] = $this->getEditableEquipmentQuantityLimit($facilityRequest, $itemName);
        }

        $venueCapacityMap = [];
        foreach (self::VENUE_OPTIONS as $venueName) {
            $venueCapacityMap[$venueName] = $this->availabilityService->getVenueCapacity($venueName);
        }

        return view('requestor.edit', [
            'request' => $facilityRequest,
            'user' => $user,
            'venueOptions' => self::VENUE_OPTIONS,
            'equipmentOptions' => self::EQUIPMENT_OPTIONS,
            'controlNumber' => $facilityRequest->control_number,
            'equipment' => \App\Models\Equipment::where('is_active', true)->whereNotIn('id', [2, 10])->get(),
            'equipmentQuantityLimits' => $equipmentQuantityLimits,
            'venueCapacityMap' => $venueCapacityMap,
        ]);
    }

    public function update(Request $request, FacilityRequest $facilityRequest)
    {
        $user = $this->currentUser();
        $shouldAllowReschedule = $facilityRequest->status === 'needs_reschedule'
            || $facilityRequest->venue_status === 'needs_reschedule'
            || $facilityRequest->equipment_status === 'needs_reschedule';

        if ($facilityRequest->requested_by_id !== $user->id || !$shouldAllowReschedule) {
            abort(403);
        }

        $request->merge([
            'start_date' => $request->input('start_date', $request->input('requesting_date')),
            'end_date' => $request->input('end_date', $request->input('requesting_end_date')),
            'start_time' => $request->input('start_time', $request->input('time')),
        ]);

        $reservationDuration = strtolower((string) $request->input('reservation_duration', 'specific_time'));
        if (in_array($reservationDuration, ['whole_day', 'whole-day', 'whole day'], true)) {
            $request->merge([
                'start_time' => '08:00',
                'end_time' => '23:59',
            ]);
        }

        $isNeedsReschedule = $facilityRequest->status === 'needs_reschedule';

        $rules = [
            'reservation_duration' => ['nullable', 'in:specific_time,whole_day,whole-day,whole day'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'venue' => ['required', 'string', Rule::in(array_merge(self::VENUE_OPTIONS, ['Others (specify)']))],
            'other_venue' => ['nullable', 'string', 'max:200', 'required_if:venue,Others (specify)'],
            'equipment' => ['required', 'array', 'min:1'],
            'equipment_quantities' => ['required', 'array'],
        ];

        if (!$isNeedsReschedule) {
            $rules['department'] = ['required', 'string', 'max:100'];
            $rules['name_of_activity'] = ['required', 'string', 'max:200'];
        }

        $validated = $request->validate($rules);

        $reservationDuration = strtolower((string) ($validated['reservation_duration'] ?? 'specific_time'));
        $scheduleRange = FacilityRequest::resolveReservationDuration(
            $reservationDuration,
            $validated['start_date'],
            $validated['start_time'],
            $validated['end_date'] ?? $validated['start_date'],
            $validated['end_time']
        );
        $validated['start_time'] = $scheduleRange['start']->format('H:i');
        $validated['end_time'] = $scheduleRange['end']->format('H:i');
        $validated['end_date'] = $scheduleRange['end']?->toDateString() ?? $validated['end_date'] ?? $validated['start_date'];

        $selectedEquipment = array_values(array_filter($validated['equipment'] ?? [], fn($item) => !empty($item)));
        $selectedQuantities = $validated['equipment_quantities'] ?? [];
        $quantities = [];

        // Apply venue-specific equipment rules during edit
        $selectedVenues = (array) ($validated['venue'] ?? []);
        if (is_string($selectedVenues)) {
            $selectedVenues = [$selectedVenues];
        }
        
        if (!empty($selectedVenues)) {
            $venueName = $selectedVenues[0];
            
            // Add default equipment required for this venue if not already selected
            $defaultEquipment = VenueEquipmentPolicy::getDefaultEquipment($venueName);
            foreach ($defaultEquipment as $requiredItem) {
                if (!in_array($requiredItem, $selectedEquipment)) {
                    $selectedEquipment[] = $requiredItem;
                }
            }
            
            // Check for incompatible equipment
            $incompatible = VenueEquipmentPolicy::getIncompatibleEquipment($venueName);
            $requestedIncompatible = array_intersect($selectedEquipment, $incompatible);
            if (!empty($requestedIncompatible)) {
                $incompatibleList = implode(', ', $requestedIncompatible);
                return back()->withErrors(['equipment' => "{$venueName} does not support the following equipment: {$incompatibleList}. Please select a different venue or remove these items."])->withInput();
            }
        }

        foreach ($selectedEquipment as $itemName) {
            $quantity = (int) ($selectedQuantities[$itemName] ?? 0);
            if ($quantity <= 0) {
                return back()->withErrors(['equipment' => 'Please select required equipment and quantity.'])->withInput();
            }

            $maxAllowed = $this->getEditableEquipmentQuantityLimit($facilityRequest, $itemName);
            if ($quantity > $maxAllowed) {
                return back()->withErrors(['equipment' => "Sorry, only {$maxAllowed} unit(s) of '{$itemName}' can be requested while editing this pending request."])->withInput();
            }

            $quantities[$itemName] = $quantity;
        }

        $validated['equipment_quantities'] = $quantities;

        $startDateTime = $scheduleRange['start'];
        $endDateTime = $scheduleRange['end'];

        if ($endDateTime->lte($startDateTime)) {
            return back()->withErrors(['end_date' => 'End date and time must be after the start date and time. For overnight bookings, set the end date to the next day.'])->withInput();
        }

        foreach ($quantities as $itemName => $quantity) {
            $availability = $this->checkEquipmentAvailabilityForEdit($itemName, $quantity, $startDateTime, $endDateTime, $facilityRequest->id);
            if (!$availability['available']) {
                return back()->withErrors(['equipment' => $availability['message'] ?? "Sorry, only {$availability['available_qty']} unit(s) of '{$itemName}' available."])->withInput();
            }
        }

        $submittedVenue = $request->input('venue');
        if (is_array($submittedVenue)) {
            $submittedVenue = reset($submittedVenue);
        }
        $submittedVenue = trim((string) $submittedVenue);
        $allowedVenues = Venue::query()->where('is_active', true)->pluck('name')->filter()->values()->all();
        if ($allowedVenues === []) {
            $allowedVenues = self::VENUE_OPTIONS;
        }
        $venue = in_array($submittedVenue, $allowedVenues, true) ? [$submittedVenue] : [];

        if (!empty($venue)) {
            $venueAvailability = $this->checkVenueAvailabilityForEdit($submittedVenue, $startDateTime, $endDateTime, $facilityRequest->id);
            if (!$venueAvailability['available']) {
                return back()->withErrors(['venue' => $venueAvailability['message'] ?? 'Scheduling conflict detected.'])->withInput();
            }
        }

        $fillData = [
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? $validated['start_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'venue' => $venue,
            'equipment' => array_values($validated['equipment']),
            'equipment_quantities' => $validated['equipment_quantities'],
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
            'approved_by' => null,
            'approved_by_id' => null,
            'approved_date' => null,
            'notes' => null,
            'venue_notes' => null,
            'equipment_notes' => null,
            'equipment_custodian_statuses' => [],
        ];

        if (!$isNeedsReschedule) {
            $fillData['department'] = $validated['department'];
            $fillData['name_of_activity'] = $validated['name_of_activity'];
        }

        $facilityRequest->fill($fillData);

        $facilityRequest->save();
        $facilityRequest->syncRelationalItems();

        if ($isNeedsReschedule) {
            $facilityRequest->addHistory('needs_reschedule', 'Reservation rescheduled by requestor after Priority Override.');
        }

        $facilityRequest->status = 'pending';
        $facilityRequest->venue_status = 'pending';
        $facilityRequest->equipment_status = 'pending';
        $facilityRequest->approved_by = null;
        $facilityRequest->approved_by_id = null;
        $facilityRequest->approved_date = null;
        $facilityRequest->notes = null;
        $facilityRequest->venue_notes = null;
        $facilityRequest->equipment_notes = null;
        $facilityRequest->save();

        // Notify custodians about the rescheduled request so review workflow restarts
        $equipmentCustodianIds = [];
        if (!empty($facilityRequest->equipment)) {
            $equipmentCustodianIds = \App\Models\Equipment::whereIn('name', $facilityRequest->equipment)
                ->pluck('custodian_id')
                ->filter()
                ->unique()
                ->toArray();
        }

        $venueCustodianIds = \App\Models\Venue::whereIn('name', $facilityRequest->venue)
            ->pluck('custodian_id')
            ->filter()
            ->unique()
            ->toArray();

        $custodianIds = array_values(array_unique(array_merge($equipmentCustodianIds, $venueCustodianIds)));

        if (!empty($custodianIds)) {
            $custodians = \App\Models\User::whereIn('id', $custodianIds)->get();
            try {
                Notification::send($custodians, new \App\Notifications\NewFacilityRequestNotification($facilityRequest));
            } catch (\Throwable $e) {
                Log::warning('Failed to notify custodians for rescheduled facility request.', [
                    'facility_request_id' => $facilityRequest->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('request.show', $facilityRequest->id)->with('success', 'Request updated successfully.');
    }

    private function checkEquipmentAvailabilityForEdit(string $itemName, int $quantity, Carbon $requestedStart, Carbon $requestedEnd, int $excludeRequestId): array
    {
        if (!\App\Models\Equipment::whereRaw('LOWER(name) = ?', [strtolower($itemName)])->exists()) {
            return [
                'available' => true,
                'message' => null,
                'available_qty' => $quantity,
                'total' => $quantity,
            ];
        }

        return $this->availabilityService->checkEquipmentAvailability(
            $itemName,
            $quantity,
            $requestedStart,
            $requestedEnd,
            $excludeRequestId
        );
    }

    private function checkVenueAvailabilityForEdit(string $venueName, Carbon $requestedStart, Carbon $requestedEnd, int $excludeRequestId): array
    {
        return $this->availabilityService->checkVenueAvailability(
            $venueName,
            $requestedStart,
            $requestedEnd,
            $excludeRequestId
        );
    }

    private function getEditableEquipmentQuantityLimit(FacilityRequest $facilityRequest, string $itemName): int
    {
        $equipment = \App\Models\Equipment::whereRaw('LOWER(name) = ?', [strtolower($itemName)])->first();
        if (!$equipment) {
            return max(1, (int) ($facilityRequest->getEquipmentQuantities()[$itemName] ?? 0));
        }

        $originalQuantity = (int) ($facilityRequest->getEquipmentQuantities()[$itemName] ?? 0);
        $outstandingQuantity = 0;

        $requests = \App\Models\FacilityRequest::where(function ($query) {
            $query->where('status', 'approved')
                ->orWhere(function ($pendingQuery) {
                    $pendingQuery->where('status', 'pending')
                        ->where('venue_status', '!=', 'rejected')
                        ->where('equipment_status', '!=', 'rejected');
                });
        })
            ->where(function ($query) {
                $query->where('equipment_returned_status', '!=', 'returned')
                    ->where('equipment_returned_status', '!=', 'overdue');
            })
            ->where('id', '!=', $facilityRequest->id)
            ->get();

        foreach ($requests as $request) {
            $requestQuantities = $request->getEquipmentQuantities();
            if (!empty($requestQuantities) && isset($requestQuantities[$itemName])) {
                $outstandingQuantity += (int) $requestQuantities[$itemName];
            } elseif (in_array($itemName, $request->getEquipmentItems(), true)) {
                $outstandingQuantity += 1;
            }
        }

        $availableStock = max(0, (int) $equipment->quantity - $outstandingQuantity);

        return $availableStock + $originalQuantity;
    }

    private function buildProfileMeta($user): array
    {
        $meta = [];
        if (!empty($user->department)) {
            $meta['Department'] = $user->department;
        }
        if (!empty($user->contact_number)) {
            $meta['Contact'] = $user->contact_number;
        }
        if (!empty($user->office_or_organization)) {
            $meta['Organization'] = $user->office_or_organization;
        }
        return $meta;
    }

    private function determineSemester($date): string
    {
        $month = (int) $date->format('m');

        if ($month >= 6 && $month <= 10) {
            return 'Second Semester';
        }

        if ($month === 11 || $month === 12 || $month <= 3) {
            return 'First Semester';
        }

        return 'Summer Term';
    }
 
    public function store(Request $request)
    {
        // Server-side guard: only registered requestors may submit requests
        $user = $this->currentUser();
        if ($user && $user->is_active === false) {
            Auth::logout();
            return redirect()->route('login')->withErrors(['authorization' => 'This account is deactivated. Contact an administrator.']);
        }
        if (!$user || !$user->isRequestee()) {
            return redirect()->route('login')->withErrors(['authorization' => 'Only registered requestors may submit facility/equipment requests.']);
        }

        // Build validation rules
        $hasDepartmentDirectory = College::query()->exists() && Department::query()->exists();
        $positionOptions = ['Student', 'Faculty', 'Staff', 'Instructor', 'Professor', 'Department Chair', 'Coordinator', 'Office Staff', 'External Partner', 'Other'];
        $hasSavedSignature = (bool) ($user->e_signature_file && Storage::disk('local')->exists('documents/e_signature/users/' . $user->e_signature_file));

        $rules = [
            'reservation_duration'  => ['nullable', 'in:specific_time,whole_day,whole-day,whole day'],
            'college_id'            => ['nullable', 'exists:colleges,id'],
            'department_id'         => ['nullable', 'exists:departments,id'],
            'department'            => ['nullable', 'string', 'max:100'],
            'organization_name'     => ['nullable', 'string', 'max:191'],
            'request_context'       => ['nullable', 'in:personal,student_organization,outside_organization'],
            'student_organization_id' => ['nullable', 'integer', 'exists:student_organizations,id'],
            'requested_by_position' => ['nullable', 'in:' . implode(',', $positionOptions)],
            'requested_by_position_other' => ['nullable', 'string', 'max:100', 'required_if:requested_by_position,Other'],
            'name_of_activity'      => 'required|string|max:200',
            'purpose'               => ['nullable', 'string', 'max:2000'],
            'expected_participants' => 'required|integer|min:1',
            'start_date'            => 'required|date|after_or_equal:today',
            'end_date'              => 'required|date|after_or_equal:start_date',
            'start_time'            => 'required|date_format:H:i',
            'end_time'              => 'required|date_format:H:i',
            'venue'                 => ['required', 'string'],
            'equipment'             => 'nullable|array',
            'equipment_quantities'  => 'nullable|array',
            'other_venue'           => 'nullable|string|max:200',
            'emergency_justification' => 'required_if:is_emergency,1|string|max:1000',
            'is_emergency'          => 'nullable|boolean',
            'e_signature_file'      => $hasSavedSignature
                ? 'nullable|file|mimes:jpeg,jpg,png|max:10240'
                : 'required|file|mimes:jpeg,jpg,png|max:10240',
        ];

        if (in_array($user->requestor_type, ['student', 'faculty'], true)) {
            if ($hasDepartmentDirectory) {
                $rules['college_id'][] = 'required';
                $rules['department_id'][] = 'required';
            } else {
                $rules['college_id'][] = 'required_without:department';
                $rules['department_id'][] = 'required_without:department';
            }
        } elseif (in_array($user->requestor_type, ['outsider', 'student_organization'], true)) {
            $rules['organization_name'][] = $hasDepartmentDirectory ? 'required' : 'required_without:department';
        }

        $hasExplicitRequestContext = $request->filled('request_context');
        $requestContext = $request->input('request_context') ?: match ($user->requestor_type) {
            'student', 'faculty' => 'personal',
            default => 'outside_organization',
        };
        $request->merge(['request_context' => $requestContext]);
        $rules['request_context'] = match ($user->requestor_type) {
            'student' => ['required', 'in:personal,student_organization'],
            'faculty' => ['required', 'in:personal'],
            default => ['required', 'in:personal,outside_organization'],
        };
        if ($user->isStudent() && $requestContext === 'student_organization') {
            $rules['student_organization_id'][] = 'required';
        }

        // Students and faculty personal requests use proposal documents; outside/organizational requests use receipts.
        if (($user->isStudent() && $requestContext !== 'outside_organization') || ($user->isFaculty() && $requestContext === 'personal')) {
            $rules['activity_proposal_file'] = 'required|file|mimes:pdf,jpeg,jpg,png|max:10240';
            $rules['igp_receipt_file'] = 'nullable|file|mimes:pdf,jpeg,jpg,png|max:10240';
        } else {
            $rules['igp_receipt_file'] = 'required|file|mimes:pdf,jpeg,jpg,png|max:10240';
            $rules['activity_proposal_file'] = 'nullable|file|mimes:pdf,jpeg,jpg,png|max:10240';
        }

        $request->merge([
            'start_date' => $request->input('start_date', $request->input('requesting_date')),
            'end_date' => $request->input('end_date', $request->input('requesting_end_date')),
            'start_time' => $request->input('start_time', $request->input('time')),
        ]);

        $reservationDuration = strtolower((string) $request->input('reservation_duration', 'specific_time'));
        if (in_array($reservationDuration, ['whole_day', 'whole-day', 'whole day'], true)) {
            $request->merge([
                'start_time' => '08:00',
                'end_time' => '23:59',
            ]);
        }

        $validated = $request->validate($rules);

        $hasSavedSignature = (bool) ($user->e_signature_file && Storage::disk('local')->exists('documents/e_signature/users/' . $user->e_signature_file));
        if (! $request->hasFile('e_signature_file') && ! $hasSavedSignature) {
            return back()->withErrors(['e_signature_file' => 'The e signature file field is required.'])->withInput();
        }

        if ($user->isStudent()) {
            $trustedStudentOrganization = $user->studentOrganizations()
                ->where('student_organizations.id', $validated['student_organization_id'] ?? null)
                ->first();

            if (! $trustedStudentOrganization) {
                $trustedStudentOrganization = $user->studentOrganizations()->first();
            }

            if ($validated['request_context'] === 'student_organization' && ! $trustedStudentOrganization) {
                return back()->withErrors(['student_organization_id' => 'You may request only for an active organization membership.'])->withInput();
            }

            if ($trustedStudentOrganization) {
                $validated['student_organization_id'] = $trustedStudentOrganization->id;
                $validated['organization_name'] = $trustedStudentOrganization->name;
            }
        }
        // Position must come from the authenticated user account only, not from user input.
        // If a custom profile value is stored, normalize it to a supported request position and keep the custom text as the "Other" detail.
        $positionOptions = ['Student', 'Faculty', 'Staff', 'Instructor', 'Professor', 'Department Chair', 'Coordinator', 'Office Staff', 'External Partner', 'Other'];
        $profilePosition = trim((string) ($user->position ?? ''));
        $trustedPosition = $profilePosition !== '' && in_array($profilePosition, $positionOptions, true)
            ? $profilePosition
            : (
                $profilePosition !== ''
                    ? 'Other'
                    : match ($user->requestor_type) {
                        'student' => 'Student',
                        'faculty' => 'Faculty',
                        'student_organization' => 'Student Organization',
                        'outsider' => 'External Partner',
                        default => 'Requestor',
                    }
            );
        $validated['requested_by_position'] = $trustedPosition;
        if ($trustedPosition === 'Other' && $profilePosition !== '') {
            $validated['requested_by_position_other'] = $profilePosition;
        }
        $validated['purpose'] = trim((string) ($validated['purpose'] ?? $validated['name_of_activity']));

        if (in_array($user->requestor_type, ['student', 'faculty'], true)) {
            $selectedDepartment = Department::query()->find($validated['department_id'] ?? null);
            if ($selectedDepartment && $validated['college_id'] && (int) $selectedDepartment->college_id !== (int) $validated['college_id']) {
                return back()->withErrors(['department_id' => 'Please select a department under the selected college.'])->withInput();
            }

            if ($user->college_id && (int) ($validated['college_id'] ?? 0) !== (int) $user->college_id) {
                return back()->withErrors(['college_id' => 'Your college is taken from your profile.'])->withInput();
            }

            if ($user->department_id && (int) ($validated['department_id'] ?? 0) !== (int) $user->department_id) {
                return back()->withErrors(['department_id' => 'Your department is taken from your profile.'])->withInput();
            }

            if ($selectedDepartment) {
                $validated['department'] = $selectedDepartment->name;
            }
        } elseif (in_array($user->requestor_type, ['outsider', 'student_organization'], true)) {
            $trustedOrganization = trim((string) ($user->office_or_organization ?? ''));
            if ($trustedOrganization === '') {
                return back()->withErrors(['organization_name' => 'Please update your profile organization/office before submitting this request.'])->withInput();
            }
            $validated['department'] = $trustedOrganization;
            $validated['organization_name'] = $trustedOrganization;
        }

        if ($user->requestor_type === 'student') {
            $trustedStudentOrganization = $user->studentOrganizations()->first();
            $validated['organization_name'] = $trustedStudentOrganization?->name ?? trim((string) ($validated['organization_name'] ?? $validated['department'] ?? ''));
            if ($trustedStudentOrganization) {
                $validated['student_organization_id'] = $trustedStudentOrganization->id;
            }
        }

        $reservationDuration = strtolower((string) ($validated['reservation_duration'] ?? 'specific_time'));
        $scheduleRange = FacilityRequest::resolveReservationDuration(
            $reservationDuration,
            $validated['start_date'],
            $validated['start_time'],
            $validated['end_date'] ?? $validated['start_date'],
            $validated['end_time']
        );
        $validated['start_time'] = $scheduleRange['start']->format('H:i');
        $validated['end_time'] = $scheduleRange['end']->format('H:i');
        $validated['end_date'] = $scheduleRange['end']->toDateString();

        $rulesForOtherVenue = ['nullable', 'string', 'max:200', 'required_if:venue,Others (specify)'];
        $request->validate(['other_venue' => $rulesForOtherVenue]);

        if (empty($request->input('venue'))) {
            return back()->withErrors(['venue' => 'Please select a venue.'])->withInput();
        }

        $selectedQuantities = $request->input('equipment_quantities', []);
        $selectedEquipment = array_values(array_filter($request->input('equipment', []), fn($item) => !empty($item)));
        $capacityWarning = null;

        if (!empty($selectedEquipment)) {
            foreach ($selectedEquipment as $item) {
                $quantity = (int) ($selectedQuantities[$item] ?? 0);
                if ($quantity <= 0) {
                    return back()->withErrors(['equipment' => 'Please select required equipment and quantity.'])->withInput();
                }
            }
        }
 
        $user = $this->currentUser();
 
        $submittedVenue = $request->input('venue');
        if (is_array($submittedVenue)) {
            $submittedVenue = reset($submittedVenue);
        }
        $submittedVenue = trim((string) $submittedVenue);
        $allowedVenues = array_merge(self::VENUE_OPTIONS, ['Others (specify)']);
        $venue = in_array($submittedVenue, $allowedVenues, true) ? [$submittedVenue] : [];
        
        // Normalize explicit canonical names and known legacy variants; reject ambiguous generic microphone input.
        $rawEquipment = array_values(array_filter($request->input('equipment', []), fn($e) => is_string($e) && trim($e) !== ''));
        if (in_array('microphones', array_map(static fn (string $item): string => mb_strtolower(trim($item)), $rawEquipment), true)) {
            return back()->withErrors(['equipment' => 'Please select Wireless Microphones or Non-Wireless Microphones.'])->withInput();
        }
        $requestedEquipment = self::normalizeEquipmentSelection($rawEquipment);
        $requestedEquipment = array_values(array_filter($requestedEquipment, fn ($item) => in_array($item, self::EQUIPMENT_OPTIONS, true)));

        // Apply venue-specific equipment rules
        if (!empty($venue)) {
            $venueName = $venue[0];
            
            // Add default equipment required for this venue if not already selected
            $defaultEquipment = VenueEquipmentPolicy::getDefaultEquipment($venueName);
            foreach ($defaultEquipment as $requiredItem) {
                if (!in_array($requiredItem, $requestedEquipment)) {
                    $requestedEquipment[] = $requiredItem;
                }
            }
            
            // Check for incompatible equipment
            $incompatible = VenueEquipmentPolicy::getIncompatibleEquipment($venueName);
            $requestedIncompatible = array_intersect($requestedEquipment, $incompatible);
            if (!empty($requestedIncompatible)) {
                $incompatibleList = implode(', ', $requestedIncompatible);
                return back()->withErrors(['equipment' => "{$venueName} does not support the following equipment: {$incompatibleList}. Please select a different venue or remove these items."])->withInput();
            }
        }
        
        $equipment = $requestedEquipment;
 
        $validated['reservation_duration'] = strtolower((string) ($validated['reservation_duration'] ?? 'specific_time'));
        $scheduleRange = FacilityRequest::resolveReservationDuration(
            $validated['reservation_duration'],
            $validated['start_date'],
            $validated['start_time'],
            $validated['end_date'] ?? $validated['start_date'],
            $validated['end_time']
        );
        $validated['start_time'] = $scheduleRange['start']->format('H:i');
        $validated['end_time'] = $scheduleRange['end']->format('H:i');
        $validated['end_date'] = $scheduleRange['end']?->toDateString() ?? $validated['end_date'] ?? $validated['start_date'];
        $startDateTime = $scheduleRange['start'];
        $endDateTime = $scheduleRange['end'];

        if (!empty($validated['is_emergency']) && $validated['is_emergency']) {
            $cutoff = now()->addHours(48);
            if ($startDateTime->gt($cutoff)) {
                return back()
                    ->withErrors(['is_emergency' => 'Emergency requests are only allowed within 48 hours of the reservation schedule.'])
                    ->withInput();
            }
        }

        if ($endDateTime->lte($startDateTime)) {
            return back()
                ->withErrors(['end_date' => 'End date and time must be after the start date and time. For overnight bookings, set the end date to the next day.'])
                ->withInput();
        }

        // Build quantities map e.g. ["Sound System" => 2, "Tables" => 10]
        $quantities = [];
        foreach ($equipment as $item) {
            $qty = (int) ($request->input('equipment_quantities')[$item] ?? 1);
            $quantities[$item] = $qty;
        }

        // ✅ CHECK AVAILABILITY ONLY — do NOT reserve yet (deduct upon approval)
        foreach ($quantities as $itemName => $qty) {
            $availability = $this->availabilityService->checkEquipmentAvailability($itemName, $qty, $startDateTime, $endDateTime);

            if (!$availability['available']) {
                return back()
                    ->withErrors(['equipment' => $availability['message'] ?? "Sorry, only {$availability['available_qty']} unit(s) of '{$itemName}' available."])
                    ->withInput();
            }
        }

        // ✅ CHECK FOR VENUE CONFLICTS
        $isUrgentRequest = !empty($validated['is_emergency']) && (bool) $validated['is_emergency'];
        if (!empty($venue)) {
            $requestedStart = $startDateTime;
            $requestedEnd = $endDateTime;

            if (!$isUrgentRequest) {
                $venueAvailability = $this->availabilityService->checkVenueAvailability($submittedVenue, $requestedStart, $requestedEnd);
                if (!$venueAvailability['available']) {
                    return back()
                        ->withErrors(['venue' => $venueAvailability['message'] ?? 'Scheduling conflict detected.'])
                        ->withInput();
                }
            }

            // ✅ CHECK VENUE CAPACITY
            $participants = (int) $validated['expected_participants'];
            $selectedVenues = array_values($venue);
            $venueCapacityMap = [];
            $hasUnknownCapacity = false;
            foreach ($selectedVenues as $venueName) {
                if ($venueName === 'Others (specify)') {
                    $hasUnknownCapacity = true;
                    continue;
                }

                $venueRecord = \App\Models\Venue::where('name', $venueName)->first();
                $capacity = null;

                if ($venueRecord && $venueRecord->capacity !== null) {
                    $capacity = (int) $venueRecord->capacity;
                }

                if ($capacity === null) {
                    $hasUnknownCapacity = true;
                    continue;
                }

                $venueCapacityMap[$venueName] = $capacity;
            }

            if (!$hasUnknownCapacity) {
                $totalCapacity = array_sum($venueCapacityMap);
                if ($totalCapacity < $participants) {
                    $capacityWarning = "The combined capacity of your selected venues is {$totalCapacity}, which is insufficient for {$participants} participants. The request will still be submitted for review.";
                }

            }
        }
 
        $notes = null;
        if (!empty($validated['is_emergency']) && !empty($validated['emergency_justification'])) {
            $notes = 'Emergency justification: ' . trim($validated['emergency_justification']);
        }

        if ($capacityWarning) {
            $notes = trim(($notes ? $notes . "\n" : '') . 'Capacity warning: ' . $capacityWarning);
        }

        if ($isUrgentRequest) {
            $notes = trim(($notes ? $notes . "\n" : '') . 'Urgent request submitted for administrative review; venue conflicts will be handled during approval.');
        }
 
        // Generate control number for this request
        $controlNumber = FacilityRequest::generateControlNumber();

        // Initialize document filenames
        $proposalFileName = null;
        $activityProposalFileName = null;
        $igpReceiptFileName = null;
        $eSignatureFileName = null;
        $documentMetadata = [];

        // Get the document upload service
        $documentUploadService = app(\App\Services\DocumentUploadService::class);

        // Handle proposal file upload (backward compatibility)
        if ($request->hasFile('proposal_file')) {
            $file = $request->file('proposal_file');
            $extension = $file->getClientOriginalExtension();
            $timestamp = now()->format('Ymd_His');
            $proposalFileName = $controlNumber . '_proposal_' . $timestamp . '.' . $extension;
            $file->storeAs('proposals', $proposalFileName, 'local');
        }

        // Handle activity proposal upload (Student/Faculty)
        if ($request->hasFile('activity_proposal_file')) {
            $file = $request->file('activity_proposal_file');
            $result = $documentUploadService->uploadDocument($file, 'activity_proposal', $controlNumber);
            if ($result['success']) {
                $activityProposalFileName = $result['filename'];
                $documentMetadata['activity_proposal'] = [
                    'uploaded_at' => now()->toDateTimeString(),
                    'original_name' => $file->getClientOriginalName(),
                ];
            } else {
                return back()->withErrors(['activity_proposal_file' => $result['error']])->withInput();
            }
        }

        // Handle IGP Receipt upload (External/Organization)
        if ($request->hasFile('igp_receipt_file')) {
            $file = $request->file('igp_receipt_file');
            $result = $documentUploadService->uploadDocument($file, 'igp_receipt', $controlNumber);
            if ($result['success']) {
                $igpReceiptFileName = $result['filename'];
                $documentMetadata['igp_receipt'] = [
                    'uploaded_at' => now()->toDateTimeString(),
                    'original_name' => $file->getClientOriginalName(),
                ];
            } else {
                return back()->withErrors(['igp_receipt_file' => $result['error']])->withInput();
            }
        }

        // Handle e-signature upload or private snapshot from the saved account signature.
        if ($request->hasFile('e_signature_file')) {
            $file = $request->file('e_signature_file');
            $result = $documentUploadService->uploadDocument($file, 'e_signature', $controlNumber);
            if ($result['success']) {
                $eSignatureFileName = $result['filename'];
                $documentMetadata['e_signature'] = [
                    'uploaded_at' => now()->toDateTimeString(),
                    'original_name' => $file->getClientOriginalName(),
                    'source' => 'request_upload',
                ];
            } else {
                return back()->withErrors(['e_signature_file' => $result['error']])->withInput();
            }
        } elseif ($user->e_signature_file) {
            $savedSignaturePath = 'documents/e_signature/users/' . $user->e_signature_file;
            if (Storage::disk('local')->exists($savedSignaturePath)) {
                $sourcePath = Storage::disk('local')->path($savedSignaturePath);
                $extension = strtolower(pathinfo($user->e_signature_file, PATHINFO_EXTENSION) ?: 'png');
                $snapshotFilename = $controlNumber . '_request_signature_' . now()->format('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
                Storage::disk('local')->makeDirectory('documents/e_signature');
                Storage::disk('local')->put('documents/e_signature/' . $snapshotFilename, file_get_contents($sourcePath));
                $eSignatureFileName = $snapshotFilename;
                $documentMetadata['e_signature'] = [
                    'uploaded_at' => now()->toDateTimeString(),
                    'original_name' => $user->e_signature_file,
                    'source' => 'saved_account_signature',
                ];
            }
        }

        // ✅ DO NOT call $eq->reserve() here — reservation happens on approval

        $fr = FacilityRequest::create([
            'control_number'           => $controlNumber,
            'date_requested'           => now()->toDateString(),
            'department'               => $validated['department'],
            'organization_name'        => $validated['organization_name'] ?? null,
            'request_context'          => $validated['request_context'],
            'student_organization_id'  => $validated['student_organization_id'] ?? null,
            'requested_by_position'    => $validated['requested_by_position'],
            'name_of_activity'         => $validated['name_of_activity'],
            'purpose'                  => $validated['purpose'],
            'expected_participants'    => $validated['expected_participants'],
            'start_date'               => $validated['start_date'],
            'end_date'                 => $validated['end_date'] ?? $validated['start_date'],
            'start_time'               => $validated['start_time'],
            'end_time'                 => $validated['end_time'],
            'venue'                    => $venue,
            'equipment'                => $equipment,
            'equipment_quantities'     => $quantities,
            'other_venue'              => $validated['other_venue'] ?? null,
            'notes'                    => $notes,
            'requested_by_id'          => $user->id,
            'status'                   => 'pending',
            'venue_status'             => 'pending',
            'equipment_status'         => 'pending',
            'priority'                 => 'regular',
            'requested_priority'       => null,
            'requested_is_emergency'   => $validated['is_emergency'] ?? false,
            'is_emergency'             => $validated['is_emergency'] ?? false,
            'emergency_justification'  => $validated['emergency_justification'] ?? null,
            'proposal_file'            => $proposalFileName,
            'activity_proposal_file'   => $activityProposalFileName,
            'igp_receipt_file'         => $igpReceiptFileName,
            'e_signature_file'         => $eSignatureFileName,
            'document_metadata'        => $documentMetadata,
        ]);
        $fr->syncRelationalItems();

        // Determine custodians using authoritative model helper (includes authorized alternates)
        $equipmentCustodianIds = $fr->getAssignedEquipmentCustodianIds();
        $venueCustodianIds = \App\Models\Venue::whereIn('name', $venue)
            ->pluck('custodian_id')
            ->filter()
            ->unique()
            ->toArray();

        $custodianIds = array_values(array_unique(array_merge($equipmentCustodianIds, $venueCustodianIds)));

        if (!empty($custodianIds)) {
            $custodians = \App\Models\User::whereIn('id', $custodianIds)->get();
            try {
                Notification::send($custodians, new \App\Notifications\NewFacilityRequestNotification($fr));
            } catch (\Throwable $e) {
                Log::warning('Failed to notify custodians for new facility request.', [
                    'facility_request_id' => $fr->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        // Fire Laravel event for broadcasting (include custodians)
        \App\Events\RequestCreated::dispatch($fr->id, $fr->control_number, $user->name, $fr->requested_by_id, $custodianIds);

        return redirect()->route('requestor.index', ['tab' => 'requests'])
                        ->with('success', 'Request submitted successfully!')
                        ->with('warning', $capacityWarning);
    }
 
    public function destroy(Request $request)
    {
        $user = $this->currentUser();
        $fr   = FacilityRequest::where('id', $request->input('id'))
                    ->where('requested_by_id', $user->id)
                    ->where('status', 'pending')
                    ->firstOrFail();

        DB::beginTransaction();

        try {
            // ✅ Only release if equipment was already approved (deducted)
            if ($fr->equipment_status === 'approved') {
                foreach ($fr->getEquipmentQuantities() as $itemName => $qty) {
                    $qty = (int) $qty;
                    if ($qty <= 0) {
                        continue;
                    }

                    $eq = \App\Models\Equipment::whereRaw('LOWER(name) = ?', [strtolower($itemName)])
                        ->lockForUpdate()
                        ->first();

                    if ($eq) {
                        $eq->quantity_available = min(
                            (int) $eq->quantity,
                            (int) $eq->quantity_available + $qty
                        );
                        $eq->save();
                    }
                }
            }

            $fr->addHistory('cancelled', 'Request cancelled by requester ' . $user->name, $user->id);

            // Determine affected custodians (use model helper for equipment + venue custodians)
            $equipmentCustodianIds = $fr->getAssignedEquipmentCustodianIds();
            $venueCustodianIds = \App\Models\Venue::whereIn('name', $fr->venue ?? [])->pluck('custodian_id')->filter()->unique()->toArray();
            $custodianIds = array_values(array_unique(array_merge($equipmentCustodianIds, $venueCustodianIds)));

            // Notify custodians and admins about cancellation
            if (!empty($custodianIds)) {
                $custodians = \App\Models\User::whereIn('id', $custodianIds)->get();
                try {
                    Notification::send($custodians, new \App\Notifications\RequestStatusChanged(
                        $fr,
                        'request_cancelled',
                        "Request {$fr->control_number} has been cancelled by {$user->name}. Equipment and venue are now available for other requests."
                    ));
                } catch (\Throwable $e) {
                    Log::warning('Failed to notify custodians for cancelled facility request.', [
                        'facility_request_id' => $fr->id,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }

            // Notify admins as well
            $admins = \App\Models\User::whereIn('role', ['admin', 'facility_admin'])->get();
            if ($admins->isNotEmpty()) {
                try {
                    Notification::send($admins, new \App\Notifications\RequestStatusChanged(
                        $fr,
                        'request_cancelled',
                        "Request {$fr->control_number} has been cancelled by {$user->name}. Equipment and venue are now available for other requests."
                    ));
                } catch (\Throwable $e) {
                    Log::warning('Failed to notify admins for cancelled facility request.', [
                        'facility_request_id' => $fr->id,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }

            // Fire Laravel event for broadcasting (include custodians)
            \App\Events\RequestCancelled::dispatch($fr->id, $fr->control_number, $user->name, $fr->requested_by_id, $custodianIds);

            $fr->update([
                'status' => 'cancelled',
                'venue_status' => 'cancelled',
                'equipment_status' => 'cancelled',
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Request cancelled successfully');
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()->withErrors('Unable to cancel the request at this time.');
        }
    }

    public function show($id)
    {
        $request   = FacilityRequest::with(['requestVenues', 'requestEquipment', 'reservationSchedule'])->findOrFail($id);
        $this->authorize('view', $request);
        $equipment = \App\Models\Equipment::where('is_active', true)->whereNotIn('id', [2, 10])->get();
        $assignedCustodians = \App\Models\User::whereIn('id', $request->getAssignedEquipmentCustodianIds())->get();
        $custodianStatuses = $request->equipment_custodian_statuses ?? [];
        /** @var \App\Models\User|null $currentUser */
        $currentUser = $this->currentUser();
        if ($currentUser && $currentUser->isCustodian()) {
            $assignedVenueNames = $request->getVenueNames();
            $assignedEquipment = $request->getAssignedEquipmentForCustodian((int) $currentUser->id);
            $isAssignedVenueCustodian = $currentUser->isCustodianVenue() && !empty($assignedVenueNames) && $currentUser->venues()->pluck('name')->intersect($assignedVenueNames)->isNotEmpty();
            $isAssignedEquipmentCustodian = $currentUser->isCustodianEquipment() && !empty($assignedEquipment);

            if (!$isAssignedVenueCustodian && !$isAssignedEquipmentCustodian) {
                abort(403);
            }
        }
        $currentCustodianEquipment = $currentUser && $currentUser->isCustodian()
            ? $request->getAssignedEquipmentForCustodian((int) Auth::id())
            : [];

        // Check if current custodian has already endorsed this request
        $hasEndorsed = false;
        if ($currentUser && $currentUser->isCustodian()) {
            $hasEndorsed = $request->histories()
                ->where('user_id', $currentUser->id)
                ->where('action', 'custodian_endorsed')
                ->exists();

            if (!$hasEndorsed) {
                $hasEndorsed = $currentUser->isCustodianVenue()
                    ? $request->venue_status === 'approved'
                    : $request->getCustodianEquipmentStatus((int) $currentUser->id) === 'approved';
            }
        }
 
        return view('requestor.show', [
            'request'                 => $request,
            'venueOptions'            => self::VENUE_OPTIONS,
            'equipOptions'            => self::EQUIPMENT_OPTIONS,
            'equipment'               => $equipment,
            'assignedCustodians'      => $assignedCustodians,
            'custodianStatuses'       => $custodianStatuses,
            'currentCustodianEquipment' => $currentCustodianEquipment,
            'hasEndorsed'             => $hasEndorsed,
        ]);
    }

    public function print($id)
    {
        $request = FacilityRequest::findOrFail($id);
        $this->authorize('print', $request);

        return view('request.print', [
            'request' => $request,
        ]);
    }

    public function proposal($id)
    {
        $request = FacilityRequest::findOrFail($id);
        $this->authorize('view', $request);

        $filename = $request->activity_proposal_file ?: $request->proposal_file;
        $filePath = $request->activity_proposal_file
            ? 'documents/activity_proposal/' . $filename
            : 'proposals/' . $filename;

        if (!$filename) {
            abort(404);
        }

        $disk = Storage::disk('local')->exists($filePath) ? 'local' : 'public';

        if (!Storage::disk($disk)->exists($filePath)) {
            abort(404);
        }

        return response()->file(Storage::disk($disk)->path($filePath));
    }

    public function signature($id)
    {
        $request = FacilityRequest::findOrFail($id);
        $this->authorize('view', $request);

        if (!$request->e_signature_file) {
            abort(404);
        }

        $filePath = 'documents/e_signature/' . $request->e_signature_file;
        $disk = Storage::disk('local')->exists($filePath) ? 'local' : 'public';

        if (!Storage::disk($disk)->exists($filePath)) {
            abort(404);
        }

        return response()->file(Storage::disk($disk)->path($filePath));
    }

    public function proposalDownload($id)
    {
        $request = FacilityRequest::findOrFail($id);
        $this->authorize('view', $request);

        $filename = $request->activity_proposal_file ?: $request->proposal_file;
        $filePath = $request->activity_proposal_file
            ? 'documents/activity_proposal/' . $filename
            : 'proposals/' . $filename;

        if (!$filename) {
            abort(404);
        }

        $disk = Storage::disk('local')->exists($filePath) ? 'local' : 'public';

        if (!Storage::disk($disk)->exists($filePath)) {
            abort(404);
        }

        return response()->download(Storage::disk($disk)->path($filePath), $filename);
    }

    // ✅ Real-time availability check endpoint
    public function equipmentAvailability(Request $request)
    {
        $name = trim((string) $request->input('name', ''));

        if ($name === '') {
            return response()->json(['available' => 0, 'total' => 0, 'overlapping_requests' => []]);
        }

        $normalizedName = strtolower($name);
        $eq = \App\Models\Equipment::whereRaw('LOWER(name) = ?', [$normalizedName])
            ->orWhereRaw('LOWER(name) LIKE ?', ["%{$normalizedName}%"])
            ->first();

        if (!$eq) {
            return response()->json(['available' => 0, 'total' => 0, 'overlapping_requests' => []]);
        }

        $requestedStart = null;
        $requestedEnd = null;

        if ($request->filled('start_date') && $request->filled('start_time')) {
            $requestedStart = Carbon::parse($request->input('start_date') . ' ' . $request->input('start_time'));
        }

        if ($request->filled('end_date') && $request->filled('end_time')) {
            $scheduleRange = FacilityRequest::normalizeScheduleRange($request->input('start_date'), $request->input('start_time'), $request->input('end_date'), $request->input('end_time'));
            $requestedStart = $scheduleRange['start'];
            $requestedEnd = $scheduleRange['end'];
        } elseif ($requestedStart) {
            $requestedEnd = $requestedStart->copy()->addHour();
        }

        $availability = $this->availabilityService->checkEquipmentAvailability($name, 1, $requestedStart, $requestedEnd);
        $overlappingRequests = [];

        if ($requestedStart && $requestedEnd) {
            $overlappingRequests = \App\Models\FacilityRequest::where(function ($query) {
                    $query->where('status', 'approved')
                        ->orWhere(function ($pendingQuery) {
                            $pendingQuery->where('status', 'pending')
                                ->where('venue_status', '!=', 'rejected')
                                ->where('equipment_status', '!=', 'rejected');
                        });
                })
                ->where(function ($query) {
                    $query->where('equipment_returned_status', '!=', 'returned')
                        ->where('equipment_returned_status', '!=', 'overdue');
                })
                ->whereHas('reservationSchedule', function ($query) use ($requestedStart, $requestedEnd) {
                    $query->where('start_datetime', '<', $requestedEnd)
                        ->where('end_datetime', '>', $requestedStart);
                })
                ->get()
                ->filter(function ($req) use ($normalizedName) {
                    $quantities = $req->getEquipmentQuantities();
                    if (!empty($quantities)) {
                        foreach ($quantities as $requestedName => $requestedQty) {
                            $candidate = strtolower((string) $requestedName);
                            if ($candidate === $normalizedName || str_contains($candidate, $normalizedName)) {
                                return true;
                            }
                        }
                    }

                    if (!empty($req->equipment) && is_array($req->equipment)) {
                        foreach ($req->equipment as $requestedName) {
                            $candidate = strtolower((string) $requestedName);
                            if ($candidate === $normalizedName || str_contains($candidate, $normalizedName)) {
                                return true;
                            }
                        }
                    }

                    return false;
                })
                ->map(function ($req) use ($normalizedName) {
                    $quantities = $req->getEquipmentQuantities();
                    $quantity = 0;

                    if (!empty($quantities)) {
                        foreach ($quantities as $requestedName => $requestedQty) {
                            $candidate = strtolower((string) $requestedName);
                            if ($candidate === $normalizedName || str_contains($candidate, $normalizedName)) {
                                $quantity += (int) $requestedQty;
                            }
                        }
                    }

                    if ($quantity <= 0 && !empty($req->equipment) && is_array($req->equipment)) {
                        foreach ($req->equipment as $requestedName) {
                            $candidate = strtolower((string) $requestedName);
                            if ($candidate === $normalizedName || str_contains($candidate, $normalizedName)) {
                                $quantity += 1;
                            }
                        }
                    }

                    return [
                        'activity' => $req->name_of_activity ?: 'Existing reservation',
                        'quantity' => max(1, $quantity),
                        'schedule' => $req->reservationSchedule ? $req->reservationSchedule->start_datetime?->format('F j, Y') : null,
                        'priority' => $req->priority ?? 'regular',
                        'control_number' => $req->control_number ?? null,
                    ];
                })
                ->values()
                ->all();
        }

        return response()->json([
            'available' => max(0, (int) ($availability['available_qty'] ?? 0)),
            'total' => (int) $eq->quantity,
            'overlapping_requests' => $overlappingRequests,
        ]);
    }
}
