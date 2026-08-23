<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Equipment;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FacilityRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    public function test_emergency_request_can_be_submitted_even_when_venue_conflicts_exist()
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $equipmentCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        $startDate = now()->addDay()->toDateString();

        Venue::create([
            'name' => 'Conference Hall & Interaction Center (CHIC)',
            'custodian_id' => $venueCustodian->id,
        ]);
        Equipment::factory()->create([
            'name' => 'Sound System',
            'custodian_id' => $equipmentCustodian->id,
            'quantity' => 5,
            'quantity_available' => 5,
        ]);

        $conflictingRequest = FacilityRequest::create([
            'control_number' => 'TEST-CONFLICT-001',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Existing Seminar',
            'expected_participants' => 50,
            'start_date' => $startDate,
            'end_date' => $startDate,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 1],
            'requested_by_id' => $requester->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'priority' => 'regular',
            'is_emergency' => false,
        ]);
        $conflictingRequest->syncRelationalItems();

        $response = $this->actingAs($requester)
            ->post(route('requestor.store'), [
                'department' => 'IT Department',
                'name_of_activity' => 'Urgent Seminar',
                'expected_participants' => 50,
                'start_date' => $startDate,
                'end_date' => $startDate,
                'start_time' => '09:00',
                'end_time' => '12:00',
                'venue' => 'Conference Hall & Interaction Center (CHIC)',
                'equipment' => ['Sound System'],
                'equipment_quantities' => ['Sound System' => 1],
                'emergency_justification' => 'Need immediate review',
                'is_emergency' => true,
                'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
                'e_signature_file' => UploadedFile::fake()->create('signature.png', 100, 'image/png'),
            ]);

        $response->assertRedirect(route('requestor.index', ['tab' => 'requests']));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('facility_requests', [
            'name_of_activity' => 'Urgent Seminar',
            'is_emergency' => true,
        ]);
    }

    public function test_custodian_verification_uses_current_schema_without_old_approval_columns()
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);

        Venue::create([
            'name' => 'Conference Hall & Interaction Center (CHIC)',
            'custodian_id' => $venueCustodian->id,
        ]);

        $facilityRequest = FacilityRequest::create([
            'control_number' => 'TEST-APPROVAL-001',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Seminar',
            'expected_participants' => 50,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
            'priority' => 'regular',
        ]);

        $response = $this->actingAs($venueCustodian)
            ->post(route('request.custodian.verify', $facilityRequest), ['notes' => 'Verified']);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $facilityRequest->refresh();

        $this->assertSame('approved', $facilityRequest->venue_status);
        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $facilityRequest->id,
            'action' => 'custodian_endorsed',
            'user_id' => $venueCustodian->id,
        ]);
    }

    public function test_request_details_uses_independent_workflow_icons(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $facilityRequest = FacilityRequest::create([
            'control_number' => 'TEST-WORKFLOW-ICONS',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Workflow Icon Test',
            'expected_participants' => 20,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 1],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'pending',
            'priority' => 'regular',
        ]);

        $response = $this->withViewErrors([])
            ->actingAs($requester)
            ->get(route('request.show', $facilityRequest));

        $response->assertOk();
        $response->assertSee('aria-label="approved"', false);
        $response->assertSee('aria-label="pending"', false);
        $response->assertSee('✓', false);
        $response->assertSee('🕐', false);
        $response->assertDontSee('>Completed</span>', false);
        $response->assertDontSee('>Current</span>', false);
        $response->assertDontSee('>Upcoming</span>', false);
    }

    public function test_request_details_groups_consecutive_dates_and_displays_activity_items_and_status(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $facilityRequest = FacilityRequest::create([
            'control_number' => 'TEST-REQUEST-DETAILS-DATES',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Three-Day Workshop',
            'expected_participants' => 30,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-03',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'venue' => ['Gymnasium'],
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 2],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'pending',
            'priority' => 'regular',
        ]);

        $response = $this->withViewErrors([])
            ->actingAs($requester)
            ->get(route('request.show', $facilityRequest));

        $response->assertOk();
        $response->assertSee('Three-Day Workshop');
        $response->assertSee('Sep 1, 2026 - Sep 3, 2026');
        $response->assertSee('Sound System');
        $response->assertSee('Qty 2');
        $response->assertSee('Venue approved; equipment review is in progress');
        $response->assertSee('Your venue is approved. The equipment request is now being reviewed.');
        $response->assertDontSee('Current approver');
        $response->assertDontSee('The request will continue through the existing approval workflow without changing the backend process.');
    }

    public function test_repeated_custodian_endorsement_is_idempotent_and_closes_its_transaction()
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create(['name' => 'Gymnasium', 'custodian_id' => $venueCustodian->id]);
        $facilityRequest = FacilityRequest::create([
            'control_number' => 'TEST-APPROVAL-RETRY',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Retry Test',
            'expected_participants' => 20,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'venue' => ['Gymnasium'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
            'priority' => 'regular',
        ]);

        $initialTransactionLevel = DB::transactionLevel();

        $this->actingAs($venueCustodian)->post(route('request.custodian.verify', $facilityRequest))->assertRedirect();
        $this->actingAs($venueCustodian)->post(route('request.custodian.verify', $facilityRequest))->assertRedirect();

        $this->assertSame(1, $facilityRequest->fresh()->histories()
            ->where('action', 'custodian_endorsed')
            ->where('user_id', $venueCustodian->id)
            ->count());
        $this->assertSame($initialTransactionLevel, DB::transactionLevel());
    }

    public function test_supply_final_approval_uses_current_schema_and_records_history()
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);

        $facilityRequest = FacilityRequest::create([
            'control_number' => 'TEST-APPROVAL-002',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Final Approval Test',
            'expected_participants' => 20,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
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

        $response = $this->actingAs($admin)
            ->post(route('request.supply.final-approval', $facilityRequest), ['notes' => 'Final approval']);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $facilityRequest->refresh();

        $this->assertSame('approved', $facilityRequest->status);
        $this->assertSame($admin->name, $facilityRequest->approved_by);
        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $facilityRequest->id,
            'action' => 'final_approved',
            'user_id' => $admin->id,
        ]);
    }

    public function test_full_request_workflow()
    {
        // Create users
        /** @var User $requester */
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        /** @var User $venueCustodian */
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        /** @var User $equipmentCustodian */
        $equipmentCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);

        // Create venue
        $venue = Venue::create([
            'name' => 'Conference Hall & Interaction Center (CHIC)',
            'custodian_id' => $venueCustodian->id,
        ]);

        // Create equipment
        $equipment = Equipment::factory()->create([
            'name' => 'Sound System',
            'custodian_id' => $equipmentCustodian->id,
            'quantity' => 5,
            'quantity_available' => 5,
        ]);

        // Step 1: Create request directly
        $facilityRequest = FacilityRequest::create([
            'control_number' => 'TEST-001',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Seminar',
            'expected_participants' => 50,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 2],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
            'priority' => 'regular',
        ]);

        $this->assertEquals('pending', $facilityRequest->status);
        $this->assertEquals('regular', $facilityRequest->priority);

        // Step 2: Venue custodian approves
        $response = $this->actingAs($venueCustodian)
             ->post(route('custodian.update'), [
                 'id' => $facilityRequest->id,
                 'action' => 'approve',
                 'notes' => 'Approved',
             ]);

        $facilityRequest->refresh();
        $this->assertEquals('approved', $facilityRequest->venue_status);

        // Step 3: Equipment custodian approves
        $this->actingAs($equipmentCustodian)
             ->post(route('custodian.update'), [
                 'id' => $facilityRequest->id,
                 'action' => 'approve',
                 'notes' => 'Approved',
             ]);

        $facilityRequest->refresh();
        $this->assertEquals('approved', $facilityRequest->equipment_status);

        // Step 4: Admin approves (only available after both custodians approve)
        $this->actingAs($admin)
             ->post(route('admin.update'), [
                 'id' => $facilityRequest->id,
                 'action' => 'approve',
                 'notes' => 'Final approval',
             ]);

        $facilityRequest->refresh();
        $this->assertEquals('approved', $facilityRequest->status);

        // Step 5: Equipment return
        $this->actingAs($equipmentCustodian)
             ->post(route('custodian.update'), [
                 'id' => $facilityRequest->id,
                 'action' => 'return',
                 'notes' => 'Returned',
                 'returned_equipment' => ['Sound System' => 2],
             ]);

        $facilityRequest->refresh();
        $this->assertEquals('returned', $facilityRequest->equipment_returned_status);
    }
}
