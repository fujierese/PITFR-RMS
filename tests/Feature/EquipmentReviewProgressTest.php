<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Additional Enhancements, workflow progress: once the equipment custodian approves,
 * Equipment Review shows a checkmark, the request is listed as approved for the
 * custodian, and its status says it awaits final approval instead of "Pending".
 * Equipment approval is still not the final approval.
 */
class EquipmentReviewProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;
    private User $venueCustodian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $this->venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create(['name' => 'Conference Hall & Interaction Center (CHIC)', 'capacity' => 200, 'custodian_id' => $this->venueCustodian->id, 'is_active' => true]);
    }

    private function makeRequest(array $items): FacilityRequest
    {
        $date = now()->addDays(6)->toDateString();
        $request = FacilityRequest::create([
            'control_number' => 'FER-PRG-' . random_int(1000, 9999),
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Progress test',
            'expected_participants' => 40,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => array_keys($items),
            'equipment_quantities' => $items,
            'requested_by_id' => $this->requester->id,
            'status' => 'pending',
            'venue_status' => 'approved',
            'supply_office_review_status' => 'confirmed',
            'equipment_status' => 'pending',
            'equipment_custodian_statuses' => [],
        ]);
        $request->syncRelationalItems();

        return $request->fresh();
    }

    public function test_included_equipment_owned_by_a_venue_custodian_does_not_block_equipment_review(): void
    {
        // Sound System comes with CHIC and is owned by the venue custodian, who cannot
        // review equipment. It used to keep Equipment Review pending forever.
        Equipment::create(['name' => 'Sound System', 'custodian_id' => $this->venueCustodian->id, 'quantity' => 2, 'quantity_available' => 2]);
        $custodian = User::factory()->create(['role' => 'custodian-equipment', 'name' => 'Projector Keeper']);
        Equipment::create(['name' => 'Projector', 'custodian_id' => $custodian->id, 'quantity' => 3, 'quantity_available' => 3]);
        $request = $this->makeRequest(['Projector' => 1, 'Sound System' => 1]);

        $this->actingAs($custodian)->post(route('request.custodian.verify', $request))->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame('approved', $request->equipment_status);
        $this->assertSame('pending', $request->status, 'Equipment approval is not the final approval.');

        $page = $this->actingAs($this->requester)->get(route('request.show', $request))->assertOk();
        $page->assertSee('data-step="equipment" data-step-status="approved"', false)
            ->assertSee('Approved by Projector Keeper')
            ->assertSee('Awaiting Final Approval');

        $this->actingAs($custodian)->get(route('custodian.index', ['filter' => 'approved']))
            ->assertOk()
            ->assertSee($request->control_number)
            ->assertSee('Awaiting Final Approval');
    }

    public function test_an_alternate_custodian_sees_the_request_as_approved(): void
    {
        $primary = User::factory()->create(['role' => 'custodian-equipment', 'name' => 'Primary Keeper']);
        $alternate = User::factory()->create(['role' => 'custodian-equipment', 'name' => 'Alternate Keeper']);
        Equipment::create(['name' => 'Industrial Fan', 'custodian_id' => $primary->id, 'authorized_custodian_ids' => [$alternate->id], 'quantity' => 4, 'quantity_available' => 4]);
        $request = $this->makeRequest(['Industrial Fan' => 2]);

        $this->actingAs($primary)->post(route('request.custodian.verify', $request))->assertSessionHasNoErrors();
        $request->refresh();
        $this->assertSame('approved', $request->effectiveCustodianEquipmentStatus($alternate->id));

        $this->actingAs($alternate)->get(route('custodian.index', ['filter' => 'approved']))
            ->assertOk()->assertSee($request->control_number);
        $this->actingAs($alternate)->get(route('custodian.index', ['filter' => 'pending']))
            ->assertOk()->assertDontSee($request->control_number);
        $this->actingAs($alternate)->get(route('request.show', $request))
            ->assertOk()->assertDontSee('Endorse Equipment');
    }

    public function test_equipment_review_waits_for_every_custodian_and_says_who(): void
    {
        $first = User::factory()->create(['role' => 'custodian-equipment', 'name' => 'First Keeper']);
        $second = User::factory()->create(['role' => 'custodian-equipment', 'name' => 'Second Keeper']);
        Equipment::create(['name' => 'Projector', 'custodian_id' => $first->id, 'quantity' => 3, 'quantity_available' => 3]);
        Equipment::create(['name' => 'Speaker', 'custodian_id' => $second->id, 'quantity' => 3, 'quantity_available' => 3]);
        $request = $this->makeRequest(['Projector' => 1, 'Speaker' => 1]);

        $this->actingAs($first)->post(route('request.custodian.verify', $request))->assertSessionHasNoErrors();
        $this->assertSame('pending', $request->fresh()->equipment_status);
        $this->actingAs($this->requester)->get(route('request.show', $request))
            ->assertSee('Approved by First Keeper · Waiting for Second Keeper');

        $this->actingAs($second)->post(route('request.custodian.verify', $request))->assertSessionHasNoErrors();
        $this->assertSame('approved', $request->fresh()->equipment_status);
    }
}
