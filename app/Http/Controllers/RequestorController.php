<?php
namespace App\Http\Controllers;
 
use App\Models\FacilityRequest;
use App\Models\User;
use App\Services\AvailabilityService;
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
        'Sound System', 'Microphones', 'Canopies', 'Industrial Fans',
        'Iwata Cooler Fans', 'Tables', 'Monobloc chairs',
    ];
 
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
        $equipment = \App\Models\Equipment::all();

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
        foreach (self::VENUE_OPTIONS as $venueName) {
            $venueCapacityMap[$venueName] = $this->availabilityService->getVenueCapacity($venueName);
        }

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
            'venueOptions'      => self::VENUE_OPTIONS,
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
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $this->currentUser();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:255'],
            'office_or_organization' => ['nullable', 'string', 'max:255'],
            'school_id_number' => ['nullable', 'string', 'max:255'],
        ]);

        $user->fill($validated);
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
            'equipment' => \App\Models\Equipment::all(),
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
                'end_time' => '00:00',
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
        $allowedVenues = array_merge(self::VENUE_OPTIONS, ['Others (specify)']);
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
        $equipment = \App\Models\Equipment::whereRaw('LOWER(name) = ?', [strtolower($itemName)])->first();
        $existingRequest = FacilityRequest::find($excludeRequestId);
        $existingQuantity = (int) ($existingRequest?->getEquipmentQuantities()[$itemName] ?? 0);

        if (!$equipment) {
            return [
                'available' => $existingQuantity >= $quantity,
                'message' => $existingQuantity >= $quantity ? null : "Equipment '{$itemName}' not found.",
                'available_qty' => max($existingQuantity, $quantity),
                'total' => max($existingQuantity, $quantity),
            ];
        }

        $outstanding = 0;
        $requests = FacilityRequest::where(function ($query) {
            $query->where('status', 'approved')->orWhere(function ($pendingQuery) {
                $pendingQuery->where('status', 'pending')
                    ->where('venue_status', '!=', 'rejected')
                    ->where('equipment_status', '!=', 'rejected');
            });
        })
            ->where('id', '!=', $excludeRequestId)
            ->where(function ($query) {
                $query->where('equipment_returned_status', '!=', 'returned')
                    ->where('equipment_returned_status', '!=', 'overdue');
            })
            ->whereHas('reservationSchedule', function ($query) use ($requestedStart, $requestedEnd) {
                $query->where('start_datetime', '<', $requestedEnd)
                    ->where('end_datetime', '>', $requestedStart);
            })
            ->get();

        foreach ($requests as $request) {
            $quantities = $request->getEquipmentQuantities();
            if (isset($quantities[$itemName])) {
                $outstanding += (int) $quantities[$itemName];
            }
        }

        $available = max(0, (int) $equipment->quantity - $outstanding);

        return [
            'available' => $available >= $quantity,
            'message' => $available >= $quantity ? null : "Sorry, only {$available} unit(s) of '{$itemName}' available for the selected window.",
            'available_qty' => $available,
            'total' => (int) $equipment->quantity,
        ];
    }

    private function checkVenueAvailabilityForEdit(string $venueName, Carbon $requestedStart, Carbon $requestedEnd, int $excludeRequestId): array
    {
        $venue = \App\Models\Venue::where('name', $venueName)->first();
        $venueRecord = $venue ?: null;

        if ($venueRecord && $venueRecord->capacity !== null && $venueRecord->capacity <= 0) {
            return ['available' => false, 'message' => 'The selected venue is unavailable.', 'capacity' => $venueRecord->capacity];
        }

        $conflicts = FacilityRequest::where(fn ($query) => $query->matchesVenue($venueName))
            ->where('id', '!=', $excludeRequestId)
            ->where(function ($query) {
                $query->where(function ($approvedQuery) {
                    $approvedQuery->where('status', 'approved')
                        ->where('equipment_returned_status', '!=', 'returned');
                })->orWhere(function ($pendingQuery) {
                    $pendingQuery->where('status', 'pending')
                        ->where('venue_status', '!=', 'rejected')
                        ->where('equipment_status', '!=', 'rejected');
                });
            })
            ->whereHas('reservationSchedule', function ($query) use ($requestedStart, $requestedEnd) {
                $query->where('start_datetime', '<', $requestedEnd)
                    ->where('end_datetime', '>', $requestedStart);
            })
            ->exists();

        return [
            'available' => !$conflicts,
            'message' => $conflicts ? 'The selected venue conflicts with an existing reservation.' : null,
            'capacity' => $venueRecord?->capacity,
        ];
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
        if (!$user || !$user->isRequestee()) {
            return redirect()->route('login')->withErrors(['authorization' => 'Only registered requestors may submit facility/equipment requests.']);
        }

        // Build validation rules; proposal_file is conditional for students (unless emergency)
        $rules = [
            'reservation_duration'  => ['nullable', 'in:specific_time,whole_day,whole-day,whole day'],
            'department'            => 'required|string|max:100',
            'name_of_activity'      => 'required|string|max:200',
            'expected_participants' => 'required|integer|min:1',
            'start_date'            => 'required|date|after_or_equal:today',
            'end_date'              => 'required|date|after_or_equal:start_date',
            'start_time'            => 'required|date_format:H:i',
            'end_time'              => 'required|date_format:H:i',
            'venue'                 => ['required', 'string', Rule::in(array_merge(self::VENUE_OPTIONS, ['Others (specify)']))],
            'equipment'             => 'required|array|min:1',
            'equipment_quantities'  => 'nullable|array',
            'other_venue'           => 'nullable|string|max:200',
            'emergency_justification' => 'required_if:is_emergency,1|string|max:1000',
            'priority'              => 'nullable|in:regular,institutional',
            'is_emergency'          => 'nullable|boolean',
        ];

        if ($user->requestor_type === 'student' && ! $request->input('is_emergency')) {
            $rules['proposal_file'] = 'required|file|mimes:pdf,jpeg,jpg,png|max:10240';
        } else {
            $rules['proposal_file'] = 'nullable|file|mimes:pdf,jpeg,jpg,png|max:10240';
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
                'end_time' => '00:00',
            ]);
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
        $validated['end_date'] = $scheduleRange['end']->toDateString();

        $rulesForOtherVenue = ['nullable', 'string', 'max:200', 'required_if:venue,Others (specify)'];
        $request->validate(['other_venue' => $rulesForOtherVenue]);

        if (empty($request->input('venue'))) {
            return back()->withErrors(['venue' => 'Please select a venue.'])->withInput();
        }

        if (empty($request->input('equipment', []))) {
            return back()->withErrors(['equipment' => 'Please select at least one equipment item.'])->withInput();
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
        $equipment = array_values(array_filter($request->input('equipment', []),
                        fn($e) => in_array($e, self::EQUIPMENT_OPTIONS)));
 
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
            $defaultCapacities = [
                'Conference Hall & Interaction Center (CHIC)' => 150,
                'Gymnasium' => 500,
                'Balay Alumni' => 200,
                'Covered Court' => 300,
                'Oval Grounds' => 1000,
                'Volleyball Court' => 100,
            ];

            foreach ($selectedVenues as $venueName) {
                if ($venueName === 'Others (specify)') {
                    $hasUnknownCapacity = true;
                    continue;
                }

                $venueRecord = \App\Models\Venue::where('name', $venueName)->first();
                $capacity = null;

                if ($venueRecord && $venueRecord->capacity) {
                    $capacity = (int) $venueRecord->capacity;
                } elseif (isset($defaultCapacities[$venueName])) {
                    $capacity = $defaultCapacities[$venueName];
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

                arsort($venueCapacityMap);
                $runningCapacity = 0;
                $minimumVenuesRequired = 0;

                foreach ($venueCapacityMap as $capacity) {
                    $runningCapacity += $capacity;
                    $minimumVenuesRequired++;
                    if ($runningCapacity >= $participants) {
                        break;
                    }
                }

                if ($minimumVenuesRequired < count($selectedVenues) && $capacityWarning === null) {
                    $capacityWarning = 'You are attempting to reserve multiple venues, but your anticipated participants fit into fewer locations. The request will still be submitted for review.';
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
 
        // Handle proposal file upload
        $proposalFileName = null;
        if ($request->hasFile('proposal_file')) {
            $file = $request->file('proposal_file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $timestamp = now()->format('Ymd_His');
            $controlNumber = FacilityRequest::generateControlNumber();
            $proposalFileName = $controlNumber . '_proposal_' . $timestamp . '.' . $extension;

            // Store the file in storage/app/public/proposals
            $file->storeAs('proposals', $proposalFileName, 'local');
        }

        // ✅ DO NOT call $eq->reserve() here — reservation happens on approval

        $fr = FacilityRequest::create([
            'control_number'        => FacilityRequest::generateControlNumber(),
            'date_requested'        => now()->toDateString(),
            'department'            => $validated['department'],
            'name_of_activity'      => $validated['name_of_activity'],
            'expected_participants' => $validated['expected_participants'],
            'start_date'            => $validated['start_date'],
            'end_date'              => $validated['end_date'] ?? $validated['start_date'],
            'start_time'            => $validated['start_time'],
            'end_time'              => $validated['end_time'],
            'venue'                 => $venue,
            'equipment'             => $equipment,
            'equipment_quantities'  => $quantities,
            'other_venue'           => $validated['other_venue'] ?? null,
            'notes'                 => $notes,
            'requested_by_id'       => $user->id,
            'status'                => 'pending',
            'venue_status'          => 'pending',
            'equipment_status'      => 'pending',
            'priority'              => $validated['priority'] ?? 'regular',
            'is_emergency'          => $validated['is_emergency'] ?? false,
            'proposal_file'         => $proposalFileName,
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

            $fr->delete();

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
        $equipment = \App\Models\Equipment::all();
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
                $hasEndorsed = $request->venue_status === 'approved' || $request->equipment_status === 'approved';
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
        $this->authorize('view', $request);

        return view('request.print', [
            'request' => $request,
        ]);
    }

    public function proposal($id)
    {
        $request = FacilityRequest::findOrFail($id);
        $this->authorize('view', $request);

        if (!$request->proposal_file) {
            abort(404);
        }

        $filePath = 'proposals/' . $request->proposal_file;
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

        if (!$request->proposal_file) {
            abort(404);
        }

        $filePath = 'proposals/' . $request->proposal_file;
        $disk = Storage::disk('local')->exists($filePath) ? 'local' : 'public';

        if (!Storage::disk($disk)->exists($filePath)) {
            abort(404);
        }

        return response()->download(Storage::disk($disk)->path($filePath), $request->proposal_file);
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
