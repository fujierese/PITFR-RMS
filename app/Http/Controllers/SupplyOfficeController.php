<?php
namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\Venue;
use App\Notifications\RequestStatusChanged;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplyOfficeController extends Controller
{
    public function index(Request $request)
    {
        $baseQuery = FacilityRequest::whereIn('status', ['pending', 'approved']);

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

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('control_number', 'like', '%' . $search . '%')
                  ->orWhere('name_of_activity', 'like', '%' . $search . '%')
                  ->orWhereHas('requester', function ($reqQuery) use ($search) {
                      $reqQuery->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        $filteredRequests = $query->orderByDesc('created_at')->get();
        $allRequests = $baseQuery->orderByDesc('created_at')->get();
        $pendingReviewQueue = FacilityRequest::with('requester')
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->where('venue_status', '!=', 'rejected')
                    ->orWhere('equipment_status', '!=', 'rejected');
            })
            ->orderByDesc('created_at')
            ->get();
        $totalCount = $allRequests->count();
        $pendingCount = $allRequests->where('status', 'pending')->count();
        $approvedCount = $allRequests->where('status', 'approved')->count();
        $finalApprovalQueue = $pendingReviewQueue;

        $venueSearch = trim((string) $request->get('venue_search', ''));
        $equipmentSearch = trim((string) $request->get('equipment_search', ''));

        $venues = Venue::with('custodian')
            ->when($venueSearch !== '', function ($query) use ($venueSearch) {
                $query->where('name', 'like', '%' . $venueSearch . '%')
                    ->orWhereHas('custodian', function ($custodianQuery) use ($venueSearch) {
                        $custodianQuery->where('name', 'like', '%' . $venueSearch . '%');
                    });
            })
            ->orderBy('name')
            ->get();

        $equipmentItems = Equipment::with('custodian')
            ->when($equipmentSearch !== '', function ($query) use ($equipmentSearch) {
                $query->where('name', 'like', '%' . $equipmentSearch . '%')
                    ->orWhere('quantity', 'like', '%' . $equipmentSearch . '%')
                    ->orWhereHas('custodian', function ($custodianQuery) use ($equipmentSearch) {
                        $custodianQuery->where('name', 'like', '%' . $equipmentSearch . '%');
                    });
            })
            ->orderBy('name')
            ->get();

        $custodians = \App\Models\User::where('role', 'custodian')->orderBy('name')->get();

        return view('supply-office.index', [
            'requests'          => $filteredRequests,
            'allRequests'       => $allRequests,
            'finalApprovalQueue' => $finalApprovalQueue,
            'pendingFinalApprovalCount' => $finalApprovalQueue->count(),
            'totalCount'        => $totalCount,
            'pendingCount'      => $pendingCount,
            'approvedCount'     => $approvedCount,
            'filter'            => $request->get('filter', 'all'),
            'priority'          => $request->get('priority', 'all'),
            'searchQuery'       => $request->get('search', ''),
            'reviewRequest'     => $request->has('review') ? FacilityRequest::find($request->review) : null,
            'venues'            => $venues,
            'equipmentItems'    => $equipmentItems,
            'custodians'        => $custodians,
            'venueSearch'       => $venueSearch,
            'equipmentSearch'   => $equipmentSearch,
            'editVenueId'       => (int) $request->get('edit_venue', 0),
            'editEquipmentId'   => (int) $request->get('edit_equipment', 0),
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
        $this->ensureAdminAccess();

        $query = $this->buildRequestListQuery($request)
            ->where('status', 'pending')
            ->where(function (Builder $query): void {
                $query->where('venue_status', 'approved')
                    ->where('equipment_status', 'approved');
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

        $baseQuery = $this->buildRequestListQuery($request)
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
                Auth::id()
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
                    Auth::user()->name,
                    $reason
                ));
            }
        }

        return redirect()->route('supply-office.requests.final-approval')->with('success', 'Request marked for rescheduling.');
    }

    public function storeVenue(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'custodian_id' => ['required', 'exists:users,id'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        Venue::create($validated);

        return redirect()->route('supply-office.index')->with('success', 'Venue created successfully.');
    }

    public function updateVenue(Request $request, Venue $venue)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'custodian_id' => ['required', 'exists:users,id'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        $venue->update($validated);

        return redirect()->route('supply-office.index')->with('success', 'Venue updated successfully.');
    }

    public function destroyVenue(Request $request, Venue $venue)
    {
        $hasActiveReservation = FacilityRequest::whereIn('status', ['pending', 'approved'])
            ->where(fn ($query) => $query->matchesVenue($venue->name))
            ->exists();

        if ($hasActiveReservation) {
            return back()->withErrors(['venue' => 'Cannot delete venue because it still has active reservations.']);
        }

        $venue->delete();

        return redirect()->route('supply-office.index')->with('success', 'Venue deleted successfully.');
    }

    public function storeEquipment(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'custodian_id' => ['required', 'exists:users,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'quantity_available' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['quantity_available'] = $validated['quantity_available'] ?? $validated['quantity'];
        Equipment::create($validated);

        return redirect()->route('supply-office.index')->with('success', 'Equipment created successfully.');
    }

    public function updateEquipment(Request $request, Equipment $equipment)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'custodian_id' => ['required', 'exists:users,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'quantity_available' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['quantity_available'] = $validated['quantity_available'] ?? $validated['quantity'];
        $equipment->update($validated);

        return redirect()->route('supply-office.index')->with('success', 'Equipment updated successfully.');
    }

    public function destroyEquipment(Request $request, Equipment $equipment)
    {
        $hasActiveReservation = FacilityRequest::whereIn('status', ['pending', 'approved'])
            ->where(fn ($query) => $query->matchesEquipment($equipment->name))
            ->exists();

        if ($hasActiveReservation) {
            return back()->withErrors(['equipment' => 'Cannot delete equipment because it still has active reservations.']);
        }

        $equipment->delete();

        return redirect()->route('supply-office.index')->with('success', 'Equipment deleted successfully.');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'id'       => 'required|integer|exists:facility_requests,id',
            'action'   => 'required|in:approve,reject',
            'notes'    => 'nullable|string',
            'priority' => 'nullable|in:regular,institutional',
        ]);

        DB::beginTransaction();

        try {
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

            $conflictingRequest = null;

            if ($validated['action'] === 'approve') {
                $isOverrideEligible = ($fr->priority === 'institutional') || (!empty($fr->is_emergency) && (bool) $fr->is_emergency);

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

        $originalStatus = $fr->status;
        
        $this->applyRequestApproval($fr, $validated['action'], $validated['notes'] ?? '', $validated['priority'] ?? null);

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
        ]);

        $urgentRequest = FacilityRequest::find($validated['urgent_request_id']);
        $conflictingRequest = FacilityRequest::find($validated['conflicting_request_id']);

        if (!$urgentRequest) {
            return redirect()->route('supply-office.index')->withErrors(['urgent_request_id' => 'The urgent request could not be found.']);
        }

        if (!$conflictingRequest) {
            return redirect()->route('supply-office.index')->withErrors(['conflicting_request_id' => 'The conflicting request could not be found.']);
        }

        if ($conflictingRequest->status !== 'approved' || $conflictingRequest->venue_status !== 'approved' || $conflictingRequest->equipment_status !== 'approved') {
            return redirect()->route('supply-office.index')->with('warning', 'The conflicting request is no longer approved, so the override could not be applied.');
        }

        $overrideReason = trim((string) ($validated['override_reason'] ?? ''));
        $actingUser = Auth::user();

        try {
            DB::transaction(function () use ($urgentRequest, $conflictingRequest, $overrideReason, $actingUser): void {
                $timestamp = now()->toDateTimeString();

                $lockedUrgentRequest = FacilityRequest::whereKey($urgentRequest->id)
                    ->lockForUpdate()
                    ->first();
                $lockedConflictingRequest = FacilityRequest::whereKey($conflictingRequest->id)
                    ->lockForUpdate()
                    ->first();

                if (!$lockedUrgentRequest || !$lockedConflictingRequest) {
                    throw new \RuntimeException('The selected requests are no longer available.');
                }

                if ($lockedConflictingRequest->status !== 'approved' || $lockedConflictingRequest->venue_status !== 'approved' || $lockedConflictingRequest->equipment_status !== 'approved') {
                    throw new \RuntimeException('The conflicting reservation was already updated before the override could be applied.');
                }

                $lockedConflictingRequest->update([
                    'status' => 'needs_reschedule',
                    'venue_status' => 'needs_reschedule',
                    'equipment_status' => 'needs_reschedule',
                    'approved_by' => null,
                    'approved_by_id' => null,
                    'approved_date' => null,
                ]);

                $lockedConflictingRequest->addHistory(
                    'needs_reschedule',
                    'Request moved to Needs Reschedule due to Priority Override. Reason: ' . $overrideReason . ' | Acting user: ' . ($actingUser?->name ?? 'System') . ' | Timestamp: ' . $timestamp,
                    $actingUser?->id
                );

                $this->applyRequestApproval($lockedUrgentRequest, 'approve', $overrideReason, $lockedUrgentRequest->priority ?? null);

                $lockedUrgentRequest->addHistory(
                    'approved',
                    'Request approved through Priority Override. Reason: ' . $overrideReason . ' | Acting user: ' . ($actingUser?->name ?? 'System') . ' | Timestamp: ' . $timestamp,
                    $actingUser?->id
                );
            });
        } catch (\Throwable $e) {
            Log::warning('Priority override aborted due to concurrent state change.', [
                'urgent_request_id' => $urgentRequest->id,
                'conflicting_request_id' => $conflictingRequest->id,
                'exception' => $e->getMessage(),
            ]);

            return redirect()->route('supply-office.index')->with('warning', 'The reservation was updated by another approval action before the override could be completed.');
        }

        try {
            $requester = $conflictingRequest->requester;
            if ($requester) {
                $urgentPriority = ($urgentRequest->priority ?? 'institutional') ? strtoupper($urgentRequest->priority ?? 'INSTITUTIONAL') : 'URGENT';
                $priorityLabel = $urgentPriority === 'INSTITUTIONAL' ? 'URGENT' : strtoupper($urgentRequest->priority ?? 'REGULAR');
                $overrideMessage = "Your reservation ({$conflictingRequest->control_number}) has been overridden due to an {$priorityLabel} institutional reservation.\n\nReason:\n" . trim((string) ($validated['override_reason'] ?? '')) . "\n\nPlease edit and reschedule your reservation.";

                DB::afterCommit(function () use ($requester, $conflictingRequest, $overrideMessage, $actingUser, $validated): void {
                    $reason = trim((string) ($validated['override_reason'] ?? '')) ?: 'calendar conflict';
                    $requester->notify(new RequestStatusChanged(
                        $conflictingRequest,
                        'needs_reschedule',
                        $overrideMessage,
                        null,
                        null,
                        [],
                        $actingUser->name,
                        $reason
                    ));
                });
            }
        } catch (\Throwable $e) {
            Log::error('Priority override notification failed for request ' . $conflictingRequest->id . ': ' . $e->getMessage(), ['exception' => $e]);
        }

        return redirect()->route('supply-office.index')->with('success', 'Priority override completed successfully.');
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
        FacilityRequest::findOrFail($request->input('id'))->delete();
        return redirect()->route('supply-office.index')->with('success', 'Request deleted successfully.');
    }

    private function ensureAdminAccess(): void
    {
        abort_unless(Auth::check() && Auth::user()?->isAdmin(), 403);
    }

    private function buildRequestListQuery(Request $request): Builder
    {
        $query = FacilityRequest::with(['requester', 'reservationSchedule']);

        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $searchTerm = '%' . mb_strtolower($search) . '%';
            $query->where(function (Builder $filterQuery) use ($searchTerm): void {
                $filterQuery->whereRaw('LOWER(control_number) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(name_of_activity) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(department) LIKE ?', [$searchTerm])
                    ->orWhereHas('requester', function (Builder $requesterQuery) use ($searchTerm): void {
                        $requesterQuery->whereRaw('LOWER(name) LIKE ?', [$searchTerm]);
                    })
                    ->orWhereHas('requestVenues', function (Builder $venueQuery) use ($searchTerm): void {
                        $venueQuery->whereRaw('LOWER(name) LIKE ?', [$searchTerm]);
                    })
                    ->orWhereHas('requestEquipment', function (Builder $equipmentQuery) use ($searchTerm): void {
                        $equipmentQuery->whereRaw('LOWER(name) LIKE ?', [$searchTerm]);
                    })
                    ->orWhere(function (Builder $legacyQuery) use ($searchTerm): void {
                        $legacyQuery->whereRaw('LOWER(venue) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(equipment) LIKE ?', [$searchTerm]);
                    });
            });
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

        return $query->orderByDesc('created_at');
    }

    public function usageReports(Request $request)
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->endOfMonth()->toDateString());

        // Equipment usage statistics
        $equipmentUsage = DB::table('facility_requests')
            ->join('reservation_schedules', 'reservation_schedules.facility_request_id', '=', 'facility_requests.id')
            ->selectRaw('JSON_UNQUOTE(JSON_EXTRACT(equipment, "$[*]")) as equipment_item, SUM(JSON_EXTRACT(equipment_quantities, CONCAT("$.", JSON_UNQUOTE(JSON_EXTRACT(equipment, "$[*]"))))) as total_used')
            ->where('facility_requests.status', 'approved')
            ->whereBetween('reservation_schedules.start_datetime', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->groupBy('equipment_item')
            ->get()
            ->map(function ($item) {
                return [
                    'equipment' => $item->equipment_item,
                    'total_used' => $item->total_used ?? 1,
                ];
            });

        // Venue usage statistics
        $venueUsage = DB::table('facility_requests')
            ->join('reservation_schedules', 'reservation_schedules.facility_request_id', '=', 'facility_requests.id')
            ->selectRaw('JSON_UNQUOTE(JSON_EXTRACT(venue, "$[*]")) as venue_item, COUNT(*) as total_bookings')
            ->where('facility_requests.status', 'approved')
            ->whereBetween('reservation_schedules.start_datetime', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->groupBy('venue_item')
            ->get()
            ->map(function ($item) {
                return [
                    'venue' => $item->venue_item,
                    'total_bookings' => $item->total_bookings,
                ];
            });

        // Department usage
        $departmentUsage = FacilityRequest::selectRaw('department, COUNT(*) as total_requests, SUM(expected_participants) as total_participants')
            ->where('status', 'approved')
            ->whereHas('reservationSchedule', function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('start_datetime', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
            })
            ->groupBy('department')
            ->get();

        // Priority distribution
        $priorityStats = FacilityRequest::selectRaw('priority, COUNT(*) as count')
            ->where('status', 'approved')
            ->whereHas('reservationSchedule', function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('start_datetime', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
            })
            ->groupBy('priority')
            ->get();

        return view('supply-office.usage-reports', [
            'equipmentUsage' => $equipmentUsage,
            'venueUsage' => $venueUsage,
            'departmentUsage' => $departmentUsage,
            'priorityStats' => $priorityStats,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
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

        $requests = $query->orderByDesc('created_at')->get();

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
                fputcsv($handle, [
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
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
