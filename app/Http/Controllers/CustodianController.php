<?php
namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class CustodianController extends Controller
{
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
            $fr = FacilityRequest::whereKey($validated['id'])->lockForUpdate()->firstOrFail();

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

                $fr->markEquipmentReturned($user->id, $custodianEquipment, $validated['notes'] ?? null);
                $fr->addHistory('equipment_returned', 'Equipment returned by custodian ' . $user->name, $user->id);

                DB::commit();

                // Fire Laravel event for broadcasting (include custodians)
                $equipmentCustodianIds = $fr->getAssignedEquipmentCustodianIds();
                $venueCustodianIds = \App\Models\Venue::whereIn('name', $fr->venue ?? [])->pluck('custodian_id')->filter()->unique()->toArray();
                $custodianIds = array_values(array_unique(array_merge($equipmentCustodianIds, $venueCustodianIds)));

                \App\Events\EquipmentReturned::dispatch($fr->id, $fr->control_number, $user->name, $fr->requested_by_id, $custodianIds);

                $requester = \App\Models\User::find($fr->requested_by_id);
                if ($requester) {
                    $requester->notify(new \App\Notifications\RequestStatusChanged(
                        $fr,
                        'equipment_returned',
                        $validated['notes'] ?? 'Equipment has been returned and inventory updated.'
                    ));
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
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $facilityRequest->markEquipmentReturned(
                $user->id,
                $validated['equipment'],
                $validated['notes'] ?? null
            );

            return back()->with('success', 'Equipment returned successfully.');
        } catch (\Exception $e) {
            Log::error('Equipment return failed for request ' . $facilityRequest->id . ': ' . $e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Unable to return equipment at this time.');
        }
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
}
