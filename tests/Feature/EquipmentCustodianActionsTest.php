<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Additional Enhancements, part 1: an equipment custodian can Endorse, Decline (with a
 * reason) or Report an Issue about their own equipment, and can never reject, cancel
 * or reopen the reservation itself.
 */
class EquipmentCustodianActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $custodian;
    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->custodian = User::factory()->create(['role' => 'custodian-equipment', 'name' => 'Equip Keeper']);
        $this->requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        Venue::create(['name' => 'Covered Court', 'capacity' => 300, 'custodian_id' => User::factory()->create(['role' => 'custodian-venue'])->id, 'is_active' => true]);
        Equipment::create(['name' => 'Projector', 'custodian_id' => $this->custodian->id, 'quantity' => 5, 'quantity_available' => 5]);
    }

    private function makeRequest(array $overrides = []): FacilityRequest
    {
        $date = now()->addDays(5)->toDateString();
        $request = FacilityRequest::create(array_merge([
            'control_number' => 'FER-EQ-' . random_int(1000, 9999),
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Equipment test',
            'expected_participants' => 30,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => ['Covered Court'],
            'equipment' => ['Projector'],
            'equipment_quantities' => ['Projector' => 1],
            'requested_by_id' => $this->requester->id,
            'status' => 'pending',
            'venue_status' => 'approved',
            'supply_office_review_status' => 'confirmed',
            'equipment_status' => 'pending',
            'equipment_custodian_statuses' => [],
        ], $overrides));
        $request->syncRelationalItems();

        return $request->fresh();
    }

    public function test_custodian_can_endorse_assigned_equipment(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->custodian)->get(route('request.show', $request))
            ->assertOk()
            ->assertSee('Endorse Equipment')
            ->assertSee('Decline Equipment')
            ->assertSee('Report Equipment Issue')
            ->assertDontSee('>Reject<', false);

        $this->actingAs($this->custodian)->post(route('request.custodian.verify', $request))->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame('approved', $request->getCustodianEquipmentStatus($this->custodian->id));
        $this->assertSame('approved', $request->equipment_status);
        $this->assertSame('pending', $request->status, 'Endorsing equipment is not the final approval.');
    }

    public function test_decline_needs_a_reason_and_does_not_reject_the_reservation(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->custodian)->post(route('request.custodian.reject', $request), [])
            ->assertSessionHasErrors('decline_reason');
        $this->actingAs($this->custodian)->post(route('request.custodian.reject', $request), ['decline_reason' => 'other'])
            ->assertSessionHasErrors('notes');
        $this->assertSame('pending', $request->fresh()->getCustodianEquipmentStatus($this->custodian->id));

        $this->actingAs($this->custodian)
            ->post(route('request.custodian.reject', $request), ['decline_reason' => 'maintenance', 'notes' => 'Lamp replacement this week.'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $request->refresh();
        $this->assertSame('pending', $request->status);
        $this->assertSame('approved', $request->venue_status);
        $this->assertSame('rejected', $request->getCustodianEquipmentStatus($this->custodian->id));
        $this->assertStringContainsString('under maintenance', $request->equipment_notes);
        $this->assertDatabaseHas('request_histories', ['facility_request_id' => $request->id, 'action' => 'equipment_declined']);

        // The Supply Office sees the decline and keeps its own decision.
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('request.show', $request))
            ->assertOk()
            ->assertSee('Equipment was declined by its custodian');
    }

    public function test_reporting_an_issue_records_it_without_changing_the_reservation(): void
    {
        $request = $this->makeRequest(['status' => 'approved', 'equipment_status' => 'approved', 'equipment_custodian_statuses' => [$this->custodian->id => 'approved']]);

        $this->actingAs($this->custodian)
            ->post(route('request.custodian.equipment-issue', $request), ['issue_item' => 'Projector', 'issue_type' => 'damaged', 'issue_remarks' => 'Cracked lens found during inspection.'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame('approved', $request->equipment_status);
        $this->assertDatabaseHas('request_histories', ['facility_request_id' => $request->id, 'action' => 'equipment_issue_reported']);

        // Only their own equipment, and a description is required.
        $this->actingAs($this->custodian)
            ->post(route('request.custodian.equipment-issue', $request), ['issue_item' => 'Speaker', 'issue_type' => 'damaged', 'issue_remarks' => 'Not mine'])
            ->assertSessionHasErrors('issue_item');
        $this->actingAs($this->custodian)
            ->post(route('request.custodian.equipment-issue', $request), ['issue_item' => 'Projector', 'issue_type' => 'damaged'])
            ->assertSessionHasErrors('issue_remarks');
    }

    public function test_equipment_custodian_cannot_reject_cancel_or_reopen_a_confirmed_reservation(): void
    {
        $request = $this->makeRequest(['status' => 'approved', 'equipment_status' => 'approved', 'equipment_custodian_statuses' => [$this->custodian->id => 'approved']]);

        $this->actingAs($this->custodian)->post(route('request.custodian.reject', $request), ['decline_reason' => 'damaged']);
        $this->actingAs($this->custodian)->post(route('request.custodian.revision', $request), ['notes' => 'x'])->assertForbidden();
        $this->actingAs($this->custodian)->post(route('request.cancel', $request))->assertForbidden();
        $this->actingAs($this->custodian)->post(route('request.supply.decline', $request))->assertForbidden();
        $this->actingAs($this->custodian)->post(route('request.supply.final-approval', $request))->assertForbidden();

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame('approved', $request->equipment_status);
        $this->assertSame('approved', $request->getCustodianEquipmentStatus($this->custodian->id));
    }

    public function test_a_custodian_without_assigned_equipment_cannot_act(): void
    {
        $request = $this->makeRequest();
        $other = User::factory()->create(['role' => 'custodian-equipment']);

        $this->actingAs($other)->post(route('request.custodian.reject', $request), ['decline_reason' => 'damaged'])->assertForbidden();
        $this->actingAs($other)->post(route('request.custodian.equipment-issue', $request), ['issue_item' => 'Projector', 'issue_type' => 'damaged', 'issue_remarks' => 'Something'])->assertForbidden();
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_venue_included_equipment_with_a_custodian_still_needs_that_custodian(): void
    {
        // Sound System comes with CHIC; when it has a custodian, that custodian reviews it.
        Venue::create(['name' => 'Conference Hall & Interaction Center (CHIC)', 'capacity' => 200, 'custodian_id' => User::factory()->create(['role' => 'custodian-venue'])->id, 'is_active' => true]);
        Equipment::create(['name' => 'Sound System', 'custodian_id' => $this->custodian->id, 'quantity' => 2, 'quantity_available' => 2]);
        $request = $this->makeRequest([
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 1],
        ]);
        $request->recomputeEquipmentStatus();
        $this->assertSame('pending', $request->fresh()->equipment_status);

        $this->actingAs($this->custodian)
            ->post(route('request.custodian.reject', $request), ['decline_reason' => 'damaged', 'notes' => 'Mixer is broken.'])
            ->assertSessionHasNoErrors();
        $request->refresh();
        $this->assertSame('pending', $request->status, 'Declining included equipment does not reject the venue reservation.');
        $this->assertSame('approved', $request->venue_status);
    }
}
