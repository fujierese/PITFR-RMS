<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\Equipment;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\RequestStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function createRequestForApproval(User $requester): FacilityRequest
    {
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create([
            'name' => 'Conference Hall & Interaction Center (CHIC)',
            'custodian_id' => $venueCustodian->id,
        ]);

        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-030',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Approval Flow Test',
            'expected_participants' => 25,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'priority' => 'regular',
        ]);

        $request->syncRelationalItems();

        return $request;
    }

    public function test_admin_and_supply_office_dashboards_show_requests_waiting_for_review(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-031',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Pending Review Visibility Test',
            'expected_participants' => 25,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'priority' => 'regular',
        ]);

        $request->syncRelationalItems();
        $adminUser = User::factory()->create(['role' => 'admin']);

        $adminResponse = $this->actingAs($adminUser)->get(route('admin.final-approval'));
        $adminResponse->assertOk();
        $adminResponse->assertSee($request->control_number);

        $supplyOfficeResponse = $this->actingAs($adminUser)->get(route('supply-office.index'));
        $supplyOfficeResponse->assertOk();
        $supplyOfficeResponse->assertSee($request->control_number);
    }

    public function test_admin_approval_marks_request_approved_and_creates_history_and_notification(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $request = $this->createRequestForApproval($requester);
        $adminUser = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($adminUser)
            ->post(route('supply-office.update'), [
                'id' => $request->id,
                'action' => 'approve',
                'notes' => 'Approved by administrator',
            ]);

        $response->assertRedirect(route('supply-office.index'));
        $request->refresh();

        $this->assertSame('approved', $request->status);
        $this->assertSame('approved', $request->venue_status);
        $this->assertSame('approved', $request->equipment_status);
        $this->assertSame($adminUser->name, $request->approved_by);
        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $request->id,
            'action' => 'approved',
            'user_id' => $adminUser->id,
        ]);
        Notification::assertSentTo($requester, RequestStatusChanged::class);
    }

    public function test_calendar_final_approval_requires_both_custodian_approvals(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createRequestForApproval($requester);
        $request->update(['venue_status' => 'pending']);

        $this->actingAs($admin)
            ->postJson(route('calendar.approve', ['id' => $request->id]))
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Cannot finalize approval until both custodians have approved the request.',
            ]);

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame('pending', $request->fresh()->venue_status);
        $this->assertSame('approved', $request->fresh()->equipment_status);
        $this->assertNull($request->fresh()->approved_by_id);
        $this->assertNull($request->fresh()->approved_date);

        $request->update(['venue_status' => 'approved', 'equipment_status' => 'pending']);
        $this->actingAs($admin)
            ->postJson(route('calendar.approve', ['id' => $request->id]))
            ->assertStatus(409);
        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame('approved', $request->fresh()->venue_status);
        $this->assertSame('pending', $request->fresh()->equipment_status);
    }

    public function test_calendar_final_approval_does_not_allow_admin_to_approve_custodian_stages(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createRequestForApproval($requester);
        $request->update(['venue_status' => 'pending']);

        $this->actingAs($admin)
            ->postJson(route('calendar.approve', ['id' => $request->id]), ['type' => 'venue'])
            ->assertForbidden();

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame('pending', $request->fresh()->venue_status);
    }

    public function test_calendar_final_approval_succeeds_after_both_custodian_stages(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createRequestForApproval($requester);

        $this->actingAs($admin)
            ->postJson(route('calendar.approve', ['id' => $request->id]))
            ->assertOk()
            ->assertJson(['message' => 'Request approved successfully']);

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame('approved', $request->venue_status);
        $this->assertSame('approved', $request->equipment_status);
        $this->assertSame($admin->id, $request->approved_by_id);
        $this->assertNotNull($request->approved_date);
        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $request->id,
            'action' => 'final_approved',
            'user_id' => $admin->id,
        ]);
        Notification::assertSentTo($requester, RequestStatusChanged::class);
    }

    public function test_second_overlapping_venue_request_cannot_be_finally_approved(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $firstRequest = $this->createRequestForApproval($requester);
        $secondRequest = $this->createRequestForApproval($requester);
        $secondRequest->update(['control_number' => 'FER-2026-031']);

        $this->actingAs($admin)
            ->post(route('supply-office.update'), [
                'id' => $firstRequest->id,
                'action' => 'approve',
            ])
            ->assertRedirect(route('supply-office.index'));

        $this->actingAs($admin)
            ->post(route('supply-office.update'), [
                'id' => $secondRequest->id,
                'action' => 'approve',
            ])
            ->assertSessionHasErrors('action');

        $this->assertSame('approved', $firstRequest->fresh()->status);
        $this->assertSame('pending', $secondRequest->fresh()->status);
        $this->assertNull($secondRequest->fresh()->approved_by_id);
    }

    public function test_second_overlapping_equipment_request_cannot_exceed_inventory(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $equipmentCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        Equipment::create([
            'name' => 'Single Projector',
            'custodian_id' => $equipmentCustodian->id,
            'quantity' => 1,
            'quantity_available' => 1,
        ]);
        Venue::create([
            'name' => 'Separate Training Room',
            'custodian_id' => $equipmentCustodian->id,
        ]);

        $firstRequest = $this->createRequestForApproval($requester);
        $firstRequest->update([
            'equipment' => ['Single Projector'],
            'equipment_quantities' => ['Single Projector' => 1],
        ]);
        $secondRequest = $this->createRequestForApproval($requester);
        $secondRequest->update([
            'control_number' => 'FER-2026-032',
            'venue' => ['Separate Training Room'],
            'equipment' => ['Single Projector'],
            'equipment_quantities' => ['Single Projector' => 1],
        ]);

        $this->actingAs($admin)
            ->post(route('supply-office.update'), [
                'id' => $firstRequest->id,
                'action' => 'approve',
            ])
            ->assertRedirect(route('supply-office.index'));

        $this->actingAs($admin)
            ->post(route('supply-office.update'), [
                'id' => $secondRequest->id,
                'action' => 'approve',
            ])
            ->assertSessionHasErrors('action');

        $this->assertSame('approved', $firstRequest->fresh()->status);
        $this->assertSame('pending', $secondRequest->fresh()->status);
        $this->assertSame(0, (int) Equipment::where('name', 'Single Projector')->value('quantity_available'));
    }

    public function test_admin_rejection_marks_request_rejected_and_creates_history_and_notification(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $request = $this->createRequestForApproval($requester);
        $adminUser = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($adminUser)
            ->post(route('supply-office.update'), [
                'id' => $request->id,
                'action' => 'reject',
                'notes' => 'Rejected by administrator',
            ]);

        $response->assertRedirect(route('supply-office.index'));
        $request->refresh();

        $this->assertSame('rejected', $request->status);
        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $request->id,
            'action' => 'rejected',
            'user_id' => $adminUser->id,
        ]);
        Notification::assertSentTo($requester, RequestStatusChanged::class);
    }

    public function test_api_stage_approval_cannot_finalize_request(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create([
            'name' => 'Conference Hall & Interaction Center (CHIC)',
            'custodian_id' => $venueCustodian->id,
        ]);
        $request = FacilityRequest::create([
            'control_number' => 'FER-API-STAGE-001',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'API Stage Approval Test',
            'expected_participants' => 25,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'approved',
        ]);

        $this->actingAs($venueCustodian, 'sanctum')
            ->postJson('/api/facility-requests/' . $request->id . '/approve', ['type' => 'venue'])
            ->assertOk();

        $request->refresh();
        $this->assertSame('approved', $request->venue_status);
        $this->assertSame('pending', $request->status);
        $this->assertNull($request->approved_by_id);
        $this->assertNull($request->approved_date);
    }

    public function test_api_venue_custodian_cannot_approve_equipment(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $equipmentCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        Venue::create(['name' => 'Gymnasium', 'custodian_id' => $venueCustodian->id]);
        Equipment::create([
            'name' => 'Sound System',
            'custodian_id' => $equipmentCustodian->id,
            'quantity' => 2,
            'quantity_available' => 2,
        ]);
        $request = FacilityRequest::create([
            'control_number' => 'FER-API-AUTH-001',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'API Authorization Test',
            'expected_participants' => 25,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'venue' => ['Gymnasium'],
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 1],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);

        $this->actingAs($venueCustodian, 'sanctum')
            ->postJson('/api/facility-requests/' . $request->id . '/approve', ['type' => 'equipment'])
            ->assertForbidden();

        $this->assertSame('pending', $request->fresh()->equipment_status);
    }

    public function test_legacy_admin_rejection_clears_final_approval_metadata(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = FacilityRequest::factory()->create([
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'approved_by' => 'Previous Approver',
            'approved_by_id' => $admin->id,
            'approved_date' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.update'), [
                'id' => $request->id,
                'action' => 'reject',
                'notes' => 'Legacy rejection reason',
            ])
            ->assertRedirect();

        $request->refresh();
        $this->assertSame('rejected', $request->status);
        $this->assertNull($request->approved_by);
        $this->assertNull($request->approved_by_id);
        $this->assertNull($request->approved_date);
        $this->assertSame('Legacy rejection reason', $request->notes);
    }
}
