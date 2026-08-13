<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplyOfficePriorityOverrideInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    private function createVenueAndRequest(User $requester, array $attributes = []): FacilityRequest
    {
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create([
            'name' => 'Conference Hall & Interaction Center (CHIC)',
            'custodian_id' => $venueCustodian->id,
        ]);

        $request = FacilityRequest::create(array_merge([
            'control_number' => 'FER-2026-INV-' . uniqid(),
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Test Activity',
            'expected_participants' => 50,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'priority' => 'regular',
            'is_emergency' => false,
        ], $attributes));

        $request->syncRelationalItems();

        return $request;
    }

    public function test_priority_override_decrements_inventory_for_urgent_request(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);

        $equipment = Equipment::factory()->create([
            'name' => 'Projector',
            'custodian_id' => User::factory()->create(['role' => 'custodian-equipment'])->id,
            'quantity' => 5,
            'quantity_available' => 5,
        ]);

        $conflictingRequest = $this->createVenueAndRequest($requester, [
            'control_number' => 'FER-2026-INV-CONFLICT',
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'equipment' => ['Projector'],
            'equipment_quantities' => ['Projector' => 1],
        ]);

        $urgentRequest = $this->createVenueAndRequest($requester, [
            'control_number' => 'FER-2026-INV-URGENT',
            'priority' => 'institutional',
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'equipment' => ['Projector'],
            'equipment_quantities' => ['Projector' => 2],
        ]);

        $response = $this->actingAs($admin)
            ->post(route('supply-office.priority-override.submit'), [
                'urgent_request_id' => $urgentRequest->id,
                'conflicting_request_id' => $conflictingRequest->id,
                'override_reason' => 'Institutional priority override',
            ]);

        $response->assertRedirect(route('supply-office.index'));
        $response->assertSessionHas('success', 'Priority override completed successfully.');

        $urgentRequest->refresh();
        $equipment->refresh();

        $this->assertSame('approved', $urgentRequest->status);
        $this->assertSame(3, $equipment->quantity_available);
        $this->assertGreaterThanOrEqual(0, $equipment->quantity_available);
        $this->assertLessThanOrEqual($equipment->quantity, $equipment->quantity_available);
    }

    public function test_priority_override_rejects_when_inventory_is_insufficient(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);

        $equipment = Equipment::factory()->create([
            'name' => 'Projector',
            'custodian_id' => User::factory()->create(['role' => 'custodian-equipment'])->id,
            'quantity' => 3,
            'quantity_available' => 1,
        ]);

        $conflictingRequest = $this->createVenueAndRequest($requester, [
            'control_number' => 'FER-2026-INV-CONFLICT-2',
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'equipment' => ['Projector'],
            'equipment_quantities' => ['Projector' => 1],
        ]);

        $urgentRequest = $this->createVenueAndRequest($requester, [
            'control_number' => 'FER-2026-INV-URGENT-2',
            'priority' => 'institutional',
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'equipment' => ['Projector'],
            'equipment_quantities' => ['Projector' => 2],
        ]);

        $response = $this->actingAs($admin)
            ->post(route('supply-office.priority-override.submit'), [
                'urgent_request_id' => $urgentRequest->id,
                'conflicting_request_id' => $conflictingRequest->id,
                'override_reason' => 'Institutional priority override',
            ]);

        $response->assertRedirect(route('supply-office.index'));
        $response->assertSessionHas('warning');

        $urgentRequest->refresh();
        $equipment->refresh();

        $this->assertSame('pending', $urgentRequest->status);
        $this->assertSame(1, $equipment->quantity_available);
        $this->assertGreaterThanOrEqual(0, $equipment->quantity_available);
        $this->assertLessThanOrEqual($equipment->quantity, $equipment->quantity_available);
    }

    public function test_requestor_destroy_restores_inventory_without_exceeding_quantity(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $custodian = User::factory()->create(['role' => 'custodian-equipment']);

        $equipment = Equipment::factory()->create([
            'name' => 'Projector',
            'custodian_id' => $custodian->id,
            'quantity' => 5,
            'quantity_available' => 5,
        ]);

        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-INV-CANCEL',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Cancellation Restore Test',
            'expected_participants' => 10,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => [],
            'equipment' => ['Projector'],
            'equipment_quantities' => ['Projector' => 2],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'approved',
            'priority' => 'regular',
        ]);

        $response = $this->actingAs($requester)
            ->post(route('requestor.destroy'), ['id' => $request->id]);

        $response->assertRedirect();

        $equipment->refresh();
        $this->assertSame(5, $equipment->quantity_available);
        $this->assertGreaterThanOrEqual(0, $equipment->quantity_available);
        $this->assertLessThanOrEqual($equipment->quantity, $equipment->quantity_available);
    }

    public function test_mixed_equipment_status_documents_current_global_behavior(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $approvedCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        $rejectedCustodian = User::factory()->create(['role' => 'custodian-equipment']);

        Equipment::factory()->create([
            'name' => 'Projector',
            'custodian_id' => $approvedCustodian->id,
            'quantity' => 5,
            'quantity_available' => 5,
        ]);

        Equipment::factory()->create([
            'name' => 'Mic',
            'custodian_id' => $rejectedCustodian->id,
            'quantity' => 5,
            'quantity_available' => 5,
        ]);

        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-INV-MIXED',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Mixed Status Test',
            'expected_participants' => 20,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => [],
            'equipment' => ['Projector', 'Mic'],
            'equipment_quantities' => ['Projector' => 1, 'Mic' => 1],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
            'priority' => 'regular',
        ]);

        $request->equipment_custodian_statuses = [
            $approvedCustodian->id => 'approved',
            $rejectedCustodian->id => 'rejected',
        ];

        $request->recomputeEquipmentStatus();
        $request->refresh();

        $this->assertSame('pending', $request->equipment_status);
    }
}
