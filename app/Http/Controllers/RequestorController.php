<?php
namespace App\Http\Controllers;
 
use Illuminate\Validation\Rules\Password as PasswordRule;
use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\RevisionHistory;
use App\Models\College;
use App\Models\Department;
use App\Models\User;
use App\Models\Venue;
use App\Services\AvailabilityService;
use App\Services\VenueEquipmentPolicy;
use App\Support\EquipmentQuantity;
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
            return back()->withErrors(['revision' => 'This suggested schedule has already been answered.']);
        }

        $affectedUserIds = array_map('intval', $revision->affected_user_ids ?? [$facilityRequest->requested_by_id]);
        abort_unless(in_array((int) $user->id, $affectedUserIds, true), 403);
        if ($revision->memorandum_priority
            && (int) $user->id === (int) $facilityRequest->requested_by_id
            && ! $user->isCustodian()) {
            abort(403, 'Memorandum-priority transfers do not require requestor consent.');
        }

        $revisionResponses = $revision->getResponsesRequiringConfirmation((int) $facilityRequest->requested_by_id);
        $requestorResponse = $revisionResponses[(string) $facilityRequest->requested_by_id] ?? 'pending';
        $isRequestor = (int) $user->id === (int) $facilityRequest->requested_by_id;
        if ($validated['response'] === 'accept' && ! $isRequestor && $requestorResponse === 'pending') {
            return back()->withErrors(['revision' => 'The requestor must accept the suggested schedule before custodians can review it.']);
        }

        if ($revision->response_deadline_at && $revision->response_deadline_at->isPast()) {
            $revision->update(['status' => 'expired', 'responded_at' => now()]);
            $facilityRequest->addHistory('revision_expired', 'Suggested schedule expired without changing the approved reservation.', $user->id);
            Notification::send(
                User::query()->whereIn('id', $affectedUserIds)->get(),
                new \App\Notifications\RequestStatusChanged($facilityRequest, 'revision_expired', 'The change proposal expired. The approved reservation remains unchanged.')
            );
            return back()->withErrors(['revision' => 'This proposal expired. The existing reservation remains unchanged.']);
        }

        if ($validated['response'] === 'decline') {
            $declined = DB::transaction(function () use ($facilityRequest, $revision, $user, $isRequestor): bool {
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
                if ($isRequestor && $lockedRequest->status !== 'approved') {
                    $lockedRequest->update([
                        'status' => 'needs_reschedule',
                        'venue_status' => 'needs_reschedule',
                    ]);
                }
                $lockedRequest->addHistory(
                    'revision_declined',
                    $isRequestor && $lockedRequest->status !== 'approved'
                        ? $user->name . ' declined the suggested schedule and may edit the request for another venue or schedule.'
                        : $user->name . ' declined the suggested new schedule. The current reservation remains unchanged.',
                    $user->id,
                );
                return true;
            });
            if (!$declined) {
                return back()->withErrors(['revision' => 'This suggested schedule has already been answered.']);
            }

            $revision->refresh();
            $revision->revisedBy?->notify(new \App\Notifications\ReservationRescheduleResponse($revision));
            Notification::send(
                User::query()->whereIn('id', $affectedUserIds)->whereKeyNot($user->id)->get(),
                new \App\Notifications\RequestStatusChanged($facilityRequest, 'change_request_rejected', $user->name . ' declined the proposed change. The approved reservation remains unchanged.')
            );

            return redirect()->route('request.show', $facilityRequest)
                ->with('success', $isRequestor && $facilityRequest->status !== 'approved'
                    ? 'You declined the suggested schedule. Edit your request to choose another venue, date, or time; it will return to venue review.'
                    : 'You declined the suggested schedule. The current reservation remains unchanged.');
        }

        if ($user->isCustodian()) {
            $availabilityConflict = $this->availabilityService->checkRevisionAvailabilityForCustodian(
                $facilityRequest,
                $revision,
                $user,
            );
            if ($availabilityConflict !== null && ! $revision->override_conflict) {
                return back()->withErrors(['revision' => $availabilityConflict]);
            }
        }

        $responseProgress = DB::transaction(function () use ($facilityRequest, $revision, $user, $isRequestor): ?bool {
            $lockedRequest = FacilityRequest::query()->lockForUpdate()->findOrFail($facilityRequest->id);
            $lockedRevision = RevisionHistory::query()->lockForUpdate()->findOrFail($revision->id);
            if ($lockedRevision->status !== 'pending' || $lockedRevision->facility_request_id !== $lockedRequest->id) {
                return null;
            }
            if ($lockedRevision->response_deadline_at && $lockedRevision->response_deadline_at->isPast()) {
                $lockedRevision->update(['status' => 'expired', 'responded_at' => now()]);
                $lockedRequest->addHistory('revision_expired', 'Suggested schedule expired without changing the approved reservation.', $user->id);
                return null;
            }

            $responses = $lockedRevision->getResponsesRequiringConfirmation(
                (int) $lockedRequest->requested_by_id,
                $isRequestor,
            );
            $responses[(string) $user->id] = 'accepted';
            $lockedRevision->responses = $responses;
            $lockedRevision->save();
            $responseDetail = $user->name . ' accepted the suggested new schedule.';
            if ($user->isCustodianVenue()) {
                $responseDetail .= ' Venue availability was rechecked against the suggested schedule.';
            } elseif ($user->isCustodianEquipment()) {
                $responseDetail .= ' Assigned equipment availability was rechecked for the suggested schedule.';
            }
            $lockedRequest->addHistory('revision_response_received', $responseDetail, $user->id);

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
            if ($isRequestor) {
                $effectiveResponses = $revision->getResponsesRequiringConfirmation(
                    (int) $facilityRequest->requested_by_id,
                    $isRequestor,
                );
                $pendingUserIds = collect($revision->affected_user_ids ?? [])
                    ->filter(fn ($userId): bool => ($effectiveResponses[(string) $userId] ?? 'pending') === 'pending')
                    ->map(fn ($userId): int => (int) $userId)
                    ->reject(fn (int $userId): bool => $userId === (int) $user->id)
                    ->values();
                $pendingUsers = User::query()->whereIn('id', $pendingUserIds)->get();
                $reviewRecipients = $pendingUsers->push($revision->revisedBy)->filter()->unique('id');
                Notification::send(
                    $reviewRecipients,
                    new \App\Notifications\ReservationRescheduleReviewRequired($revision, $user->name),
                );
            }

            return redirect()->route('request.show', $facilityRequest)
                ->with('success', 'Your acceptance was recorded. The reservation remains unchanged while affected custodians review the suggested schedule.');
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
            'reservation_duration' => 'specific_time',
        ];
        if ($newState['start_time'] <= '05:00' || $newState['end_time'] <= '05:00') {
            return back()->withErrors([
                'revision' => 'This suggested schedule is not allowed because reservations cannot start or end between 12:00 AM and 5:00 AM. Contact the Supply Office for a revised schedule.',
            ]);
        }
        $range = FacilityRequest::normalizeScheduleRange(
            $newState['start_date'],
            $newState['start_time'],
            $newState['end_date'],
            $newState['end_time']
        );
        // REQ-16: never accept a proposal whose end is not after its start.
        if (! $range['start'] || ! $range['end'] || $range['end']->lte($range['start'])) {
            return back()->withErrors([
                'revision' => 'This suggested schedule ends before it starts. Contact the Supply Office for a revised schedule.',
            ]);
        }
        $availabilityService = $this->availabilityService;
        $venueConflict = false;
        foreach ($newState['venue'] as $venueName) {
            if ($availabilityService->checkVenueTimeRangeForReview(
                $venueName,
                $range['start'],
                $range['end'],
                $facilityRequest->id
            ) !== null) {
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
                'revision' => 'The suggested schedule is no longer available. Contact the Supply Office for an updated schedule.',
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
            $proposedResources = new FacilityRequest([
                'venue' => $newState['venue'],
                'equipment' => $newState['equipment'],
                'equipment_quantities' => $newState['equipment_quantities'],
            ]);
            app(AvailabilityService::class)->lockResourcesForFacilityRequests($facilityRequest, $proposedResources);
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
                if ($availabilityService->checkVenueTimeRangeForReview(
                    $venueName,
                    $range['start'],
                    $range['end'],
                    $lockedRequest->id
                ) !== null) {
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

            $venueCustodianIds = Venue::query()
                ->whereIn('name', $newState['venue'])
                ->pluck('custodian_id')
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();
            $confirmedVenueCustodianId = $venueCustodianIds->first(
                fn (int $custodianId): bool => ($lockedRevision->responses[(string) $custodianId] ?? 'pending') === 'accepted'
            );
            $updates = [
                'start_date' => $newState['start_date'],
                'end_date' => $newState['end_date'],
                'start_time' => $newState['start_time'],
                'end_time' => $newState['end_time'],
                'venue' => $newState['venue'],
                'equipment' => $newState['equipment'],
                'equipment_quantities' => $newState['equipment_quantities'],
                'reservation_duration' => 'specific_time',
            ];
            $wasApproved = $lockedRequest->status === 'approved';
            if ($wasApproved) {
                // APR-05: the old approval does not cover the new details. The changed
                // reservation goes back to the Supply Office for final approval; custodians
                // who accepted the new details count as re-endorsing them. History keeps
                // the old approved version (request_version, APR-03).
                $meta = $lockedRequest->approval_signature_meta ?? [];
                unset($meta['final'], $meta['venue'], $meta['equipment']);
                $updates += [
                    'status' => 'pending',
                    'venue_status' => $confirmedVenueCustodianId !== null ? 'approved' : 'pending',
                    'equipment_custodian_statuses' => [],
                    'approved_by' => null,
                    'approved_by_id' => null,
                    'approved_date' => null,
                    'final_approval_signature' => null,
                    'final_approval_signature_file' => null,
                    'venue_approval_signature' => null,
                    'venue_approval_signature_file' => null,
                    'equipment_approval_signature' => null,
                    'equipment_approval_signature_file' => null,
                    'approval_signature_meta' => $meta,
                ];
            } else {
                $updates += [
                    'status' => 'pending',
                    'venue_status' => $confirmedVenueCustodianId !== null ? 'approved' : 'pending',
                    'equipment_status' => empty($newState['equipment']) ? 'approved' : 'pending',
                    'equipment_custodian_statuses' => [],
                    'venue_notes' => null,
                    'equipment_notes' => null,
                    'supply_office_review_status' => 'pending',
                    'supply_office_reviewed_by_id' => null,
                    'supply_office_reviewed_at' => null,
                    'supply_office_review_notes' => null,
                    'approved_by' => null,
                    'approved_by_id' => null,
                    'approved_date' => null,
                ];
            }
            $lockedRequest->update($updates);
            $lockedRequest->syncRelationalItems();
            if ($confirmedVenueCustodianId !== null) {
                $venueApprover = User::query()->find($confirmedVenueCustodianId);
                if ($venueApprover) {
                    $lockedRequest->recordApprovalSignature('venue', $venueApprover);
                    $lockedRequest->addHistory(
                        'custodian_endorsed',
                        'Venue availability for the suggested schedule confirmed by ' . $venueApprover->name . '.',
                        $venueApprover->id,
                    );
                }
            }
            if ($wasApproved) {
                $equipmentStatuses = [];
                foreach ($lockedRequest->getAssignedEquipmentCustodianIds() as $custodianId) {
                    if (($lockedRevision->responses[(string) $custodianId] ?? 'pending') === 'accepted') {
                        $equipmentStatuses[(string) $custodianId] = 'approved';
                        if ($equipmentApprover = User::query()->find($custodianId)) {
                            $lockedRequest->recordApprovalSignature('equipment', $equipmentApprover);
                        }
                    }
                }
                $lockedRequest->equipment_custodian_statuses = $equipmentStatuses;
                $lockedRequest->recomputeEquipmentStatus();
            }
            $lockedRevision->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);
            $lockedRequest->addHistory(
                'revision_applied',
                "Requestor accepted the suggested new schedule. {$lockedRevision->getSummary()}",
                $user->id
            );
            if ($wasApproved) {
                $lockedRequest->addHistory(
                    'reapproval_required',
                    'The approved reservation was changed. The previous approval does not cover the new details; Supply Office final approval is required again.',
                );
            }
            return true;
        });
        if (!$accepted) {
            return back()->withErrors([
                'revision' => 'This suggestion has already been answered, the reservation has changed or is locked, or the suggested schedule is no longer available. Contact the Supply Office.',
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
        $notifiedCustodians = $custodianIds->all();
        if ($notifiedCustodians !== []) {
            $revision->markCustodianNotified();
        }

        $notificationRecipientIds = collect($revision->affected_user_ids ?? [])
                ->merge([(int) $facilityRequest->requested_by_id])
                ->merge($custodianIds)
                ->unique();
        if ($facilityRequest->status === 'pending') {
            $notificationRecipientIds = $notificationRecipientIds->merge(
                User::query()->whereIn('role', ['admin', 'supply_office'])->pluck('id')
            )->unique();
        }
        $notificationRecipients = User::query()->whereIn('id', $notificationRecipientIds)->get();
        Notification::send($notificationRecipients, $notification);
        $revision->markRequestorNotified();

        $successMessage = match (true) {
            $facilityRequest->status === 'pending' && $facilityRequest->supply_office_review_status === 'pending'
                => 'You accepted the proposal. The schedule is updated, but the request still needs Supply Office review and any remaining custodian approvals.',
            $facilityRequest->status === 'pending'
                => 'You accepted the proposal. The schedule is updated and goes back to the Supply Office for final approval of the new details.',
            default => 'You accepted the proposal. The reservation schedule has been updated and the custodians have been notified.',
        };

        return redirect()->route('request.show', $facilityRequest)
            ->with('success', $successMessage);
    }

    /**
     * REQ-11 / REQ-13: the one list of venues a requestor may choose, used by the
     * request form, submitting, editing and the availability check: every active
     * venue, plus the built-in names that have no venue record. A deactivated venue
     * is never on the list.
     *
     * @return list<string>
     */
    /**
     * REQ-19: with a requestor given, venues closed to that requestor (the Gym for
     * outsiders) are left out.
     */
    private function requestableVenueOptions(?\App\Models\User $requestor = null): array
    {
        $normalize = static function ($name): string {
            $value = trim((string) $name);

            return strtolower($value) === 'balay alumni hall' ? 'Balay Alumni' : $value;
        };
        $records = Venue::query()->orderBy('name')->get(['name', 'is_active']);
        $known = $records->map(fn (Venue $venue): string => mb_strtolower($normalize($venue->name)))->all();
        $active = $records->filter(fn (Venue $venue): bool => (bool) $venue->is_active)
            ->map(fn (Venue $venue): string => $normalize($venue->name))
            ->filter()
            ->values()
            ->all();
        $builtIn = array_values(array_filter(
            self::VENUE_OPTIONS,
            static fn (string $name): bool => ! in_array(mb_strtolower($name), $known, true)
        ));

        $options = array_values(array_unique(array_merge($active, $builtIn)));
        $closed = \App\Support\VenueRules::closedVenuesFor($requestor, $options);

        return array_values(array_diff($options, $closed));
    }

    /** REQ-13: identifies the version of a request an edit form was opened on. */
    public static function editFormVersion(FacilityRequest $facilityRequest): string
    {
        return (string) ($facilityRequest->updated_at?->getTimestamp() ?? 0);
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
            ->where(function ($reservationDates): void {
                $reservationDates->upcomingReservation()
                    ->orWhere(function ($pastReservations): void {
                        $pastReservations->pastReservation()
                            ->whereIn('status', ['approved', 'completed']);
                    });
            });

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

        $allRequests = $query->get();
        $completedRequests = $allRequests
            ->filter(fn (FacilityRequest $requestItem): bool => $requestItem->isReservationComplete())
            ->values();
        $requests = $allRequests
            ->reject(fn (FacilityRequest $requestItem): bool => $requestItem->isReservationComplete())
            ->values();
        $equipment = \App\Models\Equipment::where('is_active', true)->get();

        // Keep ended reservations with outstanding equipment visible until their return is recorded.
        $today = now()->toDateString();
        $upcomingRequests = $requests->filter(function ($request) use ($today) {
            $schedule = $request->reservationSchedule;
            $start = $schedule ? $schedule->start_datetime : $request->start_date;
            return $request->status === 'approved' && $start && $start->toDateString() >= $today;
        });
        $pastRequests = $requests->filter(function ($request) use ($today) {
            return $request->getRequestedEndDateTime()->toDateString() < $today;
        });
 
        $activeRequests    = $requests;
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
        $venueOptions = $this->requestableVenueOptions($user);

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
            'venueActivityRequired' => collect($venueOptions)->filter(fn (string $name): bool => \App\Support\VenueRules::requiresActivityType($name))->values()->all(),
            'venueRecords'      => $venueRecords,
            'colleges'          => $colleges,
            'departments'       => $departments,
            'profileCollegeId'  => $profileCollegeId,
            'profileDepartmentId' => $profileDepartmentId,
            'studentOrganizations' => $user->isStudent()
                ? $user->studentOrganizations()->orderBy('name')->get()
                : collect(),
            'equipOptions'      => self::EQUIPMENT_OPTIONS,
            // QA-1: the real control number is assigned when the request is saved; the
            // form no longer shows a preview number that changes on every visit.
            'controlNumber'     => null,
            'recentSubmission'  => $this->recentSubmission($request),
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
            'password' => ['required', 'string', PasswordRule::defaults(), 'confirmed'],
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
        $shouldAllowEdit = $facilityRequest->isEditableByRequestor();

        if ($facilityRequest->requested_by_id !== $user->id || !$shouldAllowEdit) {
            abort(403);
        }

        // QA-7: the same form as a new request, pre-filled from this request.
        if (! session()->hasOldInput()) {
            session()->now('_old_input', $this->editFormPrefill($facilityRequest, $this->requestableVenueOptions($user)));
        }

        return view('requestor.edit', $this->requestFormData($user) + [
            'request' => $facilityRequest,
            'editingRequest' => $facilityRequest,
            'controlNumber' => $facilityRequest->control_number,
        ]);
    }

    /** QA-7: what the request form needs, for a new request and for editing. */
    private function requestFormData(User $user): array
    {
        $venueOptions = $this->requestableVenueOptions($user);
        $venueCapacityMap = [];
        foreach ($venueOptions as $venueName) {
            $venueCapacityMap[$venueName] = $this->availabilityService->getVenueCapacity($venueName);
        }
        $departments = Department::query()->orderBy('name')->get();
        $colleges = College::query()->with('departments')->orderBy('name')->get();
        $profileDepartment = $user->department_id
            ? $departments->firstWhere('id', (int) $user->department_id)
            : $departments->firstWhere('name', $user->department);

        return [
            'user' => $user,
            'venueOptions' => $venueOptions,
            'venueActivityRequired' => collect($venueOptions)->filter(fn (string $name): bool => \App\Support\VenueRules::requiresActivityType($name))->values()->all(),
            'venueRecords' => Venue::query()->where('is_active', true)->orderBy('name')->get(),
            'colleges' => $colleges,
            'departments' => $departments,
            'profileCollegeId' => $user->college_id ?? $profileDepartment?->college_id,
            'profileDepartmentId' => $user->department_id ?? $profileDepartment?->id,
            'studentOrganizations' => $user->isStudent() ? $user->studentOrganizations()->orderBy('name')->get() : collect(),
            'equipOptions' => self::EQUIPMENT_OPTIONS,
            'equipment' => \App\Models\Equipment::where('is_active', true)->get(),
            'venueCapacityMap' => $venueCapacityMap,
            'venueDefaultEquipmentMap' => collect($venueOptions)
                ->mapWithKeys(fn (string $venueName): array => [$venueName => VenueEquipmentPolicy::getDefaultEquipment($venueName)])
                ->all(),
            'venueEquipmentVisibilityMap' => VenueEquipmentPolicy::getVenueRestrictedEquipment(),
        ];
    }

    /** QA-7: the request's current details, in the request form's field names. */
    private function editFormPrefill(FacilityRequest $facilityRequest, array $venueOptions): array
    {
        $venues = [];
        $schedules = [];
        $participants = [];
        $exceptions = [];
        $equipmentByVenue = [];
        $quantitiesByVenue = [];
        $allocations = $facilityRequest->getVenueEquipmentAllocations();
        foreach ($facilityRequest->getVenueNames() as $venueName) {
            $option = FacilityRequest::matchVenueOption($venueName, $venueOptions) ?? $venueName;
            $venues[] = $option;
            $schedule = $facilityRequest->getVenueSchedule($venueName);
            $schedules[$option] = array_filter([
                'start_date' => $schedule['start_date'] ?? null,
                'end_date' => $schedule['end_date'] ?? null,
                'start_time' => isset($schedule['start_time']) ? substr((string) $schedule['start_time'], 0, 5) : null,
                'end_time' => isset($schedule['end_time']) ? substr((string) $schedule['end_time'], 0, 5) : null,
                'reservation_duration' => $schedule['reservation_duration'] ?? 'specific_time',
                'activity_type' => $schedule['activity_type'] ?? null,
                'activity_type_detail' => $schedule['activity_type_detail'] ?? null,
            ], fn ($value) => $value !== null);
            $participants[$option] = $facilityRequest->venue_participants[$venueName] ?? $facilityRequest->expected_participants;
            if (! empty($facilityRequest->capacity_exceptions[$venueName])) {
                $exceptions[$option] = $facilityRequest->capacity_exceptions[$venueName];
            }
            $items = $allocations[$venueName] ?? [];
            if ($items === [] && count($facilityRequest->getVenueNames()) === 1) {
                $items = $facilityRequest->getEquipmentQuantities();
            }
            $equipmentByVenue[$option] = array_keys($items);
            $quantitiesByVenue[$option] = $items;
        }

        return array_filter([
            'department' => $facilityRequest->department,
            'organization_name' => $facilityRequest->organization_name,
            'request_context' => $facilityRequest->request_context,
            'student_organization_id' => $facilityRequest->student_organization_id,
            'requested_by_position' => $facilityRequest->requested_by_position,
            'name_of_activity' => $facilityRequest->name_of_activity,
            'purpose' => $facilityRequest->purpose,
            'expected_participants' => $facilityRequest->expected_participants,
            'venue' => $venues,
            'venue_schedules' => $schedules,
            'venue_participants' => $participants,
            'capacity_exceptions' => $exceptions,
            'venue_equipment' => $equipmentByVenue,
            'venue_equipment_quantities' => $quantitiesByVenue,
            'contact_person' => $facilityRequest->contact_person,
            'contact_number' => $facilityRequest->contact_number,
            'contact_email' => $facilityRequest->contact_email,
            'additional_remarks' => $facilityRequest->additional_remarks,
            'is_emergency' => $facilityRequest->is_emergency ? '1' : null,
            'emergency_office' => data_get($facilityRequest->document_metadata, 'urgent_priority.office'),
            'emergency_justification' => $facilityRequest->emergency_justification,
        ], fn ($value) => $value !== null && $value !== []);
    }

    public function update(Request $request, FacilityRequest $facilityRequest)
    {
        $user = $this->currentUser();
        $shouldAllowEdit = $facilityRequest->isEditableByRequestor();

        if ($facilityRequest->requested_by_id !== $user->id || !$shouldAllowEdit) {
            abort(403);
        }

        // REQ-13: refuse to save over changes made after this form was opened.
        if ($request->filled('form_version')
            && (string) $request->input('form_version') !== self::editFormVersion($facilityRequest)) {
            return redirect()->route('requestor.edit', $facilityRequest)->withErrors([
                'form_version' => 'This request was changed after you opened the form, so your edits were not saved. '
                    . 'Review the latest details below and make your changes again.',
            ]);
        }

        // QA-7: editing uses the same form, rules, availability checks and document
        // handling as submitting (persistRequest), so the two cannot drift apart.
        return $this->persistRequest($request, $facilityRequest);
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

    private const STAGED_UPLOAD_FIELDS = ['proposal_file', 'activity_proposal_file', 'igp_receipt_file'];

    /**
     * REQ-05: a browser cannot refill a file input after a failed submission, so each
     * valid upload is copied to private storage and offered again on the next submit.
     * A kept file is reused only when the form sends back its matching token.
     */
    private function restoreStagedUploads(Request $request, User $user): void
    {
        if (! $request->hasSession()) {
            return;
        }
        $staged = (array) $request->session()->get('staged_uploads', []);

        foreach (self::STAGED_UPLOAD_FIELDS as $field) {
            $entry = $staged[$field] ?? null;
            $file = $request->file($field);

            if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                $extension = strtolower((string) $file->getClientOriginalExtension());
                if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true) || $file->getSize() > 10 * 1024 * 1024) {
                    continue; // Validation reports the problem; nothing to keep.
                }
                if ($entry) {
                    Storage::disk('local')->delete($entry['path']);
                }
                $path = Storage::disk('local')->putFileAs("staged-uploads/{$user->id}", $file, \Illuminate\Support\Str::uuid() . '.' . $extension);
                $staged[$field] = [
                    'path' => $path,
                    'name' => mb_substr($file->getClientOriginalName(), 0, 150),
                    'token' => \Illuminate\Support\Str::random(40),
                ];
                continue;
            }

            if (! $entry) {
                continue;
            }

            $keptToken = (string) $request->input("kept_{$field}", '');
            if ($keptToken !== '' && hash_equals($entry['token'], $keptToken) && Storage::disk('local')->exists($entry['path'])) {
                $request->files->set($field, new \Illuminate\Http\UploadedFile(
                    Storage::disk('local')->path($entry['path']),
                    $entry['name'],
                    Storage::disk('local')->mimeType($entry['path']) ?: null,
                    null,
                    true,
                ));
            } elseif ($keptToken === '') {
                // The requestor removed the kept file.
                Storage::disk('local')->delete($entry['path']);
                unset($staged[$field]);
            }
        }

        $request->session()->put('staged_uploads', $staged);
        // Laravel caches the converted file list; reset it so validation sees restored files.
        (fn () => $this->convertedFiles = null)->call($request);
    }

    private function clearStagedUploads(): void
    {
        if (! request()->hasSession()) {
            return;
        }
        foreach ((array) session('staged_uploads', []) as $entry) {
            if (! empty($entry['path'])) {
                Storage::disk('local')->delete($entry['path']);
            }
        }
        session()->forget('staged_uploads');
    }

    public function store(Request $request)
    {
        return $this->persistRequest($request, null);
    }

    /**
     * Validates, checks availability, stores documents and saves a request. With
     * $editing it updates that request instead (QA-7): same rules and checks, the
     * control number, date and requester stay, existing documents are kept unless a
     * new file replaces them, and the request goes back to review.
     */
    private function persistRequest(Request $request, ?FacilityRequest $editing)
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
        // REQ-14: the same form sent twice (double click, refresh, Back, retry) does not
        // make a second request.
        $submissionToken = $editing ? null : FacilityRequest::normalizeSubmissionToken($request->input('submission_token'));
        if ($editing === null && ($alreadySubmitted = FacilityRequest::findBySubmissionToken((int) $user->id, $submissionToken))) {
            return $this->alreadySubmittedResponse($alreadySubmitted);
        }
        // QA-7: when editing, a field the form does not send keeps the request's value.
        if ($editing) {
            foreach ([
                'department', 'organization_name', 'request_context', 'student_organization_id', 'requested_by_position',
                'name_of_activity', 'purpose', 'expected_participants', 'contact_person', 'contact_number', 'contact_email', 'additional_remarks',
            ] as $field) {
                if (! $request->has($field) && $editing->getAttribute($field) !== null) {
                    $request->merge([$field => $editing->getAttribute($field)]);
                }
            }
        }
        // REQ-05: keep uploaded documents across validation errors.
        $this->restoreStagedUploads($request, $user);
        if ($user->isOutsider() && $request->boolean('is_emergency')) {
            return back()
                ->withErrors(['is_emergency' => 'Urgent processing is available only to Institute requestors.'])
                ->withInput();
        }

        // Build validation rules
        $hasDepartmentDirectory = College::query()->exists() && Department::query()->exists();
        $positionOptions = ['Student', 'Faculty', 'Staff', 'Instructor', 'Professor', 'Department Chair', 'Coordinator', 'Office Staff', 'External Partner', 'Student Organization', 'Other'];

        // REQ-19: outsiders cannot book venues closed to them; say so plainly instead of
        // the generic "choose a listed venue".
        $closedVenues = \App\Support\VenueRules::closedVenuesFor($user, array_filter((array) $request->input('venue', []), 'is_string'));
        if ($closedVenues !== []) {
            return back()->withErrors(['venue' => \App\Support\VenueRules::closedVenueMessage($closedVenues)])->withInput();
        }
        $requestableVenueNames = $this->requestableVenueOptions($user);

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
            // B: outsider inquiry contact details (pre-filled from the account).
            'contact_person'        => ['nullable', 'string', 'max:191'],
            'contact_number'        => ['nullable', 'string', 'max:50'],
            'contact_email'         => ['nullable', 'email', 'max:191'],
            'additional_remarks'    => ['nullable', 'string', 'max:2000'],
            'request_context'       => ['nullable', 'in:personal,student_organization,outside_organization'],
            'student_organization_id' => ['nullable', 'integer', 'exists:student_organizations,id'],
            'requested_by_position' => ['nullable', 'string', 'max:100'],
            'requested_by_position_other' => ['nullable', 'string', 'max:100', 'required_if:requested_by_position,Other'],
            'name_of_activity'      => 'required|string|max:200',
            'purpose'               => ['nullable', 'string', 'max:2000'],
            'expected_participants' => 'required|integer|min:1|max:100000',
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
            // D2: participants per venue and capacity exception reasons.
            'venue_participants'    => ['nullable', 'array'],
            'venue_participants.*'  => ['nullable', 'integer', 'min:1', 'max:100000'],
            'capacity_exceptions'   => ['nullable', 'array'],
            'capacity_exceptions.*' => ['nullable', 'string', 'max:500'],
            'venue'                 => ['required', 'array', 'min:1'],
            'venue.*'               => ['required', 'string', Rule::in($requestableVenueNames)],
            'venue_equipment' => ['nullable', 'array'],
            'venue_equipment.*' => ['array'],
            'venue_equipment.*.*' => ['string', 'max:191'],
            'venue_equipment_quantities' => ['nullable', 'array'],
            'venue_equipment_quantities.*' => ['array'],
            'venue_equipment_quantities.*.*' => EquipmentQuantity::rules(),
            'equipment'             => 'nullable|array',
            'equipment_quantities'  => 'nullable|array',
            'equipment_quantities.*' => EquipmentQuantity::rules(),
            'other_venue'           => 'prohibited',
            'emergency_justification' => 'required_if:is_emergency,1|nullable|string|max:1000',
            'emergency_office' => ['required_if:is_emergency,1', 'nullable', Rule::in(['CHED', 'OP', 'OVP'])],
            'is_emergency'          => 'nullable|boolean',
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
        // QA-7: documents were checked when the request was submitted. When editing they
        // are kept as they are, and a new file only replaces one (an urgent request still
        // needs its supporting document, checked below).
        if ($editing) {
            foreach (['activity_proposal_file', 'igp_receipt_file'] as $documentField) {
                if (is_string($rules[$documentField] ?? null)) {
                    $rules[$documentField] = str_replace('required|', 'nullable|', $rules[$documentField]);
                }
            }
            $rules['proposal_file'] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];
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

        $validated = $request->validate($rules, [
            'venue.required' => 'Choose a venue. Every request needs at least one venue; equipment is requested together with a venue.',
            'venue.min' => 'Choose a venue. Every request needs at least one venue; equipment is requested together with a venue.',
            'venue.*.in' => 'Choose one of the listed venues. Venues that are no longer available cannot be requested.',
            'other_venue.prohibited' => '"Others" is not a venue. Choose one of the listed venues.',
        ] + EquipmentQuantity::messages('venue_equipment_quantities.*.*') + EquipmentQuantity::messages('equipment_quantities.*'));

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

            // REQ-19: a venue that asks for it needs the type of school activity.
            $activityType = $submittedSchedules[$venueName]['activity_type'] ?? null;
            $activityDetail = $submittedSchedules[$venueName]['activity_type_detail'] ?? null;
            // QA-7: an edit that does not send the activity keeps the one already chosen.
            if ($editing && $activityType === null && in_array($venueName, $editing->getVenueNames(), true)) {
                $previousSchedule = $editing->getVenueSchedule($venueName);
                $activityType = $previousSchedule['activity_type'] ?? null;
                $activityDetail = $previousSchedule['activity_type_detail'] ?? null;
            }
            if ($activityError = \App\Support\VenueRules::activityTypeError($venueName, $activityType, $activityDetail)) {
                return back()->withErrors(["venue_schedules.{$venueName}.activity_type" => $activityError])->withInput();
            }

            $scheduleRanges[$venueName] = $range;
            $venueSchedules[$venueName] = [
                'start_date' => $range['start']->toDateString(),
                'end_date' => $range['end']->toDateString(),
                'start_time' => $range['start']->format('H:i'),
                'end_time' => $range['end']->format('H:i'),
                'reservation_duration' => $duration,
            ] + \App\Support\VenueRules::activityFields($venueName, $activityType, $activityDetail);
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
            if (collect($scheduleRanges)->contains(
                fn (array $range): bool => $range['start']->lt(now()->startOfMinute())
            )) {
                return back()
                    ->withErrors(['is_emergency' => 'Urgent reservations must start at the current time or later.'])
                    ->withInput();
            }
        }

        if ($user->isStudent()) {
            $submittedOrganizationId = $validated['student_organization_id'] ?? null;
            $trustedStudentOrganization = $submittedOrganizationId
                ? $user->studentOrganizations()->where('student_organizations.id', $submittedOrganizationId)->first()
                : $user->studentOrganizations()->first();

            // A submitted organization must be one of the student's own active memberships.
            if ($submittedOrganizationId && ! $trustedStudentOrganization) {
                return back()->withErrors(['student_organization_id' => 'You may request only for an active organization membership.'])->withInput();
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
            // Outsiders who registered as individuals have no organization on file.
            $trustedOrganization = trim((string) ($user->office_or_organization ?? '')) ?: 'Individual / Personal';
            $validated['department'] = $trustedOrganization;
            $validated['organization_name'] = $trustedOrganization;
        }

        if ($user->requestor_type === 'student' && empty($validated['student_organization_id'])) {
            $validated['organization_name'] = trim((string) ($validated['organization_name'] ?? $validated['department'] ?? ''));
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
            // REQ-12: every quantity belongs to a selected item.
            $unmatchedQuantities = EquipmentQuantity::unmatched(
                array_filter($hasVenueSpecificSelection ? $venueQuantities : $legacyQuantities, static fn ($qty): bool => $qty !== null && $qty !== ''),
                $normalizedItems,
                static fn (string $name): string => self::canonicalizeEquipmentName($name)
            );
            if ($unmatchedQuantities !== []) {
                return back()->withErrors(['equipment' => EquipmentQuantity::unmatchedMessage($unmatchedQuantities, $venueName)])->withInput();
            }
            foreach ($normalizedItems as $equipmentName) {
                // Equipment the venue already provides is not a separate request (REQ-09).
                if (in_array($equipmentName, VenueEquipmentPolicy::getDefaultEquipment($venueName), true)) {
                    continue;
                }
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

            if (! $request->hasFile('proposal_file') && ! $request->hasFile('activity_proposal_file')
                && ! ($editing && ($editing->proposal_file || $editing->activity_proposal_file))) {
                return back()->withErrors(['proposal_file' => 'Urgent requests require a proposal or memorandum order.'])->withInput();
            }
        }

        $isUrgentRequest = $request->boolean('is_emergency');

        // Availability is validated below per venue schedule and equipment assignment.

        // Venue conflicts are checked by AvailabilityService using each venue's schedule.
        $venueParticipants = [];
        $capacityExceptions = [];
        if (!empty($venue) && FacilityRequest::capacityRulesEnabled()) {
            // D2: each venue is a separate booking with its own participant count. A venue
            // smaller than that count needs a capacity exception the Supply Office approves.
            $participants = (int) $validated['expected_participants'];
            $capacityErrors = [];
            foreach (array_values($venue) as $venueName) {
                $count = (int) ($request->input("venue_participants.{$venueName}") ?: $participants);
                if ($count > $participants) {
                    $capacityErrors["venue_participants.{$venueName}"] = "{$venueName}: participants at this venue cannot be more than the {$participants} expected in total.";
                    continue;
                }
                $venueParticipants[$venueName] = $count;

                $capacity = \App\Models\Venue::where('name', $venueName)->value('capacity');
                if ($capacity !== null && $count > (int) $capacity) {
                    $reason = trim((string) $request->input("capacity_exceptions.{$venueName}", ''));
                    if ($reason === '') {
                        $capacityErrors["venue_participants.{$venueName}"] = "{$venueName} holds {$capacity} people, fewer than the {$count} you entered. Lower the participants at this venue, choose another venue, or request a capacity exception with a reason.";
                        continue;
                    }
                    $capacityExceptions[$venueName] = $reason;
                }
            }
            if ($capacityErrors !== []) {
                return back()->withErrors($capacityErrors)->withInput();
            }
            if ($capacityExceptions !== []) {
                $capacityWarning = 'Capacity exception requested for ' . implode('; ', array_map(
                    fn ($name, $reason) => "{$name} ({$venueParticipants[$name]} participants): {$reason}",
                    array_keys($capacityExceptions),
                    $capacityExceptions,
                )) . '. The Supply Office must approve it.';
            }
        } elseif (!empty($venue)) {
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
 
        // Generate control number for this request (an edited request keeps its own)
        $controlNumber = $editing?->control_number ?? FacilityRequest::generateControlNumber();

        // Initialize document filenames
        $proposalFileName = null;
        $activityProposalFileName = null;
        $igpReceiptFileName = null;
        $eSignatureFileName = null;
        $documentMetadata = $editing ? (array) ($editing->document_metadata ?? []) : [];
        if ($editing && ! $request->boolean('is_emergency')) {
            unset($documentMetadata['urgent_priority']);
        }
        if ($request->boolean('is_emergency') && in_array((string) $request->input('emergency_office'), ['CHED', 'OP', 'OVP'], true)) {
            $documentMetadata['urgent_priority'] = [
                'office' => $request->input('emergency_office'),
                'recorded_at' => now()->toDateTimeString(),
            ];
        }

        // Get the document upload service
        $documentUploadService = app(\App\Services\DocumentUploadService::class);
        // REQ-14: each document is checked and recorded on its own; when anything fails
        // later, every file saved for this attempt is removed again.
        $discardUploadedDocuments = function () use (&$proposalFileName, &$activityProposalFileName, &$igpReceiptFileName, &$eSignatureFileName): void {
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
        };

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
                $discardUploadedDocuments();
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
                $discardUploadedDocuments();
                return back()->withErrors(['igp_receipt_file' => $result['error']])->withInput();
            }
        }

        // No signature image is collected: the requestor signs the printed form by hand,
        // and approvals are recorded against each approver's account (issue APR-01, decision D1).

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
            'equipment_status'         => $equipment === [] ? 'approved' : 'pending',
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
        if ($submissionToken !== null && FacilityRequest::submissionTokensEnabled()) {
            $requestAttributes['submission_token'] = $submissionToken;
        }
        if ($editing) {
            // QA-7: keep the request's identity and documents; it goes back to review.
            foreach (['proposal_file' => $proposalFileName, 'activity_proposal_file' => $activityProposalFileName, 'igp_receipt_file' => $igpReceiptFileName, 'e_signature_file' => $eSignatureFileName] as $column => $newFile) {
                $requestAttributes[$column] = $newFile ?? $editing->getAttribute($column);
            }
            unset($requestAttributes['control_number'], $requestAttributes['date_requested'], $requestAttributes['requested_by_id'], $requestAttributes['igp_payment_status']);
            // After a priority override only the schedule may change.
            if ($editing->status === 'needs_reschedule') {
                $requestAttributes['name_of_activity'] = $editing->name_of_activity;
                $requestAttributes['department'] = $editing->department;
            }
            $requestAttributes += [
                'approved_by' => null,
                'approved_by_id' => null,
                'approved_date' => null,
                'venue_notes' => null,
                'equipment_notes' => null,
                'equipment_custodian_statuses' => [],
                'supply_office_reviewed_by_id' => null,
                'supply_office_reviewed_at' => null,
                'supply_office_review_notes' => null,
            ];
        }
        // B: an outsider's request is an inquiry that IGP reviews before any custodian sees it.
        $isOutsiderInquiry = $user->isOutsider() && \Illuminate\Support\Facades\Schema::hasColumn('facility_requests', 'outsider_stage');
        if ($isOutsiderInquiry) {
            $requestAttributes += [
                // An edited inquiry keeps its stage; the model moves it back to review as needed.
                ...($editing ? [] : ['outsider_stage' => 'submitted']),
                'contact_person' => trim((string) ($validated['contact_person'] ?? '')) ?: $user->name,
                'contact_number' => trim((string) ($validated['contact_number'] ?? '')) ?: $user->contact_number,
                'contact_email'  => trim((string) ($validated['contact_email'] ?? '')) ?: $user->username,
                'additional_remarks' => trim((string) ($validated['additional_remarks'] ?? '')) ?: null,
            ];
        }
        if (FacilityRequest::capacityRulesEnabled()) {
            $requestAttributes += [
                'venue_participants'        => $venueParticipants ?: null,
                'capacity_exceptions'       => $capacityExceptions ?: null,
                'capacity_exception_status' => $capacityExceptions !== [] ? 'pending' : null,
            ];
        }

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
        $availabilityCandidate->requested_by_id = $user->id;

        DB::beginTransaction();
        $this->availabilityService->lockResourcesForFacilityRequests($availabilityCandidate);
        $availabilityMessage = $this->availabilityService->checkFacilityRequest(
            $availabilityCandidate,
            $editing?->id,
            $isUrgentRequest
        );
        if ($availabilityMessage) {
            DB::rollBack();
            $discardUploadedDocuments();

            $errorKey = str_contains(strtolower($availabilityMessage), 'venue') ? 'venue' : 'equipment';

            return back()->withErrors([$errorKey => $availabilityMessage])->withInput();
        }

        // REQ-14: the request, its schedule and items, and its history are saved together
        // or not at all.
        $replacedFiles = [];
        try {
            if ($editing) {
                $wasNeedsReschedule = $editing->status === 'needs_reschedule';
                foreach (['proposal_file' => 'proposal_file', 'activity_proposal_file' => 'activity_proposal', 'igp_receipt_file' => 'igp_receipt'] as $column => $folder) {
                    $old = $editing->getOriginal($column);
                    if ($old && $requestAttributes[$column] !== $old) {
                        $replacedFiles[] = "documents/{$folder}/{$old}";
                    }
                }
                $fr = $editing;
                $fr->fill($requestAttributes);
                $fr->save();
                $fr->syncRelationalItems($venueSchedules, $venueEquipment);
                $fr->unsetRelation('requestVenues');
                $fr->unsetRelation('requestEquipment');
                if ($wasNeedsReschedule) {
                    $fr->addHistory('needs_reschedule', 'Reservation rescheduled by requestor after Priority Override.', $user->getKey());
                }
                $fr->addHistory('requestor_edited', 'Request edited by ' . $user->name . ' and returned to pending verification.', $user->getKey());
            } else {
                $fr = $this->createRequestRecord($requestAttributes);
                $fr->syncRelationalItems($venueSchedules, $venueEquipment);
                $fr->addHistory('submitted', 'Request submitted by ' . $user->name . '.', $user->getKey());
                if ($isOutsiderInquiry) {
                    $fr->addHistory('outsider_inquiry_submitted', 'Reservation inquiry submitted by ' . $user->name . '. Waiting for IGP review.', $user->getKey());
                }
            }
            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            $discardUploadedDocuments();

            if ($editing) {
                report($exception);

                return back()
                    ->withErrors(['request' => 'Your changes could not be saved, so the request was not changed. Please try again. If this keeps happening, contact the Supply Office.'])
                    ->withInput();
            }

            // The same form arrived twice at the same moment: the other copy was saved.
            if ($duplicate = FacilityRequest::findBySubmissionToken((int) $user->id, $submissionToken)) {
                return $this->alreadySubmittedResponse($duplicate);
            }

            report($exception);

            return back()
                ->withErrors(['request' => 'Your request could not be saved, so nothing was submitted. Please try again. If this keeps happening, contact the Supply Office.'])
                ->withInput();
        }

        if ($editing) {
            // A replaced document is removed once the new one is saved.
            foreach ($replacedFiles as $replacedFile) {
                Storage::disk('local')->delete($replacedFile);
            }
            $this->clearStagedUploads();
            $this->notifyCustodiansOfEdit($fr, $user);

            return redirect()->route('request.show', $fr)
                ->with('success', 'Your changes were saved. The request is back in review.')
                ->with('warning', $capacityWarning);
        }

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

        if ($isOutsiderInquiry) {
            \Illuminate\Support\Facades\Notification::send(
                User::query()->withAnyRole(['igp'])->where('is_active', true)->get(),
                new \App\Notifications\RequestStatusChanged($fr, 'outsider_inquiry_submitted', '', $user->name)
            );
        }

        $this->clearStagedUploads();
        $this->rememberSubmission($fr);

        return redirect()->route('requestor.index', ['tab' => 'requests'])
                        ->with('success', 'Request submitted successfully as ' . $fr->control_number . '.')
                        ->with('request_draft_submitted', true)
                        ->with('warning', $capacityWarning);
    }
 
    /**
     * CHG-01: why the Supply Office may propose a schedule change now, or null when
     * nothing calls for one. The change route itself stays limited to the Supply
     * Office (and assigned venue custodians for their own venues).
     */
    private function scheduleChangeReason(FacilityRequest $request): ?string
    {
        if (! in_array($request->status, ['pending', 'approved'], true)) {
            return null;
        }
        if ($request->requestChangeRequests()->where('status', 'pending')->exists()) {
            return 'The requestor asked for a change.';
        }
        if ($request->status === 'pending'
            && ($request->supply_office_review_status === 'unavailable'
                || in_array('needs_reschedule', [$request->venue_status, $request->equipment_status], true))) {
            return 'The requested schedule was marked unavailable.';
        }

        $conflict = $request->status === 'pending'
            ? $this->availabilityService->checkFacilityRequestForReview($request)
            : $this->availabilityService->checkFacilityRequest($request, $request->id);
        if ($conflict !== null) {
            return $conflict;
        }

        if ($request->status === 'approved') {
            $urgent = FacilityRequest::query()
                ->where('status', 'pending')
                ->whereKeyNot($request->id)
                ->where(fn ($query) => $query->where('is_emergency', true)->orWhere('priority', 'institutional'))
                ->with(['requestVenues', 'reservationSchedule'])
                ->get()
                ->first(fn (FacilityRequest $candidate): bool => $this->availabilityService->getApprovedVenueConflicts($candidate)->contains('id', $request->id));
            if ($urgent) {
                return "Urgent request {$urgent->control_number} needs this booking's venue and time.";
            }
        }

        return null;
    }

    /**
     * REQ-14: creates the request row; if another request took the control number in
     * the meantime, tries again with a new one.
     */
    private function createRequestRecord(array $attributes): FacilityRequest
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn (): FacilityRequest => FacilityRequest::create($attributes));
            } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
                $controlNumberTaken = str_contains($exception->getMessage(), 'control_number');
                if (! $controlNumberTaken || $attempt >= 3) {
                    throw $exception;
                }
                $attributes['control_number'] = FacilityRequest::generateControlNumber();
            }
        }
    }

    /**
     * QA-1: after a successful submission, going Back to the form (or opening it again
     * within a few minutes) shows that request instead of a fresh form ready to be
     * sent again. "Start a new request" clears it.
     */
    private function rememberSubmission(FacilityRequest $facilityRequest): void
    {
        session()->put('recent_request_submission', [
            'id' => $facilityRequest->getKey(),
            'control_number' => $facilityRequest->control_number,
            'at' => now()->getTimestamp(),
        ]);
    }

    private function recentSubmission(Request $request): ?array
    {
        if ($request->boolean('new_request')) {
            session()->forget('recent_request_submission');

            return null;
        }

        $recent = session('recent_request_submission');
        if (! is_array($recent) || (now()->getTimestamp() - (int) ($recent['at'] ?? 0)) > 15 * 60) {
            return null;
        }

        return $recent;
    }

    /** Tells the custodians of an edited request that it needs their review again. */
    private function notifyCustodiansOfEdit(FacilityRequest $facilityRequest, User $user): void
    {
        $custodianIds = array_values(array_unique(array_merge(
            $facilityRequest->getAssignedEquipmentCustodianIds(),
            Venue::whereIn('name', $facilityRequest->getVenueNames())->pluck('custodian_id')->filter()->all(),
        )));
        if ($custodianIds === []) {
            return;
        }
        try {
            Notification::send(User::whereIn('id', $custodianIds)->get(), new \App\Notifications\RequestStatusChanged($facilityRequest, 'change_requested', '', $user->name));
        } catch (\Throwable $exception) {
            Log::warning('Failed to notify custodians of an edited request.', ['facility_request_id' => $facilityRequest->id, 'exception' => $exception->getMessage()]);
        }
    }

    private function alreadySubmittedResponse(FacilityRequest $facilityRequest)
    {
        $this->clearStagedUploads();
        $this->rememberSubmission($facilityRequest);

        return redirect()->route('requestor.index', ['tab' => 'requests'])
            ->with('success', 'This request was already submitted as ' . $facilityRequest->control_number . '. It was not submitted again.')
            ->with('request_draft_submitted', true);
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

        // B: IGP opening a new inquiry starts its review.
        $viewer = Auth::user();
        if ($viewer?->isIgp() && $request->outsider_stage === 'submitted' && $request->status === 'pending') {
            $request->forceFill(['outsider_stage' => 'igp_review'])->save();
            $request->addHistory('igp_review_started', 'IGP (' . $viewer->name . ') started reviewing the inquiry.', $viewer->getKey());
            $request->load('histories');
        }
        $pendingRevision = $request->revisionHistories()->where('status', 'pending')->first();
        $pendingRevisionResponses = $pendingRevision?->getResponsesRequiringConfirmation((int) $request->requested_by_id) ?? [];
        $pendingRevisionUserIds = collect($pendingRevision?->affected_user_ids ?? [])
            ->filter(fn ($userId): bool => ($pendingRevisionResponses[(string) $userId] ?? 'pending') === 'pending')
            ->map(fn ($userId): int => (int) $userId)
            ->values();
        $pendingRevisionUsers = $pendingRevisionUserIds->isNotEmpty()
            ? User::query()->whereIn('id', $pendingRevisionUserIds)->get()->keyBy('id')
            : collect();
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
                'status' => $request->effectiveCustodianEquipmentStatus((int) $custodian->id),
            ]))
            ->values();
        /** @var \App\Models\User|null $currentUser */
        $currentUser = $this->currentUser();
        $assignedVenueOptions = $currentUser?->isCustodianVenue()
            ? $currentUser->venues()->where('is_active', true)->orderBy('name')->pluck('name')
            : collect();
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
            ? $request->getAssignedEquipmentForCustodian((int) $currentUser->id)
            : [];
        $venueCalendarName = $currentUser?->isCustodianVenue() && $request->getVenueNames() !== []
            ? $request->getVenueNames()[0]
            : null;
        $venueCalendarDate = $venueCalendarName
            ? ($request->getVenueSchedule($venueCalendarName)['start_date'] ?? $request->start_date?->toDateString())
            : null;

        $hasEndorsed = false;
        if ($currentUser && $currentUser->isCustodian()) {
            $awaitingVenueConfirmation = $request->venue_status === 'pending'
                && $request->supply_office_review_status === 'pending';
            $awaitingEquipmentReview = $request->supply_office_review_status === 'confirmed'
                && $currentUser->isCustodianEquipment()
                && $request->getAssignedEquipmentForCustodian((int) $currentUser->id) !== [];

            if ($awaitingVenueConfirmation && $currentUser->isCustodianVenue()) {
                $hasEndorsed = $request->venue_status === 'approved';
            } elseif ($awaitingEquipmentReview) {
                $hasEndorsed = $request->getCustodianEquipmentStatus((int) $currentUser->id) === 'approved';
            } else {
                $hasEndorsed = $request->histories()
                    ->where('user_id', $currentUser->id)
                    ->where('action', 'custodian_endorsed')
                    ->exists();
            }
        }
 
        // REQ-08: every active venue can be proposed, and each proposed venue is checked on its own.
        $revisionVenueOptions = Venue::query()->where('is_active', true)->orderBy('name')->pluck('name');
        if ($revisionVenueOptions->isEmpty()) {
            $revisionVenueOptions = collect(self::VENUE_OPTIONS);
        }
        $pendingRevisionVenueChecks = [];
        if ($pendingRevision && $pendingRevision->new_start_date && $pendingRevision->new_start_time && $pendingRevision->new_end_time) {
            $proposedRange = FacilityRequest::normalizeScheduleRange(
                $pendingRevision->new_start_date->toDateString(),
                substr((string) $pendingRevision->new_start_time, 0, 5),
                ($pendingRevision->new_end_date ?? $pendingRevision->new_start_date)->toDateString(),
                substr((string) $pendingRevision->new_end_time, 0, 5)
            );
            $availabilityService = app(\App\Services\AvailabilityService::class);
            foreach ($pendingRevision->new_venue ?? [] as $proposedVenue) {
                $message = $availabilityService->checkVenueTimeRangeForReview($proposedVenue, $proposedRange['start'], $proposedRange['end'], $request->id);
                $pendingRevisionVenueChecks[] = ['venue' => $proposedVenue, 'available' => $message === null, 'message' => $message];
            }
        }

        // CHG-01: the Supply Office's schedule-change button appears only when there is
        // a reason to move the booking.
        $scheduleChangeReason = $viewer && ($viewer->isSupplyOffice() || $viewer->isAdmin())
            ? $this->scheduleChangeReason($request)
            : null;

        return view('requestor.show', [
            'request'                 => $request,
            'scheduleChangeReason'    => $scheduleChangeReason,
            'pendingRevision'         => $pendingRevision,
            'pendingRevisionVenueChecks' => $pendingRevisionVenueChecks,
            'revisionVenueOptions'    => $revisionVenueOptions,
            'pendingRevisionResponses' => $pendingRevisionResponses,
            'pendingRevisionUsers'    => $pendingRevisionUsers,
            'assignedVenueOptions'   => $assignedVenueOptions,
            'venueOptions'            => self::VENUE_OPTIONS,
            'equipOptions'            => self::EQUIPMENT_OPTIONS,
            'equipment'               => $equipment,
            'custodialEndorsements'   => $custodialEndorsements,
            'currentCustodianEquipment' => $currentCustodianEquipment,
            'hasEndorsed'             => $hasEndorsed,
            'venueCalendarName'       => $venueCalendarName,
            'venueCalendarDate'       => $venueCalendarDate,
        ]);
    }

    public function print($id)
    {
        $request = FacilityRequest::findOrFail($id);
        $this->authorize('print', $request);

        return view('request.print', [
            'request' => $request,
            'approvalRecords' => $request->digitalApprovalRecords(),
            'verificationUrl' => $request->verificationUrl(),
            'verificationQrSvg' => $request->verificationQrSvg(60),
        ]);
    }

    public function proposal($id)
    {
        $request = FacilityRequest::findOrFail($id);
        $this->authorize('view', $request);

        $documentType = request()->query('document', 'proposal');
        [$filename, $disk, $filePath] = $this->resolveRequestDocument($request, $documentType);
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

        $documentType = request()->query('document', 'proposal');
        [$filename, $disk, $filePath] = $this->resolveRequestDocument($request, $documentType);

        return Storage::disk($disk)->download($filePath, basename($filename), [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function resolveRequestDocument(FacilityRequest $request, mixed $documentType): array
    {
        if (!is_string($documentType)) {
            abort(404);
        }

        if ($documentType === 'receipt') {
            $filename = $request->igp_receipt_file;
            $paths = $filename ? [['local', 'documents/igp_receipt/' . $filename]] : [];
        } elseif ($documentType === 'proposal') {
            $filename = $request->activity_proposal_file ?: $request->proposal_file;
            if (!$filename) {
                abort(404);
            }

            [$disk, $path] = $this->resolveProposalPath($request, $filename);

            return [$filename, $disk, $path];
        } else {
            abort(404);
        }

        if (
            !$filename
            || $filename === '.'
            || $filename === '..'
            || str_contains($filename, '/')
            || str_contains($filename, '\\')
        ) {
            abort(404);
        }

        foreach ($paths as [$disk, $path]) {
            if (Storage::disk($disk)->exists($path)) {
                return [$filename, $disk, $path];
            }
        }

        abort(404);
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
        $availabilityUser = $this->currentUser();
        $validated = $request->validate([
            'venues' => ['required', 'array', 'min:1'],
            'venues.*' => ['required', 'string', Rule::in($this->requestableVenueOptions($availabilityUser))],
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
            // REQ-10: a clash with a request still under review is only a warning here;
            // the Supply Office is blocked from confirming both.
            $pendingNotice = null;
            if ($availability['available'] && $this->availabilityService->checkVenueTimeRangeForReview($venueName, $range['start'], $range['end']) !== null) {
                $pendingNotice = 'Another request for the same venue and time is waiting for review. You can still submit; the Supply Office will confirm only one of them.';
            }
            // QA-5: one status per venue, used the same way everywhere on the form.
            $status = ! $availability['available'] ? 'unavailable' : ($pendingNotice !== null ? 'pending_conflict' : 'available');
            $results[] = [
                'venue' => $venueName,
                'available' => $availability['available'],
                'status' => $status,
                'status_label' => ['available' => 'Available', 'pending_conflict' => 'Pending schedule conflict', 'unavailable' => 'Unavailable'][$status],
                'can_submit' => $status !== 'unavailable',
                'awaiting_supply_office' => $status !== 'unavailable',
                'message' => $availability['message'] ?? null,
                'pending_notice' => $pendingNotice,
            ];
        }

        return response()->json([
            'available' => collect($results)->every(fn (array $result): bool => $result['available']),
            'venues' => $results,
        ]);
    }
}
