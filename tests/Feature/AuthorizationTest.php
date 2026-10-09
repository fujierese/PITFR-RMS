<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function createNeedsRescheduleRequest(User $requester): FacilityRequest
    {
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create([
            'name' => 'Conference Hall & Interaction Center (CHIC)',
            'custodian_id' => $venueCustodian->id,
        ]);

        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-040',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Authorized Activity',
            'expected_participants' => 20,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requester->id,
            'status' => 'needs_reschedule',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
            'priority' => 'regular',
        ]);

        $request->syncRelationalItems();

        return $request;
    }

    public function test_student_requestor_can_access_reschedule_flow(): void
    {
        $requester = User::factory()->create(['role' => 'student', 'requestor_type' => 'student']);
        $request = $this->createNeedsRescheduleRequest($requester);

        $response = $this->actingAs($requester)
            ->get(route('requestor.edit', $request));

        $response->assertOk();
    }

    public function test_faculty_requestor_can_access_reschedule_flow(): void
    {
        $requester = User::factory()->create(['role' => 'faculty', 'requestor_type' => 'faculty']);
        $request = $this->createNeedsRescheduleRequest($requester);

        $response = $this->actingAs($requester)
            ->get(route('requestor.edit', $request));

        $response->assertOk();
    }

    public function test_outsider_requestor_can_access_reschedule_flow(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'outsider']);
        $request = $this->createNeedsRescheduleRequest($requester);

        $response = $this->actingAs($requester)
            ->get(route('requestor.edit', $request));

        $response->assertOk();
    }

    public function test_staff_requestor_type_receives_normal_requestor_authorization(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'staff']);
        $request = $this->createNeedsRescheduleRequest($requester);

        $response = $this->actingAs($requester)
            ->get(route('requestor.edit', $request));

        $response->assertOk();
        $this->assertTrue($requester->isRequestee());
        $this->assertFalse($requester->isAdmin());
        $this->assertFalse($requester->isCustodian());
    }

    public function test_staff_requestor_type_is_not_admin_or_custodian(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'staff']);

        $this->assertFalse($requester->isAdmin());
        $this->assertFalse($requester->isCustodian());
        $this->assertSame('requestor', $requester->role);
        $this->assertSame('staff', $requester->requestor_type);
    }

    public function test_custodian_cannot_access_requestor_reschedule_flow(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $request = $this->createNeedsRescheduleRequest($requester);
        $custodian = User::factory()->create(['role' => 'custodian']);

        $response = $this->actingAs($custodian)
            ->get(route('requestor.edit', $request));

        $response->assertForbidden();
    }

    public function test_admin_cannot_access_requestor_reschedule_flow(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $request = $this->createNeedsRescheduleRequest($requester);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->get(route('requestor.edit', $request));

        $response->assertForbidden();
    }

    public function test_legacy_admin_aliases_cannot_access_literal_admin_routes(): void
    {
        foreach (['facility_admin', 'supply_office'] as $role) {
            $legacyUser = User::factory()->create(['role' => $role]);

            $this->actingAs($legacyUser)
                ->get(route('admin.users'))
                ->assertForbidden();
        }
    }
}
