<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Notifications\RequestStatusChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RequestActionController extends Controller
{
    public function cancel($facilityRequest, Request $request)
    {
        $facilityRequest = $this->resolveFacilityRequest($facilityRequest);

        /** @var User|null $user */
        $user = Auth::user();

        abort_unless($user && $user->id === $facilityRequest->requested_by_id, 403);
        abort_unless($facilityRequest->status === 'pending', 403);

        DB::beginTransaction();

        try {
            if ($facilityRequest->equipment_status === 'approved') {
                foreach ($facilityRequest->getEquipmentQuantities() as $itemName => $qty) {
                    $equipment = Equipment::whereRaw('LOWER(name) = ?', [strtolower($itemName)])->first();
                    if ($equipment) {
                        $equipment->release($qty);
                    }
                }
            }

            $facilityRequest->addHistory('cancelled', 'Request cancelled by requester ' . $user->name, $user->id);
            $facilityRequest->delete();

            DB::commit();

            return redirect()->route('requestor.index')->with('success', 'Request cancelled successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors('Unable to cancel the request at this time.');
        }
    }

    public function custodianVerify($facilityRequest, Request $request)
    {
        $facilityRequest = $this->resolveFacilityRequest($facilityRequest);

        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user && $user->isCustodian(), 403);
        $this->authorize('approve', $facilityRequest);

        DB::beginTransaction();

        try {
            $conflictMessage = $this->checkHybridResources($facilityRequest);
            if ($conflictMessage) {
                DB::rollBack();
                return redirect()->back()->withErrors($conflictMessage);
            }

            if ($facilityRequest->histories()->where('action', 'custodian_endorsed')->where('user_id', $user->id)->exists()) {
                DB::rollBack();
                return redirect()->back()->with('info', 'Your endorsement has already been recorded.');
            }

            if ($facilityRequest->status === 'approved' || $facilityRequest->status === 'rejected') {
                DB::rollBack();
                return redirect()->back()->with('info', 'This request is no longer awaiting custodial verification.');
            }

            if ($user->isCustodianVenue()) {
                $facilityRequest->venue_status = 'approved';
                $facilityRequest->addHistory('custodian_endorsed', 'Venue request verified and endorsed by ' . $user->name, $user->id);
            } elseif ($user->isCustodianEquipment()) {
                $assigned = $facilityRequest->getAssignedEquipmentForCustodian($user->id);
                if (empty($assigned)) {
                    DB::rollBack();
                    return redirect()->back()->withErrors('You are not assigned to any equipment in this request, so you cannot endorse it yet.');
                }

                $statuses = $facilityRequest->equipment_custodian_statuses ?? [];
                $statuses[$user->id] = 'approved';
                $facilityRequest->equipment_custodian_statuses = $statuses;
                $facilityRequest->save();
                $facilityRequest->recomputeEquipmentStatus();
                $facilityRequest->addHistory('custodian_endorsed', 'Equipment request verified and endorsed by ' . $user->name, $user->id);

                DB::commit();
                return redirect()->back()->with('success', 'Equipment endorsement recorded and request forwarded to Administrator.');
            }

            $facilityRequest->save();
            $this->notifyRequestorForStatusChange($facilityRequest, $user->isCustodianVenue() ? 'venue_approved' : 'equipment_approved', $request->input('notes', ''));
            DB::commit();

            return redirect()->back()->with('success', 'Request verified and forwarded to Administrator.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Custodian verification failed for request ' . $facilityRequest->id . ': ' . $e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withErrors('Unable to verify request at this time: ' . $e->getMessage());
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
            $this->notifyRequestorForStatusChange($facilityRequest, 'revision_requested', $notes);

            DB::commit();
            return redirect()->back()->with('success', 'Revision requested; requester has been notified.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors('Unable to request revision at this time.');
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
            $conflictMessage = $this->checkHybridResources($facilityRequest);
            if ($conflictMessage) {
                DB::rollBack();
                return redirect()->back()->withErrors($conflictMessage);
            }

            if ($facilityRequest->status === 'approved' && ($facilityRequest->approved_by_id || $facilityRequest->approved_by)) {
                DB::rollBack();
                return redirect()->route('supply-office.index')->with('info', 'This request is already approved.');
            }

            $facilityRequest->status = 'approved';
            $facilityRequest->approved_by_id = $user->id;
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
            
            $facilityRequest->addHistory('final_approved', 'Final approval granted by ' . $user->name, $user->id);
            $this->notifyRequestorForStatusChange($facilityRequest, 'approved', $request->input('notes', ''));

            DB::commit();
            return redirect()->route('supply-office.index')->with('success', 'Final approval granted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Final approval failed for request ' . $facilityRequest->id . ': ' . $e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withErrors('Unable to finalize approval: ' . $e->getMessage());
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

            $facilityRequest->status = 'rejected';
            
            if (!$facilityRequest->save()) {
                throw new \Exception('Failed to save decline status.');
            }
            
            $facilityRequest->addHistory('final_rejected', 'Final decline issued by ' . $user->name, $user->id);
            $this->notifyRequestorForStatusChange($facilityRequest, 'rejected', $request->input('notes', ''));

            DB::commit();
            return redirect()->route('supply-office.index')->with('success', 'Request declined successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Decline request failed for request ' . $facilityRequest->id . ': ' . $e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withErrors('Unable to decline the request: ' . $e->getMessage());
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

    private function notifyRequestorForStatusChange(FacilityRequest $facilityRequest, string $status, ?string $notes = null): void
    {
        $requester = $facilityRequest->requester()->first();
        if (! $requester) {
            return;
        }

        try {
            $requester->notify(new RequestStatusChanged($facilityRequest, $status, $notes ?? ''));
        } catch (\Throwable $e) {
            Log::warning('Request status notification failed after workflow update.', [
                'facility_request_id' => $facilityRequest->id,
                'status' => $status,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function checkHybridResources(FacilityRequest $facilityRequest): ?string
    {
        $venueConflict = $this->detectVenueConflict($facilityRequest);
        if ($venueConflict) {
            return $venueConflict;
        }

        $equipmentMessage = $this->detectEquipmentShortage($facilityRequest);
        if ($equipmentMessage) {
            return $equipmentMessage;
        }

        return null;
    }

    private function detectVenueConflict(FacilityRequest $facilityRequest): ?string
    {
        $requestedVenueNames = $facilityRequest->getVenueNames();
        if (empty($requestedVenueNames)) {
            return null;
        }

        $requestedStart = $facilityRequest->getRequestedStartDateTime();
        $requestedEnd = $facilityRequest->getRequestedEndDateTime();

        $conflictingRequests = FacilityRequest::where('id', '!=', $facilityRequest->id)
            ->where('status', 'approved')
            ->where('venue_status', 'approved')
            ->where(function ($query) use ($requestedVenueNames) {
                foreach ($requestedVenueNames as $venueName) {
                    $query->orWhere(fn ($subQuery) => $subQuery->matchesVenue($venueName));
                }
            })
            ->get();

        foreach ($conflictingRequests as $conflict) {
            if ($conflict->overlapsRequest($facilityRequest)) {
                return 'Venue booking conflict detected with request ' . $conflict->control_number . '. Please resolve scheduling overlap before approving.';
            }
        }

        return null;
    }

    private function detectEquipmentShortage(FacilityRequest $facilityRequest): ?string
    {
        $requests = $this->getRequestedEquipmentQuantities($facilityRequest);
        if (empty($requests)) {
            return null;
        }

        foreach ($requests as $itemName => $qty) {
            $equipment = Equipment::whereRaw('LOWER(name) = ?', [strtolower($itemName)])->first();
            if (! $equipment) {
                return 'Requested equipment "' . $itemName . '" is not available in inventory.';
            }

            $reserved = $this->calculateReservedEquipmentQuantity($itemName, $facilityRequest->id);
            $available = max(0, $equipment->quantity - $reserved);

            if ($qty > $available) {
                return 'Not enough stock for "' . $itemName . '". Only ' . $available . ' remaining for this date range.';
            }
        }

        return null;
    }

    private function getRequestedEquipmentQuantities(FacilityRequest $facilityRequest): array
    {
        $quantities = $facilityRequest->getEquipmentQuantities();
        if (!empty($quantities)) {
            return $quantities;
        }

        if (!empty($facilityRequest->getEquipmentItems())) {
            return array_count_values($facilityRequest->getEquipmentItems());
        }

        return [];
    }

    private function calculateReservedEquipmentQuantity(string $itemName, int $excludeRequestId = null): int
    {
        $approvedRequests = FacilityRequest::where('status', 'approved')
            ->where('id', '!=', $excludeRequestId)
            ->where(fn ($query) => $query->matchesEquipment($itemName))
            ->where(function ($query) {
                $query->where('equipment_returned_status', '!=', 'returned')
                      ->orWhereNull('equipment_returned_status');
            })
            ->get();

        $total = 0;
        foreach ($approvedRequests as $request) {
            $quantities = $request->getEquipmentQuantities();
            if (!empty($quantities)) {
                $total += (int) ($quantities[$itemName] ?? 0);
            } elseif (!empty($request->getEquipmentItems())) {
                $total += in_array($itemName, $request->getEquipmentItems(), true) ? 1 : 0;
            }
        }

        return $total;
    }

    private function parseRequestDateTime($date, $time)
    {
        $dateString = $date instanceof \Carbon\Carbon ? $date->format('Y-m-d') : $date;
        $timeString = $time ?: '00:00';

        return \Carbon\Carbon::parse($dateString . ' ' . $timeString);
    }
}
