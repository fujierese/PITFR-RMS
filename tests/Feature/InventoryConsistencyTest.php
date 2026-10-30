<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InventoryConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        Notification::fake();
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
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
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

    public function test_equipment_custodian_can_record_partial_then_complete_return_from_request_details(): void
    {
        Carbon::setTestNow(Carbon::parse('2030-05-04 12:00:00'));

        try {
            $custodian = User::factory()->create(['role' => 'custodian-equipment']);
            $requester = User::factory()->create(['role' => 'requestor']);
            $equipment = Equipment::factory()->create([
                'name' => 'Return Workflow Projector',
                'custodian_id' => $custodian->id,
                'quantity' => 5,
                'quantity_available' => 3,
            ]);
            $request = FacilityRequest::create([
                'control_number' => 'TEST-RETURN-FORM',
                'date_requested' => '2030-04-20',
                'department' => 'IT',
                'name_of_activity' => 'Return form workflow',
                'expected_participants' => 10,
                'start_date' => '2030-05-01',
                'end_date' => '2030-05-01',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'venue' => [],
                'equipment' => ['Return Workflow Projector'],
                'equipment_quantities' => ['Return Workflow Projector' => 2],
                'requested_by_id' => $requester->id,
                'status' => 'approved',
                'venue_status' => 'approved',
                'equipment_status' => 'approved',
            ]);

            $this->actingAs($custodian)
                ->get(route('request.show', $request))
                ->assertOk()
                ->assertSee('Record equipment return')
                ->assertSee('equipment[Return Workflow Projector]', false);

            $this->post(route('custodian.return', $request), [
                'equipment' => ['Return Workflow Projector' => 1],
            ])->assertRedirect();

            $this->assertSame('partial', $request->fresh()->equipment_returned_status);
            $this->assertSame(['Return Workflow Projector' => 1], $request->fresh()->getEquipmentReturnOutstandingQuantities());
            $this->get(route('request.show', $request))
                ->assertOk()
                ->assertSee('1 of 2 still unaccounted for');

            $this->post(route('custodian.return', $request), [
                'equipment' => ['Return Workflow Projector' => 2],
            ])->assertRedirect();

            $this->assertSame('fulfilled', $request->fresh()->equipment_returned_status);
            $this->assertSame([], $request->fresh()->getEquipmentReturnOutstandingQuantities());
            $this->assertSame(5, $equipment->fresh()->quantity_available);
            $this->get(route('history.index'))
                ->assertOk()
                ->assertSee($request->control_number);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_equipment_return_is_blocked_until_the_latest_per_venue_schedule_ends(): void
    {
        Carbon::setTestNow(Carbon::parse('2030-05-01 12:00:00'));

        try {
            $custodian = User::factory()->create(['role' => 'custodian-equipment']);
            $requester = User::factory()->create(['role' => 'requestor']);
            Equipment::factory()->create([
                'name' => 'Schedule Return Projector',
                'custodian_id' => $custodian->id,
                'quantity' => 5,
                'quantity_available' => 4,
            ]);
            $request = FacilityRequest::create([
                'control_number' => 'TEST-RETURN-LATEST-VENUE',
                'date_requested' => '2030-04-20',
                'department' => 'IT',
                'name_of_activity' => 'Latest venue return check',
                'expected_participants' => 10,
                'start_date' => '2030-04-29',
                'end_date' => '2030-04-29',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'venue' => ['CHIC'],
                'equipment' => ['Schedule Return Projector'],
                'equipment_quantities' => ['Schedule Return Projector' => 1],
                'requested_by_id' => $requester->id,
                'status' => 'approved',
                'venue_status' => 'approved',
                'equipment_status' => 'approved',
            ]);
            $request->requestVenues()->update([
                'start_date' => '2030-05-02',
                'end_date' => '2030-05-02',
                'start_time' => '09:00',
                'end_time' => '17:00',
            ]);

            $this->actingAs($custodian)
                ->post(route('custodian.return', $request), [
                    'equipment' => ['Schedule Return Projector' => 1],
                ])
                ->assertRedirect()
                ->assertSessionHasErrors();

            $this->assertNull($request->fresh()->equipment_returned_status);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_unassigned_equipment_custodian_cannot_view_or_record_another_custodians_return(): void
    {
        $assignedCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        $otherCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        $requester = User::factory()->create(['role' => 'requestor']);
        Equipment::factory()->create([
            'name' => 'Assigned-only Projector',
            'custodian_id' => $assignedCustodian->id,
            'quantity' => 5,
            'quantity_available' => 4,
        ]);
        $request = FacilityRequest::create([
            'control_number' => 'TEST-RETURN-UNASSIGNED',
            'date_requested' => now()->subDays(10)->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Unassigned custodian check',
            'expected_participants' => 10,
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->subDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => [],
            'equipment' => ['Assigned-only Projector'],
            'equipment_quantities' => ['Assigned-only Projector' => 1],
            'requested_by_id' => $requester->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $this->actingAs($otherCustodian)
            ->get(route('request.show', $request))
            ->assertForbidden();

        $this->post(route('custodian.return', $request), [
            'equipment' => ['Assigned-only Projector' => 1],
        ])->assertForbidden();

        $this->assertNull($request->fresh()->equipment_returned_status);
    }

    public function test_custodian_queue_keeps_ended_reservations_visible_until_returns_are_accounted_for(): void
    {
        Carbon::setTestNow(Carbon::parse('2030-05-04 12:00:00'));

        try {
            $custodian = User::factory()->create(['role' => 'custodian-equipment']);
            Equipment::factory()->create([
                'name' => 'Pending Return Queue Speaker',
                'custodian_id' => $custodian->id,
                'quantity' => 5,
                'quantity_available' => 4,
            ]);
            $request = FacilityRequest::create([
                'control_number' => 'TEST-RETURN-QUEUE',
                'date_requested' => '2030-04-20',
                'department' => 'IT',
                'name_of_activity' => 'Pending return queue item',
                'expected_participants' => 10,
                'start_date' => '2030-05-01',
                'end_date' => '2030-05-01',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'venue' => [],
                'equipment' => ['Pending Return Queue Speaker'],
                'equipment_quantities' => ['Pending Return Queue Speaker' => 1],
                'requested_by_id' => User::factory()->create(['role' => 'requestor'])->id,
                'status' => 'approved',
                'venue_status' => 'approved',
                'equipment_status' => 'approved',
            ]);

            $this->actingAs($custodian)
                ->get(route('custodian.index'))
                ->assertOk()
                ->assertSee($request->control_number)
                ->assertSee('Review');
        } finally {
            Carbon::setTestNow();
        }
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
        $eventDate = now()->addDay()->toDateString();
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
            'start_date' => $eventDate,
            'end_date' => $eventDate,
            'start_time' => '09:00',
            'end_time' => '17:00',
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
