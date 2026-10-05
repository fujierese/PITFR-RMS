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

        $this->assertSame('cancelled', $request->fresh()->status);
        $this->actingAs($requester)
            ->get(route('requestor.index', ['tab' => 'requests']))
            ->assertOk()
            ->assertSee('TEST-CANCEL-001')
            ->assertSee('Cancelled');

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

    public function test_return_accounts_for_damage_and_missing_by_equipment_and_syncs_inventory(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian-equipment']);
        $requester = User::factory()->create(['role' => 'requestor']);
        $speaker = Equipment::factory()->create([
            'name' => 'Speaker',
            'custodian_id' => $custodian->id,
            'quantity' => 5,
            'quantity_available' => 3,
        ]);
        $projector = Equipment::factory()->create([
            'name' => 'Projector',
            'custodian_id' => $custodian->id,
            'quantity' => 5,
            'quantity_available' => 2,
        ]);
        $request = FacilityRequest::create([
            'control_number' => 'TEST-RETURN-MIXED',
            'date_requested' => now()->subDays(10)->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Mixed return test',
            'expected_participants' => 10,
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->subDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => [],
            'equipment' => ['Speaker', 'Projector'],
            'equipment_quantities' => ['Speaker' => 2, 'Projector' => 3],
            'requested_by_id' => $requester->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $this->actingAs($custodian)->post(route('custodian.update'), [
            'id' => $request->id,
            'action' => 'return',
            'equipment' => ['Speaker' => 2, 'Projector' => 2],
            'damaged_quantity' => ['Speaker' => 1, 'Projector' => 1],
            'missing_quantity' => ['Projector' => 1],
        ])->assertRedirect();

        $request->refresh();
        $this->assertSame('fulfilled', $request->equipment_returned_status);
        $this->assertSame(2, $request->equipment_return_damaged_quantity);
        $this->assertSame(1, $request->equipment_return_missing_quantity);
        $this->assertSame(['Speaker' => 1, 'Projector' => 2], $request->getInventoryOutstandingQuantities());
        $this->assertSame(4, $speaker->fresh()->quantity_available);
        $this->assertSame(3, $projector->fresh()->quantity_available);

        $this->artisan('equipment:sync-availability')->assertExitCode(0);

        $this->assertSame(4, $speaker->fresh()->quantity_available);
        $this->assertSame(3, $projector->fresh()->quantity_available);
    }

    public function test_partial_return_is_idempotent_and_only_releases_new_usable_units(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian-equipment']);
        $equipment = Equipment::factory()->create([
            'name' => 'Sound System',
            'custodian_id' => $custodian->id,
            'quantity' => 5,
            'quantity_available' => 3,
        ]);
        $request = FacilityRequest::create([
            'control_number' => 'TEST-RETURN-PARTIAL',
            'date_requested' => now()->subDays(10)->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Partial return test',
            'expected_participants' => 10,
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->subDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => [],
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 2],
            'requested_by_id' => User::factory()->create(['role' => 'requestor'])->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $payload = [
            'id' => $request->id,
            'action' => 'return',
            'equipment' => ['Sound System' => 1],
        ];
        $this->actingAs($custodian)->post(route('custodian.update'), $payload)->assertRedirect();
        $this->assertSame('partial', $request->fresh()->equipment_returned_status);
        $this->assertSame(4, $equipment->fresh()->quantity_available);

        $this->post(route('custodian.update'), $payload)->assertRedirect();
        $this->assertSame(4, $equipment->fresh()->quantity_available);

        $this->post(route('custodian.update'), [
            'id' => $request->id,
            'action' => 'return',
            'equipment' => ['Sound System' => 2],
            'damaged_quantity' => ['Sound System' => 1],
        ])->assertRedirect();

        $request->refresh();
        $this->assertSame('fulfilled', $request->equipment_returned_status);
        $this->assertSame(1, $request->equipment_return_damaged_quantity);
        $this->assertSame(4, $equipment->fresh()->quantity_available);
        $this->assertSame(['Sound System' => 1], $request->getInventoryOutstandingQuantities());
    }

    public function test_return_rejects_quantities_above_the_approved_item_count(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian-equipment']);
        $equipment = Equipment::factory()->create([
            'name' => 'Sound System',
            'custodian_id' => $custodian->id,
            'quantity' => 5,
            'quantity_available' => 3,
        ]);
        $request = FacilityRequest::create([
            'control_number' => 'TEST-RETURN-OVERFLOW',
            'date_requested' => now()->subDays(10)->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Return overflow test',
            'expected_participants' => 10,
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->subDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => [],
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 2],
            'requested_by_id' => User::factory()->create(['role' => 'requestor'])->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $this->actingAs($custodian)->post(route('custodian.return', $request->id), [
            'equipment' => ['Sound System' => 3],
        ])->assertSessionHasErrors('return');

        $this->assertNull($request->fresh()->equipment_returned_status);
        $this->assertSame(3, $equipment->fresh()->quantity_available);
    }

    public function test_return_is_rejected_before_the_scheduled_end_time(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian-equipment']);
        $equipment = Equipment::factory()->create([
            'name' => 'Sound System',
            'custodian_id' => $custodian->id,
            'quantity' => 5,
            'quantity_available' => 3,
        ]);
        $request = FacilityRequest::create([
            'control_number' => 'TEST-RETURN-EARLY',
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Early return test',
            'expected_participants' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'start_time' => now()->subHour()->format('H:i'),
            'end_time' => now()->addHour()->format('H:i'),
            'venue' => [],
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 2],
            'requested_by_id' => User::factory()->create(['role' => 'requestor'])->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $this->actingAs($custodian)->post(route('custodian.return', $request->id), [
            'equipment' => ['Sound System' => 1],
        ])->assertSessionHasErrors('return');

        $this->assertNull($request->fresh()->equipment_returned_status);
        $this->assertSame(3, $equipment->fresh()->quantity_available);
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
