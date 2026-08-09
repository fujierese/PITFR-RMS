<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustodianAlternateAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function makeRequestForFan(string $fanName, User $primaryCustodian, User $alternateCustodian): FacilityRequest
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        Venue::create([
            'name' => 'Conference Hall & Interaction Center (CHIC)',
            'custodian_id' => User::factory()->create(['role' => 'custodian-venue'])->id,
        ]);

        Equipment::create([
            'name' => $fanName,
            'custodian_id' => $primaryCustodian->id,
            'authorized_custodian_ids' => [$alternateCustodian->id],
            'quantity' => 4,
            'quantity_available' => 4,
        ]);

        $request = FacilityRequest::create([
            'control_number' => 'TEST-FAN-OR-' . strtoupper(str_replace(' ', '-', $fanName)),
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Fan Authorization Test',
            'expected_participants' => 25,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => [$fanName],
            'equipment_quantities' => [$fanName => 1],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'pending',
            'equipment_custodian_statuses' => [],
            'priority' => 'regular',
        ]);

        $request->syncRelationalItems();

        return $request;
    }

    public function test_primary_or_alternate_custodian_can_satisfy_equipment_approval(): void
    {
        $primary = User::factory()->create(['username' => 'lalmerino', 'name' => 'L. ALMERINO', 'role' => 'custodian-equipment']);
        $alternate = User::factory()->create(['username' => 'jrvillas', 'name' => 'JR. VILLAS', 'role' => 'custodian-equipment']);

        $request = $this->makeRequestForFan('Iwata Cooler Fans', $primary, $alternate);

        $response = $this->actingAs($alternate)
            ->post(route('request.custodian.verify', $request), ['notes' => 'Approved by alternate custodian']);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame('approved', $request->equipment_status);
        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $request->id,
            'user_id' => $alternate->id,
            'action' => 'custodian_endorsed',
        ]);
    }

    public function test_l_almerino_can_endorse_fan_equipment(): void
    {
        $primary = User::factory()->create(['username' => 'lalmerino', 'name' => 'L. ALMERINO', 'role' => 'custodian-equipment']);
        $alternate = User::factory()->create(['username' => 'jrvillas', 'name' => 'JR. VILLAS', 'role' => 'custodian-equipment']);

        foreach (['Industrial Fans', 'Iwata Cooler Fans'] as $fanName) {
            $request = $this->makeRequestForFan($fanName, $primary, $alternate);

            $response = $this->actingAs($primary)
                ->post(route('request.custodian.verify', $request), ['notes' => 'Approved by primary']);

            $response->assertRedirect();
            $this->assertSame('approved', $request->fresh()->equipment_status);
            $this->assertDatabaseHas('request_histories', [
                'facility_request_id' => $request->id,
                'user_id' => $primary->id,
                'action' => 'custodian_endorsed',
            ]);
        }
    }

    public function test_jr_villas_can_endorse_fan_equipment(): void
    {
        $primary = User::factory()->create(['username' => 'lalmerino', 'name' => 'L. ALMERINO', 'role' => 'custodian-equipment']);
        $alternate = User::factory()->create(['username' => 'jrvillas', 'name' => 'JR. VILLAS', 'role' => 'custodian-equipment']);

        foreach (['Industrial Fans', 'Iwata Cooler Fans'] as $fanName) {
            $request = $this->makeRequestForFan($fanName, $primary, $alternate);

            $response = $this->actingAs($alternate)
                ->post(route('request.custodian.verify', $request), ['notes' => 'Approved by alternate']);

            $response->assertRedirect();
            $this->assertSame('approved', $request->fresh()->equipment_status);
            $this->assertDatabaseHas('request_histories', [
                'facility_request_id' => $request->id,
                'user_id' => $alternate->id,
                'action' => 'custodian_endorsed',
            ]);
        }
    }

    public function test_second_authorized_custodian_cannot_create_duplicate_approval_after_or_rule_is_satisfied(): void
    {
        $primary = User::factory()->create(['username' => 'lalmerino', 'name' => 'L. ALMERINO', 'role' => 'custodian-equipment']);
        $alternate = User::factory()->create(['username' => 'jrvillas', 'name' => 'JR. VILLAS', 'role' => 'custodian-equipment']);
        $request = $this->makeRequestForFan('Industrial Fans', $primary, $alternate);

        $firstResponse = $this->actingAs($primary)
            ->post(route('request.custodian.verify', $request), ['notes' => 'Approved first']);
        $firstResponse->assertRedirect();
        $this->assertSame('approved', $request->fresh()->equipment_status);

        $secondResponse = $this->actingAs($alternate)
            ->post(route('request.custodian.verify', $request), ['notes' => 'Second attempt']);
        $secondResponse->assertRedirect();
        $this->assertSame('approved', $request->fresh()->equipment_status);
        $this->assertSame(1, $request->fresh()->histories()
            ->where('action', 'custodian_endorsed')
            ->where('user_id', $primary->id)
            ->count());
    }

    public function test_same_user_duplicate_endorsement_is_idempotent(): void
    {
        $primary = User::factory()->create(['username' => 'lalmerino', 'name' => 'L. ALMERINO', 'role' => 'custodian-equipment']);
        $alternate = User::factory()->create(['username' => 'jrvillas', 'name' => 'JR. VILLAS', 'role' => 'custodian-equipment']);
        $request = $this->makeRequestForFan('Iwata Cooler Fans', $primary, $alternate);

        $first = $this->actingAs($primary)->post(route('request.custodian.verify', $request), ['notes' => 'First approval']);
        $first->assertRedirect();

        $second = $this->actingAs($primary)->post(route('request.custodian.verify', $request), ['notes' => 'Second approval']);
        $second->assertRedirect();

        $this->assertSame(1, $request->fresh()->histories()
            ->where('action', 'custodian_endorsed')
            ->where('user_id', $primary->id)
            ->count());
    }

    public function test_unauthorized_custodian_cannot_endorse_equipment(): void
    {
        $primary = User::factory()->create(['username' => 'lalmerino', 'name' => 'L. ALMERINO', 'role' => 'custodian-equipment']);
        $alternate = User::factory()->create(['username' => 'jrvillas', 'name' => 'JR. VILLAS', 'role' => 'custodian-equipment']);
        $unauthorized = User::factory()->create(['name' => 'Unauthorized Custodian', 'role' => 'custodian-equipment']);

        $request = $this->makeRequestForFan('Industrial Fans', $primary, $alternate);

        $response = $this->actingAs($unauthorized)
            ->post(route('request.custodian.verify', $request), ['notes' => 'Unauthorized']);

        $response->assertStatus(403);
        $this->assertNotSame('approved', $request->fresh()->equipment_status);
    }

    public function test_non_custodian_cannot_endorse_equipment_directly(): void
    {
        $primary = User::factory()->create(['username' => 'lalmerino', 'name' => 'L. ALMERINO', 'role' => 'custodian-equipment']);
        $alternate = User::factory()->create(['username' => 'jrvillas', 'name' => 'JR. VILLAS', 'role' => 'custodian-equipment']);
        $requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);

        $request = $this->makeRequestForFan('Industrial Fans', $primary, $alternate);

        $response = $this->actingAs($requestor)
            ->post(route('request.custodian.verify', $request), ['notes' => 'Non-custodian attempt']);

        $response->assertStatus(403);
        $this->assertNotSame('approved', $request->fresh()->equipment_status);
    }

    public function test_industrial_fans_only_accepts_either_authorized_custodian(): void
    {
        $primary = User::factory()->create(['username' => 'lalmerino', 'name' => 'L. ALMERINO', 'role' => 'custodian-equipment']);
        $alternate = User::factory()->create(['username' => 'jrvillas', 'name' => 'JR. VILLAS', 'role' => 'custodian-equipment']);

        $requestA = $this->makeRequestForFan('Industrial Fans', $primary, $alternate);
        $this->actingAs($primary)->post(route('request.custodian.verify', $requestA), ['notes' => 'Primary'])->assertRedirect();
        $this->assertSame('approved', $requestA->fresh()->equipment_status);

        $requestB = $this->makeRequestForFan('Industrial Fans', $primary, $alternate);
        $this->actingAs($alternate)->post(route('request.custodian.verify', $requestB), ['notes' => 'Alternate'])->assertRedirect();
        $this->assertSame('approved', $requestB->fresh()->equipment_status);
    }

    public function test_iwata_cooler_fans_only_accepts_either_authorized_custodian(): void
    {
        $primary = User::factory()->create(['username' => 'lalmerino', 'name' => 'L. ALMERINO', 'role' => 'custodian-equipment']);
        $alternate = User::factory()->create(['username' => 'jrvillas', 'name' => 'JR. VILLAS', 'role' => 'custodian-equipment']);

        $requestA = $this->makeRequestForFan('Iwata Cooler Fans', $primary, $alternate);
        $this->actingAs($primary)->post(route('request.custodian.verify', $requestA), ['notes' => 'Primary'])->assertRedirect();
        $this->assertSame('approved', $requestA->fresh()->equipment_status);

        $requestB = $this->makeRequestForFan('Iwata Cooler Fans', $primary, $alternate);
        $this->actingAs($alternate)->post(route('request.custodian.verify', $requestB), ['notes' => 'Alternate'])->assertRedirect();
        $this->assertSame('approved', $requestB->fresh()->equipment_status);
    }

    public function test_fan_or_rule_remains_valid_for_mixed_equipment_requests(): void
    {
        $primary = User::factory()->create(['username' => 'lalmerino', 'name' => 'L. ALMERINO', 'role' => 'custodian-equipment']);
        $alternate = User::factory()->create(['username' => 'jrvillas', 'name' => 'JR. VILLAS', 'role' => 'custodian-equipment']);
        $otherCustodian = User::factory()->create(['name' => 'Other Equipment Custodian', 'role' => 'custodian-equipment']);

        Venue::create([
            'name' => 'Conference Hall & Interaction Center (CHIC)',
            'custodian_id' => User::factory()->create(['role' => 'custodian-venue'])->id,
        ]);

        Equipment::create([
            'name' => 'Sound System',
            'custodian_id' => $otherCustodian->id,
            'authorized_custodian_ids' => [],
            'quantity' => 2,
            'quantity_available' => 2,
        ]);

        Equipment::create([
            'name' => 'Industrial Fans',
            'custodian_id' => $primary->id,
            'authorized_custodian_ids' => [$alternate->id],
            'quantity' => 8,
            'quantity_available' => 8,
        ]);

        Equipment::create([
            'name' => 'Iwata Cooler Fans',
            'custodian_id' => $primary->id,
            'authorized_custodian_ids' => [$alternate->id],
            'quantity' => 4,
            'quantity_available' => 4,
        ]);

        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $request = FacilityRequest::create([
            'control_number' => 'TEST-MIXED-FANS-001',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Mixed Fan Request',
            'expected_participants' => 25,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => ['Industrial Fans', 'Iwata Cooler Fans', 'Sound System'],
            'equipment_quantities' => ['Industrial Fans' => 1, 'Iwata Cooler Fans' => 1, 'Sound System' => 1],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'pending',
            'equipment_custodian_statuses' => [],
            'priority' => 'regular',
        ]);
        $request->syncRelationalItems();

        $this->actingAs($alternate)
            ->post(route('request.custodian.verify', $request), ['notes' => 'Alternate fan endorsement'])
            ->assertRedirect();

        $this->assertSame('pending', $request->fresh()->equipment_status);
        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $request->id,
            'user_id' => $alternate->id,
            'action' => 'custodian_endorsed',
        ]);
    }

    public function test_quantity_does_not_change_fan_custodian_authorization(): void
    {
        $primary = User::factory()->create(['username' => 'lalmerino', 'name' => 'L. ALMERINO', 'role' => 'custodian-equipment']);
        $alternate = User::factory()->create(['username' => 'jrvillas', 'name' => 'JR. VILLAS', 'role' => 'custodian-equipment']);

        foreach ([1, 3, 4] as $quantity) {
            $request = $this->makeRequestForFan('Industrial Fans', $primary, $alternate);
            $request->equipment_quantities = ['Industrial Fans' => $quantity];
            $request->save();
            $request->syncRelationalItems();

            $response = $this->actingAs($alternate)
                ->post(route('request.custodian.verify', $request), ['notes' => 'Approved at quantity ' . $quantity]);

            $response->assertRedirect();
            $this->assertSame('approved', $request->fresh()->equipment_status);
        }
    }
}
