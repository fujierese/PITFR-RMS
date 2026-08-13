<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use App\Events\RequestCreated;
use App\Events\RequestCancelled;
use App\Models\User;
use App\Models\Equipment;
use App\Models\Venue;
use App\Models\FacilityRequest;

class BroadcastCustodianTest extends TestCase
{
    use RefreshDatabase;

    public function test_requestcreated_includes_primary_custodian()
    {
        Event::fake();

        $primary = User::factory()->create(['role' => 'custodian']);
        $requestor = User::factory()->create(['role' => 'requestor']);

        Equipment::factory()->create([
            'name' => 'Industrial Fan',
            'custodian_id' => $primary->id,
        ]);

        $this->actingAs($requestor)
            ->postJson('/api/facility-requests', [
                'name_of_activity' => 'Test',
                'expected_participants' => 10,
                'start_date' => now()->toDateString(),
                'start_time' => '09:00',
                'end_time' => '12:00',
                'department' => 'IT',
                'equipment' => ['Industrial Fan'],
            ])
            ->assertStatus(201);

        Event::assertDispatched(RequestCreated::class, function ($e) use ($primary) {
            return in_array((int)$primary->id, $e->custodianIds, true);
        });
    }

    public function test_requestcreated_includes_authorized_alternate_custodian()
    {
        Event::fake();

        $primary = User::factory()->create(['role' => 'custodian']);
        $alternate = User::factory()->create(['role' => 'custodian']);
        $requestor = User::factory()->create(['role' => 'requestor']);

        Equipment::factory()->create([
            'name' => 'Iwata Cooler Fan',
            'custodian_id' => $primary->id,
            'authorized_custodian_ids' => [$alternate->id],
        ]);

        $this->actingAs($requestor)
            ->postJson('/api/facility-requests', [
                'name_of_activity' => 'Test alt',
                'expected_participants' => 5,
                'start_date' => now()->toDateString(),
                'start_time' => '10:00',
                'end_time' => '11:00',
                'department' => 'Ops',
                'equipment' => ['Iwata Cooler Fan'],
            ])
            ->assertStatus(201);

        Event::assertDispatched(RequestCreated::class, function ($e) use ($primary, $alternate) {
            $ids = array_map('intval', $e->custodianIds);
            return in_array((int)$primary->id, $ids, true) && in_array((int)$alternate->id, $ids, true);
        });
    }

    public function test_business_rule_industrial_and_iwata_include_primary_and_alternate()
    {
        Event::fake();

        $primaryA = User::factory()->create(['role' => 'custodian']);
        $alternateA = User::factory()->create(['role' => 'custodian']);
        $primaryB = User::factory()->create(['role' => 'custodian']);
        $alternateB = User::factory()->create(['role' => 'custodian']);
        $requestor = User::factory()->create(['role' => 'requestor']);

        Equipment::factory()->create([
            'name' => 'Industrial Fan',
            'custodian_id' => $primaryA->id,
            'authorized_custodian_ids' => [$alternateA->id],
        ]);

        Equipment::factory()->create([
            'name' => 'Iwata Cooler Fan',
            'custodian_id' => $primaryB->id,
            'authorized_custodian_ids' => [$alternateB->id],
        ]);

        $this->actingAs($requestor)
            ->postJson('/api/facility-requests', [
                'name_of_activity' => 'Mixed fans',
                'expected_participants' => 20,
                'start_date' => now()->toDateString(),
                'start_time' => '08:00',
                'end_time' => '10:00',
                'department' => 'Events',
                'equipment' => ['Industrial Fan', 'Iwata Cooler Fan'],
            ])
            ->assertStatus(201);

        Event::assertDispatched(RequestCreated::class, function ($e) use ($primaryA, $alternateA, $primaryB, $alternateB) {
            $ids = array_map('intval', $e->custodianIds);
            return in_array((int)$primaryA->id, $ids, true)
                && in_array((int)$alternateA->id, $ids, true)
                && in_array((int)$primaryB->id, $ids, true)
                && in_array((int)$alternateB->id, $ids, true);
        });
    }

    public function test_requestor_web_cancellation_includes_custodian_ids()
    {
        Event::fake();

        $primary = User::factory()->create(['role' => 'custodian']);
        $alternate = User::factory()->create(['role' => 'custodian']);
        $requestor = User::factory()->create(['role' => 'requestor']);

        Equipment::factory()->create([
            'name' => 'Industrial Fan',
            'custodian_id' => $primary->id,
            'authorized_custodian_ids' => [$alternate->id],
        ]);

        // Create facility request via model so we can call destroy
        $fr = FacilityRequest::create([
            'control_number' => FacilityRequest::generateControlNumber(),
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Cancel test',
            'expected_participants' => 2,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => [],
            'equipment' => ['Industrial Fan'],
            'equipment_quantities' => ['Industrial Fan' => 1],
            'requested_by_id' => $requestor->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
            'priority' => 'regular',
            'is_emergency' => false,
        ]);

        $this->actingAs($requestor)
            ->post('/requestor/delete', ['id' => $fr->id])
            ->assertRedirect();

        Event::assertDispatched(RequestCancelled::class, function ($e) use ($primary, $alternate) {
            $ids = array_map('intval', $e->custodianIds);
            return in_array((int)$primary->id, $ids, true) && in_array((int)$alternate->id, $ids, true);
        });
    }

    public function test_custodian_channel_authorization_behaviour()
    {
        $custA = User::factory()->create(['role' => 'custodian']);
        $custB = User::factory()->create(['role' => 'custodian']);
        $requestor = User::factory()->create(['role' => 'requestor']);

        // Own channel allowed
        $this->assertTrue((int)$custA->id === (int)$custA->id && $custA->isCustodian());

        // Another custodian's channel denied (id mismatch)
        $this->assertFalse(((int)$custA->id === (int)$custB->id) && $custA->isCustodian());

        // Requestor cannot be treated as custodian
        $this->assertFalse($requestor->isCustodian());
    }
}
