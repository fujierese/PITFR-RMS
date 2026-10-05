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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        
        $query = FacilityRequest::query();

        // Filter based on user role
        if ($user->isRequestee()) {
            $query->where('requested_by_id', $user->id);
        } elseif ($user->isCustodian()) {
            if ($user->isCustodianVenue()) {
                $venueNames = $user->venues()->pluck('name');
                $query->where(function ($q) use ($venueNames) {
                    foreach ($venueNames as $venueName) {
                        $q->orWhere(fn ($subQuery) => $subQuery->matchesVenue($venueName));
                    }
                    if ($venueNames->isEmpty()) {
                        $q->whereRaw('1 = 0');
                    }
                });
            } elseif ($user->isCustodianEquipment()) {
                $equipmentNames = Equipment::where(function ($equipmentQuery) use ($user): void {
                    $equipmentQuery->where('custodian_id', $user->id)
                        ->orWhereJsonContains('authorized_custodian_ids', (string) $user->id)
                        ->orWhereJsonContains('authorized_custodian_ids', $user->id);
                })->pluck('name');
                $query->where(function ($q) use ($equipmentNames) {
                    foreach ($equipmentNames as $equipmentName) {
                        $q->orWhere(fn ($subQuery) => $subQuery->matchesEquipment($equipmentName));
                    }
                    if ($equipmentNames->isEmpty()) {
                        $q->whereRaw('1 = 0');
                    }
                });
            }
        } elseif (! $user->isAdmin()) {
            abort(403, 'This account cannot view facility requests.');
        }

        $requests = $query->orderByDesc('created_at')->paginate(20);
        if ($user->isCustodian()) {
            $assignedEquipment = $user->isCustodianEquipment()
                ? Equipment::where(function ($equipmentQuery) use ($user): void {
                    $equipmentQuery->where('custodian_id', $user->id)
                        ->orWhereJsonContains('authorized_custodian_ids', (string) $user->id)
                        ->orWhereJsonContains('authorized_custodian_ids', $user->id);
                })->pluck('name')->map(fn (string $name): string => mb_strtolower($name))->all()
                : [];

            $requests->through(function (FacilityRequest $facilityRequest) use ($user, $assignedEquipment): array {
                $equipmentQuantities = array_filter(
                    $facilityRequest->getEquipmentQuantities(),
                    fn ($quantity, $name): bool => in_array(mb_strtolower((string) $name), $assignedEquipment, true),
                    ARRAY_FILTER_USE_BOTH
                );

                return [
                    'id' => $facilityRequest->id,
                    'control_number' => $facilityRequest->control_number,
                    'name_of_activity' => $facilityRequest->name_of_activity,
                    'start_date' => $facilityRequest->start_date?->toDateString(),
                    'end_date' => $facilityRequest->end_date?->toDateString(),
                    'start_time' => $facilityRequest->start_time,
                    'end_time' => $facilityRequest->end_time,
                    'venue' => $facilityRequest->getVenueNames(),
                    'equipment_quantities' => $equipmentQuantities,
                    'status' => $facilityRequest->status,
                    'venue_status' => $user->isCustodianVenue() ? $facilityRequest->venue_status : null,
                    'equipment_status' => $user->isCustodianEquipment() ? $facilityRequest->equipment_status : null,
                ];
            });
        }

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
                'end_time' => '23:59',
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
            'venue' => 'nullable|array|max:1',
            'venue.*' => [
                'required',
                'string',
                'max:255',
                Rule::exists('venues', 'name')->where('is_active', true),
            ],
            'equipment' => 'nullable|array',
            'equipment.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists('equipment', 'name')->where('is_active', true),
            ],
            'equipment_quantities' => 'nullable|array',
            'equipment_quantities.*' => ['required', 'integer', 'min:1'],
            'other_venue' => 'nullable|string|max:200',
            'department' => 'required|string|max:100',
            'is_emergency' => 'nullable|boolean',
            'emergency_justification' => 'required_if:is_emergency,1|string|max:1000',
        ]);

        if (
            ($validated['reservation_duration'] ?? 'specific_time') === 'specific_time'
            && $validated['start_time'] <= '05:00'
        ) {
            throw ValidationException::withMessages([
                'start_time' => 'Reservations cannot start between 12:00 AM and 5:00 AM. Choose a start time after 5:00 AM or use the Whole Day option.',
            ]);
        }

        $user = Auth::user();

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

        $requestedStart = $scheduleRange['start'];
        $requestedEnd = $scheduleRange['end'];
        if (! $requestedStart || ! $requestedEnd || $requestedEnd->lte($requestedStart)) {
            return response()->json([
                'success' => false,
                'error' => 'End date and time must be after the start date and time.',
            ], 422);
        }

        // Check equipment availability
        $quantities = [];
        if (!empty($validated['equipment'])) {
            foreach ($validated['equipment'] as $item) {
                $qty = (int) ($validated['equipment_quantities'][$item] ?? 1);
                $quantities[$item] = $qty;

                $eq = Equipment::whereRaw('LOWER(name) = ?', [strtolower($item)])->first();

                if (!$eq || ! $eq->is_active) {
                    return response()->json([
                        'success' => false,
                        'error' => "Equipment '{$item}' is not available for requests."
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

        $isUrgentRequest = !empty($validated['is_emergency']) && (bool) $validated['is_emergency'];
        if ($isUrgentRequest && $requestedStart->gt(now()->addHours(48))) {
            return response()->json([
                'success' => false,
                'error' => 'Emergency requests are only allowed within 48 hours of the reservation schedule.',
            ], 422);
        }

        // Urgent requests are flagged for human Supply Office review; they do not
        // automatically override conflicts at final approval.
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
            $candidate = new FacilityRequest([
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? $validated['start_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'venue' => $validated['venue'] ?? [],
                'equipment' => $validated['equipment'] ?? [],
                'equipment_quantities' => $quantities,
            ]);
            $this->availabilityService->lockResourcesForFacilityRequests($candidate);
            $availabilityMessage = $this->availabilityService->checkFacilityRequest(
                $candidate,
                null,
                $isUrgentRequest
            );
            if ($availabilityMessage) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'error' => $availabilityMessage,
                ], 422);
            }

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
                'priority' => $isUrgentRequest ? 'institutional' : 'regular',
                'requested_priority' => $isUrgentRequest ? 'institutional' : null,
                'requested_is_emergency' => $validated['is_emergency'] ?? false,
                'is_emergency' => $validated['is_emergency'] ?? false,
                'emergency_justification' => $validated['emergency_justification'] ?? null,
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

        $user = Auth::user();
        if ($user->isCustodian()) {
            $assignedVenues = $user->isCustodianVenue()
                ? $user->venues()->pluck('name')->map(fn (string $name): string => mb_strtolower($name))->all()
                : [];
            $venueNames = array_values(array_filter(
                $facilityRequest->getVenueNames(),
                fn (string $name): bool => in_array(mb_strtolower($name), $assignedVenues, true)
            ));
            $assignedEquipment = array_map(
                'mb_strtolower',
                array_keys($facilityRequest->getAssignedEquipmentForCustodian($user->id))
            );
            $equipmentQuantities = array_filter(
                $facilityRequest->getEquipmentQuantities(),
                fn ($quantity, $name): bool => in_array(mb_strtolower((string) $name), $assignedEquipment, true),
                ARRAY_FILTER_USE_BOTH
            );

            return response()->json([
                'id' => $facilityRequest->id,
                'control_number' => $facilityRequest->control_number,
                'name_of_activity' => $facilityRequest->name_of_activity,
                'start_date' => $facilityRequest->start_date?->toDateString(),
                'end_date' => $facilityRequest->end_date?->toDateString(),
                'start_time' => $facilityRequest->start_time,
                'end_time' => $facilityRequest->end_time,
                'venue' => $venueNames,
                'equipment_quantities' => $equipmentQuantities,
                'status' => $facilityRequest->status,
                'venue_status' => $user->isCustodianVenue() ? $facilityRequest->venue_status : null,
                'equipment_status' => $user->isCustodianEquipment() ? $facilityRequest->equipment_status : null,
            ]);
        }

        return response()->json($facilityRequest->load(['user', 'histories']));
    }

    public function update(Request $request, FacilityRequest $facilityRequest)
    {
        $this->authorize('update', $facilityRequest);

        return response()->json([
            'success' => false,
            'error' => 'Updating facility requests through this API is not supported. Use the requestor reschedule workflow.',
        ], 501);
    }

    public function destroy(FacilityRequest $facilityRequest)
    {
        $this->authorize('delete', $facilityRequest);

        $user = Auth::user();

        DB::beginTransaction();

        try {
            $this->availabilityService->lockResourcesForFacilityRequests($facilityRequest);
            $facilityRequest = FacilityRequest::whereKey($facilityRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($facilityRequest->status !== 'pending') {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'error' => 'Only pending requests can be cancelled.',
                ], 409);
            }

            if ($facilityRequest->equipment_status === 'approved') {
                foreach ($facilityRequest->getEquipmentQuantities() as $itemName => $quantity) {
                    $equipment = Equipment::whereRaw('LOWER(name) = ?', [mb_strtolower($itemName)])
                        ->lockForUpdate()
                        ->first();
                    if ($equipment) {
                        $equipment->quantity_available = min(
                            $equipment->quantity,
                            $equipment->quantity_available + (int) $quantity
                        );
                        $equipment->save();
                    }
                }
            }

            $facilityRequest->addHistory('cancelled', 'Request cancelled by ' . $user->name, $user->id);
            $facilityRequest->update([
                'status' => 'cancelled',
                'venue_status' => 'cancelled',
                'equipment_status' => 'cancelled',
            ]);
            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            Log::error('Facility request API cancellation failed.', [
                'facility_request_id' => $facilityRequest->id,
                'user_id' => $user->id,
                'exception' => $exception,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'The request could not be cancelled.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Request cancelled successfully.',
        ]);
    }

    public function approve(Request $request, FacilityRequest $facilityRequest)
    {
        $this->authorize('approve', $facilityRequest);

        $user = Auth::user();
        $approvalType = $request->validate([
            'type' => ['required', 'in:venue,equipment'],
        ])['type'];

        $this->authorize('approve' . ucfirst($approvalType), $facilityRequest);

        DB::beginTransaction();

        try {
            $this->availabilityService->lockResourcesForFacilityRequests($facilityRequest);
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

            $conflictMessage = $this->availabilityService->checkFacilityRequest($facilityRequest, $facilityRequest->id);
            if ($conflictMessage) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'error' => $conflictMessage,
                ], 422);
            }

            if ($approvalType === 'venue') {
                $facilityRequest->venue_status = 'approved';
                $facilityRequest->recordApprovalSignature('venue', $user);
            } elseif ($approvalType === 'equipment') {
                $facilityRequest->setCustodianEquipmentStatus($user->id, 'approved');
                $facilityRequest->recomputeEquipmentStatus();
                $facilityRequest->recordApprovalSignature('equipment', $user);

                // Reserve equipment quantities
                $quantities = $facilityRequest->getEquipmentQuantities();
                if ($facilityRequest->equipment_status === 'approved' && !empty($quantities)) {
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
        $rejectionType = $request->validate([
            'type' => ['required', 'in:venue,equipment'],
            'reason' => ['nullable', 'string'],
        ])['type'];

        $this->authorize('reject' . ucfirst($rejectionType), $facilityRequest);

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
            'returned_items.*' => ['integer', 'min:0'],
            'damaged_quantity' => ['nullable', 'array'],
            'damaged_quantity.*' => ['integer', 'min:0'],
            'missing_quantity' => ['nullable', 'array'],
            'missing_quantity.*' => ['integer', 'min:0'],
            'damage_remarks' => ['nullable', 'array'],
            'damage_remarks.*' => ['nullable', 'string', 'max:500'],
            'missing_remarks' => ['nullable', 'array'],
            'missing_remarks.*' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::beginTransaction();

        try {
            $facilityRequest = FacilityRequest::whereKey($facilityRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($facilityRequest->equipment_returned_status, ['returned', 'fulfilled'], true)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'error' => 'Equipment has already been recorded as returned for this request.',
                ], 409);
            }

            if ($facilityRequest->status !== 'approved' || $facilityRequest->equipment_status !== 'approved') {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'error' => 'Equipment can only be returned for approved requests.',
                ], 422);
            }

            $facilityRequest->markEquipmentReturned(
                $user->id,
                $validated['returned_items'] ?? [],
                $validated['notes'] ?? null,
                $validated['damaged_quantity'] ?? [],
                $validated['missing_quantity'] ?? [],
                $validated['damage_remarks'] ?? [],
                $validated['missing_remarks'] ?? []
            );

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

        } catch (\Throwable $e) {
            DB::rollBack();

            if (! $e instanceof \InvalidArgumentException) {
                Log::error('FacilityRequestApiController@returnEquipment failed.', [
                    'facility_request_id' => $facilityRequest->id,
                    'exception' => $e,
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $e instanceof \InvalidArgumentException
                    ? $e->getMessage()
                    : 'Failed to record equipment return.',
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
        }
    }

    public function equipmentAvailability(Request $request)
    {
        $date = $request->input('date');
        $venue = $request->input('venue', []);

        $equipment = Equipment::where('is_active', true)->get();

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
                    $reqQuantities = $req->getInventoryOutstandingQuantities();
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

        $venues = Venue::where('is_active', true)->get();
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
