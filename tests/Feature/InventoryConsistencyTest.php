<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    public function test_requestor_cancellation_does_not_overflow_quantity()
    {
        $requester = User::factory()->create(['role' => 'requestor']);
        $custodian = User::factory()->create(['role' => 'custodian-equipment']);

        Equipment::factory()->create([
            'name' => 'Sound System',
            'custodian_id' => $custodian->id,
            'quantity' => 5,
            'quantity_available' => 5,
        ]);

        $request = FacilityRequest::create([
            'control_number' => 'TEST-CANCEL-001',
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Cancel Test',
            'expected_participants' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => [],
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 1],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'approved',
        ]);

        $this->actingAs($requester)
            ->post(route('request.cancel', $request))
            ->assertRedirect();

        $eq = Equipment::where('name', 'Sound System')->first();
        $this->assertSame(5, $eq->fresh()->quantity_available);
    }

    public function test_custodian_return_caps_quantity()
    {
        $custodian = User::factory()->create(['role' => 'custodian-equipment']);
        $requester = User::factory()->create(['role' => 'requestor']);

        Equipment::factory()->create([
            'name' => 'Sound System',
            'custodian_id' => $custodian->id,
            'quantity' => 5,
            'quantity_available' => 4,
        ]);

        // Request is approved and has already ended (so returns are allowed)
        $request = FacilityRequest::create([
            'control_number' => 'TEST-RETURN-001',
            'date_requested' => now()->subDays(10)->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Return Test',
            'expected_participants' => 10,
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->subDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => [],
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 2],
            'requested_by_id' => $requester->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        // Custodian posts a return that would otherwise push available > quantity
        $this->actingAs($custodian)
            ->post(route('custodian.return', $request->id), [
                'equipment' => ['Sound System' => 2],
                'notes' => 'Returning items',
            ])
            ->assertRedirect();

        $eq = Equipment::where('name', 'Sound System')->first();
        $this->assertSame(5, $eq->fresh()->quantity_available);
    }

    public function test_custodian_approval_reserves_quantity()
    {
        $requester = User::factory()->create(['role' => 'requestor']);
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $equipmentCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        $admin = User::factory()->create(['role' => 'admin']);

        Venue::create(['name' => 'Gymnasium', 'custodian_id' => $venueCustodian->id]);

        Equipment::factory()->create([
            'name' => 'Projector',
            'custodian_id' => $equipmentCustodian->id,
            'quantity' => 5,
            'quantity_available' => 5,
        ]);

        $facilityRequest = FacilityRequest::create([
            'control_number' => 'TEST-APPROVE-001',
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Reserve Test',
            'expected_participants' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => ['Gymnasium'],
            'equipment' => ['Projector'],
            'equipment_quantities' => ['Projector' => 2],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);

        // Venue custodian approves first
        $this->actingAs($venueCustodian)
            ->post(route('custodian.update'), [
                'id' => $facilityRequest->id,
                'action' => 'approve',
                'notes' => 'Venue ok',
            ])->assertRedirect();

        // Equipment custodian approves and should cause reservation decrement
        $this->actingAs($equipmentCustodian)
            ->post(route('custodian.update'), [
                'id' => $facilityRequest->id,
                'action' => 'approve',
                'notes' => 'Equipment ok',
            ])->assertRedirect();

        $eq = Equipment::where('name', 'Projector')->first();
        $this->assertSame(3, $eq->fresh()->quantity_available);
    }
}
