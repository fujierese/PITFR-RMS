<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_request_apis_require_authentication(): void
    {
        $this->getJson('/api/facility-requests')->assertUnauthorized();
        $this->getJson('/api-test/facility-requests')->assertUnauthorized();
    }

    public function test_unassigned_custodian_cannot_endorse_another_custodians_request(): void
    {
        $requester = User::factory()->create(['role' => 'requestor']);
        $assignedCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $unassignedCustodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create(['name' => 'Gymnasium', 'custodian_id' => $assignedCustodian->id]);

        $request = $this->facilityRequest($requester, ['Gymnasium'], []);

        $this->actingAs($unassignedCustodian)
            ->post(route('request.custodian.verify', $request))
            ->assertForbidden();

        $this->assertSame('pending', $request->fresh()->venue_status);
    }

    public function test_unassigned_equipment_custodian_cannot_process_return(): void
    {
        $requester = User::factory()->create(['role' => 'requestor']);
        $assignedCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        $unassignedCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        Equipment::create(['name' => 'Sound System', 'custodian_id' => $assignedCustodian->id, 'quantity' => 2, 'quantity_available' => 1]);

        $request = $this->facilityRequest($requester, [], ['Sound System' => 1], [
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $this->actingAs($unassignedCustodian)
            ->post(route('custodian.return', $request), ['equipment' => ['Sound System' => 1]])
            ->assertForbidden();
    }

    public function test_admin_cannot_record_equipment_return(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $custodian = User::factory()->create(['role' => 'custodian-equipment']);
        $requester = User::factory()->create(['role' => 'requestor']);

        Equipment::create([
            'name' => 'Sound System',
            'custodian_id' => $custodian->id,
            'quantity' => 2,
            'quantity_available' => 2,
        ]);

        $request = $this->facilityRequest($requester, [], ['Sound System' => 1], [
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->post(route('custodian.return', $request), [
                'equipment' => ['Sound System' => 1],
                'damaged_quantity' => ['Sound System' => 0],
                'missing_quantity' => ['Sound System' => 0],
            ])
            ->assertForbidden();
    }

    public function test_equipment_return_records_damaged_missing_and_marks_fulfilled(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian-equipment']);
        $requester = User::factory()->create(['role' => 'requestor']);
        $equipment = Equipment::create([
            'name' => 'Sound System',
            'custodian_id' => $custodian->id,
            'quantity' => 3,
            'quantity_available' => 2,
        ]);

        $request = $this->facilityRequest($requester, [], ['Sound System' => 2], [
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($custodian)
            ->post(route('custodian.update'), [
                'id' => $request->id,
                'action' => 'return',
                'equipment' => ['Sound System' => 2],
                'damaged_quantity' => ['Sound System' => 1],
                'missing_quantity' => ['Sound System' => 0],
                'damage_remarks' => ['Sound System' => 'Broken cable'],
                'notes' => 'Returned for final checking',
            ])
            ->assertRedirect();

        $request->refresh();
        $equipment->refresh();

        $this->assertSame('fulfilled', $request->equipment_returned_status);
        $this->assertSame(1, $request->equipment_return_damaged_quantity ?? 0);
        $this->assertSame('Broken cable', $request->equipment_return_damage_remarks ?? '');
        $this->assertSame(0, $request->equipment_return_missing_quantity ?? 0);
        $this->assertSame(2, $equipment->quantity_available);
    }

    public function test_requestor_cannot_view_another_requestors_request_or_printout(): void
    {
        $owner = User::factory()->create(['role' => 'requestor']);
        $otherRequestor = User::factory()->create(['role' => 'requestor']);
        $request = $this->facilityRequest($owner, [], []);

        $this->actingAs($otherRequestor)
            ->get(route('request.show', $request))
            ->assertForbidden();
        $this->actingAs($otherRequestor)
            ->get(route('request.print', $request))
            ->assertForbidden();
    }

    public function test_request_print_page_contains_two_identical_copies_for_authorized_users(): void
    {
        $owner = User::factory()->create(['role' => 'requestor']);
        $request = $this->facilityRequest($owner, ['Gymnasium'], ['Sound System' => 1], [
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $this->actingAs($owner)
            ->get(route('request.print', $request))
            ->assertOk()
            ->assertSee('REQUEST FOR THE USE OF FACILITY/EQUIPMENT')
            ->assertSee('COPY 1')
            ->assertSee('COPY 2');
    }

    public function test_equipment_custodian_approves_before_admin_final_approval(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $custodian = User::factory()->create(['role' => 'custodian-equipment']);
        $equipment = Equipment::create([
            'name' => 'Sound System',
            'custodian_id' => $custodian->id,
            'quantity' => 2,
            'quantity_available' => 2,
        ]);
        $request = $this->facilityRequest(User::factory()->create(['role' => 'requestor']), [], ['Sound System' => 1], [
            'venue_status' => 'approved',
        ]);

        Sanctum::actingAs($custodian);

        $this->postJson("/api/facility-requests/{$request->id}/approve", ['type' => 'equipment'])
            ->assertOk();
        $this->postJson("/api/facility-requests/{$request->id}/approve", ['type' => 'equipment'])
            ->assertStatus(409);

        $this->actingAs($admin)
            ->post(route('request.supply.final-approval', $request))
            ->assertRedirect();

        $this->assertSame(1, $equipment->fresh()->quantity_available);
        $this->assertSame(1, $request->fresh()->histories()->where('action', 'approved')->count());
    }

    public function test_duplicate_api_equipment_return_does_not_restore_inventory_twice(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian-equipment']);
        $equipment = Equipment::create([
            'name' => 'Sound System',
            'custodian_id' => $custodian->id,
            'quantity' => 2,
            'quantity_available' => 1,
        ]);
        $request = $this->facilityRequest(User::factory()->create(['role' => 'requestor']), [], ['Sound System' => 1], [
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        Sanctum::actingAs($custodian);

        $this->postJson("/api/facility-requests/{$request->id}/return-equipment", ['returned_items' => ['Sound System' => 1]])
            ->assertOk();

        $equipment->decrement('quantity_available');

        $this->postJson("/api/facility-requests/{$request->id}/return-equipment", ['returned_items' => ['Sound System' => 1]])
            ->assertStatus(409);

        $this->assertSame(1, $equipment->fresh()->quantity_available);
        $this->assertSame(1, $request->fresh()->histories()->where('action', 'equipment_returned')->count());
    }

    public function test_api_delete_retains_request_as_cancelled(): void
    {
        $requester = User::factory()->create(['role' => 'requestor']);
        $request = $this->facilityRequest($requester, [], []);

        Sanctum::actingAs($requester);

        $this->deleteJson("/api/facility-requests/{$request->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('facility_requests', [
            'id' => $request->id,
            'status' => 'cancelled',
        ]);
        $this->assertNull($request->fresh()->deleted_at);
    }

    private function facilityRequest(User $requester, array $venues, array $equipment, array $overrides = []): FacilityRequest
    {
        return FacilityRequest::create(array_merge([
            'control_number' => 'FER-2026-' . random_int(1000, 9999),
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Security test',
            'expected_participants' => 10,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'venue' => array_values($venues),
            'equipment' => array_keys($equipment),
            'equipment_quantities' => $equipment,
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
            'priority' => 'regular',
        ], $overrides));
    }
}
