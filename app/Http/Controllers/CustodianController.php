<?php
namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Concerns\ManagesAccountSettings;
use Illuminate\Support\Facades\Log;

class CustodianController extends Controller
{
    use ManagesAccountSettings;

    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $custodianType = $user->custodianType()
            ?? ($user->venues()->exists() ? 'venue' : ($user->equipmentItems()->exists() ? 'equipment' : 'venue'));

        $allRequests = $this->getRequestsForCustodian($custodianType, $user->id);

        if ($custodianType === 'equipment') {
            // ✅ Set per-custodian status on each request for blade use
            $allRequests->each(function ($r) use ($user) {
                $r->custodian_status = $r->getCustodianEquipmentStatus($user->id);
            });
        }

        $stats = [
            'total' => $allRequests->count(),
            'pending' => $custodianType === 'equipment'
                ? $allRequests->where('custodian_status', 'pending')->count()
                : $allRequests->where($custodianType . '_status', 'pending')->count(),
            'approved' => $custodianType === 'equipment'
                ? $allRequests->where('custodian_status', 'approved')->count()
                : $allRequests->where($custodianType . '_status', 'approved')->count(),
        ];

        // Separate recent/upcoming requests from old/completed requests
        $today = now()->toDateString();
        $upcomingRequests = $allRequests->filter(function ($request) use ($today) {
            $schedule = $request->reservationSchedule;
            $start = $schedule ? $schedule->start_datetime : $request->start_date;
            return $start && $start->toDateString() >= $today;
        });
        $pastRequests = $allRequests->filter(function ($request) use ($today) {
            $schedule = $request->reservationSchedule;
            $start = $schedule ? $schedule->start_datetime : $request->start_date;
            return $start && $start->toDateString() < $today;
        });

        $filter = $request->get('filter', 'all');

        if ($custodianType === 'equipment') {
            $requests = $filter === 'all'
                ? $allRequests
                : $allRequests->filter(fn($r) => $r->custodian_status === $filter);
        } else {
            $statusField = $custodianType . '_status';
            $requests    = $filter === 'all'
                ? $allRequests
                : $allRequests->filter(fn($r) => $r->$statusField === $filter);
        }

        $reviewRequest = $request->has('review')
            ? FacilityRequest::find($request->review)
            : null;

        return view('custodian.index', [
            'user'               => $user,
            'custodianType'      => $custodianType,
            'requests'           => $requests,
            'allRequests'        => $allRequests,
            'upcomingRequests'   => $upcomingRequests,
            'pastRequests'       => $pastRequests,
            'filter'             => $filter,
            'reviewRequest'      => $reviewRequest,
            'stats'              => $stats,
        ]);
    }

    public function settings(Request $request)
    {
        $user = Auth::user();

        return view('custodian.settings', [
            'user' => $user,
            'custodianType' => $user->custodianType() ?? 'venue',
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:255'],
        ]);

        $user->fill($validated);
        $user->save();

        return redirect()->route('custodian.settings')->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = Auth::user();
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return redirect()->route('custodian.settings')->with('success', 'Password updated successfully.');
    }

    public function updateNotificationPreferences(Request $request)
    {
        return $this->saveNotificationPreferences($request, 'custodian.settings');
    }

    public function updateSignature(Request $request)
    {
        return $this->saveSignature($request, 'custodian.settings');
    }

    public function update(Request $request)
    {
        /** @var \App\Models\User $user */
        $user          = Auth::user();
        $custodianType = $user->custodianType();

        $validated = $request->validate([
            'id'     => 'required|integer|exists:facility_requests,id',
            'action' => 'required|in:approve,reject,return',
            'notes'  => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $fr = FacilityRequest::whereKey($validated['id'])->lockForUpdate()->first();
            if (! $fr) {
                DB::rollBack();
                return redirect()->back()->withErrors(['id' => 'Request not found.']);
            }

            // ─── RETURN ACTION ───────────────────────────────────────────────
            if ($validated['action'] === 'return') {
                $this->authorize('returnEquipment', $fr);

                if ($custodianType !== 'equipment') {
                    DB::rollBack();
                    return back()->withErrors(['error' => 'Only equipment custodians can process returns.']);
                }

                $custodianEquipment = $fr->equipmentForCustodian($user->id);
                if (empty($custodianEquipment)) {
                    DB::rollBack();
                    return back()->withErrors(['error' => 'You are not assigned to any equipment in this request.']);
                }

                $returnEquipmentPayload = $request->input('equipment', []);
                $damagePayload = $request->input('damaged_quantity', []);
                $missingPayload = $request->input('missing_quantity', []);
                $damageRemarks = $request->input('damage_remarks', []);
                $missingRemarks = $request->input('missing_remarks', []);

                $fr->markEquipmentReturned(
                    $user->id,
                    $returnEquipmentPayload,
                    $validated['notes'] ?? null,
                    $damagePayload,
                    $missingPayload,
                    $damageRemarks,
                    $missingRemarks
                );

                DB::commit();

                $frId = $fr->id;
                $frControlNumber = $fr->control_number;
                $frRequestedById = $fr->requested_by_id;
                $frVenue = $fr->venue ?? [];

                $equipmentCustodianIds = $fr->getAssignedEquipmentCustodianIds();
                $venueCustodianIds = \App\Models\Venue::whereIn('name', $frVenue)->pluck('custodian_id')->filter()->unique()->toArray();
                $custodianIds = array_values(array_unique(array_merge($equipmentCustodianIds, $venueCustodianIds)));

                \App\Events\EquipmentReturned::dispatch($frId, $frControlNumber, $user->name, $frRequestedById, $custodianIds);

                try {
                    $refreshedFr = FacilityRequest::find($frId);
                    if ($refreshedFr) {
                        $notifiedUsers = collect();
                        $requester = \App\Models\User::find($frRequestedById);
                        if ($requester) {
                            $notifiedUsers->push($requester);
                        }

                        foreach (array_unique(array_filter(array_merge($equipmentCustodianIds, $venueCustodianIds))) as $custodianId) {
                            $custodianUser = \App\Models\User::find($custodianId);
                            if ($custodianUser) {
                                $notifiedUsers->push($custodianUser);
                            }
                        }

                        foreach ($notifiedUsers->unique('id')->values() as $targetUser) {
                            $targetUser->notify(new \App\Notifications\RequestStatusChanged(
                                $refreshedFr,
                                'equipment_returned',
                                $validated['notes'] ?? 'Equipment return recorded and inventory adjusted.'
                            ));
                        }
                    }
                } catch (\Throwable $notificationError) {
                    Log::warning('Equipment return notification failed for request ' . $frId . ': ' . $notificationError->getMessage());
                }

                return redirect()->route('custodian.index')
                                 ->with('success', 'Equipment return recorded successfully.');
            }

            $this->authorize($validated['action'] === 'approve' ? 'approve' : 'reject', $fr);

            // ─── APPROVE / REJECT ACTION ─────────────────────────────────────
            $statusField   = $custodianType . '_status';
            $notesField    = $custodianType . '_notes';
            $statusValue   = $validated['action'] === 'approve' ? 'approved' : 'rejected';

            if ($custodianType === 'equipment') {

                // ✅ Guard: prevent acting if this custodian already responded
                $currentStatus = $fr->getCustodianEquipmentStatus($user->id);
                if ($currentStatus !== 'pending') {
                    DB::commit();
                    return redirect()->route('custodian.index')
                                     ->with('info', 'You have already responded to this request.');
                }

                // ✅ Guard: check custodian has assigned equipment in this request
                $myEquipment = $fr->getAssignedEquipmentForCustodian($user->id);
                if (empty($myEquipment)) {
                    DB::commit();
                    return redirect()->route('custodian.index')
                                     ->with('error', 'You have no assigned equipment in this request.');
                }

                $wasEquipmentPreviouslyApproved = $fr->equipment_status === 'approved';

                // Set this custodian's individual status
                $fr->setCustodianEquipmentStatus($user->id, $statusValue);
                $fr->recomputeEquipmentStatus();
                $fr->refresh();

                if ($statusValue === 'approved') {
                    $fr->recordApprovalSignature('equipment', $user);
                    $fr->save();
                }

                // ✅ Deduct quantity ONLY when ALL custodians have approved (full approval)
                if (!$wasEquipmentPreviouslyApproved && $fr->equipment_status === 'approved') {
                    $quantities = $fr->getEquipmentQuantities();
                    if (empty($quantities) && !empty($fr->getEquipmentItems())) {
                        $quantities = array_fill_keys($fr->getEquipmentItems(), 1);
                    }

                    foreach ($quantities as $itemName => $qty) {
                        $eq = \App\Models\Equipment::whereRaw('LOWER(name) = ?', [strtolower($itemName)])->lockForUpdate()->first();
                        if ($eq) {
                            if ($eq->quantity_available < $qty) {
                                DB::rollBack();
                                return back()->withErrors(['equipment' => "Insufficient inventory for '{$itemName}'. Please refresh availability before approving."]);
                            }
                            $eq->reserve((int) $qty);
                        }
                    }
                }

                $fr->update([
                    $notesField => $validated['notes'] ?? '',
                ]);

                // ✅ Track if equipment status transitioned to approved
                $equipmentStatusChanged = !$wasEquipmentPreviouslyApproved && $fr->equipment_status === 'approved';

            } else {
                // Venue custodian — simple single approval
                $originalVenueStatus = $fr->{$statusField};
                
                if ($originalVenueStatus === $statusValue) {
                    DB::commit();
                    return redirect()->route('custodian.index')
                        ->with('info', 'This request already has the selected ' . $custodianType . ' status.');
                }

                $fr->update([
                    $statusField => $statusValue,
                    $notesField  => $validated['notes'] ?? '',
                ]);

                if ($statusValue === 'approved') {
                    $fr->recordApprovalSignature('venue', $user);
                    $fr->save();
                }
            }

            $historyAction = $statusField . '_' . $statusValue;
            if ($fr->histories()->where('action', $historyAction)->where('user_id', $user->id)->exists()) {
                DB::commit();
                return redirect()->route('custodian.index')
                    ->with('info', 'This decision has already been recorded.');
            }

            $fr->addHistory(
                $historyAction,
                'Custodian ' . $user->name . ' ' . $statusValue . ' the request',
                $user->id
            );

            DB::commit();

            // Fire Laravel event for broadcasting
            $eventClass = $statusValue === 'approved' ? \App\Events\RequestApproved::class : \App\Events\RequestRejected::class;
            $equipmentCustodianIds = $fr->getAssignedEquipmentCustodianIds();
            $venueCustodianIds = \App\Models\Venue::whereIn('name', $fr->venue ?? [])->pluck('custodian_id')->filter()->unique()->toArray();
            $custodianIds = array_values(array_unique(array_merge($equipmentCustodianIds, $venueCustodianIds)));
            $eventClass::dispatch($fr->id, $fr->control_number, $custodianType, $user->name, $fr->requested_by_id, $custodianIds);

            // ✅ Only notify if relevant status actually changed
            $shouldNotify = false;
            if ($custodianType === 'equipment' && isset($equipmentStatusChanged) && $equipmentStatusChanged) {
                $shouldNotify = true;
            } elseif ($custodianType === 'venue' && $statusValue === 'approved') {
                $shouldNotify = true;
            }

            // ✅ Don't send notifications at custodian stage - only at final approval
            // Notifications will be consolidated and sent when supply office approves

            $label = $statusValue === 'approved' ? 'approved' : 'rejected';
            return redirect()->route('custodian.index')
                             ->with('success', ucfirst($custodianType) . " request {$label} successfully.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Custodian update failed for request ' . ($validated['id'] ?? 'unknown'), ['exception' => $e]);
            return redirect()->back()->withErrors('Unable to process the request at this time.');
        }
        return redirect()->route('custodian.index')
                         ->with('success', ucfirst($custodianType) . " request {$label} successfully.");
    }

    private function getRequestsForCustodian(string $type, int $custodianId)
    {
        if ($type === 'venue') {
            $venueNames = Venue::where('custodian_id', $custodianId)->pluck('name');
            if ($venueNames->isEmpty()) {
                return collect([]);
            }

            return FacilityRequest::with('requester')->where(function ($query) use ($venueNames) {
                foreach ($venueNames as $name) {
                    $query->orWhere(fn ($subQuery) => $subQuery->matchesVenue($name));
                }
            })
            ->orderByDesc('created_at')
            ->get();
        }

        if ($type === 'equipment') {
            $equipmentNames = Equipment::where(function ($query) use ($custodianId) {
                $query->where('custodian_id', $custodianId)
                    ->orWhereJsonContains('authorized_custodian_ids', (string) $custodianId);
            })->pluck('name');

            if ($equipmentNames->isEmpty()) {
                return collect([]);
            }

            return FacilityRequest::with('requester')->where(function ($query) use ($equipmentNames) {
                foreach ($equipmentNames as $name) {
                    $query->orWhere(fn ($subQuery) => $subQuery->matchesEquipment($name));
                }
            })
            ->orderByDesc('created_at')
            ->get();
        }

        // Fallback for generic custodians: show requests for any of their assigned venues or equipment.
        $venueNames = Venue::where('custodian_id', $custodianId)->pluck('name');
        $equipmentNames = Equipment::where('custodian_id', $custodianId)->pluck('name');
        if ($venueNames->isEmpty() && $equipmentNames->isEmpty()) {
            return collect([]);
        }

        return FacilityRequest::with('requester')->where(function ($query) use ($venueNames, $equipmentNames) {
                foreach ($venueNames as $name) {
                    $query->orWhere(fn ($subQuery) => $subQuery->matchesVenue($name));
                }
                foreach ($equipmentNames as $name) {
                    $query->orWhere(fn ($subQuery) => $subQuery->matchesEquipment($name));
                }
            })
            ->orderByDesc('created_at')
            ->get();
    }

    public function returnEquipment(Request $request, $id)
    {
        $facilityRequest = \App\Models\FacilityRequest::findOrFail($id);
        $user = auth()->user();
        $this->authorize('returnEquipment', $facilityRequest);
        abort_unless(
            $facilityRequest->status === 'approved' && $facilityRequest->equipment_status === 'approved',
            403,
            'Equipment can only be returned for approved requests.'
        );

        // Validate input
        $validated = $request->validate([
            'equipment' => 'required|array',
            'equipment.*' => 'nullable|integer|min:0',
            'damaged_quantity' => 'nullable|array',
            'damaged_quantity.*' => 'nullable|integer|min:0',
            'missing_quantity' => 'nullable|array',
            'missing_quantity.*' => 'nullable|integer|min:0',
            'damage_remarks' => 'nullable|array',
            'missing_remarks' => 'nullable|array',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $facilityRequest->markEquipmentReturned(
                $user->id,
                $validated['equipment'],
                $validated['notes'] ?? null,
                $validated['damaged_quantity'] ?? [],
                $validated['missing_quantity'] ?? [],
                $validated['damage_remarks'] ?? [],
                $validated['missing_remarks'] ?? []
            );

            return back()->with('success', 'Equipment returned successfully.');
        } catch (\Exception $e) {
            Log::error('Equipment return failed for request ' . $facilityRequest->id . ': ' . $e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Unable to return equipment at this time.');
        }
    }

    /**
     * Display venue management page for Venue Custodians.
     * 
     * Shows only venues assigned to this custodian.
     * Allows add, edit, enable/disable operations on own venues only.
     */
    public function venueManagement()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Load only venues assigned to this custodian
        $venues = \App\Models\Venue::where('custodian_id', $user->id)
            ->orderBy('name')
            ->get();

        return view('custodian.venue', [
            'user' => $user,
            'venues' => $venues,
        ]);
    }

    /**
     * Display equipment management page for Equipment Custodians.
     * 
     * Shows only equipment assigned to this custodian.
     * Allows add, edit, enable/disable operations on own equipment only.
     */
    public function equipmentManagement()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Load only equipment assigned to this custodian
        $equipment = \App\Models\Equipment::where('custodian_id', $user->id)
            ->orderBy('name')
            ->get();

        return view('custodian.equipment', [
            'user' => $user,
            'equipment' => $equipment,
        ]);
    }

    public function assignments()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $custodianType = $user->custodianType();

        $venues = [];
        $equipment = [];

        if ($custodianType === 'venue' || $custodianType === null) {
            $venues = \App\Models\Venue::where('custodian_id', $user->id)->get();
        }

        if ($custodianType === 'equipment' || $custodianType === null) {
            $equipment = \App\Models\Equipment::where('custodian_id', $user->id)->get();
        }

        return view('custodian.assignments', [
            'user' => $user,
            'custodianType' => $custodianType,
            'venues' => $venues,
            'equipment' => $equipment,
        ]);
    }

    public function storeVenue(Request $request)
    {
        $user = Auth::user();
        // Custodians cannot create venues (spec section 6)
        abort(403, 'Custodians cannot create venues. Venues are created and assigned by administrators.');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]);
        Venue::create($validated + ['custodian_id' => $user->id, 'is_active' => true]);

        return redirect()->route('custodian.venue')->with('success', 'Venue added successfully.');
    }

    public function updateVenue(Request $request, Venue $venue)
    {
        $user = Auth::user();
        abort_unless($user->isCustodianVenue() && $venue->custodian_id === $user->id, 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]);
        $venue->update($validated);

        return redirect()->route('custodian.venue')->with('success', 'Venue updated successfully.');
    }

    public function toggleVenue(Venue $venue)
    {
        $user = Auth::user();
        abort_unless($user->isCustodianVenue() && $venue->custodian_id === $user->id, 403);
        
        $wasActive = $venue->is_active;
        $venue->update(['is_active' => ! $venue->is_active]);

        // Notify affected users (requestors with pending/approved requests using this venue)
        try {
            $affectedRequestors = \App\Models\FacilityRequest::where(function ($query) use ($venue) {
                $query->orWhere(fn ($subQuery) => $subQuery->matchesVenue($venue->name));
            })
            ->whereIn('status', ['pending', 'approved'])
            ->distinct()
            ->pluck('requested_by_id')
            ->filter()
            ->unique();

            foreach ($affectedRequestors as $requestorId) {
                $requestor = \App\Models\User::find($requestorId);
                if ($requestor) {
                    $requestor->notify(new \App\Notifications\ResourceStatusChanged(
                        $venue,
                        $venue->is_active ? 'enabled' : 'disabled',
                        'venue',
                        $user->name
                    ));
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to notify users of venue status change: ' . $e->getMessage());
        }

        return redirect()->route('custodian.venue')->with('success', 'Venue availability updated.');
    }

    public function storeEquipment(Request $request)
    {
        $user = Auth::user();
        // Custodians cannot create equipment (spec section 12)
        abort(403, 'Custodians cannot create equipment. Equipment is created and assigned by administrators.');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'quantity' => ['required', 'integer', 'min:1'],
            'quantity_available' => ['nullable', 'integer', 'min:0'],
        ]);
        $validated['quantity_available'] = min($validated['quantity'], $validated['quantity_available'] ?? $validated['quantity']);
        Equipment::create($validated + ['custodian_id' => $user->id, 'is_active' => true]);

        return redirect()->route('custodian.equipment')->with('success', 'Equipment added successfully.');
    }

    public function updateEquipment(Request $request, Equipment $equipment)
    {
        $user = Auth::user();
        abort_unless($user->isCustodianEquipment() && $equipment->custodian_id === $user->id, 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'quantity' => ['required', 'integer', 'min:1'],
            'quantity_available' => ['required', 'integer', 'min:0'],
        ]);
        $validated['quantity_available'] = min($validated['quantity'], $validated['quantity_available']);
        $equipment->update($validated);

        return redirect()->route('custodian.equipment')->with('success', 'Equipment updated successfully.');
    }

    public function toggleEquipment(Equipment $equipment)
    {
        $user = Auth::user();
        abort_unless($user->isCustodianEquipment() && $equipment->custodian_id === $user->id, 403);
        
        $equipment->update(['is_active' => ! $equipment->is_active]);

        // Notify affected users (requestors with pending/approved requests using this equipment)
        try {
            $affectedRequestors = \App\Models\FacilityRequest::where(function ($query) use ($equipment) {
                $query->orWhere(fn ($subQuery) => $subQuery->matchesEquipment($equipment->name));
            })
            ->whereIn('status', ['pending', 'approved'])
            ->distinct()
            ->pluck('requested_by_id')
            ->filter()
            ->unique();

            foreach ($affectedRequestors as $requestorId) {
                $requestor = \App\Models\User::find($requestorId);
                if ($requestor) {
                    $requestor->notify(new \App\Notifications\ResourceStatusChanged(
                        $equipment,
                        $equipment->is_active ? 'enabled' : 'disabled',
                        'equipment',
                        $user->name
                    ));
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to notify users of equipment status change: ' . $e->getMessage());
        }

        return redirect()->route('custodian.equipment')->with('success', 'Equipment availability updated.');
    }

    /**
     * Report an equipment issue (damage, loss, malfunction).
     * Equipment Custodian can report problems with assigned equipment.
     * Report is stored in audit logs for Admin review.
     */
    public function reportEquipmentIssue(Request $request, Equipment $equipment)
    {
        $user = Auth::user();
        
        // Authorization: verify equipment custodian role and ownership
        abort_unless(
            $user->isCustodianEquipment() && $equipment->custodian_id === $user->id,
            403
        );

        // Validate report submission
        $validated = $request->validate([
            'issue_type' => ['required', 'in:damaged,lost,non_functional,other'],
            'quantity_affected' => ['required', 'integer', 'min:1', 'max:' . $equipment->quantity],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        // Store the report in audit logs
        \App\Models\AuditLog::create([
            'actor_id' => $user->id,
            'action' => 'equipment_issue_reported',
            'details' => "Equipment '{$equipment->name}' issue reported by Custodian {$user->name}",
            'new_values' => [
                'equipment_id' => $equipment->id,
                'equipment_name' => $equipment->name,
                'issue_type' => $validated['issue_type'],
                'quantity_affected' => $validated['quantity_affected'],
                'description' => $validated['description'] ?? null,
                'custodian_id' => $user->id,
                'custodian_name' => $user->name,
            ],
        ]);

        // Send notification to admin/supply office (using existing notification system)
        try {
            $adminUsers = \App\Models\User::whereIn('role', ['admin', 'supply-office'])
                ->where('is_active', true)
                ->get();
            
            foreach ($adminUsers as $admin) {
                $admin->notify(new \App\Notifications\EquipmentIssueReported(
                    $equipment,
                    $validated['issue_type'],
                    $validated['quantity_affected'],
                    $validated['description'] ?? '',
                    $user->name
                ));
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to notify admin of equipment issue: ' . $e->getMessage());
            // Continue even if notification fails
        }

        return redirect()->route('custodian.equipment')
                       ->with('success', "Equipment issue reported successfully. Administrators have been notified.");
    }

    /**
     * Handle equipment return from custodian
     * Validates quantity returned, condition, and records in audit log
     * Sends notifications to admin users
     */
    public function submitEquipmentReturn(Request $request, Equipment $equipment)
    {
        $user = Auth::user();
        
        // Verify authorization: user must be equipment custodian AND equipment must be assigned to user
        abort_unless($user->isCustodianEquipment() && $equipment->custodian_id === $user->id, 403);

        // Validate return data
        $validated = $request->validate([
            'quantity_returned' => ['required', 'integer', 'min:1', 'max:' . ($equipment->quantity - $equipment->quantity_available)],
            'condition' => ['required', 'in:good,acceptable,poor'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        // Update equipment quantity available
        $old_quantity_available = $equipment->quantity_available;
        $equipment->quantity_available += $validated['quantity_returned'];
        $equipment->save();

        // Record return in audit log
        \App\Models\AuditLog::create([
            'actor_id' => $user->id,
            'action' => 'equipment_returned',
            'details' => "Equipment '{$equipment->name}' returned by Custodian {$user->name}",
            'new_values' => [
                'equipment_id' => $equipment->id,
                'equipment_name' => $equipment->name,
                'quantity_returned' => $validated['quantity_returned'],
                'condition' => $validated['condition'],
                'remarks' => $validated['remarks'] ?? null,
                'quantity_available_before' => $old_quantity_available,
                'quantity_available_after' => $equipment->quantity_available,
            ],
        ]);

        // Send notifications to admin/supply office users
        $adminUsers = \App\Models\User::whereIn('role', ['admin', 'supply-office'])->get();
        foreach ($adminUsers as $admin) {
            $admin->notify(new \App\Notifications\EquipmentReturned(
                $equipment,
                $user,
                $validated['quantity_returned'],
                $validated['condition'],
                $validated['remarks'] ?? null
            ));
        }

        return redirect()->route('custodian.equipment')
                       ->with('success', "Equipment returned successfully. Quantity available updated.");
    }
}

