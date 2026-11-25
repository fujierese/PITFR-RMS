<?php
namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\RequestStatusChanged;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplyOfficeController extends Controller
{
    private function getFilterOptions(): array
    {
        return [
            'departments' => Department::orderBy('name')->get(),
            'venues' => Venue::orderBy('name')->get(),
        ];
    }

    public function index(Request $request)
    {
        $baseQuery = FacilityRequest::with([
            'requestEquipment.requestVenue.venue',
            'requestEquipment.equipment',
        ])->upcomingReservation()->whereIn('status', ['pending', 'approved']);
        $query = clone $baseQuery;
        if ($filter = $request->get('filter')) {
            if ($filter !== 'all') {
                $query->where('status', $filter);
            }
        }

        if ($priority = $request->get('priority')) {
            if (in_array($priority, ['regular', 'institutional'], true)) {
                $query->where('priority', $priority);
            }
        }

        \App\Support\RequestSearch::apply($query, (string) $request->get('search', ''));

        $filteredRequests = $query->orderBy('start_date')->orderBy('start_time')->orderBy('created_at')
            ->paginate(15, ['*'], 'requests_page')
            ->appends($request->query());
        $pendingReviewQueue = $this->buildRequestListQuery($request)
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->where('venue_status', '!=', 'rejected')
                    ->orWhere('equipment_status', '!=', 'rejected');
            })
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->orderBy('created_at')
            ->paginate(15, ['*'], 'queue_page')
            ->appends($request->query());
        $availabilityReviewQueue = $this->buildRequestListQuery($request)
            ->where('status', 'pending')
            ->where('supply_office_review_status', 'pending')
            ->where('venue_status', 'approved')
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->orderBy('created_at')
            ->paginate(15, ['*'], 'availability_page')
            ->appends($request->query());
        $venueRescheduleQueue = $this->buildRequestListQuery($request)
            ->where('status', 'pending')
            ->where('venue_status', 'needs_reschedule')
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->orderBy('created_at')
            ->get();
        $cancellationQueue = FacilityRequest::query()
            ->with(['requester'])
            ->upcomingReservation()
            ->where('status', 'approved')
            ->where('cancellation_status', 'pending')
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->get();
        $totalCount = (clone $baseQuery)->count();
        $pendingCount = (clone $baseQuery)->where('status', 'pending')->count();
        $approvedCount = (clone $baseQuery)->where('status', 'approved')->count();
        $finalApprovalQueue = $this->buildRequestListQuery($request)
            ->where('status', 'pending')
            ->where('venue_status', 'approved')
            ->where('equipment_status', 'approved')
            ->where(function (Builder $query): void {
                $query->whereNull('supply_office_review_status')
                    ->orWhere('supply_office_review_status', 'confirmed');
            })
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->orderBy('created_at')
            ->paginate(15, ['*'], 'final_page')
            ->appends($request->query());

        $filterOptions = $this->getFilterOptions();

        return view('supply-office.index', [
            'requests'          => $filteredRequests,
            'finalApprovalQueue' => $finalApprovalQueue,
            'availabilityReviewQueue' => $availabilityReviewQueue,
            'venueRescheduleQueue' => $venueRescheduleQueue,
            'cancellationQueue' => $cancellationQueue,
            'pendingFinalApprovalCount' => $finalApprovalQueue->total(),
            'totalCount'        => $totalCount,
            'pendingCount'      => $pendingCount,
            'approvedCount'     => $approvedCount,
            'filter'            => $request->get('filter', 'all'),
            'priority'          => $request->get('priority', 'all'),
            'searchQuery'       => $request->get('search', ''),
            'reviewRequest'     => $request->has('review') ? FacilityRequest::find($request->review) : null,
            'venuesCount'       => Venue::count(),
            'equipmentCount'    => Equipment::count(),
            'allDepartments'    => $filterOptions['departments'],
            'allVenues'         => $filterOptions['venues'],
        ]);
    }

    public function reviewAvailability(Request $request)
    {
        $this->ensureAdminAccess();

        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:facility_requests,id'],
            'decision' => ['required', 'in:available,unavailable'],
            'notes' => ['required_if:decision,unavailable', 'nullable', 'string', 'max:1000'],
            // B: optional deadline for an approved outsider inquiry to meet its requirements.
            'outsider_hold_until' => ['nullable', 'date', 'after_or_equal:today'],
        ], [
            'outsider_hold_until.after_or_equal' => 'The requirements deadline cannot be in the past.',
        ]);

        $user = Auth::user();
        // Approval cutoff: availability cannot be confirmed once the reservation has started.
        if ($validated['decision'] === 'available'
            && ($cutoff = \App\Support\ApprovalCutoff::reason(FacilityRequest::findOrFail($validated['id'])))) {
            return back()->withErrors(['approval' => $cutoff]);
        }

        try {
            $facilityRequest = DB::transaction(function () use ($validated, $user, $request): FacilityRequest {
                $facilityRequest = FacilityRequest::whereKey($validated['id'])->lockForUpdate()->firstOrFail();
                if ($facilityRequest->status !== 'pending'
                    || $facilityRequest->supply_office_review_status !== 'pending'
                    || $facilityRequest->venue_status !== 'approved') {
                    abort(409, 'This request is no longer awaiting initial Supply Office review.');
                }

                if ($validated['decision'] === 'available') {
                    $availability = app(\App\Services\AvailabilityService::class);
                    $availability->lockResourcesForFacilityRequests($facilityRequest);
                    $conflict = $availability->checkFacilityRequestForReview($facilityRequest);
                    $approvedOverlaps = $facilityRequest->is_emergency ? $availability->getApprovedVenueConflicts($facilityRequest) : collect();
                    if ($conflict && $facilityRequest->is_emergency && $approvedOverlaps->isNotEmpty()
                        && $request->boolean('urgent_conflict_reviewed')
                        && $availability->checkFacilityRequest($facilityRequest, $facilityRequest->id, true) === null) {
                        // Additional Part 2: the Supply Office reviewed the overlap and chose to
                        // continue. Nothing is changed for the other reservation(s) now; after
                        // final approval the overlap is resolved by hand.
                        $facilityRequest->addHistory(
                            'urgent_conflict_acknowledged',
                            'Supply Office reviewed the overlap with approved reservation(s) ' . $approvedOverlaps->pluck('control_number')->implode(', ')
                                . ' and continued. After final approval the Supply Office decides how to adjust one of the reservations; nothing changes automatically.',
                            $user->getKey()
                        );
                        $conflict = null;
                    }
                    if ($conflict) {
                        if ($facilityRequest->is_emergency) {
                            $approvedConflicts = $availability->getApprovedVenueConflicts($facilityRequest);
                            $conflictIds = $approvedConflicts->pluck('control_number')->filter()->implode(', ');
                            $facilityRequest->addHistory(
                                'urgent_priority_transfer_pending',
                                'Urgent CHED/OP priority transfer requires schedule transfer before confirmation.'
                                    . ($conflictIds !== '' ? ' Affected approved reservation(s): ' . $conflictIds . '.' : '')
                                    . ' Existing reservations remain active until Supply Office confirms a proposal and affected custodians respond.',
                                $user->getKey()
                            );
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'decision' => 'Priority conflict detected. '
                                    . ($conflictIds !== '' ? 'Move or reschedule approved reservation(s): ' . $conflictIds . '. ' : '')
                                    . 'Confirm an alternative schedule through the transfer proposal workflow before confirming this urgent request.'
                            ]);
                        }
                        throw \Illuminate\Validation\ValidationException::withMessages(['decision' => $conflict]);
                    }

                    if ($facilityRequest->is_emergency) {
                        $request->validate(
                            ['urgent_document_validated' => ['required', 'accepted']],
                            [
                                'urgent_document_validated.required' => 'Confirm that you reviewed the urgent supporting document before confirming availability.',
                                'urgent_document_validated.accepted' => 'Confirm that you reviewed the urgent supporting document before confirming availability.',
                            ]
                        );
                        if (! $facilityRequest->proposal_file && ! $facilityRequest->activity_proposal_file) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'decision' => 'An urgent request must include its proposal or memorandum order.',
                            ]);
                        }
                        $facilityRequest->urgent_document_status = 'validated';
                    }

                    // D2: a venue below its participant count needs an explicit, recorded approval.
                    $approvedCapacityException = false;
                    if ($facilityRequest->hasPendingCapacityException()) {
                        $request->validate(
                            ['capacity_exception_approved' => ['required', 'accepted']],
                            [
                                'capacity_exception_approved.required' => 'Approve the capacity exception, or mark the request unavailable so the requestor chooses another venue.',
                                'capacity_exception_approved.accepted' => 'Approve the capacity exception, or mark the request unavailable so the requestor chooses another venue.',
                            ]
                        );
                        $facilityRequest->capacity_exception_status = 'approved';
                        $facilityRequest->capacity_exception_reviewed_by_id = $user->getKey();
                        $facilityRequest->capacity_exception_reviewed_at = now();
                        $approvedCapacityException = true;
                    }

                    $facilityRequest->supply_office_review_status = 'confirmed';
                    if ($facilityRequest->isOutsiderInquiry()) {
                        // B: approved subject to requirements; IGP coordinates them next.
                        // The schedule is held until the deadline, if one is set.
                        $facilityRequest->outsider_hold_until = ! empty($validated['outsider_hold_until'])
                            ? \Carbon\Carbon::parse($validated['outsider_hold_until'])->endOfDay()
                            : null;
                    }
                    $facilityRequest->supply_office_review_notes = trim((string) ($validated['notes'] ?? '')) ?: null;
                    $facilityRequest->supply_office_reviewed_by_id = $user->getKey();
                    $facilityRequest->supply_office_reviewed_at = now();
                    $facilityRequest->save();
                    $confirmedAt = now()->format('F j, Y \a\t g:i A');
                    $facilityRequest->addHistory(
                        'availability_confirmed',
                        'Availability confirmed by ' . $user->name . ', Supply Office Staff' . "\n"
                            . $confirmedAt . "\n"
                            . 'Validation: No approved booking or active scheduling conflict found'
                            . ($facilityRequest->is_emergency ? "\nUrgent supporting document validated." : '')
                            . ($approvedCapacityException ? "\nCapacity exception approved for: " . implode(', ', array_keys((array) $facilityRequest->capacity_exceptions)) . '.' : ''),
                        $user->getKey()
                    );

                    return $facilityRequest;
                }

                $notes = trim((string) $validated['notes']);
                if ($facilityRequest->hasPendingCapacityException()) {
                    $facilityRequest->capacity_exception_status = 'rejected';
                    $facilityRequest->capacity_exception_reviewed_by_id = $user->getKey();
                    $facilityRequest->capacity_exception_reviewed_at = now();
                }
                $facilityRequest->supply_office_review_status = 'unavailable';
                $facilityRequest->supply_office_review_notes = $notes;
                $facilityRequest->supply_office_reviewed_by_id = $user->getKey();
                $facilityRequest->supply_office_reviewed_at = now();
                $facilityRequest->status = 'needs_reschedule';
                $facilityRequest->notes = $notes;
                $facilityRequest->save();
                $facilityRequest->addHistory(
                    'availability_unavailable',
                    'Availability reviewed by ' . $user->name . ' from the Supply Office. The request was returned for an available alternative: ' . $notes,
                    $user->getKey()
                );

                return $facilityRequest;
            });
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Supply Office availability review failed.', [
                'facility_request_id' => $validated['id'],
                'actor_id' => $user->getKey(),
                'exception' => $exception,
            ]);

            return back()->withErrors(['decision' => 'Unable to record the availability review. Please retry or contact support.']);
        }

        if ($facilityRequest->isOutsiderInquiry()) {
            $facilityRequest->refresh();
            $approved = $validated['decision'] === 'available';
            $facilityRequest->addHistory(
                $approved ? 'outsider_inquiry_approved' : 'outsider_alternative_requested',
                $approved
                    ? 'Supply Office approved the inquiry, subject to the institution\'s requirements. IGP will coordinate them with the client.'
                        . ($facilityRequest->outsider_hold_until ? ' Schedule held until ' . $facilityRequest->outsider_hold_until->format('M j, Y') . '.' : '')
                    : 'Supply Office requested an alternative venue or schedule: ' . $facilityRequest->supply_office_review_notes,
                $user->getKey()
            );
            if ($approved) {
                Notification::send(
                    User::query()->withAnyRole(['igp'])->get(),
                    new RequestStatusChanged($facilityRequest, 'outsider_inquiry_approved', '', $user->name . ' - Supply Office')
                );
                $facilityRequest->requester?->notify(new RequestStatusChanged($facilityRequest, 'outsider_inquiry_approved', '', $user->name . ' - Supply Office'));
            }
        }

        if ($validated['decision'] === 'available') {
            $custodianIds = $facilityRequest->getAssignedEquipmentCustodianIds();
            foreach (User::whereIn('id', $custodianIds)->get() as $custodian) {
                $custodian->notify(new \App\Notifications\NewFacilityRequestNotification($facilityRequest));
            }
            if ($custodianIds !== []) {
                $message = 'Supply Office availability confirmed. The request has been forwarded to the equipment custodians for approval.';
            } elseif ($facilityRequest->getEquipmentItems() !== []) {
                $message = 'Supply Office availability confirmed. No equipment custodian is assigned yet; assign one before final approval.';
            } else {
                $message = 'Supply Office availability confirmed. No equipment approval is needed; the request will proceed to final approval after any remaining requirements are complete.';
            }
        } else {
            $facilityRequest->requester?->notify(new RequestStatusChanged(
                $facilityRequest,
                'needs_revision',
                $facilityRequest->supply_office_review_notes,
                null,
                null,
                [],
                $user->name . ' - Supply Office'
            ));
            $this->notifyAffectedCustodiansOfScheduleChange(
                $facilityRequest,
                'needs_revision',
                $facilityRequest->supply_office_review_notes
            );
            $message = 'The request was marked unavailable and returned for rescheduling.';
        }

        return redirect()->route('supply-office.index')->with('success', $message);
    }

    /**
     * B.7: cancel an approved outsider inquiry whose requirements were not met by the
     * deadline (or for another stated reason) and release its schedule.
     */
    public function cancelOutsiderApproval(Request $request, FacilityRequest $facilityRequest)
    {
        $this->ensureAdminAccess();
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Give the reason for cancelling the approval (for example, requirements not completed by the deadline).',
        ]);
        $user = Auth::user();

        $facilityRequest = DB::transaction(function () use ($facilityRequest, $validated, $user): FacilityRequest {
            $locked = FacilityRequest::query()->lockForUpdate()->findOrFail($facilityRequest->id);
            abort_unless(
                in_array($locked->outsider_stage, FacilityRequest::OUTSIDER_HOLD_STAGES, true) && $locked->status === 'pending',
                409,
                'Only an approved outsider inquiry that is not yet confirmed can be cancelled here.'
            );
            $reason = trim($validated['reason']);
            $locked->forceFill([
                'status' => 'cancelled',
                'venue_status' => 'cancelled',
                'equipment_status' => 'cancelled',
                'cancellation_status' => 'confirmed',
                'cancellation_reviewed_by_id' => $user->getKey(),
                'cancellation_reviewed_at' => now(),
                'cancellation_notes' => $reason,
                'outsider_hold_until' => null,
            ])->save();
            $locked->addHistory('cancelled', 'Supply Office (' . $user->name . ') cancelled the approval and released the schedule: ' . $reason, $user->getKey());

            return $locked;
        });

        $notice = new RequestStatusChanged($facilityRequest, 'request_cancelled', trim($validated['reason']), $user->name . ' - Supply Office');
        $facilityRequest->requester?->notify($notice);
        Notification::send(User::query()->withAnyRole(['igp'])->get(), $notice);

        return back()->with('success', 'Approval cancelled. The venue schedule is released.');
    }

    public function reviewCancellation(Request $request, FacilityRequest $facilityRequest)
    {
        $this->ensureAdminAccess();
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['confirm', 'decline'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $actor = Auth::user();

        DB::transaction(function () use ($facilityRequest, $validated, $actor): void {
            $lockedRequest = FacilityRequest::query()->lockForUpdate()->findOrFail($facilityRequest->id);
            if ($lockedRequest->status !== 'approved' || $lockedRequest->cancellation_status !== 'pending') {
                abort(409, 'This cancellation request is no longer pending.');
            }
            $reservationDate = Carbon::parse($lockedRequest->start_date)->startOfDay();
            if ($reservationDate->lessThanOrEqualTo(now()->startOfDay())) {
                abort(422, 'Cancellation is outside the allowed one-day notice period.');
            }

            if ($validated['decision'] === 'confirm') {
                foreach ($lockedRequest->getEquipmentQuantities() as $itemName => $quantity) {
                    $equipment = Equipment::query()
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($itemName)])
                        ->lockForUpdate()
                        ->first();
                    if ($equipment) {
                        $equipment->quantity_available = min(
                            (int) $equipment->quantity,
                            (int) $equipment->quantity_available + (int) $quantity
                        );
                        $equipment->save();
                    }
                }
                $lockedRequest->update([
                    'status' => 'cancelled',
                    'venue_status' => 'cancelled',
                    'equipment_status' => 'cancelled',
                    'cancellation_status' => 'confirmed',
                    'cancellation_reviewed_by_id' => $actor->id,
                    'cancellation_reviewed_at' => now(),
                    'cancellation_notes' => trim((string) ($validated['notes'] ?? $lockedRequest->cancellation_notes)),
                ]);
                $detail = 'Approved reservation cancellation confirmed by ' . $actor->name . '.';
                $action = 'cancelled';
                app(\App\Services\EquipmentCustodianRouting::class)->notify($lockedRequest, 'cancelled');
            } else {
                $notes = trim((string) ($validated['notes'] ?? ''));
                if ($notes === '') {
                    throw \Illuminate\Validation\ValidationException::withMessages(['notes' => 'Provide a reason when declining a cancellation request.']);
                }
                $lockedRequest->update([
                    'cancellation_status' => 'declined',
                    'cancellation_reviewed_by_id' => $actor->id,
                    'cancellation_reviewed_at' => now(),
                    'cancellation_notes' => $notes,
                ]);
                $detail = 'Cancellation request declined by ' . $actor->name . ': ' . $notes . ' The approved reservation remains active.';
                $action = 'cancellation_declined';
            }
            $lockedRequest->addHistory($action, $detail, $actor->id);
        });

        $facilityRequest->refresh();
        $recipients = collect([$facilityRequest->requester])
            ->merge(Venue::query()->whereIn('name', $facilityRequest->getVenueNames())->with('custodian')->get()->pluck('custodian'))
            ->merge(User::query()->whereIn('id', $facilityRequest->getAssignedEquipmentCustodianIds())->get())
            ->filter()
            ->unique('id')
            ->values();
        Notification::send($recipients, new RequestStatusChanged(
            $facilityRequest,
            $validated['decision'] === 'confirm' ? 'request_cancelled' : 'cancellation_declined',
            $facilityRequest->cancellation_notes ?? 'Cancellation request declined; the reservation remains active.',
            $actor->name
        ));

        return redirect()->route('supply-office.index')->with(
            'success',
            $validated['decision'] === 'confirm'
                ? 'Cancellation confirmed and affected users have been notified.'
                : 'Cancellation declined; the approved reservation remains active.'
        );
    }

    public function venueManagement(Request $request)
    {
        $this->ensureAdminAccess();

        $search = trim((string) $request->query('search', ''));
        $venues = Venue::with('custodian')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhereHas('custodian', fn ($custodianQuery) => $custodianQuery->where('name', 'like', '%' . $search . '%'));
            })
            ->orderBy('name')
            ->get();

        return view('supply-office.venue-management', [
            'venues' => $venues,
            'venueCustodians' => User::withAnyRole(['custodian-venue', 'custodian'])->orderBy('name')->get(),
            'search' => $search,
            'editVenueId' => (int) $request->query('edit_venue', 0),
        ]);
    }

    public function equipmentManagement(Request $request)
    {
        $this->ensureAdminAccess();

        $search = trim((string) $request->query('search', ''));
        $equipmentItems = Equipment::with('custodian')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('quantity', 'like', '%' . $search . '%')
                    ->orWhereHas('custodian', fn ($custodianQuery) => $custodianQuery->where('name', 'like', '%' . $search . '%'));
            })
            ->orderBy('name')
            ->get();

        return view('supply-office.equipment-management', [
            'equipmentItems' => $equipmentItems,
            'equipmentCustodians' => User::withAnyRole(['custodian-equipment', 'custodian'])->orderBy('name')->get(),
            'search' => $search,
            'editEquipmentId' => (int) $request->query('edit_equipment', 0),
        ]);
    }

    public function pendingRequests(Request $request)
    {
        $this->ensureAdminAccess();

        $query = $this->buildRequestListQuery($request)
            ->where('status', 'pending');

        return view('supply-office.pending-requests', [
            'requests' => $query->paginate(15)->appends($request->query()),
            'searchQuery' => trim((string) $request->get('search', '')),
            'departmentFilter' => trim((string) $request->get('department', '')),
            'venueFilter' => trim((string) $request->get('venue', '')),
            'dateFrom' => $request->get('date_from', ''),
            'dateTo' => $request->get('date_to', ''),
            'priorityFilter' => $request->get('priority', ''),
        ]);
    }

    public function finalApprovalRequests(Request $request)
    {
        abort_unless(Auth::user()?->isFinalApprover(), 403);

        $query = $this->buildRequestListQuery($request)
            ->where('status', 'pending')
            ->where(function (Builder $query): void {
                $query->where('venue_status', 'approved')
                    ->where('equipment_status', 'approved');
            })
            ->where(function (Builder $query): void {
                $query->whereNull('supply_office_review_status')
                    ->orWhere('supply_office_review_status', 'confirmed');
            });

        return view('supply-office.final-approval', [
            'requests' => $query->paginate(15)->appends($request->query()),
            'searchQuery' => trim((string) $request->get('search', '')),
            'departmentFilter' => trim((string) $request->get('department', '')),
            'venueFilter' => trim((string) $request->get('venue', '')),
            'dateFrom' => $request->get('date_from', ''),
            'dateTo' => $request->get('date_to', ''),
            'priorityFilter' => $request->get('priority', ''),
        ]);
    }

    public function approvedRequests(Request $request)
    {
        $this->ensureAdminAccess();

        $query = $this->buildRequestListQuery($request)
            ->where('status', 'approved');

        return view('supply-office.approved-requests', [
            'requests' => $query->paginate(15)->appends($request->query()),
            'searchQuery' => trim((string) $request->get('search', '')),
            'departmentFilter' => trim((string) $request->get('department', '')),
            'venueFilter' => trim((string) $request->get('venue', '')),
            'dateFrom' => $request->get('date_from', ''),
            'dateTo' => $request->get('date_to', ''),
            'priorityFilter' => $request->get('priority', ''),
        ]);
    }

    public function rejectedRequests(Request $request)
    {
        $this->ensureAdminAccess();

        $query = $this->buildRequestListQuery($request)
            ->where('status', 'rejected');

        return view('supply-office.rejected-requests', [
            'requests' => $query->paginate(15)->appends($request->query()),
            'searchQuery' => trim((string) $request->get('search', '')),
            'departmentFilter' => trim((string) $request->get('department', '')),
            'venueFilter' => trim((string) $request->get('venue', '')),
            'dateFrom' => $request->get('date_from', ''),
            'dateTo' => $request->get('date_to', ''),
            'priorityFilter' => $request->get('priority', ''),
        ]);
    }

    public function needsRescheduleRequests(Request $request)
    {
        $this->ensureAdminAccess();

        $query = $this->buildRequestListQuery($request)
            ->where(function (Builder $query): void {
                $query->where('status', 'needs_reschedule')
                    ->orWhere('venue_status', 'needs_reschedule')
                    ->orWhere('equipment_status', 'needs_reschedule');
            });

        return view('supply-office.needs-reschedule', [
            'requests' => $query->paginate(15)->appends($request->query()),
            'searchQuery' => trim((string) $request->get('search', '')),
            'departmentFilter' => trim((string) $request->get('department', '')),
            'venueFilter' => trim((string) $request->get('venue', '')),
            'dateFrom' => $request->get('date_from', ''),
            'dateTo' => $request->get('date_to', ''),
            'priorityFilter' => $request->get('priority', ''),
        ]);
    }

    public function equipmentReturns(Request $request)
    {
        $this->ensureAdminAccess();

        $baseQuery = $this->buildRequestListQuery($request, false)
            ->where('status', 'approved')
            ->where(function (Builder $query): void {
                $query->where('equipment_status', 'approved')
                    ->orWhere('equipment_status', 'rejected');
            });

        $pendingReturns = (clone $baseQuery)->where(function (Builder $query): void {
            $query->whereNull('equipment_returned_status')
                ->orWhere('equipment_returned_status', 'pending');
        })->orderByDesc('created_at')->get();

        $partialReturns = (clone $baseQuery)->where('equipment_returned_status', 'partial')->orderByDesc('created_at')->get();
        $returnedRequests = (clone $baseQuery)->where('equipment_returned_status', 'returned')->orderByDesc('created_at')->get();
        $overdueRequests = (clone $baseQuery)->where('equipment_returned_status', 'overdue')->orderByDesc('created_at')->get();

        return view('supply-office.equipment-returns', [
            'pendingReturns' => $pendingReturns,
            'partialReturns' => $partialReturns,
            'returnedRequests' => $returnedRequests,
            'overdueRequests' => $overdueRequests,
            'searchQuery' => trim((string) $request->get('search', '')),
            'departmentFilter' => trim((string) $request->get('department', '')),
            'venueFilter' => trim((string) $request->get('venue', '')),
            'dateFrom' => $request->get('date_from', ''),
            'dateTo' => $request->get('date_to', ''),
            'priorityFilter' => $request->get('priority', ''),
        ]);
    }

    public function needsRescheduleRequest(Request $request)
    {
        $this->ensureAdminAccess();

        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:facility_requests,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $facilityRequest = FacilityRequest::findOrFail($validated['id']);
        $originalStatus = $facilityRequest->status;

        DB::transaction(function () use ($facilityRequest, $validated): void {
            $facilityRequest->update([
                'status' => 'needs_reschedule',
                'venue_status' => 'needs_reschedule',
                'equipment_status' => 'needs_reschedule',
                'notes' => trim((string) ($validated['notes'] ?? '')) ?: $facilityRequest->notes,
            ]);

            $facilityRequest->addHistory(
                'needs_reschedule',
                'Request moved to Needs Reschedule by Supply Office.' . ($validated['notes'] ? ' Reason: ' . $validated['notes'] : ''),
                Auth::user()->getKey()
            );
        });

        // ✅ Only notify if status actually changed
        if ($originalStatus !== 'needs_reschedule') {
            $requester = $facilityRequest->requester;
            if ($requester) {
                $reason = $validated['notes'] ?? 'scheduling conflict';
                $requester->notify(new RequestStatusChanged(
                    $facilityRequest,
                    'needs_reschedule',
                    $validated['notes'] ?? 'Your request requires rescheduling before final review.',
                    null,
                    null,
                    [],
                    'Supply Office',
                    $reason
                ));
            }

            $this->notifyAffectedCustodiansOfScheduleChange($facilityRequest, 'needs_reschedule', $reason);
        }

        return redirect()->route('supply-office.requests.final-approval')->with('success', 'Request marked for rescheduling.');
    }

    public function needsRevision(Request $request)
    {
        $this->ensureAdminAccess();

        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:facility_requests,id'],
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        $facilityRequest = FacilityRequest::findOrFail($validated['id']);
        $notes = trim($validated['notes']);

        DB::transaction(function () use ($facilityRequest, $notes): void {
            $facilityRequest->update([
                'status' => 'needs_reschedule',
                'notes' => $notes,
            ]);
            $facilityRequest->addHistory(
                'needs_revision',
                'Needs Revision decision recorded by Supply Office: ' . $notes,
                Auth::user()->getKey()
            );
        });

        $requester = $facilityRequest->requester;
        if ($requester) {
            $requester->notify(new RequestStatusChanged(
                $facilityRequest,
                'needs_revision',
                $notes,
                null,
                null,
                [],
                'Supply Office'
            ));
        }

        $this->notifyAffectedCustodiansOfScheduleChange($facilityRequest, 'needs_revision', $notes);

        return redirect()->route('supply-office.index')->with('success', 'Request marked as Needs Revision.');
    }

    private function notifyAffectedCustodiansOfScheduleChange(FacilityRequest $facilityRequest, string $status, string $reason): void
    {
        $equipmentCustodianIds = $facilityRequest->getAssignedEquipmentCustodianIds();
        $venueCustodianIds = Venue::whereIn('name', $facilityRequest->getVenueNames())
            ->pluck('custodian_id')
            ->filter()
            ->unique()
            ->all();
        $custodianIds = array_values(array_unique(array_merge($equipmentCustodianIds, $venueCustodianIds)));

        foreach (User::whereIn('id', $custodianIds)->get() as $custodian) {
            try {
                $custodian->notify(new RequestStatusChanged(
                    $facilityRequest,
                    $status,
                    $reason,
                    null,
                    null,
                    [],
                    'Supply Office',
                    $reason
                ));
            } catch (\Throwable $notificationError) {
                Log::warning('Affected custodian schedule notification failed.', [
                    'facility_request_id' => $facilityRequest->id,
                    'custodian_id' => $custodian->id,
                    'exception' => $notificationError->getMessage(),
                ]);
            }
        }
    }

    /**
     * Supply Office or an assigned venue custodian proposes a schedule revision.
     * Checks availability, detects conflicts, and allows authorized overrides.
     */
    public function reviseReservation(Request $request)
    {
        $user = Auth::user();
        $isVenueCustodian = $user?->isCustodianVenue() ?? false;
        abort_unless($user && ($user->isAdmin() || $isVenueCustodian), 403);

        // REQ-08: a time picker or a stored value may carry seconds (09:00:00); keep HH:MM.
        foreach (['start_time', 'end_time'] as $timeField) {
            $timeValue = trim((string) $request->input($timeField, ''));
            if (preg_match('/^(\d{2}:\d{2}):\d{2}$/', $timeValue, $timeMatch)) {
                $request->merge([$timeField => $timeMatch[1]]);
            }
        }

        $validated = $request->validate([
            'facility_request_id' => ['required', 'integer', 'exists:facility_requests,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'end_time' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'venue' => ['required', 'array', 'min:1', 'max:10'],
            'venue.*' => ['required', 'string', Rule::exists('venues', 'name')->where('is_active', true)],
            'equipment' => ['nullable', 'array'],
            'equipment.*' => ['string', 'max:191'],
            'equipment_quantities' => ['nullable', 'array'],
            'revision_reason' => ['required', 'string', 'min:10', 'max:1000'],
            'override_conflict' => ['nullable', 'boolean'],
            'override_reason' => ['required_if:override_conflict,true', 'nullable', 'string', 'max:500'],
            'memorandum_priority' => ['nullable', 'boolean'],
            'memorandum_office' => ['required_if:memorandum_priority,1', 'nullable', Rule::in(['CHED', 'OP', 'OVP'])],
        ], [
            'start_time.required' => 'Enter a start time.',
            'end_time.required' => 'Enter an end time.',
            'start_time.regex' => 'Enter the start time as hours and minutes, for example 9:00 AM.',
            'end_time.regex' => 'Enter the end time as hours and minutes, for example 5:00 PM.',
            'venue.required' => 'Choose at least one venue.',
            'venue.min' => 'Choose at least one venue.',
            'venue.*.exists' => 'One of the chosen venues is not available for booking.',
        ]);

        $facilityRequest = FacilityRequest::findOrFail($validated['facility_request_id']);
        if ($isVenueCustodian) {
            // REQ-08: a venue custodian of this request may propose any active venue.
            // Venues owned by another custodian need that custodian's confirmation (see $responses).
            $assignedVenues = $user->venues()->pluck('name')->map(fn (string $name): string => mb_strtolower($name));
            abort_unless(
                $facilityRequest->status === 'pending'
                && in_array($facilityRequest->venue_status, ['pending', 'needs_reschedule'], true)
                && in_array($facilityRequest->supply_office_review_status, ['pending', 'unavailable'], true)
                && collect($facilityRequest->getVenueNames())
                    ->map(fn (string $name): string => mb_strtolower($name))
                    ->intersect($assignedVenues)
                    ->isNotEmpty(),
                403,
                'You may only propose a new schedule for a pending request assigned to one of your venues.'
            );
            if (($validated['override_conflict'] ?? false) || ($validated['memorandum_priority'] ?? false)) {
                abort(403, 'Venue custodians cannot override schedule conflicts or submit memorandum-priority transfers.');
            }
        }
        if ($validated['start_time'] <= '05:00' || $validated['end_time'] <= '05:00') {
            return $this->revisionFailure(
                $request,
                'Reservations cannot start or end between 12:00 AM and 5:00 AM. Choose times after 5:00 AM.',
                'end_time'
            );
        }
        // REQ-19: a schedule change cannot move an outsider's request into a venue closed
        // to outsiders.
        $closedRevisionVenues = \App\Support\VenueRules::closedVenuesFor($facilityRequest->requester, $validated['venue']);
        if ($closedRevisionVenues !== []) {
            return $this->revisionFailure($request, \App\Support\VenueRules::closedVenueMessage($closedRevisionVenues), 'venue');
        }
        // REQ-16: an end time at or before the start time on the same date is not a schedule.
        if (\Carbon\Carbon::parse($validated['start_date'])->isSameDay(\Carbon\Carbon::parse($validated['end_date'])) && $validated['end_time'] <= $validated['start_time']) {
            return $this->revisionFailure(
                $request,
                'The end time must be after the start time. For an overnight booking, set the end date to the next day.',
                'end_time'
            );
        }

        // Check if revision is allowed
        if (!$facilityRequest->canBeRevised()) {
            return $this->revisionFailure(
                $request,
                $facilityRequest->getRevisionLockReason() ?? 'Cannot revise this reservation.'
            );
        }

        if ($facilityRequest->revisionHistories()->where('status', 'pending')->exists()) {
            return $this->revisionFailure($request, 'A suggested new schedule is already waiting for the requestor to respond.', 'revision', 409);
        }

        // Capture old state
        $oldState = $facilityRequest->getCurrentState();

        // Prepare new state
        $newState = [
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'venue' => $validated['venue'],
            'equipment' => $validated['equipment'] ?? [],
            'equipment_quantities' => $validated['equipment_quantities'] ?? [],
        ];
        $memoPriority = (bool) ($validated['memorandum_priority'] ?? false);
        if ($memoPriority) {
            $validated['revision_reason'] = 'Memorandum-priority transfer required by '
                . $validated['memorandum_office'] . ': ' . $validated['revision_reason'];
        }
        $affectedUserIds = collect([(int) $facilityRequest->requested_by_id])
            ->merge(Venue::query()->whereIn(
                'name',
                $facilityRequest->status === 'approved'
                    ? array_unique(array_merge($oldState['venue'], $newState['venue']))
                    : array_unique($newState['venue'])
            )->pluck('custodian_id'))
            ->when(
                $facilityRequest->status === 'approved',
                fn ($userIds) => $userIds->merge(
                    Equipment::query()->whereIn('name', array_unique(array_merge($oldState['equipment'], $newState['equipment'])))->pluck('custodian_id')
                )
            )
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        // Custodians of the old or new venues and equipment; a requestor who is also one
        // of them must still respond to a memorandum-priority transfer.
        $custodianUserIds = Venue::query()
            ->whereIn('name', array_unique(array_merge($oldState['venue'], $newState['venue'])))
            ->pluck('custodian_id')
            ->merge(Equipment::query()->whereIn('name', array_unique(array_merge($oldState['equipment'], $newState['equipment'])))->pluck('custodian_id'))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $responses = [];
        foreach ($affectedUserIds as $affectedUserId) {
            $responses[(string) $affectedUserId] = $memoPriority
                && $affectedUserId === (int) $facilityRequest->requested_by_id
                && ! $custodianUserIds->contains($affectedUserId)
                ? 'not_required'
                : 'pending';
        }
        // Check availability for new schedule
        $availabilityService = app(\App\Services\AvailabilityService::class);
        $conflictDetected = false;
        $conflictDetails = '';

        // Check venue availability
        $range = FacilityRequest::normalizeScheduleRange(
            $validated['start_date'],
            $validated['start_time'],
            $validated['end_date'],
            $validated['end_time']
        );

        // REQ-08: check every venue and item on its own and name each one that conflicts.
        $parts = [];
        foreach ($validated['venue'] as $venueName) {
            $venueMessage = $availabilityService->checkVenueTimeRangeForReview(
                $venueName,
                $range['start'],
                $range['end'],
                $facilityRequest->id
            );
            if ($venueMessage !== null) {
                $parts[] = $venueName . ': ' . $venueMessage;
            }
        }

        foreach (($validated['equipment'] ?? []) as $equipment) {
            $qty = (int) ($validated['equipment_quantities'][$equipment] ?? 1);
            if (!$availabilityService->checkEquipmentAvailability($equipment, $qty, $range['start'], $range['end'], $facilityRequest->id)['available']) {
                $parts[] = $equipment . ': not enough units available for this schedule';
            }
        }

        if ($parts !== []) {
            $conflictDetected = true;
            $conflictDetails = implode('; ', $parts);

            // Pending requests are still proposals; the venue custodian rechecks availability
            // after the requestor responds. Approved reservations require an explicit override.
            if ($facilityRequest->status === 'approved' && !($validated['override_conflict'] ?? false)) {
                return $this->revisionFailure(
                    $request,
                    "Scheduling conflict detected: $conflictDetails. Provide an override reason to proceed.",
                    'conflict'
                );
            }
        }

        try {
            $revision = DB::transaction(function () use (
                $facilityRequest,
                $oldState,
                $newState,
                $validated,
                $user,
                $conflictDetected,
                $conflictDetails,
                $affectedUserIds,
                $responses,
                $memoPriority
            ): \App\Models\RevisionHistory {
                $lockedRequest = FacilityRequest::query()->lockForUpdate()->findOrFail($facilityRequest->id);
                if ($lockedRequest->revisionHistories()->where('status', 'pending')->exists()) {
                    throw new \RuntimeException('A suggested new schedule is already waiting for the requestor to respond.');
                }

                return $lockedRequest->revisionHistories()->create([
                    'revised_by_id' => $user->id,
                    'old_start_date' => $oldState['start_date'],
                    'old_end_date' => $oldState['end_date'],
                    'old_start_time' => $oldState['start_time'],
                    'old_end_time' => $oldState['end_time'],
                    'old_venue' => $oldState['venue'],
                    'old_equipment' => $oldState['equipment'],
                    'old_equipment_quantities' => $oldState['equipment_quantities'],
                    'new_start_date' => $newState['start_date'],
                    'new_end_date' => $newState['end_date'],
                    'new_start_time' => $newState['start_time'],
                    'new_end_time' => $newState['end_time'],
                    'new_venue' => $newState['venue'],
                    'new_equipment' => $newState['equipment'],
                    'new_equipment_quantities' => $newState['equipment_quantities'],
                    'revision_reason' => $validated['revision_reason'],
                    'status' => 'pending',
                    'conflict_detected' => $conflictDetected,
                    'conflict_details' => $conflictDetails,
                    'override_conflict' => $validated['override_conflict'] ?? false,
                    'override_reason' => $validated['override_reason'] ?? null,
                    'response_deadline_at' => now()->addDay(),
                    'affected_user_ids' => $affectedUserIds,
                    'responses' => $responses,
                    'memorandum_priority' => $memoPriority,
                ]);
            });

            $notification = new \App\Notifications\ReservationRescheduleProposed($revision);
            $notificationRecipientIds = collect($affectedUserIds)
                ->push((int) $user->id)
                ->unique()
                ->values();
            Notification::send(User::query()->whereIn('id', $notificationRecipientIds)->get(), $notification);
            $revision->markRequestorNotified();
            $revision->markCustodianNotified();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Suggested new schedule sent to the requestor for approval.',
                ]);
            }

            $successRedirect = $isVenueCustodian
                ? redirect()->route('request.show', $facilityRequest)
                : redirect()->route('supply-office.index');

            return $successRedirect->with('success', 'Suggested new schedule sent to the requestor and affected custodians.');
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'already waiting')) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => $e->getMessage()], 422);
                }

                return redirect()->back()->withInput()->withErrors(['revision' => $e->getMessage()]);
            }

            Log::error('Revision proposal failed', ['error' => $e->getMessage()]);
            return redirect()->back()->withInput()->withErrors(['revision' => 'Failed to send the suggested schedule. Please try again.']);
        } catch (\Exception $e) {
            Log::error('Revision proposal failed', ['error' => $e->getMessage()]);
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Failed to send the suggested schedule. Please try again.',
                ], 422);
            }

            return redirect()->back()
                ->withInput()
                ->withErrors(['revision' => 'Failed to send the suggested schedule. Please try again.']);
        }
    }

    private function revisionFailure(Request $request, string $message, string $errorKey = 'revision', int $status = 422)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'errors' => [$errorKey => [$message]],
            ], $status);
        }

        return redirect()->back()->withInput()->withErrors([$errorKey => $message]);
    }

    public function storeVenue(Request $request)
    {
        $this->ensureAdminAccess();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'custodian_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['custodian-venue', 'custodian'])
                    ->orWhereJsonContains('additional_roles', 'custodian-venue')
                    ->orWhereJsonContains('additional_roles', 'custodian')),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            // REQ-19: venue rules.
            'open_to_outsiders' => ['sometimes', 'boolean'],
            'requires_activity_type' => ['sometimes', 'boolean'],
        ]);

        if ($nameError = \App\Support\ResourceRename::venueError($validated['name'])) {
            return back()->withErrors(['name' => $nameError])->withInput();
        }
        if (! \Illuminate\Support\Facades\Schema::hasColumn('venues', 'open_to_outsiders')) {
            unset($validated['open_to_outsiders'], $validated['requires_activity_type']);
        }
        Venue::create($validated);

        return back()->with('success', 'Venue created successfully.');
    }

    public function updateVenue(Request $request, Venue $venue)
    {
        $this->ensureAdminAccess();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'custodian_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['custodian-venue', 'custodian'])
                    ->orWhereJsonContains('additional_roles', 'custodian-venue')
                    ->orWhereJsonContains('additional_roles', 'custodian')),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            // REQ-19: venue rules.
            'open_to_outsiders' => ['sometimes', 'boolean'],
            'requires_activity_type' => ['sometimes', 'boolean'],
        ]);

        if ($renameError = \App\Support\ResourceRename::venueError($validated['name'], $venue)) {
            return back()->withErrors(['name' => $renameError])->withInput();
        }
        if (! \Illuminate\Support\Facades\Schema::hasColumn('venues', 'open_to_outsiders')) {
            unset($validated['open_to_outsiders'], $validated['requires_activity_type']);
        }
        $venue->update($validated);

        // REQ-20: say which bookings the new custodian now looks after.
        if ($venue->wasChanged('custodian_id')
            && ($reassignWarning = \App\Support\VenueLifecycle::warningFor($venue, \App\Support\VenueLifecycle::liveBookings($venue), 'reassigned'))) {
            return back()->with('success', 'Venue updated successfully.')->with('warning', $reassignWarning);
        }

        // REQ-02: tell the Supply Office which bookings no longer fit.
        if ($venue->wasChanged('capacity') && $venue->capacity !== null) {
            $affected = FacilityRequest::activeRequestsAboveCapacity($venue, (int) $venue->capacity);
            if ($affected->isNotEmpty()) {
                return back()->with('success', 'Venue updated successfully.')->with('warning',
                    "The new capacity ({$venue->capacity}) is below the participants of " . $affected->count()
                    . ' active booking(s): ' . $affected->pluck('control_number')->implode(', ') . '. Each one was noted in its history; review them.');
            }
        }

        return back()->with('success', 'Venue updated successfully.');
    }

    public function destroyVenue(Request $request, Venue $venue)
    {
        $this->ensureAdminAccess();

        // REQ-20: a venue any request refers to (past ones included) is deactivated, not
        // deleted, so bookings, history and reports keep it.
        if ($deleteError = \App\Support\VenueLifecycle::deleteError($venue)) {
            return back()->withErrors(['venue' => $deleteError]);
        }

        $venue->delete();

        return back()->with('success', 'Venue deleted successfully.');
    }

    /** REQ-20 / QA-2: disable or re-enable a venue (Supply Office only); its bookings are kept and noted. */
    public function toggleVenue(Request $request, Venue $venue)
    {
        $this->ensureAdminAccess();

        $wasActive = (bool) $venue->is_active;
        $bookings = \App\Support\VenueLifecycle::liveBookings($venue);
        $venue->update(['is_active' => ! $wasActive]);

        return back()
            ->with('success', $wasActive ? "{$venue->name} was disabled. It can no longer be booked." : "{$venue->name} was re-enabled and can be booked again.")
            ->with('warning', $wasActive ? \App\Support\VenueLifecycle::warningFor($venue, $bookings, 'deactivated') : null);
    }

    public function storeEquipment(Request $request)
    {
        $this->ensureAdminAccess();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'custodian_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['custodian-equipment', 'custodian'])
                    ->orWhereJsonContains('additional_roles', 'custodian-equipment')
                    ->orWhereJsonContains('additional_roles', 'custodian')),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'quantity_available' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['quantity_available'] = $validated['quantity_available'] ?? $validated['quantity'];
        if ($nameError = \App\Support\ResourceRename::equipmentError($validated['name'])) {
            return back()->withErrors(['name' => $nameError])->withInput();
        }
        Equipment::create($validated);

        return back()->with('success', 'Equipment created successfully.');
    }

    public function updateEquipment(Request $request, Equipment $equipment)
    {
        $this->ensureAdminAccess();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'custodian_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['custodian-equipment', 'custodian'])
                    ->orWhereJsonContains('additional_roles', 'custodian-equipment')
                    ->orWhereJsonContains('additional_roles', 'custodian')),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'quantity_available' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['quantity_available'] = $validated['quantity_available'] ?? $validated['quantity'];
        if ($renameError = \App\Support\ResourceRename::equipmentError($validated['name'], $equipment)) {
            return back()->withErrors(['name' => $renameError])->withInput();
        }
        $equipment->update($validated);

        return back()->with('success', 'Equipment updated successfully.');
    }

    public function destroyEquipment(Request $request, Equipment $equipment)
    {
        $this->ensureAdminAccess();

        $hasActiveReservation = FacilityRequest::whereIn('status', ['pending', 'approved'])
            ->where(fn ($query) => $query->matchesEquipment($equipment->name))
            ->exists();

        if ($hasActiveReservation) {
            return back()->withErrors(['equipment' => 'Cannot delete equipment because it still has active reservations.']);
        }

        $equipment->delete();

        return back()->with('success', 'Equipment deleted successfully.');
    }

    public function update(Request $request)
    {
        abort_unless(Auth::user()?->isFinalApprover(), 403);

        $validated = $request->validate([
            'id'       => 'required|integer|exists:facility_requests,id',
            'action'   => 'required|in:approve,reject',
            'notes'    => 'nullable|string|max:1000',
            'priority' => 'nullable|in:regular,institutional',
        ]);

        $requestForLocking = FacilityRequest::find($validated['id']);
        if ($validated['action'] === 'approve' && $requestForLocking
            && ($cutoff = \App\Support\ApprovalCutoff::reason($requestForLocking))) {
            return back()->withErrors(['action' => $cutoff]);
        }
        if (! $requestForLocking) {
            return redirect()->back()->withErrors(['id' => 'Request not found.']);
        }

        DB::beginTransaction();

        try {
            if ($validated['action'] === 'approve') {
                app(\App\Services\AvailabilityService::class)
                    ->lockResourcesForFacilityRequests($requestForLocking);
            }
            $fr = FacilityRequest::whereKey($validated['id'])->lockForUpdate()->firstOrFail();

            $alreadyApproved = $fr->status === 'approved' || ($fr->approved_by_id || $fr->approved_by);
            if ($alreadyApproved && $validated['action'] === 'approve') {
                DB::rollBack();
                return back()->with('info', 'This request has already been approved.');
            }

            if ($fr->status === 'rejected' && $validated['action'] === 'reject') {
                DB::rollBack();
                return back()->with('info', 'This request has already been rejected.');
            }

            if ($fr->venue_status !== 'approved' || $fr->equipment_status !== 'approved') {
                DB::rollBack();
                return back()->withErrors(['action' => 'Cannot approve: custodians have not yet approved.']);
            }
            if (in_array($fr->supply_office_review_status, ['pending', 'unavailable'], true)) {
                DB::rollBack();
                return back()->withErrors(['action' => 'The initial Supply Office availability review must be completed before final approval.']);
            }
            // B: an outsider reservation is confirmed only after IGP has coordinated the
            // institution's requirements with the client. Payment itself stays offline.
            if ($validated['action'] === 'approve'
                && $fr->isOutsiderInquiry()
                && $fr->outsider_stage !== 'igp_coordination') {
                DB::rollBack();
                return back()->withErrors(['action' => 'IGP must first inform the client of the payment and other institutional requirements (For IGP Coordination) before the Supply Office confirms the reservation.']);
            }
            if ($validated['action'] === 'approve' && $fr->requester && ! $fr->requester->isVerified()) {
                // D4: the requester's ID must be reviewed before final approval.
                DB::rollBack();
                return back()->withErrors(['action' => "The requester's ID has not been verified yet. Review it under Account Verification before final approval."]);
            }
            if ($validated['action'] === 'approve' && $fr->hasCustodianApprovalBy(Auth::user())) {
                DB::rollBack();
                return back()->withErrors(['action' => 'A custodian cannot also provide final Supply Office approval for the same request. A second Supply Office staff member must review and document any staffing exception.']);
            }

            $conflictingRequest = null;
            $isOverrideEligible = ($fr->priority === 'institutional') || (!empty($fr->is_emergency) && (bool) $fr->is_emergency);

            if ($validated['action'] === 'approve') {
            if ($isOverrideEligible) {
                $requestedVenueNames = $fr->getVenueNames();
                if (!empty($requestedVenueNames)) {
                    $conflictingRequests = FacilityRequest::query()
                        ->where('id', '!=', $fr->id)
                        ->where('status', 'approved')
                        ->where('venue_status', 'approved')
                        ->whereNotIn('status', ['rejected', 'cancelled', 'completed', 'pending', 'needs_reschedule'])
                        ->where(function ($query) use ($requestedVenueNames) {
                            foreach ($requestedVenueNames as $venueName) {
                                $query->orWhere(fn ($subQuery) => $subQuery->matchesVenue($venueName));
                            }
                        })
                        ->get();

                    foreach ($conflictingRequests as $conflict) {
                        if ($conflict->overlapsRequest($fr)) {
                            $conflictingRequest = $conflict;
                            break;
                        }
                    }
                }
            }
        }

        if ($conflictingRequest) {
            DB::rollBack();
            return redirect()->route('supply-office.priority-override.confirm', [
                'urgent_request_id' => $fr->id,
                'conflicting_request_id' => $conflictingRequest->id,
                'urgent_requester_name' => $fr->requester?->name ?? '',
                'conflicting_requester_name' => $conflictingRequest->requester?->name ?? '',
                'urgent_activity_name' => $fr->name_of_activity,
                'conflicting_activity_name' => $conflictingRequest->name_of_activity,
                'venue' => implode(', ', $fr->getVenueNames()),
                'date' => $fr->start_date ? $fr->start_date->format('Y-m-d') : '',
                'time' => $fr->start_time ? $fr->start_time : '',
                'priority' => $fr->priority ?? 'regular',
            ]);
        }

        if ($validated['action'] === 'approve' && !$isOverrideEligible) {
            $availabilityMessage = app(\App\Services\AvailabilityService::class)
                ->checkFacilityRequest($fr, $fr->id);
            if ($availabilityMessage) {
                DB::rollBack();
                return back()->withErrors(['action' => $availabilityMessage]);
            }
        }

        $originalStatus = $fr->status;
        
        $this->applyRequestApproval($fr, $validated['action'], $validated['notes'] ?? '', $validated['priority'] ?? null);
        if ($validated['action'] === 'approve') {
            $fr->recordApprovalSignature('final', Auth::user());
        }

        $fr->addHistory($validated['action'] === 'approve' ? 'approved' : 'rejected',
            'Administrator ' . Auth::user()->name . ' completed request as ' . ($validated['action'] === 'approve' ? 'approved' : 'rejected') .
            (($validated['priority'] ?? null) ? ' with priority ' . $validated['priority'] : ''),
            Auth::user()->getKey());

        DB::commit();

        // ✅ Send consolidated notification at final approval/rejection
        if ($originalStatus !== $fr->status) {
            $requester = \App\Models\User::find($fr->requested_by_id);
            if ($requester) {
                if ($validated['action'] === 'approve') {
                    // Get all custodian approval details
                    $approvalDetails = $fr->getConsolidatedApprovalDetails();
                    
                    $requester->notify(new \App\Notifications\RequestStatusChanged(
                        $fr,
                        'approved',
                        $validated['notes'] ?? '',
                        null,
                        $approvalDetails['venue_custodian'],
                        $approvalDetails['equipment_custodians'],
                        Auth::user()->name
                    ));
                } else {
                    // Rejection notification
                    $requester->notify(new \App\Notifications\RequestStatusChanged(
                        $fr,
                        'rejected',
                        $validated['notes'] ?? '',
                        null,
                        null,
                        [],
                        Auth::user()->name
                    ));
                }
            }
        }

        // B.8: IGP is told the Supply Office's final decision on an outsider inquiry.
        if ($originalStatus !== $fr->status && $fr->isOutsiderInquiry()) {
            Notification::send(
                User::query()->withAnyRole(['igp'])->get(),
                new RequestStatusChanged($fr, $validated['action'] === 'approve' ? 'outsider_reservation_confirmed' : 'rejected', $validated['notes'] ?? '', Auth::user()->name . ' - Supply Office')
            );
        }

        $label = $validated['action'] === 'approve' ? 'approved' : 'rejected';
        return redirect()->route('supply-office.index')
                        ->with('success', "Request {$label} successfully.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Supply office update failed for request ' . ($validated['id'] ?? 'unknown') . ': ' . $e->getMessage(), ['exception' => $e]);
            return back()->withErrors('Unable to process the request at this time.');
        }
    }

    public function confirmPriorityOverride(Request $request)
    {
        $urgentRequest = FacilityRequest::find($request->get('urgent_request_id'));
        $conflictingRequest = FacilityRequest::find($request->get('conflicting_request_id'));

        return view('supply-office.priority-override-confirm', [
            'urgentRequestId' => $urgentRequest?->id ?? $request->get('urgent_request_id'),
            'conflictingRequestId' => $conflictingRequest?->id ?? $request->get('conflicting_request_id'),
            'urgentRequesterName' => $urgentRequest?->requester?->name ?? $request->get('urgent_requester_name'),
            'conflictingRequesterName' => $conflictingRequest?->requester?->name ?? $request->get('conflicting_requester_name'),
            'urgentActivityName' => $urgentRequest?->name_of_activity ?? $request->get('urgent_activity_name'),
            'conflictingActivityName' => $conflictingRequest?->name_of_activity ?? $request->get('conflicting_activity_name'),
            'urgentStatus' => $urgentRequest?->status ?? 'unknown',
            'conflictingStatus' => $conflictingRequest?->status ?? 'unknown',
            'venue' => $request->get('venue'),
            'date' => $request->get('date'),
            'time' => $request->get('time'),
            'priority' => $request->get('priority'),
        ]);
    }

    public function submitPriorityOverride(Request $request)
    {
        $validated = $request->validate([
            'override_reason' => ['required', 'string', 'max:1000'],
            'urgent_request_id' => ['required', 'integer', 'exists:facility_requests,id'],
            'conflicting_request_id' => ['required', 'integer', 'exists:facility_requests,id'],
            'new_start_date' => ['required', 'date'],
            'new_end_date' => ['required', 'date', 'after_or_equal:new_start_date'],
            'new_start_time' => ['required', 'date_format:H:i'],
            'new_end_time' => ['required', 'date_format:H:i'],
        ]);

        // REQ-16: the moved reservation follows the same time rules as every other schedule.
        if ($validated['new_start_time'] <= '05:00' || $validated['new_end_time'] <= '05:00') {
            return back()->withErrors(['new_end_time' => 'Reservations cannot start or end between 12:00 AM and 5:00 AM. Choose times after 5:00 AM.'])->withInput();
        }
        if (\Carbon\Carbon::parse($validated['new_start_date'])->isSameDay(\Carbon\Carbon::parse($validated['new_end_date'])) && $validated['new_end_time'] <= $validated['new_start_time']) {
            return back()->withErrors(['new_end_time' => 'The end time must be after the start time. For an overnight booking, set the end date to the next day.'])->withInput();
        }

        $urgentRequest = FacilityRequest::findOrFail($validated['urgent_request_id']);
        $conflictingRequest = FacilityRequest::findOrFail($validated['conflicting_request_id']);
        if (!($urgentRequest->is_emergency || $urgentRequest->priority === 'institutional')) {
            return back()->withErrors(['urgent_request_id' => 'This request is not eligible for an urgent priority transfer.']);
        }
        if ($conflictingRequest->status !== 'approved' || $conflictingRequest->venue_status !== 'approved') {
            return back()->with('warning', 'The conflicting request is no longer an active approved reservation.');
        }

        $actingUser = Auth::user();
        $overrideReason = trim($validated['override_reason']);

        try {
            DB::transaction(function () use ($urgentRequest, $conflictingRequest, $validated, $overrideReason, $actingUser): void {
                app(\App\Services\AvailabilityService::class)
                    ->lockResourcesForFacilityRequests($urgentRequest, $conflictingRequest);

                $lockedUrgent = FacilityRequest::whereKey($urgentRequest->id)->lockForUpdate()->firstOrFail();
                $lockedConflict = FacilityRequest::whereKey($conflictingRequest->id)->lockForUpdate()->firstOrFail();

                if (!($lockedUrgent->is_emergency || $lockedUrgent->priority === 'institutional')) {
                    throw new \RuntimeException('The urgent priority request is no longer eligible.');
                }
                if ($lockedConflict->status !== 'approved' || $lockedConflict->venue_status !== 'approved') {
                    throw new \RuntimeException('The approved reservation changed before the transfer proposal was created.');
                }
                if ($lockedConflict->revisionHistories()->where('status', 'pending')->exists()) {
                    throw new \RuntimeException('A suggested schedule is already pending for this approved reservation.');
                }

                $oldState = $lockedConflict->getCurrentState();
                $newVenues = $oldState['venue'];
                $office = data_get($lockedUrgent->document_metadata, 'urgent_priority.office', 'CHED/OP/OVP');
                $reason = "CHED/OP/OVP priority transfer requested by {$office}: {$overrideReason}";

                $revision = $lockedConflict->revisionHistories()->create([
                    'revised_by_id' => $actingUser->id,
                    'old_start_date' => $oldState['start_date'],
                    'old_end_date' => $oldState['end_date'],
                    'old_start_time' => $oldState['start_time'],
                    'old_end_time' => $oldState['end_time'],
                    'old_venue' => $oldState['venue'],
                    'old_equipment' => $oldState['equipment'],
                    'old_equipment_quantities' => $oldState['equipment_quantities'],
                    'new_start_date' => $validated['new_start_date'],
                    'new_end_date' => $validated['new_end_date'],
                    'new_start_time' => $validated['new_start_time'],
                    'new_end_time' => $validated['new_end_time'],
                    'new_venue' => $newVenues,
                    'new_equipment' => $oldState['equipment'],
                    'new_equipment_quantities' => $oldState['equipment_quantities'],
                    'revision_reason' => $reason,
                    'status' => 'pending',
                    'conflict_detected' => true,
                    'conflict_details' => 'Urgent priority transfer created for ' . $lockedUrgent->control_number,
                    'override_conflict' => true,
                    'override_reason' => $overrideReason,
                    'response_deadline_at' => now()->addDay(),
                    'affected_user_ids' => [(int) $lockedConflict->requested_by_id],
                    'responses' => [(string) $lockedConflict->requested_by_id => 'not_required'],
                    'memorandum_priority' => true,
                ]);

                $lockedUrgent->addHistory(
                    'urgent_priority_transfer_pending',
                    "Priority transfer proposal {$revision->id} created for approved reservation {$lockedConflict->control_number}. Existing reservations remain active until the proposal is accepted and custodians respond.",
                    $actingUser->id
                );
                $lockedConflict->addHistory(
                    'priority_transfer_proposed',
                    "A formal priority-transfer proposal was created for urgent request {$lockedUrgent->control_number}. The approved reservation remains active pending response. Reason: {$reason}",
                    $actingUser->id
                );
            });
        } catch (\Throwable $e) {
            Log::warning('Priority transfer proposal was not created.', [
                'urgent_request_id' => $urgentRequest->id,
                'conflicting_request_id' => $conflictingRequest->id,
                'exception' => $e->getMessage(),
            ]);
            return back()->withErrors(['override_reason' => $e->getMessage()]);
        }

        if ($requester = $conflictingRequest->requester) {
            DB::afterCommit(function () use ($requester, $conflictingRequest, $urgentRequest, $overrideReason, $actingUser): void {
                $requester->notify(new RequestStatusChanged(
                    $conflictingRequest,
                    'priority_transfer_proposed',
                    "Urgent request {$urgentRequest->control_number} requires a priority transfer. A proposed alternative schedule is waiting for custodian response. Reason: {$overrideReason}",
                    null,
                    null,
                    [],
                    $actingUser->name,
                    $overrideReason
                ));
            });
        }

        return redirect()->route('supply-office.index')->with('success', 'Priority transfer proposal created. The approved reservation remains active until the proposal is accepted.');
    }

    private function applyRequestApproval(FacilityRequest $fr, string $action, ?string $notes = null, ?string $priority = null): void
    {
        $statusValue = $action === 'approve' ? 'approved' : 'rejected';
        $updates = [
            'status' => $statusValue,
            'notes' => $notes ?? '',
        ];

        if ($action === 'approve') {
            $quantities = $fr->getEquipmentQuantities();
            if (empty($quantities) && !empty($fr->getEquipmentItems())) {
                $quantities = array_fill_keys($fr->getEquipmentItems(), 1);
            }

            foreach ($quantities as $itemName => $qty) {
                $qty = (int) $qty;
                if ($qty <= 0) {
                    continue;
                }

                $equipment = Equipment::whereRaw('LOWER(name) = ?', [strtolower($itemName)])
                    ->lockForUpdate()
                    ->first();

                if (!$equipment) {
                    continue;
                }

                if ($equipment->quantity_available < $qty) {
                    throw new \RuntimeException("Insufficient inventory for '{$itemName}'.");
                }

                $equipment->quantity_available = min(
                    (int) $equipment->quantity,
                    max(0, (int) $equipment->quantity_available - $qty)
                );
                $equipment->save();
            }

            $updates['approved_by'] = Auth::user()->name;
            $updates['approved_by_id'] = Auth::user()->getKey();
            $updates['approved_date'] = now();
            $updates['venue_status'] = 'approved';
            $updates['equipment_status'] = 'approved';
        } else {
            $updates['approved_by'] = null;
            $updates['approved_by_id'] = null;
            $updates['approved_date'] = null;
        }

        if (!empty($priority)) {
            $updates['priority'] = $priority;
        }

        $fr->update($updates);
    }

    public function destroy(Request $request)
    {
        return redirect()->route('supply-office.index')
            ->withErrors(['id' => 'Requests cannot be permanently deleted. Use Cancel Request where applicable.']);
    }

    private function ensureAdminAccess(): void
    {
        abort_unless(Auth::check() && Auth::user()?->isAdmin(), 403);
    }

    private function buildRequestListQuery(Request $request, bool $hidePastReservations = true): Builder
    {
        $query = FacilityRequest::with([
            'requester',
            'reservationSchedule',
            'requestEquipment.requestVenue.venue',
            'requestEquipment.equipment',
        ]);
        if ($hidePastReservations) {
            $query->upcomingReservation();
        }

        // Additional Enhancements, search: shared rule (every word must match).
        \App\Support\RequestSearch::apply($query, (string) $request->get('search', ''));

        $equipmentFilter = trim((string) $request->get('equipment', ''));
        if ($equipmentFilter !== '') {
            $equipmentLike = '%' . mb_strtolower($equipmentFilter) . '%';
            $query->where(function (Builder $equipmentQuery) use ($equipmentLike): void {
                $equipmentQuery->whereHas('requestEquipment', fn (Builder $items) => $items->whereRaw('LOWER(name) LIKE ?', [$equipmentLike]))
                    ->orWhereRaw('LOWER(equipment) LIKE ?', [$equipmentLike]);
            });
        }

        // Review status filter: venue review, equipment review or Supply Office decision.
        $reviewFilters = [
            'venue_pending' => ['venue_status', 'pending'],
            'venue_approved' => ['venue_status', 'approved'],
            'venue_reschedule' => ['venue_status', 'needs_reschedule'],
            'supply_pending' => ['supply_office_review_status', 'pending'],
            'supply_confirmed' => ['supply_office_review_status', 'confirmed'],
            'supply_unavailable' => ['supply_office_review_status', 'unavailable'],
            'equipment_pending' => ['equipment_status', 'pending'],
            'equipment_approved' => ['equipment_status', 'approved'],
            'equipment_declined' => ['equipment_status', 'rejected'],
        ];
        $review = (string) $request->get('review', '');
        if (isset($reviewFilters[$review])) {
            [$column, $value] = $reviewFilters[$review];
            $query->where($column, $value);
        }

        $department = trim((string) $request->get('department', ''));
        if ($department !== '') {
            $query->where('department', 'like', '%' . $department . '%');
        }

        $venueFilter = trim((string) $request->get('venue', ''));
        if ($venueFilter !== '') {
            $query->where(function (Builder $venueQuery) use ($venueFilter): void {
                $venueQuery->whereHas('requestVenues', function (Builder $relatedVenueQuery) use ($venueFilter): void {
                    $relatedVenueQuery->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($venueFilter) . '%']);
                })->orWhere(function (Builder $legacyVenueQuery) use ($venueFilter): void {
                    $legacyVenueQuery->whereRaw('LOWER(venue) LIKE ?', ['%' . mb_strtolower($venueFilter) . '%']);
                });
            });
        }

        $dateFrom = $request->get('date_from');
        if (!blank($dateFrom)) {
            $query->whereDate('start_date', '>=', $dateFrom);
        }

        $dateTo = $request->get('date_to');
        if (!blank($dateTo)) {
            $query->whereDate('start_date', '<=', $dateTo);
        }

        $priority = $request->get('priority');
        if (!blank($priority) && in_array($priority, ['regular', 'institutional'], true)) {
            $query->where('priority', $priority);
        }

        return $query->orderBy('start_date')->orderBy('start_time')->orderBy('created_at');
    }

    public function usageReports(Request $request)
    {
        $filters = $this->usageReportFilters($request);
        $rows = $this->getUsageReportRows($filters['from'], $filters['to']);
        $breakdown = $request->query('breakdown');
        $breakdownValue = trim((string) $request->query('value', ''));

        $detailRows = $rows->when(
            in_array($breakdown, ['equipment', 'venue', 'department', 'priority'], true) && $breakdownValue !== '',
            function ($reportRows) use ($breakdown, $breakdownValue) {
                return $reportRows->filter(function (array $row) use ($breakdown, $breakdownValue): bool {
                    return match ($breakdown) {
                        'equipment' => array_key_exists($breakdownValue, $row['equipment']),
                        'venue' => in_array($breakdownValue, $row['venues'], true),
                        'department' => $row['department'] === $breakdownValue,
                        'priority' => $row['priority'] === $breakdownValue,
                    };
                })->values();
            }
        );

        $equipmentUsage = [];
        $venueUsage = [];
        $departmentUsage = [];
        $priorityCounts = [];
        $monthlyUsage = [];

        foreach ($rows as $row) {
            foreach ($row['equipment'] as $name => $quantity) {
                $equipmentUsage[$name] = ($equipmentUsage[$name] ?? 0) + $quantity;
            }
            foreach ($row['venues'] as $name) {
                $venueUsage[$name] = ($venueUsage[$name] ?? 0) + 1;
            }

            $department = $row['department'];
            $departmentUsage[$department]['total_requests'] = ($departmentUsage[$department]['total_requests'] ?? 0) + 1;
            $departmentUsage[$department]['total_participants'] = ($departmentUsage[$department]['total_participants'] ?? 0) + $row['participants'];

            $priorityCounts[$row['priority']] = ($priorityCounts[$row['priority']] ?? 0) + 1;
            $month = $row['request']->reservationSchedule->start_datetime->format('Y-m');
            $monthlyUsage[$month] = ($monthlyUsage[$month] ?? 0) + 1;
        }

        arsort($equipmentUsage);
        arsort($venueUsage);
        ksort($monthlyUsage);
        $monthCursor = $filters['from']->copy()->startOfMonth();
        $lastMonth = $filters['to']->copy()->startOfMonth();
        while ($monthCursor->lte($lastMonth)) {
            $monthlyUsage[$monthCursor->format('Y-m')] = $monthlyUsage[$monthCursor->format('Y-m')] ?? 0;
            $monthCursor->addMonth();
        }
        ksort($monthlyUsage);

        $previousTo = $filters['from']->copy()->subDay()->endOfDay();
        $previousFrom = $previousTo->copy()->startOfDay()->subDays($filters['days'] - 1);
        $previousPeriodCount = $this->getUsageReportRows($previousFrom, $previousTo)->count();
        $changePercent = $previousPeriodCount === 0
            ? ($rows->isEmpty() ? 0 : null)
            : round((($rows->count() - $previousPeriodCount) / $previousPeriodCount) * 100, 1);

        return view('supply-office.usage-reports', [
            'equipmentUsage' => collect($equipmentUsage)->map(fn ($count, $name) => ['equipment' => $name, 'total_used' => $count]),
            'venueUsage' => collect($venueUsage)->map(fn ($count, $name) => ['venue' => $name, 'total_bookings' => $count]),
            'departmentUsage' => collect($departmentUsage)->map(fn ($totals, $department) => (object) [
                'department' => $department,
                'total_requests' => $totals['total_requests'],
                'total_participants' => $totals['total_participants'],
            ]),
            'priorityStats' => collect($priorityCounts)->map(fn ($count, $priority) => (object) ['priority' => $priority, 'count' => $count]),
            'approvedRequestCount' => $rows->count(),
            'dateFrom' => $filters['from']->toDateString(),
            'dateTo' => $filters['to']->toDateString(),
            'preset' => $filters['preset'],
            'detailRows' => $detailRows,
            'breakdown' => $breakdown,
            'breakdownValue' => $breakdownValue,
            'totalEquipmentUnits' => array_sum($equipmentUsage),
            'totalVenueBookings' => array_sum($venueUsage),
            'totalParticipants' => $rows->sum('participants'),
            'monthlyUsage' => $monthlyUsage,
            'previousPeriodCount' => $previousPeriodCount,
            'changePercent' => $changePercent,
            'previousFrom' => $previousFrom->toDateString(),
            'previousTo' => $previousTo->toDateString(),
        ]);
    }

    public function exportUsageReports(Request $request): StreamedResponse
    {
        $filters = $this->usageReportFilters($request);
        $rows = $this->getUsageReportRows($filters['from'], $filters['to']);
        $filename = 'usage-report-' . $filters['from']->toDateString() . '-to-' . $filters['to']->toDateString() . '.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                throw new \RuntimeException('Unable to open CSV output stream.');
            }

            fwrite($handle, "\xEF\xBB\xBF");

            $safeCsvText = static function (?string $value): string {
                $value = $value ?? '';
                return preg_match('/^[\s\t\r]*[=+\-@]/u', $value) === 1 ? "'" . $value : $value;
            };

            fputcsv($handle, ['Control Number', 'Activity', 'Requestor', 'Department', 'Priority', 'Start', 'End', 'Venues', 'Equipment', 'Equipment Units', 'Participants']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $safeCsvText($row['request']->control_number),
                    $safeCsvText($row['request']->name_of_activity),
                    $safeCsvText($row['request']->requester?->name ?? $row['request']->user?->name ?? 'Unknown'),
                    $safeCsvText($row['department']),
                    $safeCsvText($row['priority']),
                    $row['request']->reservationSchedule->start_datetime->format('Y-m-d H:i'),
                    $row['request']->reservationSchedule->end_datetime->format('Y-m-d H:i'),
                    $safeCsvText(implode('; ', $row['venues'])),
                    $safeCsvText(implode('; ', array_map(fn ($name, $quantity) => $name . ' (' . $quantity . ')', array_keys($row['equipment']), array_values($row['equipment'])))),
                    array_sum($row['equipment']),
                    $row['participants'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function usageReportFilters(Request $request): array
    {
        $validated = $request->validate([
            'preset' => ['nullable', 'in:this_month,last_month,calendar_half_year,this_year,custom'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);
        $preset = $validated['preset'] ?? 'this_month';
        $today = now();

        [$from, $to] = match ($preset) {
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'calendar_half_year' => [
                $today->month <= 6 ? $today->copy()->startOfYear() : $today->copy()->month(7)->startOfMonth(),
                $today->month <= 6 ? $today->copy()->month(6)->endOfMonth() : $today->copy()->endOfYear(),
            ],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'custom' => [
                isset($validated['date_from']) ? \Illuminate\Support\Carbon::parse($validated['date_from']) : $today->copy()->startOfMonth(),
                isset($validated['date_to']) ? \Illuminate\Support\Carbon::parse($validated['date_to']) : $today->copy()->endOfMonth(),
            ],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
        };

        if ($preset !== 'custom' && isset($validated['date_from'], $validated['date_to'])) {
            $preset = 'custom';
            $from = \Illuminate\Support\Carbon::parse($validated['date_from']);
            $to = \Illuminate\Support\Carbon::parse($validated['date_to']);
        }

        if ($from->gt($to)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'date_to' => 'The end date must be on or after the start date.',
            ]);
        }

        $days = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;

        return ['from' => $from->startOfDay(), 'to' => $to->endOfDay(), 'days' => $days, 'preset' => $preset];
    }

    private function getUsageReportRows(\Illuminate\Support\Carbon $from, \Illuminate\Support\Carbon $to)
    {
        return FacilityRequest::with(['requester', 'user', 'requestVenues', 'requestEquipment.equipment', 'reservationSchedule'])
            ->where('status', 'approved')
            ->whereHas('reservationSchedule', function (Builder $query) use ($from, $to): void {
                $query->whereBetween('start_datetime', [$from, $to]);
            })
            ->get()
            ->map(function (FacilityRequest $request): array {
                $equipment = $request->getEquipmentQuantities();
                $venues = $request->getVenueNames();
                $schedule = $request->reservationSchedule;

                return [
                    'request' => $request,
                    'venues' => array_values(array_unique(array_filter($venues))),
                    'equipment' => array_filter($equipment, fn ($quantity, $name) => $name !== '' && $quantity > 0, ARRAY_FILTER_USE_BOTH),
                    'department' => trim((string) $request->department) ?: 'Unspecified',
                    'priority' => trim((string) $request->priority) ?: 'regular',
                    'participants' => (int) $request->expected_participants,
                    'start' => $schedule->start_datetime,
                ];
            });
    }

    private function normalizeReportListValue($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(fn ($item) => is_string($item) ? trim($item) : null, $value)));
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map(fn ($item) => is_string($item) ? trim($item) : null, $decoded)));
            }

            return array_values(array_filter(array_map('trim', preg_split('/[,;]+/', $trimmed) ?: [])));
        }

        return [];
    }

    public function export(Request $request)
    {
        $query = FacilityRequest::with('requester');

        $scope = $request->get('scope');
        if ($scope === 'approved') {
            $query->where('status', 'approved');
        } elseif ($scope === 'rejected') {
            $query->where('status', 'rejected');
        } elseif ($scope === 'pending') {
            $query->where('status', 'pending');
        } elseif ($scope === 'needs-reschedule') {
            $query->where(function (Builder $statusQuery): void {
                $statusQuery->where('status', 'needs_reschedule')
                    ->orWhere('venue_status', 'needs_reschedule')
                    ->orWhere('equipment_status', 'needs_reschedule');
            });
        }

        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $searchTerm = '%' . mb_strtolower($search) . '%';
            $query->where(function (Builder $filterQuery) use ($searchTerm): void {
                $filterQuery->whereRaw('LOWER(control_number) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(name_of_activity) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(department) LIKE ?', [$searchTerm])
                    ->orWhereHas('requester', function (Builder $requesterQuery) use ($searchTerm): void {
                        $requesterQuery->whereRaw('LOWER(name) LIKE ?', [$searchTerm]);
                    });
            });
        }

        $requests = $query->orderBy('start_date')->orderBy('start_time')->orderBy('created_at')->get();

        $filename = 'facility_requests_report_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return new StreamedResponse(function () use ($requests) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Control Number',
                'Date Requested',
                'Department',
                'Activity',
                'Participants',
                'Request Date',
                'Time',
                'Venue',
                'Equipment',
                'Requester',
                'Priority',
                'Status',
                'Approved Date',
            ]);

            foreach ($requests as $req) {
                fputcsv($handle, \App\Support\CsvCell::row([
                    $req->control_number,
                    $req->date_requested,
                    $req->department,
                    $req->name_of_activity,
                    $req->expected_participants,
                    $req->start_date,
                    $req->start_time,
                    implode(', ', $req->getVenueNames()),
                    implode(', ', $req->getEquipmentItems()),
                    $req->requester?->name ?? $req->user?->name ?? '',
                    $req->priority,
                    $req->status,
                    $req->approved_date?->format('Y-m-d'),
                ]));
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
