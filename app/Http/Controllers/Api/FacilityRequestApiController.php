<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FacilityRequest;
use App\Models\Equipment;
use App\Models\Venue;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FacilityRequestApiController extends Controller
{
    public function __construct(private readonly AvailabilityService $availabilityService)
    {
    }

    // No middleware needed for API controller
    // Authentication is handled per-route

    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Require authentication - avoid returning unfiltered data when no user present
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        
        $query = FacilityRequest::with(['user', 'histories']);

        // Filter based on user role
        if ($user->isRequestee()) {
            $query->where('requested_by_id', $user->id);
        } elseif ($user->isCustodian()) {
            if ($user->isCustodianVenue()) {
                $query->where(function ($q) use ($user) {
                    foreach ($user->venues()->pluck('name') as $venueName) {
                        $q->orWhere(fn ($subQuery) => $subQuery->matchesVenue($venueName));
                    }
                });
            } elseif ($user->isCustodianEquipment()) {
                $query->where(function ($q) use ($user) {
                    foreach (Equipment::where('custodian_id', $user->id)->pluck('name') as $equipmentName) {
                        $q->orWhere(fn ($subQuery) => $subQuery->matchesEquipment($equipmentName));
                    }
                    $q->orWhere(function ($pendingQuery) {
                        $pendingQuery->where('equipment_status', 'pending')
                            ->orWhere('equipment_status', 'approved');
                    });
                });
            }
        }
        // Admin sees all

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($requests);
    }

    public function store(Request $request)
    {
        $this->authorize('create', FacilityRequest::class);
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

        $validated = $request->validate([
            'reservation_duration' => ['nullable', 'in:specific_time,whole_day,whole-day,whole day'],
            'name_of_activity' => 'required|string|max:200',
            'expected_participants' => 'required|integer|min:1',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'venue' => 'nullable|array',
            'equipment' => 'nullable|array',
            'equipment_quantities' => 'nullable|array',
            'other_venue' => 'nullable|string|max:200',
            'department' => 'required|string|max:100',
            'priority' => 'nullable|in:regular,institutional',
            'is_emergency' => 'nullable|boolean',
        ]);

        $user = Auth::user();

        // Check venue capacity
        if (!empty($validated['venue'])) {
            foreach ($validated['venue'] as $venueName) {
                $venueRecord = Venue::where('name', $venueName)->first();
                $participants = (int) $validated['expected_participants'];

                if ($venueRecord && $venueRecord->capacity) {
                    $maxCapacity = (int) $venueRecord->capacity;
                } else {
                    $defaultCapacities = [
                        'Conference Hall & Interaction Center (CHIC)' => 150,
                        'Gymnasium' => 500,
                        'Balay Alumni' => 200,
                        'Covered Court' => 300,
                        'Oval Grounds' => 1000,
                        'Volleyball Court' => 100,
                    ];
                    $maxCapacity = $defaultCapacities[$venueName] ?? null;
                }

                if ($maxCapacity !== null && $participants > $maxCapacity) {
                    return response()->json([
                        'success' => false,
                        'error' => "{$venueName} has a maximum capacity of {$maxCapacity} people, but you're expecting {$participants} participants."
                    ], 422);
                }
            }
        }

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
        $validated['end_date'] = $scheduleRange['end']->toDateString();

        $requestedStart = null;
        $requestedEnd = null;

        if (!empty($validated['venue']) || !empty($validated['equipment'])) {
            $requestedStart = Carbon::parse($validated['start_date'] . ' ' . $validated['start_time']);
            $requestedEnd = Carbon::parse(($validated['end_date'] ?? $validated['start_date']) . ' ' . $validated['end_time']);
            if ($requestedEnd->lte($requestedStart)) {
                $requestedEnd->addDay();
            }
        }

        // Check equipment availability
        $quantities = [];
        if (!empty($validated['equipment'])) {
            foreach ($validated['equipment'] as $item) {
                $qty = (int) ($validated['equipment_quantities'][$item] ?? 1);
                $quantities[$item] = $qty;

                $eq = Equipment::whereRaw('LOWER(name) = ?', [strtolower($item)])->first();

                if (!$eq) {
                    return response()->json([
                        'success' => false,
                        'error' => "Equipment '{$item}' not found."
                    ], 422);
                }

                $equipmentAvailability = $this->availabilityService->checkEquipmentAvailability($item, $qty, $requestedStart ?? null, $requestedEnd ?? null);

                if (!$equipmentAvailability['available']) {
                    return response()->json([
                        'success' => false,
                        'error' => $equipmentAvailability['message'] ?? "Sorry, only {$equipmentAvailability['available_qty']} unit(s) of '{$item}' available."
                    ], 422);
                }
            }
        }

        // Check venue conflicts
        $isUrgentRequest = !empty($validated['is_emergency']) && (bool) $validated['is_emergency'];
        if (!empty($validated['venue']) && !$isUrgentRequest) {
            $venueAvailability = $this->availabilityService->checkVenueAvailability($validated['venue'][0] ?? '', $requestedStart, $requestedEnd);
            if (!$venueAvailability['available']) {
                return response()->json([
                    'success' => false,
                    'error' => $venueAvailability['message'] ?? 'Scheduling conflict detected with selected venues and dates.'
                ], 422);
            }
        }

        DB::beginTransaction();

        try {
            $fr = FacilityRequest::create([
                'control_number' => FacilityRequest::generateControlNumber(),
                'date_requested' => now()->toDateString(),
                'department' => $validated['department'],
                'name_of_activity' => $validated['name_of_activity'],
                'expected_participants' => $validated['expected_participants'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? $validated['start_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'venue' => $validated['venue'] ?? [],
                'equipment' => $validated['equipment'] ?? [],
                'equipment_quantities' => $quantities,
                'other_venue' => $validated['other_venue'] ?? null,
                'requested_by_id' => $user->id,
                'status' => 'pending',
                'venue_status' => 'pending',
                'equipment_status' => 'pending',
                'priority' => $validated['priority'] ?? 'regular',
                'is_emergency' => $validated['is_emergency'] ?? false,
            ]);

            $fr->addHistory('submitted', 'Request submitted via API by ' . $user->name, $user->id);

            DB::commit();

            // Determine custodians (equipment + venue custodians) and fire event for broadcasting
            $equipmentCustodianIds = $fr->getAssignedEquipmentCustodianIds();

            $venueCustodianIds = \App\Models\Venue::whereIn('name', $fr->venue ?? [])->
                pluck('custodian_id')
                ->filter()
                ->unique()
                ->toArray();

            $custodianIds = array_values(array_unique(array_merge($equipmentCustodianIds, $venueCustodianIds)));

            \App\Events\RequestCreated::dispatch($fr->id, $fr->control_number, $user->name, $fr->requested_by_id, $custodianIds);

            return response()->json([
                'success' => true,
                'request' => $fr,
                'message' => 'Facility request created successfully.'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FacilityRequestApiController@store failed: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to create request.'
            ], 500);
        }
    }

    public function show(FacilityRequest $facilityRequest)
    {
        $this->authorize('view', $facilityRequest);

        return response()->json($facilityRequest->load(['user', 'histories']));
    }

    public function update(Request $request, FacilityRequest $facilityRequest)
    {
        $this->authorize('update', $facilityRequest);

        // Implementation for updating requests
        // Similar validation as store but for updates

        return response()->json([
            'success' => true,
            'message' => 'Request updated successfully.'
        ]);
    }

    public function destroy(FacilityRequest $facilityRequest)
    {
        $this->authorize('delete', $facilityRequest);

        $user = Auth::user();

        DB::beginTransaction();

        try {
            $facilityRequest->addHistory('cancelled', 'Request cancelled by ' . $user->name, $user->id);
            $facilityRequest->delete();

            DB::commit();

            // Determine custodians for this request
            $equipmentCustodianIds = $facilityRequest->getAssignedEquipmentCustodianIds();
            $venueCustodianIds = \App\Models\Venue::whereIn('name', $facilityRequest->venue ?? [])->pluck('custodian_id')->filter()->unique()->toArray();
            $custodianIds = array_values(array_unique(array_merge($equipmentCustodianIds, $venueCustodianIds)));

            \App\Events\RequestCancelled::dispatch($facilityRequest->id, $facilityRequest->control_number, $user->name, $facilityRequest->requested_by_id, $custodianIds);

            return response()->json([
                'success' => true,
                'message' => 'Request cancelled successfully.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Failed to cancel request.'
            ], 500);
        }
    }

    public function approve(Request $request, FacilityRequest $facilityRequest)
    {
        $this->authorize('approve', $facilityRequest);

        $user = Auth::user();
        $approvalType = $request->validate([
            'type' => ['nullable', 'in:venue,equipment'],
        ])['type'] ?? 'venue';

        DB::beginTransaction();

        try {
            $facilityRequest = FacilityRequest::whereKey($facilityRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $statusColumn = $approvalType . '_status';
            if ($facilityRequest->status !== 'pending' || $facilityRequest->{$statusColumn} !== 'pending') {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'error' => ucfirst($approvalType) . ' has already been processed for this request.',
                ], 409);
            }

            if ($approvalType === 'venue') {
                $facilityRequest->venue_status = 'approved';
            } elseif ($approvalType === 'equipment') {
                $facilityRequest->equipment_status = 'approved';

                // Reserve equipment quantities
                $quantities = $facilityRequest->getEquipmentQuantities();
                if (!empty($quantities)) {
                    foreach ($quantities as $itemName => $qty) {
                        $eq = Equipment::whereRaw('LOWER(name) = ?', [strtolower($itemName)])
                            ->lockForUpdate()
                            ->first();
                        if (!$eq || $eq->quantity_available < $qty) {
                            DB::rollBack();

                            return response()->json([
                                'success' => false,
                                'error' => "Insufficient inventory for '{$itemName}'. Please refresh availability before approving.",
                            ], 422);
                        }

                        $eq->decrement('quantity_available', $qty);
                    }
                }
            }

            // Generate an HMAC approval signature for auditability
            $payload = json_encode(['request_id' => $facilityRequest->id ?? null, 'approver_id' => $user->id, 'type' => $approvalType, 'time' => now()->toISOString()]);
            $signature = hash_hmac('sha256', $payload, config('app.key'));
            if ($approvalType === 'venue') {
                $facilityRequest->venue_approval_signature = $signature;
            } else {
                $facilityRequest->equipment_approval_signature = $signature;
            }

            $meta = $facilityRequest->approval_signature_meta ?? [];
            $meta[$approvalType] = $payload;
            $facilityRequest->approval_signature_meta = $meta;

            // Check if fully approved
            if ($facilityRequest->venue_status === 'approved' && $facilityRequest->equipment_status === 'approved') {
                $facilityRequest->status = 'approved';
                $facilityRequest->approved_by_id = $user->id;
                $facilityRequest->approved_by = $user->name;
                $facilityRequest->approved_date = now();
            }

            $facilityRequest->save();
            $facilityRequest->addHistory('approved', ucfirst($approvalType) . ' approved by ' . $user->name, $user->id);

            DB::commit();

            // Determine custodians for this request
            $equipmentCustodianIds = $facilityRequest->getAssignedEquipmentCustodianIds();
            $venueCustodianIds = \App\Models\Venue::whereIn('name', $facilityRequest->venue ?? [])->pluck('custodian_id')->filter()->unique()->toArray();
            $custodianIds = array_values(array_unique(array_merge($equipmentCustodianIds, $venueCustodianIds)));

            \App\Events\RequestApproved::dispatch($facilityRequest->id, $facilityRequest->control_number, $approvalType, $user->name, $facilityRequest->requested_by_id, $custodianIds);

            return response()->json([
                'success' => true,
                'message' => ucfirst($approvalType) . ' approved successfully.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Failed to approve request.'
            ], 500);
        }
    }

    public function reject(Request $request, FacilityRequest $facilityRequest)
    {
        $this->authorize('reject', $facilityRequest);

        $user = Auth::user();
        $reason = $request->input('reason', '');
        $rejectionType = $request->input('type', 'venue');

        DB::beginTransaction();

        try {
            if ($rejectionType === 'venue') {
                $facilityRequest->venue_status = 'rejected';
                $facilityRequest->venue_notes = $reason;
            } elseif ($rejectionType === 'equipment') {
                $facilityRequest->equipment_status = 'rejected';
                $facilityRequest->equipment_notes = $reason;
            }

            $facilityRequest->status = 'rejected';
            $facilityRequest->save();

            $facilityRequest->addHistory('rejected', ucfirst($rejectionType) . ' rejected by ' . $user->name . ': ' . $reason, $user->id);

            DB::commit();

            // Determine custodians for this request
            $equipmentCustodianIds = $facilityRequest->getAssignedEquipmentCustodianIds();
            $venueCustodianIds = \App\Models\Venue::whereIn('name', $facilityRequest->venue ?? [])->pluck('custodian_id')->filter()->unique()->toArray();
            $custodianIds = array_values(array_unique(array_merge($equipmentCustodianIds, $venueCustodianIds)));

            \App\Events\RequestRejected::dispatch($facilityRequest->id, $facilityRequest->control_number, $rejectionType, $reason, $user->name, $facilityRequest->requested_by_id, $custodianIds);

            return response()->json([
                'success' => true,
                'message' => ucfirst($rejectionType) . ' rejected successfully.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Failed to reject request.'
            ], 500);
        }
    }

    public function cancel(Request $request, FacilityRequest $facilityRequest)
    {
        return $this->destroy($facilityRequest);
    }

    public function returnEquipment(Request $request, FacilityRequest $facilityRequest)
    {
        $this->authorize('returnEquipment', $facilityRequest);

        $user = Auth::user();
        $validated = $request->validate([
            'returned_items' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $returnedItems = $validated['returned_items'] ?? [];

        DB::beginTransaction();

        try {
            $facilityRequest = FacilityRequest::whereKey($facilityRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($facilityRequest->equipment_returned_status === 'returned') {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'error' => 'Equipment has already been returned for this request.',
                ], 409);
            }

            if ($facilityRequest->status !== 'approved' || $facilityRequest->equipment_status !== 'approved') {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'error' => 'Equipment can only be returned for approved requests.',
                ], 422);
            }

            $facilityRequest->equipment_returned_status = 'returned';
            $facilityRequest->equipment_returned_by = $user->id;
            $facilityRequest->equipment_returned_date = now();
            $facilityRequest->equipment_return_notes = $validated['notes'] ?? '';
            $facilityRequest->equipment_returned_items = $returnedItems;
            $facilityRequest->save();

            // Return equipment to inventory
            $quantities = $facilityRequest->getEquipmentQuantities();
            if (!empty($quantities)) {
                foreach ($quantities as $itemName => $qty) {
                    $eq = Equipment::whereRaw('LOWER(name) = ?', [strtolower($itemName)])
                        ->lockForUpdate()
                        ->first();
                    if ($eq) {
                        $eq->quantity_available = min($eq->quantity, $eq->quantity_available + $qty);
                        $eq->save();
                    }
                }
            }

            $facilityRequest->addHistory('equipment_returned', 'Equipment returned by ' . $user->name, $user->id);

            DB::commit();

            // Determine custodians for this request
            $equipmentCustodianIds = $facilityRequest->getAssignedEquipmentCustodianIds();
            $venueCustodianIds = \App\Models\Venue::whereIn('name', $facilityRequest->venue ?? [])->pluck('custodian_id')->filter()->unique()->toArray();
            $custodianIds = array_values(array_unique(array_merge($equipmentCustodianIds, $venueCustodianIds)));

            \App\Events\EquipmentReturned::dispatch($facilityRequest->id, $facilityRequest->control_number, $user->name, $facilityRequest->requested_by_id, $custodianIds);

            return response()->json([
                'success' => true,
                'message' => 'Equipment returned successfully.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Failed to return equipment.'
            ], 500);
        }
    }

    public function equipmentAvailability(Request $request)
    {
        $date = $request->input('date');
        $venue = $request->input('venue', []);

        $equipment = Equipment::all();

        $result = [];
        foreach ($equipment as $eq) {
            $outstandingQuantity = 0;

            if ($date) {
                                // Include approved and pending (non-rejected) requests when computing outstanding quantities for date
                                $approvedRequests = FacilityRequest::where(function($q) {
                                                $q->where('status', 'approved')
                                                    ->orWhere(function($q2) {
                                                            $q2->where('status', 'pending')
                                                                 ->where('venue_status', '!=', 'rejected')
                                                                 ->where('equipment_status', '!=', 'rejected');
                                                    });
                                        })
                                        ->where(function($q) {
                                                $q->where('equipment_returned_status', '!=', 'returned')
                                                    ->where('equipment_returned_status', '!=', 'overdue');
                                        })
                                        ->where('start_date', '<=', $date)
                                        ->where('end_date', '>=', $date)
                                        ->get();

                foreach ($approvedRequests as $req) {
                    $reqQuantities = $req->getEquipmentQuantities();
                    if (!empty($reqQuantities) && isset($reqQuantities[$eq->name])) {
                        $outstandingQuantity += (int) $reqQuantities[$eq->name];
                    } elseif (!empty($req->getEquipmentItems())) {
                        if (in_array($eq->name, $req->getEquipmentItems(), true)) {
                            $outstandingQuantity += 1;
                        }
                    }
                }
            }

            $available = max(0, $eq->quantity - $outstandingQuantity);
            $result[] = [
                'name' => $eq->name,
                'total' => $eq->quantity,
                'available' => $available,
                'custodian_id' => $eq->custodian_id,
            ];
        }

        return response()->json($result);
    }

    public function venueAvailability(Request $request)
    {
        $date = $request->input('date');
        $time = $request->input('time');

        if (!$date || !$time) {
            return response()->json(['error' => 'Date and time required'], 400);
        }

        $venues = Venue::all();
        $result = [];

        foreach ($venues as $venue) {
            $conflicts = FacilityRequest::query()
                ->where(fn ($query) => $query->matchesVenue($venue->name))
                ->where(function($query) {
                    $query->where(function($approvedQuery) {
                        $approvedQuery->where('status', 'approved')
                                      ->where('equipment_returned_status', '!=', 'returned');
                    })
                    ->orWhere(function($pendingQuery) {
                        $pendingQuery->where('status', 'pending')
                                     ->where('venue_status', '!=', 'rejected')
                                     ->where('equipment_status', '!=', 'rejected');
                    });
                })
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->where('start_time', '<=', $time)
                ->where('end_time', '>=', $time)
                ->exists();

            $result[] = [
                'name' => $venue->name,
                'available' => !$conflicts,
                'capacity' => $venue->capacity,
                'custodian_id' => $venue->custodian_id,
            ];
        }

        return response()->json($result);
    }
}
