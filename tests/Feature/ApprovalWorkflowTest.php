<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
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
}
