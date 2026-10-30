<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\RequestChangeRequest;
use App\Models\User;
use App\Models\Venue;
use App\Events\RequestCancelled;
use App\Notifications\RequestStatusChanged;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class RequestActionController extends Controller
{
    public function __construct(private readonly AvailabilityService $availabilityService)
    {
    }

    public function cancel($facilityRequest, Request $request)
    {
        $facilityRequest = $this->resolveFacilityRequest($facilityRequest);
        $user = Auth::user();
        abort_unless($user && (int) $user->id === (int) $facilityRequest->requested_by_id, 403);
        abort_unless(in_array($facilityRequest->status, ['pending', 'approved'], true), 403);

        $reservationDate = Carbon::parse($facilityRequest->start_date)->startOfDay();
        if ($reservationDate->lessThanOrEqualTo(now()->startOfDay())) {
            return back()->withErrors(['cancellation' => 'Cancellation must be requested no later than the calendar day before the reservation.']);
        }

        if ($facilityRequest->status === 'approved') {
            if ($facilityRequest->cancellation_status === 'pending') {
                return back()->withErrors(['cancellation' => 'A cancellation request is already awaiting Supply Office confirmation.']);
            }
            $facilityRequest->update([
                'cancellation_status' => 'pending',
                'cancellation_requested_by_id' => $user->id,
                'cancellation_requested_at' => now(),
                'cancellation_notes' => trim((string) $request->input('reason', '')),
            ]);
            $reason = 'Cancellation requested by ' . $user->name . '. Supply Office confirmation is required.';
            $facilityRequest->addHistory('cancellation_requested', $reason, $user->id);
            $this->notifyCancellationParties($facilityRequest, 'cancellation_requested', $reason, true);

            return back()->with('success', 'Cancellation requested. The approved reservation remains active until the Supply Office confirms it.');
        }

        DB::transaction(function () use ($facilityRequest, $user): void {
            $lockedRequest = FacilityRequest::query()->lockForUpdate()->findOrFail($facilityRequest->id);
            if ($lockedRequest->status !== 'pending') {
                abort(409, 'This request can no longer be cancelled directly.');
            }
            $lockedRequest->update([
                'status' => 'cancelled',
                'venue_status' => 'cancelled',
                'equipment_status' => 'cancelled',
                'cancellation_status' => 'confirmed',
                'cancellation_requested_by_id' => $user->id,
                'cancellation_requested_at' => now(),
                'cancellation_reviewed_by_id' => $user->id,
                'cancellation_reviewed_at' => now(),
            ]);
            $lockedRequest->addHistory('cancelled', 'Pending request cancelled by ' . $user->name, $user->id);
        });
        $this->notifyCancellationParties($facilityRequest->fresh(), 'request_cancelled', 'Pending request cancelled by ' . $user->name . '.', false);

        return redirect()->route('requestor.index')->with('success', 'Request cancelled successfully.');
    }

    private function notifyCancellationParties(FacilityRequest $facilityRequest, string $status, string $reason, bool $includeSupplyOffice): void
    {
        $recipientIds = Venue::query()->whereIn('name', $facilityRequest->getVenueNames())->pluck('custodian_id')
            ->merge($facilityRequest->getAssignedEquipmentCustodianIds())
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique();
        $recipients = User::query()->whereIn('id', $recipientIds)->get();
        if ($facilityRequest->requester) {
            $recipients->push($facilityRequest->requester);
        }
        if ($includeSupplyOffice) {
            $recipients = $recipients->merge(User::query()->withAnyRole(['admin', 'supply_office'])->get());
        }
        $recipients = $recipients->unique('id')->values();
        if ($status === 'request_cancelled') {
            event(new RequestCancelled(
                $facilityRequest->id,
                $facilityRequest->control_number,
                Auth::user()?->name ?? '',
                $facilityRequest->requested_by_id,
                $recipientIds->all()
            ));
        }

        Notification::send($recipients, new RequestStatusChanged(
            $facilityRequest,
            $status,
            $reason,
            Auth::user()?->name
        ));
    }

    public function submitChangeRequest($facilityRequest, Request $request)
    {
        $facilityRequest = $this->resolveFacilityRequest($facilityRequest);
        $user = Auth::user();

        abort_unless($user && $user->id === $facilityRequest->requested_by_id, 403);
        abort_unless($facilityRequest->status === 'pending'
            && ($facilityRequest->venue_status === 'approved' || $facilityRequest->equipment_status === 'approved'), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        if ($facilityRequest->requestChangeRequests()->where('status', 'pending')->exists()) {
            return redirect()->back()->withErrors(['reason' => 'A change request is already waiting for a decision.']);
        }

        $changeRequest = $facilityRequest->requestChangeRequests()->create([
            'requested_by_id' => $user->id,
            'reason' => trim($validated['reason']),
            'status' => 'pending',
        ]);
        $facilityRequest->addHistory('change_requested', 'Change requested by ' . $user->name . ': ' . $changeRequest->reason, $user->id);
        $this->notifyChangeRequestParties($facilityRequest, 'change_requested', $changeRequest->reason, $user);

        return redirect()->back()->with('success', 'Change request submitted for review.');
    }

    public function approveChangeRequest($facilityRequest, Request $request)
    {
        return $this->decideChangeRequest($facilityRequest, $request, 'approved');
    }

    public function rejectChangeRequest($facilityRequest, Request $request)
    {
        return $this->decideChangeRequest($facilityRequest, $request, 'rejected');
    }

    private function decideChangeRequest($facilityRequest, Request $request, string $decision)
    {
        $facilityRequest = $this->resolveFacilityRequest($facilityRequest);
        $user = Auth::user();
        $changeRequest = $facilityRequest->requestChangeRequests()->where('status', 'pending')->latest()->first();

        abort_unless($user && $changeRequest, 404);
        abort_unless($this->canDecideChangeRequest($facilityRequest, $user), 403);

        $notes = trim((string) $request->input('notes', ''));
        $changeRequest->update([
            'status' => $decision,
            'decided_by_id' => $user->id,
            'decided_at' => now(),
        ]);

        if ($decision === 'approved') {
            $facilityRequest->update([
                'status' => 'needs_reschedule',
                'venue_status' => 'needs_reschedule',
                'equipment_status' => 'needs_reschedule',
            ]);
            $detail = 'Change request approved by ' . $user->name . '. Requestor may edit and resubmit.';
        } else {
            $detail = 'Change request rejected by ' . $user->name . ($notes !== '' ? ': ' . $notes : '. Existing request approvals were preserved.');
        }

        $facilityRequest->addHistory('change_request_' . $decision, $detail, $user->id);
        $this->notifyRequestorForStatusChange($facilityRequest, $decision === 'approved' ? 'needs_revision' : 'change_request_rejected', $notes ?: $detail, null, [], $user->name);

        return redirect()->back()->with('success', $decision === 'approved'
            ? 'Change request approved. The requestor may now revise the request.'
            : 'Change request rejected. The existing request approvals were preserved.');
    }

    private function canDecideChangeRequest(FacilityRequest $facilityRequest, User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($facilityRequest->venue_status === 'approved' && $user->isCustodianVenue()) {
            return collect($facilityRequest->getVenueNames())->contains(fn (string $name) => $user->venues()->where('name', $name)->exists());
        }

        return $facilityRequest->equipment_status === 'approved'
            && $user->isCustodianEquipment()
            && $facilityRequest->getAssignedEquipmentForCustodian($user->id) !== [];
    }

    private function notifyChangeRequestParties(FacilityRequest $facilityRequest, string $status, string $reason, User $requestor): void
    {
        $recipients = collect();
        if ($facilityRequest->venue_status === 'approved') {
            $recipients = $recipients->merge($facilityRequest->requestVenues()->with('venue.custodian')->get()->map(fn ($item) => $item->venue?->custodian));
        }
        if ($facilityRequest->equipment_status === 'approved') {
            $recipients = $recipients->merge(User::whereIn('id', Equipment::whereIn('name', $facilityRequest->getEquipmentItems())->pluck('custodian_id')->filter())->get());
        }
        $recipients = $recipients->merge(User::query()->whereIn('role', ['admin', 'supply_office'])->get())->filter()->unique('id');

        Notification::send($recipients, new RequestStatusChanged($facilityRequest, $status, $reason, $requestor->name));
    }

    public function custodianVerify($facilityRequest, Request $request)
    {
        $facilityRequest = $this->resolveFacilityRequest($facilityRequest);

        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user && $user->isCustodian(), 403);
        $isVenueConfirmation = $user->isCustodianVenue()
            && $facilityRequest->supply_office_review_status === 'pending'
            && $facilityRequest->venue_status === 'pending';
        $isEquipmentApproval = ! $isVenueConfirmation
            && $user->isCustodianEquipment()
            && $facilityRequest->supply_office_review_status === 'confirmed'
            && $facilityRequest->getAssignedEquipmentForCustodian($user->id) !== [];
        if ($isVenueConfirmation) {
            $this->authorize('approveVenue', $facilityRequest);
            $authorizedAction = 'venue';
        } elseif ($isEquipmentApproval) {
            $this->authorize('approveEquipment', $facilityRequest);
            $authorizedAction = 'equipment';
        } else {
            $this->authorize('approve', $facilityRequest);
            $authorizedAction = null;
        }

        DB::beginTransaction();

        try {
            $this->availabilityService->lockResourcesForFacilityRequests($facilityRequest);
            $facilityRequest = FacilityRequest::whereKey($facilityRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($facilityRequest->revisionHistories()->where('status', 'pending')->exists()) {
                DB::rollBack();
                return redirect()->back()->withErrors('A schedule proposal is awaiting responses. Custodian actions are paused until it is answered.');
            }

            $isVenueConfirmation = $user->isCustodianVenue()
                && $facilityRequest->supply_office_review_status === 'pending'
                && $facilityRequest->venue_status === 'pending';
            $assignedEquipment = $facilityRequest->getAssignedEquipmentForCustodian($user->id);
            $isEquipmentApproval = ! $isVenueConfirmation
                && $user->isCustodianEquipment()
                && $facilityRequest->supply_office_review_status === 'confirmed'
                && $assignedEquipment !== [];

            if (($isVenueConfirmation && $authorizedAction !== 'venue')
                || ($isEquipmentApproval && $authorizedAction !== 'equipment')) {
                DB::rollBack();
                return redirect()->back()->withErrors('The request workflow changed. Refresh the page and review the current action.');
            }

            if ($isVenueConfirmation) {
                $this->authorize('approveVenue', $facilityRequest);
            } elseif ($isEquipmentApproval) {
                $this->authorize('approveEquipment', $facilityRequest);
            } elseif ($user->isCustodianVenue()) {
                DB::rollBack();
                return redirect()->back()->withErrors('Venue availability can only be confirmed before the Supply Office review.');
            } elseif ($user->isCustodianEquipment()) {
                DB::rollBack();
                return redirect()->back()->withErrors('The venue custodian and Supply Office must confirm availability before equipment review can begin.');
            } else {
                DB::rollBack();
                abort(403);
            }

            $conflictMessage = $isVenueConfirmation
                ? $this->availabilityService->checkFacilityRequestForReview($facilityRequest)
                : $this->checkHybridResources($facilityRequest);
            if ($conflictMessage) {
                DB::rollBack();
                return redirect()->back()->withErrors($conflictMessage);
            }

            if ($isVenueConfirmation && $facilityRequest->venue_status === 'approved') {
                DB::rollBack();
                return redirect()->back()->with('info', 'Your endorsement has already been recorded.');
            }
            if ($isEquipmentApproval && $facilityRequest->getCustodianEquipmentStatus((int) $user->id) === 'approved') {
                DB::rollBack();
                return redirect()->back()->with('info', 'Your endorsement has already been recorded.');
            }

            if ($facilityRequest->status === 'approved' || $facilityRequest->status === 'rejected') {
                DB::rollBack();
                return redirect()->back()->with('info', 'This request is no longer awaiting custodial verification.');
            }

            if ($isEquipmentApproval && $facilityRequest->equipment_status === 'approved') {
                DB::rollBack();
                return redirect()->back()->with('info', 'This equipment requirement is already satisfied by an authorized custodian.');
            }

            if ($isVenueConfirmation) {
                $facilityRequest->venue_status = 'approved';
                $facilityRequest->recordApprovalSignature('venue', $user);
                $facilityRequest->addHistory('custodian_endorsed', 'Venue request verified and endorsed by ' . $user->name, $user->id);
            } elseif ($isEquipmentApproval) {
                $statuses = $facilityRequest->equipment_custodian_statuses ?? [];
                $statuses[$user->id] = 'approved';
                $facilityRequest->equipment_custodian_statuses = $statuses;
                $facilityRequest->save();
                $facilityRequest->recomputeEquipmentStatus();
                $facilityRequest->recordApprovalSignature('equipment', $user);
                $facilityRequest->save();
                $facilityRequest->addHistory('custodian_endorsed', 'Equipment request verified and endorsed by ' . $user->name, $user->id);
            }

            $facilityRequest->save();
            $this->notifyRequestorForStatusChange(
                $facilityRequest,
                $isVenueConfirmation ? 'venue_approved' : 'equipment_approved',
                $request->input('notes', ''),
                $isVenueConfirmation ? $this->venueActorLabel($facilityRequest, $user) : null,
                $isVenueConfirmation ? [] : [$this->equipmentActorLabel($facilityRequest, $user)],
            );
            DB::commit();

            return redirect()->back()->with(
                'success',
                $isVenueConfirmation
                    ? 'Venue availability confirmed. The request is now waiting for Supply Office confirmation.'
                    : 'Assigned equipment approval recorded.'
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Custodian verification failed for request ' . $facilityRequest->id, ['exception' => $e]);
            return redirect()->back()->withErrors('Unable to verify request at this time.');
        }
    }

    public function custodianRequestRevision($facilityRequest, Request $request)
    {
        $facilityRequest = $this->resolveFacilityRequest($facilityRequest);

        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user && $user->isCustodian(), 403);
        $this->authorize('reject', $facilityRequest);

        $notes = $request->input('notes', 'Revision requested by custodian.');

        DB::beginTransaction();

        try {
            if ($facilityRequest->histories()->where('action', 'revision_requested')->exists()) {
                DB::rollBack();
                return redirect()->back()->with('info', 'A revision request has already been recorded for this request.');
            }

            if ($user->isCustodianVenue()) {
                $facilityRequest->venue_status = 'pending';
                $facilityRequest->venue_notes = $notes;
            } elseif ($user->isCustodianEquipment()) {
                $statuses = $facilityRequest->equipment_custodian_statuses ?? [];
                $statuses[$user->id] = 'revision_requested';
                $facilityRequest->equipment_custodian_statuses = $statuses;
                $facilityRequest->recomputeEquipmentStatus();
            }

            $facilityRequest->status = 'pending';
            $facilityRequest->addHistory('revision_requested', 'Revision requested by ' . $user->name . ': ' . $notes, $user->id);
            $facilityRequest->save();
            $this->notifyRequestorForStatusChange(
                $facilityRequest,
                'revision_requested',
                $notes,
                $user->isCustodianVenue() ? $this->venueActorLabel($facilityRequest, $user) : null,
                $user->isCustodianVenue() ? [] : [$this->equipmentActorLabel($facilityRequest, $user)],
            );

            DB::commit();
            return redirect()->back()->with('success', 'Revision requested; requester has been notified.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors('Unable to request revision at this time.');
        }
    }

    public function custodianReject($facilityRequest, Request $request)
    {
        $facilityRequest = $this->resolveFacilityRequest($facilityRequest);

        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user && $user->isCustodian(), 403);
        $isVenueAction = $user->isCustodianVenue()
            && $facilityRequest->supply_office_review_status === 'pending'
            && $facilityRequest->venue_status === 'pending';
        $isEquipmentAction = ! $isVenueAction
            && $user->isCustodianEquipment()
            && $facilityRequest->supply_office_review_status === 'confirmed'
            && $facilityRequest->getAssignedEquipmentForCustodian($user->id) !== [];
        if ($isVenueAction) {
            $this->authorize('rejectVenue', $facilityRequest);
        } elseif ($isEquipmentAction) {
            $this->authorize('rejectEquipment', $facilityRequest);
        } else {
            $this->authorize('reject', $facilityRequest);
        }

        $notes = trim((string) $request->input('notes', ''));
        if ($isVenueAction) {
            $request->validate(['notes' => ['required', 'string', 'max:1000']]);
        } elseif (! $isEquipmentAction) {
            abort(409, 'The venue custodian and Supply Office must confirm availability before equipment review begins.');
        }

        DB::beginTransaction();

        try {
            if ($facilityRequest->status === 'approved' || $facilityRequest->status === 'rejected') {
                DB::rollBack();
                return redirect()->back()->with('info', 'This request is verified and endorsed to Supply Office for Approval.');
            }
            if ($facilityRequest->revisionHistories()->where('status', 'pending')->exists()) {
                DB::rollBack();
                return redirect()->back()->withErrors('A schedule proposal is awaiting responses. Custodian actions are paused until it is answered.');
            }

            $statusField = $isVenueAction ? 'venue_status' : 'equipment_status';
            $notesField = $isVenueAction ? 'venue_notes' : 'equipment_notes';
            $statusUpdates = $isVenueAction
                ? [
                    $statusField => 'needs_reschedule',
                    $notesField => $notes,
                    'status' => 'pending',
                    'supply_office_review_status' => 'pending',
                ]
                : [
                    $statusField => 'rejected',
                    $notesField => $notes,
                    'status' => 'rejected',
                ];

            if ($statusField === 'equipment_status') {
                $equipmentCustodianStatuses = $facilityRequest->equipment_custodian_statuses ?? [];
                $equipmentCustodianStatuses[$user->id] = 'rejected';
                $statusUpdates['equipment_custodian_statuses'] = $equipmentCustodianStatuses;
            }

            $facilityRequest->update($statusUpdates);
            $facilityRequest->addHistory(
                $isVenueAction ? 'venue_availability_unavailable' : $statusField . '_rejected',
                $isVenueAction
                    ? 'Venue availability marked unavailable by ' . $user->name . ($notes !== '' ? ': ' . $notes : '') . ' Supply Office coordination is required.'
                    : 'Request rejected by ' . $user->name . ($notes !== '' ? ': ' . $notes : ''),
                $user->id
            );
            $this->notifyRequestorForStatusChange(
                $facilityRequest,
                $isVenueAction ? 'venue_availability_unavailable' : 'rejected',
                $notes,
                $isVenueAction ? $this->venueActorLabel($facilityRequest, $user) : null,
                $isVenueAction ? [] : [$this->equipmentActorLabel($facilityRequest, $user)],
            );

            DB::commit();
            if ($isVenueAction) {
                Notification::send(
                    User::query()->withAnyRole(['admin', 'supply_office'])->get(),
                    new RequestStatusChanged($facilityRequest, 'venue_availability_unavailable', $notes, $user->name)
                );
            }
            return redirect()->back()->with('success', 'Request rejected successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Custodian rejection failed for request ' . $facilityRequest->id, ['exception' => $e]);
            return redirect()->back()->withErrors('Unable to reject request at this time.');
        }
    }

    public function supplyFinalApproval($facilityRequest, Request $request)
    {
        $facilityRequest = $this->resolveFacilityRequest($facilityRequest);

        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user && $user->isAdmin(), 403);

        DB::beginTransaction();

        try {
            $this->availabilityService->lockResourcesForFacilityRequests($facilityRequest);
            $facilityRequest = FacilityRequest::whereKey($facilityRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $conflictMessage = $this->checkHybridResources($facilityRequest);
            if ($conflictMessage) {
                DB::rollBack();
                if ($request->expectsJson()) {
                    return response()->json(['message' => $conflictMessage], 422);
                }

                return redirect()->back()->withErrors($conflictMessage);
            }

            if ($facilityRequest->status === 'approved' && ($facilityRequest->approved_by_id || $facilityRequest->approved_by)) {
                DB::rollBack();
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'This request is already approved.'], 409);
                }

                return redirect()->route('supply-office.index')->with('info', 'This request is already approved.');
            }

            if ($facilityRequest->status !== 'pending'
                || $facilityRequest->venue_status !== 'approved'
                || $facilityRequest->equipment_status !== 'approved') {
                DB::rollBack();
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Cannot finalize approval until both custodians have approved the request.',
                    ], 409);
                }

                return redirect()->back()->withErrors('Cannot finalize approval: required custodial endorsements are incomplete.');
            }

            if (in_array($facilityRequest->supply_office_review_status, ['pending', 'unavailable'], true)) {
                DB::rollBack();
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Initial Supply Office availability review is incomplete.'], 409);
                }
                return redirect()->back()->withErrors('Initial Supply Office availability review must be confirmed before final approval.');
            }

            if ($facilityRequest->hasCustodianApprovalBy($user)) {
                DB::rollBack();
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'A custodian cannot also provide final Supply Office approval for this request.'], 409);
                }
                return redirect()->back()->withErrors('A custodian cannot also provide final Supply Office approval for the same request.');
            }

            if ($facilityRequest->is_emergency && $facilityRequest->urgent_document_status !== 'validated') {
                DB::rollBack();
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'The urgent supporting document must be validated before approval.'], 409);
                }
                return redirect()->back()->withErrors('Validate the urgent supporting document before final approval.');
            }

            $originalStatus = $facilityRequest->status;
            
            $facilityRequest->status = 'approved';
            $facilityRequest->approved_by_id = $user->getKey();
            $facilityRequest->approved_by = $user->name;
            $facilityRequest->approved_date = now();

            if ($facilityRequest->venue_status !== 'approved') {
                $facilityRequest->venue_status = 'approved';
            }
            if ($facilityRequest->equipment_status !== 'approved') {
                $facilityRequest->equipment_status = 'approved';
            }

            if (!$facilityRequest->save()) {
                throw new \Exception('Failed to save facility request changes.');
            }

            $facilityRequest->recordApprovalSignature('final', $user);
            $facilityRequest->addHistory('final_approved', 'Final approval granted by ' . $user->name, $user->getKey());

            // ✅ Only notify if status actually changed
            if ($originalStatus !== 'approved') {
                $approvalDetails = $facilityRequest->getConsolidatedApprovalDetails();
                $this->notifyRequestorForStatusChange(
                    $facilityRequest,
                    'approved',
                    $request->input('notes', ''),
                    $this->approvalActorLabel($facilityRequest, $approvalDetails['venue_custodian']),
                    $this->equipmentActorLabels($facilityRequest, $approvalDetails['equipment_custodians']),
                    'Supply Office',
                );
            }

            DB::commit();
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Request approved successfully']);
            }

            return redirect()->route('supply-office.index')->with('success', 'Final approval granted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Final approval failed for request ' . $facilityRequest->id, ['exception' => $e]);
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unable to finalize approval at this time.'], 500);
            }

            return redirect()->back()->withErrors('Unable to finalize approval at this time.');
        }
    }

    public function supplyDecline($facilityRequest, Request $request)
    {
        $facilityRequest = $this->resolveFacilityRequest($facilityRequest);

        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user && $user->isAdmin(), 403);

        DB::beginTransaction();

        try {
            if ($facilityRequest->status === 'rejected') {
                DB::rollBack();
                return redirect()->route('supply-office.index')->with('info', 'This request is already rejected.');
            }

            $originalStatus = $facilityRequest->status;
            
            $facilityRequest->status = 'rejected';
            $facilityRequest->approved_by_id = null;
            $facilityRequest->approved_by = null;
            $facilityRequest->approved_date = null;
            
            if (!$facilityRequest->save()) {
                throw new \Exception('Failed to save decline status.');
            }
            
            $facilityRequest->addHistory('final_rejected', 'Final decline issued by ' . $user->name, $user->getKey());
            
            // ✅ Only notify if status actually changed
            if ($originalStatus !== 'rejected') {
                $this->notifyRequestorForStatusChange(
                    $facilityRequest,
                    'rejected',
                    $request->input('notes', ''),
                    null,
                    [],
                    $user->name . ' - Supply Office',
                );
            }

            DB::commit();
            return redirect()->route('supply-office.index')->with('success', 'Request declined successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Decline request failed for request ' . $facilityRequest->id, ['exception' => $e]);
            return redirect()->back()->withErrors('Unable to decline the request at this time.');
        }
    }

    private function resolveFacilityRequest($facilityRequest): FacilityRequest
    {
        if ($facilityRequest instanceof FacilityRequest) {
            if ($facilityRequest->exists) {
                return $facilityRequest;
            }

            if (!empty($facilityRequest->getKey())) {
                return FacilityRequest::findOrFail($facilityRequest->getKey());
            }
        }

        if (is_scalar($facilityRequest)) {
            return FacilityRequest::findOrFail((int) $facilityRequest);
        }

        throw new \InvalidArgumentException('Invalid facility request reference.');
    }

    private function notifyRequestorForStatusChange(
        FacilityRequest $facilityRequest,
        string $status,
        ?string $notes = null,
        ?string $venueCustodian = null,
        array $equipmentCustodians = [],
        ?string $supplyOffice = null
    ): void
    {
        $requester = $facilityRequest->requester()->first();
        if (! $requester) {
            return;
        }

        try {
            $requester->notify(new RequestStatusChanged(
                $facilityRequest,
                $status,
                $notes ?? '',
                null,
                $venueCustodian,
                $equipmentCustodians,
                $supplyOffice,
            ));
        } catch (\Throwable $e) {
            Log::warning('Request status notification failed after workflow update.', [
                'facility_request_id' => $facilityRequest->id,
                'status' => $status,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function venueActorLabel(FacilityRequest $facilityRequest, User $user): string
    {
        $venues = $facilityRequest->getVenueNames();
        $venue = collect($venues)->first(fn (string $venueName) => $user->venues()->where('name', $venueName)->exists());

        return $user->name . ($venue ? " - {$venue} Venue Custodian" : ' - Venue Custodian');
    }

    private function equipmentActorLabel(FacilityRequest $facilityRequest, User $user): string
    {
        $equipment = array_keys($facilityRequest->getAssignedEquipmentForCustodian($user->id));
        $resource = $equipment !== [] ? implode(', ', $equipment) : 'Equipment';

        return "{$user->name} - {$resource} Equipment Custodian";
    }

    private function approvalActorLabel(FacilityRequest $facilityRequest, ?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        $user = User::where('name', $name)->first();
        if (! $user) {
            return $name . ' - Venue Custodian';
        }

        return $this->venueActorLabel($facilityRequest, $user);
    }

    private function equipmentActorLabels(FacilityRequest $facilityRequest, array $names): array
    {
        return collect($names)->map(function (string $name) use ($facilityRequest): string {
            $user = User::where('name', $name)->first();
            return $user ? $this->equipmentActorLabel($facilityRequest, $user) : "{$name} - Equipment Custodian";
        })->values()->all();
    }

    private function checkHybridResources(FacilityRequest $facilityRequest): ?string
    {
        return $this->availabilityService->checkFacilityRequest($facilityRequest, $facilityRequest->id);
    }

    private function parseRequestDateTime($date, $time)
    {
        $dateString = $date instanceof \Carbon\Carbon ? $date->format('Y-m-d') : $date;
        $timeString = $time ?: '00:00';

        return \Carbon\Carbon::parse($dateString . ' ' . $timeString);
    }
}
