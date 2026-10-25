<?php
namespace App\Http\Controllers;
 
use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\RevisionHistory;
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

    public function respondToRevision(Request $request, FacilityRequest $facilityRequest, RevisionHistory $revision)
    {
        $user = $this->currentUser();
        abort_unless((int) $revision->facility_request_id === (int) $facilityRequest->id, 404);

        $validated = $request->validate([
            'response' => ['required', 'in:accept,decline'],
        ]);

        if ($revision->status !== 'pending') {
            return back()->withErrors(['revision' => 'This schedule proposal has already been answered.']);
        }

        $affectedUserIds = array_map('intval', $revision->affected_user_ids ?? [$facilityRequest->requested_by_id]);
        abort_unless(in_array((int) $user->id, $affectedUserIds, true), 403);
        if ($revision->memorandum_priority
            && (int) $user->id === (int) $facilityRequest->requested_by_id
            && ! $user->isCustodian()) {
            abort(403, 'Memorandum-priority transfers do not require requestor consent.');
        }

        if ($revision->response_deadline_at && $revision->response_deadline_at->isPast()) {
            $revision->update(['status' => 'expired', 'responded_at' => now()]);
            $facilityRequest->addHistory('revision_expired', 'Schedule proposal expired without changing the approved reservation.', $user->id);
            Notification::send(
                User::query()->whereIn('id', $affectedUserIds)->get(),
                new \App\Notifications\RequestStatusChanged($facilityRequest, 'revision_expired', 'The change proposal expired. The approved reservation remains unchanged.')
            );
            return back()->withErrors(['revision' => 'This proposal expired. The existing reservation remains unchanged.']);
        }

        if ($validated['response'] === 'decline') {
            $declined = DB::transaction(function () use ($facilityRequest, $revision, $user): bool {
                $lockedRequest = FacilityRequest::query()->lockForUpdate()->findOrFail($facilityRequest->id);
                $lockedRevision = RevisionHistory::query()->lockForUpdate()->findOrFail($revision->id);
                if ($lockedRevision->status !== 'pending' || $lockedRevision->facility_request_id !== $lockedRequest->id) {
                    return false;
                }

                $responses = $lockedRevision->responses ?? [];
                $responses[(string) $user->id] = 'declined';
                $lockedRevision->update([
                    'status' => 'rejected',
                    'responded_at' => now(),
                    'responses' => $responses,
                ]);
                $lockedRequest->addHistory('revision_declined', $user->name . ' declined the proposed schedule change. The approved reservation remains unchanged.', $user->id);
                return true;
            });
            if (!$declined) {
                return back()->withErrors(['revision' => 'This schedule proposal has already been answered.']);
            }

            $revision->refresh();
            $revision->revisedBy?->notify(new \App\Notifications\ReservationRescheduleResponse($revision));
            Notification::send(
                User::query()->whereIn('id', $affectedUserIds)->whereKeyNot($user->id)->get(),
                new \App\Notifications\RequestStatusChanged($facilityRequest, 'change_request_rejected', $user->name . ' declined the proposed change. The approved reservation remains unchanged.')
            );

            return redirect()->route('request.show', $facilityRequest)
                ->with('success', 'You declined the schedule proposal. The current approved reservation remains unchanged.');
        }

        $responseProgress = DB::transaction(function () use ($facilityRequest, $revision, $user): ?bool {
            $lockedRequest = FacilityRequest::query()->lockForUpdate()->findOrFail($facilityRequest->id);
            $lockedRevision = RevisionHistory::query()->lockForUpdate()->findOrFail($revision->id);
            if ($lockedRevision->status !== 'pending' || $lockedRevision->facility_request_id !== $lockedRequest->id) {
                return null;
            }
            if ($lockedRevision->response_deadline_at && $lockedRevision->response_deadline_at->isPast()) {
                $lockedRevision->update(['status' => 'expired', 'responded_at' => now()]);
                $lockedRequest->addHistory('revision_expired', 'Schedule proposal expired without changing the approved reservation.', $user->id);
                return null;
            }

            $responses = $lockedRevision->responses ?? [];
            $responses[(string) $user->id] = 'accepted';
            $lockedRevision->responses = $responses;
            $lockedRevision->save();
            $lockedRequest->addHistory('revision_response_received', $user->name . ' accepted the proposed schedule change.', $user->id);

            foreach ($lockedRevision->affected_user_ids ?? [$lockedRequest->requested_by_id] as $requiredUserId) {
                if (($responses[(string) $requiredUserId] ?? 'pending') === 'pending') {
                    return false;
                }
            }

            return true;
        });
        if ($responseProgress === null) {
            return back()->withErrors(['revision' => 'This proposal is no longer active or has expired. The approved reservation remains unchanged.']);
        }
        if ($responseProgress === false) {
            return redirect()->route('request.show', $facilityRequest)
                ->with('success', 'Your acceptance was recorded. The approved reservation stays unchanged until all affected custodians and users respond.');
        }

        if (!$facilityRequest->canBeRevised()) {
            return back()->withErrors([
                'revision' => $facilityRequest->getRevisionLockReason() ?? 'This reservation can no longer be rescheduled.',
            ]);
        }

        $newState = [
            'start_date' => $revision->new_start_date?->toDateString(),
            'end_date' => $revision->new_end_date?->toDateString(),
            'start_time' => $revision->new_start_time,
            'end_time' => $revision->new_end_time,
            'venue' => $revision->new_venue ?? [],
            'equipment' => $revision->new_equipment ?? [],
            'equipment_quantities' => $revision->new_equipment_quantities ?? [],
        ];
        if ($newState['start_time'] <= '05:00' || $newState['end_time'] <= '05:00') {
            return back()->withErrors([
                'revision' => 'This proposed schedule is not allowed because reservations cannot start or end between 12:00 AM and 5:00 AM. Contact the Supply Office for a revised schedule.',
            ]);
        }
        $range = FacilityRequest::normalizeScheduleRange(
            $newState['start_date'],
            $newState['start_time'],
            $newState['end_date'],
            $newState['end_time']
        );
        $availabilityService = $this->availabilityService;
        $venueConflict = false;
        foreach ($newState['venue'] as $venueName) {
            if (! $availabilityService->checkVenueAvailability(
                $venueName,
                $range['start'],
                $range['end'],
                $facilityRequest->id
            )['available']) {
                $venueConflict = true;
                break;
            }
        }
        $equipmentConflict = false;
        foreach ($newState['equipment'] as $equipment) {
            $quantity = (int) ($newState['equipment_quantities'][$equipment] ?? 1);
            if (!$availabilityService->checkEquipmentAvailability(
                $equipment,
                $quantity,
                $range['start'],
                $range['end'],
                $facilityRequest->id
            )['available']) {
                $equipmentConflict = true;
                break;
            }
        }

        if (($venueConflict || $equipmentConflict) && !$revision->override_conflict) {
            return back()->withErrors([
                'revision' => 'The proposed schedule is no longer available. Contact the Supply Office for an updated proposal.',
            ]);
        }

        $oldState = $facilityRequest->getCurrentState();
        $oldCustodianIds = Venue::query()
            ->whereIn('name', $oldState['venue'])
            ->pluck('custodian_id')
            ->merge(Equipment::query()->whereIn('name', $oldState['equipment'])->pluck('custodian_id'))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $accepted = DB::transaction(function () use ($facilityRequest, $revision, $user, $newState, $range): bool {
            $lockedRequest = FacilityRequest::query()->lockForUpdate()->findOrFail($facilityRequest->id);
            $lockedRevision = RevisionHistory::query()->lockForUpdate()->findOrFail($revision->id);
            if ($lockedRevision->status !== 'pending' || $lockedRevision->facility_request_id !== $lockedRequest->id) {
                return false;
            }
            if (!$lockedRequest->canBeRevised()) {
                return false;
            }
            $currentQuantities = array_map('intval', $lockedRequest->getEquipmentQuantities());
            $proposedFromQuantities = array_map('intval', $lockedRevision->old_equipment_quantities ?? []);
            ksort($currentQuantities);
            ksort($proposedFromQuantities);
            if (
                $lockedRequest->start_date?->toDateString() !== $lockedRevision->old_start_date?->toDateString()
                || $lockedRequest->end_date?->toDateString() !== $lockedRevision->old_end_date?->toDateString()
                || $lockedRequest->start_time !== $lockedRevision->old_start_time
                || $lockedRequest->end_time !== $lockedRevision->old_end_time
                || $lockedRequest->getVenueNames() !== ($lockedRevision->old_venue ?? [])
                || $lockedRequest->getEquipmentItems() !== ($lockedRevision->old_equipment ?? [])
                || $currentQuantities !== $proposedFromQuantities
            ) {
                return false;
            }

            $availabilityService = app(AvailabilityService::class);
            $venueAvailable = true;
            foreach ($newState['venue'] as $venueName) {
                if (! $availabilityService->checkVenueAvailability(
                    $venueName,
                    $range['start'],
                    $range['end'],
                    $lockedRequest->id
                )['available']) {
                    $venueAvailable = false;
                    break;
                }
            }
            $equipmentAvailable = true;
            foreach ($newState['equipment'] as $equipment) {
                $quantity = (int) ($newState['equipment_quantities'][$equipment] ?? 1);
                if (!$availabilityService->checkEquipmentAvailability(
                    $equipment,
                    $quantity,
                    $range['start'],
                    $range['end'],
                    $lockedRequest->id
                )['available']) {
                    $equipmentAvailable = false;
                    break;
                }
            }
            if ((!$venueAvailable || !$equipmentAvailable) && !$lockedRevision->override_conflict) {
                return false;
            }

            $lockedRequest->update([
                'start_date' => $newState['start_date'],
                'end_date' => $newState['end_date'],
                'start_time' => $newState['start_time'],
                'end_time' => $newState['end_time'],
                'venue' => $newState['venue'],
                'equipment' => $newState['equipment'],
                'equipment_quantities' => $newState['equipment_quantities'],
            ]);
            $lockedRequest->syncRelationalItems();
            $lockedRevision->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);
            $lockedRequest->addHistory(
                'revision_applied',
                "Requestor accepted the proposed schedule change. {$lockedRevision->getSummary()}",
                $user->id
            );
            return true;
        });
        if (!$accepted) {
            return back()->withErrors([
                'revision' => 'This proposal has already been answered, the reservation has changed or is locked, or the proposed schedule is no longer available. Contact the Supply Office.',
            ]);
        }

        $facilityRequest->refresh();
        $revision->refresh();
        $revision->revisedBy?->notify(new \App\Notifications\ReservationRescheduleResponse($revision));

        $notification = new \App\Notifications\ReservationRevised(
            $facilityRequest,
            $oldState,
            $newState,
            $revision->revision_reason,
            $revision->revisedBy?->name,
            $revision->conflict_detected,
            $revision->override_conflict,
            $revision->conflict_details,
        );
        $custodianIds = $oldCustodianIds
            ->merge(Venue::query()->whereIn('name', $facilityRequest->getVenueNames())->pluck('custodian_id'))
            ->merge($facilityRequest->getAssignedEquipmentCustodianIds())
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        foreach (User::query()->whereIn('id', $custodianIds)->get() as $custodian) {
            $custodian->notify($notification);
        }
        $notifiedCustodians = $custodianIds->all();
        if ($notifiedCustodians !== []) {
            $revision->markCustodianNotified();
        }

        $notificationRecipients = User::query()
            ->whereIn('id', collect($revision->affected_user_ids ?? [])
                ->merge([(int) $facilityRequest->requested_by_id])
                ->merge($custodianIds)
                ->unique()
                ->all())
            ->get();
        Notification::send($notificationRecipients, $notification);
        $revision->markRequestorNotified();

        return redirect()->route('request.show', $facilityRequest)
            ->with('success', 'You accepted the proposal. The reservation schedule has been updated and the custodians have been notified.');
    }

    private const VENUE_OPTIONS = [
        'Conference Hall & Interaction Center (CHIC)', 'Gymnasium', 'Balay Alumni',
        'Oval Grounds', 'Covered Court', 'Volleyball Court',
    ];
    private const EQUIPMENT_OPTIONS = [
        'Sound System', 'Canopies', 'Industrial Fans',
        'Iwata Cooler Fans', 'Tables', 'Chairs', 'Aircon', 'Wireless Microphones', 'Non-Wireless Microphones', 'Monobloc Chairs',
        'Lights', 'Podium', 'HDMI', 'Television (CHIC)', 'Television (Balay Alumni)',
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
            'aircon' => 'Aircon',
            'air conditioner' => 'Aircon',
            'air conditioning unit' => 'Aircon',
            'chair' => 'Chairs',
            'chairs' => 'Chairs',
            'monobloc chair' => 'Monobloc Chairs',
            'monobloc chairs' => 'Monobloc Chairs',
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
        $sort = $request->query('sort', 'oldest');

        $query = FacilityRequest::with(['requestVenues', 'requestEquipment', 'reservationSchedule', 'histories'])
            ->where('requested_by_id', $user->id)
            ->upcomingReservation();

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
            if ($statusFilter === 'cancelled') {
                $query->where(function ($statusQuery): void {
                    $statusQuery->whereRaw('LOWER(status) = ?', ['cancelled'])
                        ->orWhereHas('histories', fn ($historyQuery) => $historyQuery->where('action', 'cancelled'));
                });
            } elseif ($statusFilter === 'pending') {
                $query->whereRaw('LOWER(status) = ?', ['pending'])
                    ->whereDoesntHave('histories', fn ($historyQuery) => $historyQuery->where('action', 'cancelled'));
            } else {
                $query->whereRaw('LOWER(status) = ?', [$statusFilter]);
            }
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

        $sortDirection = $sort === 'latest' ? 'desc' : 'asc';
        $query->orderBy('start_date', $sortDirection)
            ->orderBy('start_time', $sortDirection)
            ->orderBy('created_at', $sortDirection);

        $requests = $query->get();
        $equipment = \App\Models\Equipment::where('is_active', true)->get();

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
        $venueOptions = array_values(array_unique(array_map(function ($venueName): string {
            $value = trim((string) $venueName);

            return strtolower($value) === 'balay alumni hall' ? 'Balay Alumni' : $value;
        }, array_merge(
            $venueRecords->pluck('name')->filter()->values()->all(),
            self::VENUE_OPTIONS
        ))));

        foreach ($venueOptions as $venueName) {
            $venueCapacityMap[$venueName] = $this->availabilityService->getVenueCapacity($venueName);
        }
        $venueDefaultEquipmentMap = collect($venueOptions)
            ->mapWithKeys(fn (string $venueName): array => [$venueName => VenueEquipmentPolicy::getDefaultEquipment($venueName)])
            ->all();
        $venueEquipmentVisibilityMap = VenueEquipmentPolicy::getVenueRestrictedEquipment();

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
            'venueDefaultEquipmentMap' => $venueDefaultEquipmentMap,
            'venueEquipmentVisibilityMap' => $venueEquipmentVisibilityMap,
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
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $user = $this->currentUser();
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $user->password = Hash::make($request->password);
        $user->save();
        $user->tokens()->delete();
        $this->notifyPasswordChanged($user);

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

        $viewer = Auth::user();
        if (! $viewer || ($viewer->id !== $targetUser->id && ! $viewer->isAdmin())) {
            abort(403);
        }

        $path = 'documents/e_signature/users/' . $targetUser->e_signature_file;

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $filePath = Storage::disk('local')->path($path);
        $mimeType = mime_content_type($filePath) ?: 'image/png';

        return response()->file($filePath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
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
        $shouldAllowEdit = $facilityRequest->status === 'needs_reschedule'
            || $facilityRequest->venue_status === 'needs_reschedule'
            || $facilityRequest->equipment_status === 'needs_reschedule'
            || ($facilityRequest->status === 'pending'
                && $facilityRequest->venue_status === 'pending'
                && $facilityRequest->equipment_status === 'pending');

        if ($facilityRequest->requested_by_id !== $user->id || !$shouldAllowEdit) {
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
            'equipment' => \App\Models\Equipment::where('is_active', true)->get(),
            'equipmentQuantityLimits' => $equipmentQuantityLimits,
            'venueCapacityMap' => $venueCapacityMap,
        ]);
    }

    public function update(Request $request, FacilityRequest $facilityRequest)
    {
        $user = $this->currentUser();
        $shouldAllowEdit = $facilityRequest->status === 'needs_reschedule'
            || $facilityRequest->venue_status === 'needs_reschedule'
            || $facilityRequest->equipment_status === 'needs_reschedule'
            || ($facilityRequest->status === 'pending'
                && $facilityRequest->venue_status === 'pending'
                && $facilityRequest->equipment_status === 'pending');

        if ($facilityRequest->requested_by_id !== $user->id || !$shouldAllowEdit) {
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
                'end_time' => '17:00',
            ]);
        }

        $isNeedsReschedule = $facilityRequest->status === 'needs_reschedule';

        $rules = [
            'reservation_duration' => ['nullable', 'in:specific_time,whole_day,whole-day,whole day'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
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

        if (
            ($validated['reservation_duration'] ?? 'specific_time') === 'specific_time'
            && $validated['start_time'] <= '05:00'
        ) {
            return back()
                ->withErrors(['start_time' => 'Reservations cannot start between 12:00 AM and 5:00 AM. Choose a start time after 5:00 AM or use the Whole Day option.'])
                ->withInput();
        }
        if (
            ($validated['reservation_duration'] ?? 'specific_time') === 'specific_time'
            && $validated['end_time'] <= '05:00'
        ) {
            return back()
                ->withErrors(['end_time' => 'Reservations cannot end between 12:00 AM and 5:00 AM. Choose an end time after 5:00 AM or use the Whole Day option.'])
                ->withInput();
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
        $validated['end_date'] = $scheduleRange['end']?->toDateString() ?? $validated['end_date'] ?? $validated['start_date'];

        $selectedEquipment = array_values(array_filter($validated['equipment'] ?? [], fn($item) => !empty($item)));
        $selectedQuantities = $validated['equipment_quantities'] ?? [];
        $explicitEquipment = $selectedEquipment;
        $quantities = [];

        // Apply venue-specific equipment rules during edit
        $selectedVenues = (array) ($validated['venue'] ?? []);
        if (is_string($selectedVenues)) {
            $selectedVenues = [$selectedVenues];
        }

        if (!empty($selectedVenues)) {
            $venueName = $selectedVenues[0];

            // Add default equipment required for this venue if not already selected.
            // These defaults are part of the venue policy, but they are not necessarily
            // explicitly chosen by the requestor and therefore should not be forced to
            // carry an input quantity during reschedule updates.
            $defaultEquipment = VenueEquipmentPolicy::getDefaultEquipment($venueName);
            foreach ($defaultEquipment as $requiredItem) {
                if (!in_array($requiredItem, $selectedEquipment, true)) {
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
            $isExplicitSelection = in_array($itemName, $explicitEquipment, true);
            $quantity = (int) ($selectedQuantities[$itemName] ?? ($isExplicitSelection ? 0 : 1));

            if ($isExplicitSelection && $quantity <= 0) {
                return back()->withErrors(['equipment' => 'Please select required equipment and quantity.'])->withInput();
            }

            if (!$isExplicitSelection && $quantity <= 0) {
                $quantity = 1;
            }

            $maxAllowed = $this->getEditableEquipmentQuantityLimit($facilityRequest, $itemName);
            if ($quantity > $maxAllowed) {
                return back()->withErrors(['equipment' => "Sorry, only {$maxAllowed} unit(s) of '{$itemName}' can be requested while editing this pending request."])->withInput();
            }

            $quantities[$itemName] = $quantity;
        }

        $validated['equipment'] = array_values($selectedEquipment);
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

        $facilityRequest->addHistory(
            'requestor_edited',
            'Request edited by ' . $user->name . ' and returned to pending verification.',
            $user->id
        );

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
                Notification::send($custodians, new \App\Notifications\RequestStatusChanged(
                    $facilityRequest,
                    'change_requested',
                    '',
                    $user->name
                ));
            } catch (\Throwable $e) {
                Log::warning('Failed to notify custodians for rescheduled facility request.', [
                    'facility_request_id' => $facilityRequest->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $supplyOfficeUsers = User::query()->whereIn('role', ['admin', 'supply_office'])->get();
        if ($supplyOfficeUsers->isNotEmpty()) {
            Notification::send($supplyOfficeUsers, new \App\Notifications\RequestStatusChanged(
                $facilityRequest,
                'change_requested',
                '',
                $user->name
            ));
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Request updated successfully.',
                'redirect' => route('requestor.index', ['tab' => 'requests']),
            ]);
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
        $positionOptions = ['Student', 'Faculty', 'Staff', 'Instructor', 'Professor', 'Department Chair', 'Coordinator', 'Office Staff', 'External Partner', 'Student Organization', 'Other'];

        $hasSavedSignature = (bool) ($user->e_signature_file && Storage::disk('local')->exists('documents/e_signature/users/' . $user->e_signature_file));
        $activeVenueNames = Venue::query()->where('is_active', true)->pluck('name')->all();

        $profileCollegeId = $user->college_id ?? null;
        $profileDepartmentId = $user->department_id ?? null;
        if (($user->requestor_type === 'student' || $user->requestor_type === 'faculty') && ! $request->filled('college_id') && $profileCollegeId) {
            $request->merge(['college_id' => $profileCollegeId]);
        }
        if (! $request->filled('department_id') && $profileDepartmentId) {
            $request->merge(['department_id' => $profileDepartmentId]);
        }
        if (is_string($request->input('venue'))) {
            $request->merge(['venue' => [$request->input('venue')]]);
        }

        $rules = [
            'reservation_duration'  => ['nullable', 'in:specific_time,whole_day,whole-day,whole day'],
            'college_id'            => ['nullable', 'exists:colleges,id'],
            'department_id'         => ['nullable', 'exists:departments,id'],
            'department'            => ['nullable', 'string', 'max:100'],
            'organization_name'     => ['nullable', 'string', 'max:191'],
            'request_context'       => ['nullable', 'in:personal,student_organization,outside_organization'],
            'student_organization_id' => ['nullable', 'integer', 'exists:student_organizations,id'],
            'requested_by_position' => ['nullable', 'string', 'max:100'],
            'requested_by_position_other' => ['nullable', 'string', 'max:100', 'required_if:requested_by_position,Other'],
            'name_of_activity'      => 'required|string|max:200',
            'purpose'               => ['nullable', 'string', 'max:2000'],
            'expected_participants' => 'required|integer|min:1',
            'start_date'            => 'nullable|date|after_or_equal:today',
            'end_date'              => 'nullable|date|after_or_equal:start_date',
            'start_time'            => 'nullable|date_format:H:i',
            'end_time'              => 'nullable|date_format:H:i',
            'venue_schedules'       => 'nullable|array',
            'venue_schedules.*'     => 'array',
            'venue_schedules.*.start_date' => 'nullable|date',
            'venue_schedules.*.end_date' => 'nullable|date',
            'venue_schedules.*.start_time' => 'nullable|date_format:H:i',
            'venue_schedules.*.end_time' => 'nullable|date_format:H:i',
            'venue_schedules.*.reservation_duration' => ['nullable', 'in:specific_time,whole_day'],
            'venue'                 => ['required', 'array', 'min:1'],
            'venue.*'               => ['required', 'string', Rule::in(array_merge(self::VENUE_OPTIONS, $activeVenueNames))],
            'venue_equipment' => ['nullable', 'array'],
            'venue_equipment.*' => ['array'],
            'venue_equipment.*.*' => ['string'],
            'venue_equipment_quantities' => ['nullable', 'array'],
            'venue_equipment_quantities.*' => ['array'],
            'venue_equipment_quantities.*.*' => ['nullable', 'integer', 'min:1'],
            'equipment'             => 'nullable|array',
            'equipment_quantities'  => 'nullable|array',
            'equipment_quantities.*' => ['nullable', 'integer', 'min:1'],
            'other_venue'           => 'prohibited',
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
        } elseif (in_array($user->requestor_type, ['outsider'], true)) {
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
        $rules['proposal_file'] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];
        if ($user->isStudent() && $requestContext === 'student_organization') {
            $rules['student_organization_id'][] = 'required';
        }

        // Students and faculty personal requests use proposal documents; outside/organizational requests use receipts.
        if (($user->isStudent() && $requestContext !== 'outside_organization') || ($user->isFaculty() && $requestContext === 'personal')) {
            $rules['activity_proposal_file'] = 'required|file|mimes:pdf,jpeg,jpg,png|max:10240';
            $rules['igp_receipt_file'] = 'nullable|file|mimes:pdf,jpeg,jpg,png|max:10240';
        } elseif ($user->isOutsider()) {
            $rules['igp_receipt_file'] = 'nullable|file|mimes:pdf,jpeg,jpg,png|max:10240';
            $rules['activity_proposal_file'] = 'nullable|file|mimes:pdf,jpeg,jpg,png|max:10240';
        } else {
            $rules['igp_receipt_file'] = 'required|file|mimes:pdf,jpeg,jpg,png|max:10240';
            $rules['activity_proposal_file'] = 'nullable|file|mimes:pdf,jpeg,jpg,png|max:10240';
        }
        if ($request->boolean('is_emergency')) {
            $rules['proposal_file'] = ['required_without:activity_proposal_file', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];
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
                'end_time' => '17:00',
            ]);
        }

        $validated = $request->validate($rules);

        $venue = array_values(array_unique(array_map(
            static fn (string $venueName): string => trim($venueName),
            $validated['venue']
        )));
        $submittedSchedules = $request->input('venue_schedules', []);
        $venueSchedules = [];
        $scheduleRanges = [];
        foreach ($venue as $venueName) {
            $scheduleInput = $submittedSchedules[$venueName] ?? [];
            if (! is_array($scheduleInput) || $scheduleInput === []) {
                $scheduleInput = [
                    'start_date' => $validated['start_date'] ?? null,
                    'end_date' => $validated['end_date'] ?? null,
                    'start_time' => $validated['start_time'] ?? null,
                    'end_time' => $validated['end_time'] ?? null,
                    'reservation_duration' => $validated['reservation_duration'] ?? 'specific_time',
                ];
            }

            $scheduleValidator = \Illuminate\Support\Facades\Validator::make($scheduleInput, [
                'start_date' => ['required', 'date', 'after_or_equal:today'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
                'start_time' => ['required', 'date_format:H:i'],
                'end_time' => ['required', 'date_format:H:i'],
                'reservation_duration' => ['nullable', 'in:specific_time,whole_day'],
            ]);
            if ($scheduleValidator->fails()) {
                return back()->withErrors($scheduleValidator)->withInput();
            }

            $scheduleInput = $scheduleValidator->validated();
            $duration = $scheduleInput['reservation_duration'] ?? 'specific_time';
            if ($duration === 'specific_time' && $scheduleInput['start_time'] <= '05:00') {
                return back()->withErrors([
                    "venue_schedules.{$venueName}.start_time" => "{$venueName}: reservations cannot start from 12:00 AM through 5:00 AM.",
                ])->withInput();
            }
            if ($duration === 'specific_time' && $scheduleInput['end_time'] <= '05:00') {
                return back()->withErrors([
                    "venue_schedules.{$venueName}.end_time" => "{$venueName}: reservations cannot end from 12:00 AM through 5:00 AM.",
                ])->withInput();
            }

            $range = FacilityRequest::resolveReservationDuration(
                $duration,
                $scheduleInput['start_date'],
                $scheduleInput['start_time'],
                $scheduleInput['end_date'],
                $scheduleInput['end_time']
            );
            if ($range['end']->lte($range['start'])) {
                return back()->withErrors([
                    "venue_schedules.{$venueName}.end_date" => "{$venueName}: end date and time must be after the start date and time.",
                ])->withInput();
            }

            $scheduleRanges[$venueName] = $range;
            $venueSchedules[$venueName] = [
                'start_date' => $range['start']->toDateString(),
                'end_date' => $range['end']->toDateString(),
                'start_time' => $range['start']->format('H:i'),
                'end_time' => $range['end']->format('H:i'),
                'reservation_duration' => $duration,
            ];
        }

        $startDateTime = collect($scheduleRanges)->pluck('start')->sort()->first();
        $endDateTime = collect($scheduleRanges)->pluck('end')->sort()->last();
        $validated['start_date'] = $startDateTime->toDateString();
        $validated['end_date'] = $endDateTime->toDateString();
        $validated['start_time'] = $startDateTime->format('H:i');
        $validated['end_time'] = $endDateTime->format('H:i');
        $validated['reservation_duration'] = collect($venueSchedules)->every(
            fn (array $schedule): bool => $schedule['reservation_duration'] === 'whole_day'
        ) ? 'whole_day' : 'specific_time';
        if ($request->boolean('is_emergency')) {
            $hasSameDayUrgentSchedule = collect($scheduleRanges)->every(
                fn (array $range): bool => $range['start']->toDateString() === now()->toDateString()
                    && $range['end']->toDateString() === now()->toDateString()
            );
            if (! $hasSameDayUrgentSchedule) {
                return back()
                    ->withErrors(['is_emergency' => 'Urgent requests must be submitted and held entirely on the same local calendar day.'])
                    ->withInput();
            }
        }

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
        $positionOptions = ['Student', 'Faculty', 'Staff', 'Instructor', 'Professor', 'Department Chair', 'Coordinator', 'Office Staff', 'External Partner', 'Student Organization', 'Other'];
        $profilePosition = trim((string) ($user->position ?? ''));
        $trustedPosition = $profilePosition !== '' && in_array($profilePosition, $positionOptions, true)
            ? $profilePosition
            : (
                $profilePosition !== ''
                    ? 'Other'
                    : match ($user->requestor_type) {
                        'student' => 'Student Organization',
                        'faculty' => 'Faculty',
                        'staff' => 'Staff',
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
        } elseif (in_array($user->requestor_type, ['outsider'], true)) {
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

        $capacityWarning = null;
 
        $user = $this->currentUser();
 
        $submittedVenueEquipment = $request->input('venue_equipment');
        $submittedVenueQuantities = $request->input('venue_equipment_quantities', []);
        $legacyEquipment = array_values(array_filter(
            $request->input('equipment', []),
            fn ($item): bool => is_string($item) && trim($item) !== ''
        ));
        $legacyQuantities = $request->input('equipment_quantities', []);
        $venueEquipment = [];
        $equipmentQuantitiesByName = [];

        foreach ($venue as $venueName) {
            $hasVenueSpecificSelection = is_array($submittedVenueEquipment)
                && array_key_exists($venueName, $submittedVenueEquipment);
            $venueItems = $hasVenueSpecificSelection
                ? (array) $submittedVenueEquipment[$venueName]
                : $legacyEquipment;

            if (in_array('microphones', array_map(static fn ($item): string => mb_strtolower(trim((string) $item)), $venueItems), true)) {
                return back()->withErrors(['equipment' => 'Please select Wireless Microphones or Non-Wireless Microphones.'])->withInput();
            }

            $normalizedItems = self::normalizeEquipmentSelection(array_values(array_filter(
                $venueItems,
                fn ($item): bool => is_string($item) && trim($item) !== ''
            )));
            $normalizedItems = array_values(array_unique(array_merge(
                $normalizedItems,
                VenueEquipmentPolicy::getDefaultEquipment($venueName)
            )));
            $unsupportedEquipment = array_values(array_diff($normalizedItems, self::EQUIPMENT_OPTIONS));
            if ($unsupportedEquipment !== []) {
                return back()
                    ->withErrors(['equipment' => 'One or more selected equipment items are no longer available. Please refresh the form and select from the available equipment.'])
                    ->withInput();
            }

            $requestedIncompatible = array_intersect($normalizedItems, VenueEquipmentPolicy::getIncompatibleEquipment($venueName));
            if ($requestedIncompatible !== []) {
                $incompatibleList = implode(', ', $requestedIncompatible);
                return back()->withErrors(['equipment' => "{$venueName} does not support the following equipment: {$incompatibleList}. Please select a different venue or remove these items."])->withInput();
            }

            $microphoneCount = count(array_intersect($normalizedItems, ['Wireless Microphones', 'Non-Wireless Microphones']));
            if ($microphoneCount > 1) {
                return back()->withErrors(['equipment' => "{$venueName}: select either Wireless Microphones or Non-Wireless Microphones, not both."])->withInput();
            }

            $venueQuantities = is_array($submittedVenueQuantities[$venueName] ?? null)
                ? $submittedVenueQuantities[$venueName]
                : [];
            foreach ($normalizedItems as $equipmentName) {
                $hasSubmittedQuantity = array_key_exists($equipmentName, $venueQuantities)
                    || (! $hasVenueSpecificSelection && array_key_exists($equipmentName, $legacyQuantities));
                $quantity = (int) ($venueQuantities[$equipmentName] ?? $legacyQuantities[$equipmentName] ?? 0);
                if (! $hasSubmittedQuantity && in_array($equipmentName, VenueEquipmentPolicy::getDefaultEquipment($venueName), true)) {
                    $quantity = 1;
                }
                if ($quantity <= 0) {
                    return back()->withErrors(['equipment' => "Enter a quantity for {$equipmentName} at {$venueName}."])->withInput();
                }

                $venueEquipment[$venueName][$equipmentName] = $quantity;
                $equipmentQuantitiesByName[$equipmentName] = ($equipmentQuantitiesByName[$equipmentName] ?? 0) + $quantity;
            }
        }

        $equipment = array_keys($equipmentQuantitiesByName);
        $quantities = $equipmentQuantitiesByName;

        if (!empty($validated['is_emergency']) && $validated['is_emergency']) {
            $hasSameDayUrgentSchedule = collect($scheduleRanges)->every(
                fn (array $range): bool => $range['start']->toDateString() === now()->toDateString()
                    && $range['end']->toDateString() === now()->toDateString()
            );
            if (! $hasSameDayUrgentSchedule) {
                return back()
                    ->withErrors(['is_emergency' => 'Urgent requests must be submitted and held entirely on the same local calendar day.'])
                    ->withInput();
            }

            if (! $request->hasFile('proposal_file') && ! $request->hasFile('activity_proposal_file')) {
                return back()->withErrors(['proposal_file' => 'Urgent requests require a proposal or memorandum order.'])->withInput();
            }
        }

        $isUrgentRequest = $request->boolean('is_emergency');

        // Availability is validated below per venue schedule and equipment assignment.

        // Venue conflicts are checked by AvailabilityService using each venue's schedule.
        if (!empty($venue)) {
            // ✅ CHECK VENUE CAPACITY
            $participants = (int) $validated['expected_participants'];
            $selectedVenues = array_values($venue);
            $overCapacityVenues = [];
            foreach ($selectedVenues as $venueName) {
                $venueRecord = \App\Models\Venue::where('name', $venueName)->first();
                $capacity = null;

                if ($venueRecord && $venueRecord->capacity !== null) {
                    $capacity = (int) $venueRecord->capacity;
                }

                if ($capacity !== null && $participants > $capacity) {
                    $overCapacityVenues[] = "{$venueName} (maximum {$capacity})";
                }
            }

            if ($overCapacityVenues !== []) {
                $venueList = implode(', ', $overCapacityVenues);
                $capacityWarning = "The expected {$participants} participants exceed the capacity of: {$venueList}. The request will still be submitted for Supply Office review.";
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
            $result = $documentUploadService->uploadDocument($file, 'proposal_file', $controlNumber);
            if ($result['success']) {
                $proposalFileName = $result['filename'];
                $documentMetadata['proposal_file'] = [
                    'uploaded_at' => now()->toDateTimeString(),
                    'original_name' => $file->getClientOriginalName(),
                ];
            } else {
                return back()->withErrors(['proposal_file' => $result['error']])->withInput();
            }
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

        $requestAttributes = [
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
            'reservation_duration'     => $validated['reservation_duration'],
            'venue'                    => $venue,
            'equipment'                => $equipment,
            'equipment_quantities'     => $quantities,
            'other_venue'              => $validated['other_venue'] ?? null,
            'notes'                    => $notes,
            'requested_by_id'          => $user->id,
            'status'                   => 'pending',
            'venue_status'             => 'pending',
            'equipment_status'         => 'pending',
            'supply_office_review_status' => 'pending',
            'urgent_document_status'   => $isUrgentRequest ? 'pending' : 'not_required',
            'igp_payment_status'       => $user->isOutsider() ? 'awaiting_availability' : null,
            'priority'                 => $isUrgentRequest ? 'institutional' : 'regular',
            'requested_priority'       => $isUrgentRequest ? 'institutional' : null,
            'requested_is_emergency'   => $validated['is_emergency'] ?? false,
            'is_emergency'             => $validated['is_emergency'] ?? false,
            'emergency_justification'  => $validated['emergency_justification'] ?? null,
            'proposal_file'            => $proposalFileName,
            'activity_proposal_file'   => $activityProposalFileName,
            'igp_receipt_file'         => $igpReceiptFileName,
            'e_signature_file'         => $eSignatureFileName,
            'document_metadata'        => $documentMetadata,
        ];

        $knownEquipmentNames = Equipment::query()
            ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(name)'), array_map('strtolower', array_keys($quantities)))
            ->pluck('name')
            ->map(fn (string $name) => mb_strtolower($name))
            ->all();
        $availabilityCandidate = new FacilityRequest($requestAttributes);
        $availabilityCandidate->setRelation(
            'requestVenues',
            collect($venueSchedules)->map(
                fn (array $schedule, string $name): \App\Models\RequestVenue => new \App\Models\RequestVenue([
                    'name' => $name,
                    ...$schedule,
                ])
            )->values()
        );
        $availabilityCandidate->setAttribute('venue_equipment_allocations', $venueEquipment);
        $availabilityQuantities = [];
        foreach ($quantities as $name => $quantity) {
            if (in_array(mb_strtolower($name), $knownEquipmentNames, true)) {
                $availabilityQuantities[$name] = $quantity;
            }
        }
        $availabilityCandidate->equipment_quantities = $availabilityQuantities;

        DB::beginTransaction();
        $this->availabilityService->lockResourcesForFacilityRequests($availabilityCandidate);
        $availabilityMessage = $this->availabilityService->checkFacilityRequest(
            $availabilityCandidate,
            null,
            $isUrgentRequest
        );
        if ($availabilityMessage) {
            DB::rollBack();

            foreach ([
                ['proposal_file', $proposalFileName],
                ['activity_proposal', $activityProposalFileName],
                ['igp_receipt', $igpReceiptFileName],
                ['e_signature', $eSignatureFileName],
            ] as [$documentType, $filename]) {
                if ($filename) {
                    Storage::disk('local')->delete("documents/{$documentType}/{$filename}");
                }
            }

            $errorKey = str_contains(strtolower($availabilityMessage), 'venue') ? 'venue' : 'equipment';

            return back()->withErrors([$errorKey => $availabilityMessage])->withInput();
        }

        $fr = FacilityRequest::create($requestAttributes);
        $fr->syncRelationalItems($venueSchedules, $venueEquipment);
        DB::commit();

        // Determine custodians using authoritative model helper (includes authorized alternates)
        $equipmentCustodianIds = $fr->getAssignedEquipmentCustodianIds();
        $venueCustodianIds = \App\Models\Venue::whereIn('name', $venue)
            ->pluck('custodian_id')
            ->filter()
            ->unique()
            ->toArray();

        $custodianIds = array_values(array_unique(array_merge($equipmentCustodianIds, $venueCustodianIds)));

        // Custodians are notified only after the Supply Office's initial availability review.
        \App\Events\RequestCreated::dispatch($fr->id, $fr->control_number, $user->name, $fr->requested_by_id, []);

        return redirect()->route('requestor.index', ['tab' => 'requests'])
                        ->with('success', 'Request submitted successfully!')
                        ->with('warning', $capacityWarning);
    }
 
    public function destroy(Request $request)
    {
        $facilityRequest = FacilityRequest::query()
            ->whereKey($request->input('id'))
            ->firstOrFail();

        return app(RequestActionController::class)->cancel($facilityRequest->id, $request);
    }

    public function show($id)
    {
        $request   = FacilityRequest::with(['requestVenues', 'requestEquipment', 'reservationSchedule', 'histories'])->findOrFail($id);
        $this->authorize('view', $request);
        $pendingRevision = $request->revisionHistories()->where('status', 'pending')->first();
        $equipment = \App\Models\Equipment::where('is_active', true)->get();
        $venueCustodians = Venue::query()
            ->whereIn('name', $request->getVenueNames())
            ->with('custodian')
            ->get()
            ->pluck('custodian')
            ->filter()
            ->unique('id')
            ->values();
        $equipmentCustodians = User::query()
            ->whereIn('id', $request->getAssignedEquipmentCustodianIds())
            ->get();
        $custodialEndorsements = $venueCustodians
            ->map(fn (User $custodian): array => [
                'name' => $custodian->name,
                'resource_type' => 'Venue custodian',
                'status' => $request->venue_status ?? 'pending',
            ])
            ->concat($equipmentCustodians->map(fn (User $custodian): array => [
                'name' => $custodian->name,
                'resource_type' => 'Equipment custodian',
                'status' => $request->getCustodianEquipmentStatus((int) $custodian->id),
            ]))
            ->values();
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
            'pendingRevision'         => $pendingRevision,
            'venueOptions'            => self::VENUE_OPTIONS,
            'equipOptions'            => self::EQUIPMENT_OPTIONS,
            'equipment'               => $equipment,
            'custodialEndorsements'   => $custodialEndorsements,
            'currentCustodianEquipment' => $currentCustodianEquipment,
            'hasEndorsed'             => $hasEndorsed,
        ]);
    }

    public function print($id)
    {
        $request = FacilityRequest::findOrFail($id);
        $this->authorize('print', $request);

        $signatureTimestamp = data_get($request->document_metadata, 'e_signature.uploaded_at');
        $signatureTime = $signatureTimestamp
            ? Carbon::parse($signatureTimestamp)
            : $request->created_at;
        $signatureSerial = $request->e_signature_file && $signatureTime
            ? sprintf('ES-%d-%s', $request->id, $signatureTime->format('YmdHis'))
            : null;

        $approvalSignatures = [];
        foreach ([
            'venue' => 'venue_approval_signature_file',
            'equipment' => 'equipment_approval_signature_file',
            'final' => 'final_approval_signature_file',
        ] as $stage => $signatureField) {
            $snapshots = $request->getStageApprovalSnapshots($stage);
            if ($snapshots === []) {
                $snapshots = [[
                    'approver_name' => $request->getStageApproverName($stage),
                    'approver_position' => $request->getStageApproverPosition($stage),
                    'signature_file' => $request->{$signatureField},
                    'time' => $request->getStageApprovalDate($stage)?->toISOString(),
                ]];
            }

            $approvalSignatures[$stage] = array_map(function (array $snapshot) use ($request, $signatureField): array {
                $filename = $snapshot['signature_file'] ?? $request->{$signatureField};

                return [
                    'name' => $snapshot['approver_name'] ?? 'Approver',
                    'position' => $snapshot['approver_position'] ?? null,
                    'date' => ! empty($snapshot['time']) ? Carbon::parse($snapshot['time'])->format('M j, Y') : null,
                    'image' => $this->signatureDataUri($filename, 'documents/e_signature/approvals'),
                ];
            }, $snapshots);
        }

        return view('request.print', [
            'request' => $request,
            'signatureSerial' => $signatureSerial,
            'requestorSignatureImage' => $this->signatureDataUri(
                $request->e_signature_file,
                'documents/e_signature'
            ),
            'approvalSignatures' => $approvalSignatures,
        ]);
    }

    private function signatureDataUri(?string $filename, string $directory): ?string
    {
        if (! $filename) {
            return null;
        }

        $disk = Storage::disk('local');
        $path = $directory . '/' . $filename;
        if (! $disk->exists($path)) {
            return null;
        }

        $mimeType = $disk->mimeType($path);
        if (! in_array($mimeType, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
            return null;
        }

        return 'data:' . $mimeType . ';base64,' . base64_encode($disk->get($path));
    }

    public function proposal($id)
    {
        $request = FacilityRequest::findOrFail($id);
        $this->authorize('view', $request);

        $filename = $request->activity_proposal_file ?: $request->proposal_file;
        if (!$filename) {
            abort(404);
        }

        [$disk, $filePath] = $this->resolveProposalPath($request, $filename);
        $mimeType = match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };

        if ($mimeType === 'application/octet-stream') {
            return Storage::disk($disk)->download($filePath, basename($filename), [
                'Content-Type' => $mimeType,
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response()->file(Storage::disk($disk)->path($filePath), [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
        if (!$filename) {
            abort(404);
        }

        [$disk, $filePath] = $this->resolveProposalPath($request, $filename);

        return Storage::disk($disk)->download($filePath, basename($filename), [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array{0: string, 1: string} */
    private function resolveProposalPath(FacilityRequest $request, string $filename): array
    {
        if (
            $filename === '.' || $filename === '..'
            || str_contains($filename, '/') || str_contains($filename, '\\')
        ) {
            abort(404);
        }

        $paths = $request->activity_proposal_file
            ? [
                ['local', 'documents/activity_proposal/' . $filename],
                ['public', 'documents/activity_proposal/' . $filename],
            ]
            : [
                ['local', 'documents/proposal_file/' . $filename],
                ['local', 'proposals/' . $filename],
                ['public', 'proposals/' . $filename],
            ];

        foreach ($paths as [$disk, $path]) {
            if (Storage::disk($disk)->exists($path)) {
                return [$disk, $path];
            }
        }

        abort(404);
    }

    // ✅ Real-time availability check endpoint
    public function equipmentAvailability(Request $request)
    {
        $name = trim((string) $request->input('name', ''));

        if ($name === '') {
            return response()->json([
                'available' => 0,
                'total' => 0,
                'reserved_quantity' => 0,
                'remaining_quantity' => 0,
                'overlapping_requests' => [],
            ]);
        }

        $normalizedName = strtolower($name);
        $eq = \App\Models\Equipment::whereRaw('LOWER(name) = ?', [$normalizedName])
            ->orWhereRaw('LOWER(name) LIKE ?', ["%{$normalizedName}%"])
            ->first();

        if (!$eq) {
            return response()->json([
                'available' => 0,
                'total' => 0,
                'reserved_quantity' => 0,
                'remaining_quantity' => 0,
                'overlapping_requests' => [],
            ]);
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
            $overlappingRequests = \App\Models\FacilityRequest::where('status', 'approved')
                ->whereHas('reservationSchedule', function ($query) use ($requestedStart, $requestedEnd) {
                    $query->where('start_datetime', '<', $requestedEnd)
                        ->where('end_datetime', '>', $requestedStart);
                })
                ->orWhere(function ($query) use ($requestedStart, $requestedEnd) {
                    $query->where('status', 'approved')
                        ->whereDoesntHave('reservationSchedule')
                        ->whereDate('start_date', '<=', $requestedEnd->toDateString())
                        ->where(function ($endDateQuery) use ($requestedStart) {
                            $endDateQuery->whereDate('end_date', '>=', $requestedStart->toDateString())
                                ->orWhere(function ($singleDateQuery) use ($requestedStart) {
                                    $singleDateQuery->whereNull('end_date')
                                        ->whereDate('start_date', '>=', $requestedStart->toDateString());
                                });
                        });
                })
                ->get()
                ->filter(function ($req) use ($normalizedName) {
                    $quantities = $req->getInventoryOutstandingQuantities();
                    if (!empty($quantities)) {
                        foreach ($quantities as $requestedName => $requestedQty) {
                            $candidate = strtolower((string) $requestedName);
                            if ($candidate === $normalizedName && (int) $requestedQty > 0) {
                                return true;
                            }
                        }
                    }

                    return false;
                })
                ->map(function ($req) use ($normalizedName) {
                    $quantities = $req->getInventoryOutstandingQuantities();
                    $quantity = 0;

                    if (!empty($quantities)) {
                        foreach ($quantities as $requestedName => $requestedQty) {
                            $candidate = strtolower((string) $requestedName);
                            if ($candidate === $normalizedName) {
                                $quantity += (int) $requestedQty;
                            }
                        }
                    }

                    return [
                        'activity' => $req->name_of_activity ?: 'Existing reservation',
                        'quantity' => max(0, $quantity),
                        'schedule' => $req->reservationSchedule ? $req->reservationSchedule->start_datetime?->format('F j, Y') : null,
                        'priority' => $req->priority ?? 'regular',
                        'control_number' => $req->control_number ?? null,
                    ];
                })
                ->filter(fn ($reservation) => $reservation['quantity'] > 0)
                ->values()
                ->all();
        }

        $total = (int) $eq->quantity;
        $remainingQuantity = max(0, (int) ($availability['available_qty'] ?? 0));

        return response()->json([
            'available' => $remainingQuantity,
            'total' => $total,
            'reserved_quantity' => max(0, $total - $remainingQuantity),
            'remaining_quantity' => $remainingQuantity,
            'overlapping_requests' => $overlappingRequests,
        ]);
    }

    public function venueAvailability(Request $request)
    {
        $this->currentUser();
        $validated = $request->validate([
            'venues' => ['required', 'array', 'min:1'],
            'venues.*' => ['required', 'string', Rule::in(array_merge(
                self::VENUE_OPTIONS,
                Venue::query()->where('is_active', true)->pluck('name')->all()
            ))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'reservation_duration' => ['nullable', 'in:specific_time,whole_day'],
        ]);

        $range = FacilityRequest::resolveReservationDuration(
            $validated['reservation_duration'] ?? 'specific_time',
            $validated['start_date'],
            $validated['start_time'],
            $validated['end_date'],
            $validated['end_time'],
        );

        $results = [];
        foreach (array_values(array_unique($validated['venues'])) as $venueName) {
            $availability = $this->availabilityService->checkVenueAvailability(
                $venueName,
                $range['start'],
                $range['end'],
            );
            $results[] = [
                'venue' => $venueName,
                'available' => $availability['available'],
                'message' => $availability['message'] ?? null,
            ];
        }

        return response()->json([
            'available' => collect($results)->every(fn (array $result): bool => $result['available']),
            'venues' => $results,
        ]);
    }
}
