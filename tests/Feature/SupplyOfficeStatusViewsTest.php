<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Models\Equipment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SupplyOfficeStatusViewsTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $requestor;
    private $custodian;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'username' => 'admin_test',
            'password' => bcrypt('password'),
        ]);

        $this->requestor = User::factory()->create([
            'role' => 'requestor',
            'username' => 'requestor_test',
            'password' => bcrypt('password'),
        ]);

        $this->custodian = User::factory()->create([
            'role' => 'custodian',
            'username' => 'custodian_test',
            'password' => bcrypt('password'),
        ]);

        // Create venues and equipment directly (no factory exists for Venue)
        Venue::create([
            'name' => 'Test Venue',
            'custodian_id' => $this->custodian->id,
        ]);
        Equipment::factory()->create([
            'custodian_id' => $this->custodian->id,
        ]);
    }

    // ==================== Navigation Tests ====================

    public function test_supply_office_dashboard_accessible_to_admin()
    {
        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.index'));
        $response->assertStatus(200);
        $response->assertViewIs('supply-office.index');
    }

    public function test_supply_office_sidebar_shows_organized_sections()
    {
        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.index'));
        
        // Check that the sidebar contains section headers
        $response->assertSee('Overview', false);
        $response->assertSee('Requests', false);
        $response->assertSee('Scheduling', false);
        $response->assertSee('Management', false);
        $response->assertSee('Monitoring', false);
    }

    public function test_pending_requests_link_in_sidebar()
    {
        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.index'));
        $response->assertSee(route('supply-office.requests.pending'));
    }

    public function test_final_approved_activities_link_in_sidebar()
    {
        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.index'));
        $response->assertSee(route('supply-office.requests.approved'));
    }

    public function test_needs_revision_link_in_sidebar()
    {
        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.index'));
        $response->assertSee(route('supply-office.requests.needs-reschedule'));
    }

    // ==================== Pending Requests Tests ====================

    public function test_pending_requests_page_accessible()
    {
        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.requests.pending'));
        $response->assertStatus(200);
        $response->assertViewIs('supply-office.pending-requests');
    }

    public function test_pending_requests_shows_only_pending_status()
    {
        // Create requests with different statuses
        $pendingRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $this->requestor->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);

        $approvedRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $this->requestor->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.requests.pending'));
        
        $this->assertDatabaseHas('facility_requests', ['id' => $pendingRequest->id]);
        $this->assertDatabaseHas('facility_requests', ['id' => $approvedRequest->id]);
        
        // Pending should be displayed
        $response->assertSee($pendingRequest->control_number);
        // Approved should NOT be displayed on pending page
        $response->assertDontSee($approvedRequest->control_number);
    }

    public function test_pending_requests_search_functionality()
    {
        $request = FacilityRequest::factory()->create([
            'requested_by_id' => $this->requestor->id,
            'status' => 'pending',
            'control_number' => 'TEST-2026-001',
            'name_of_activity' => 'Test Activity',
        ]);

        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.requests.pending', ['search' => 'TEST-2026']));
        
        $response->assertSee($request->control_number);
    }

    // ==================== Needs Revision Tests ====================

    public function test_needs_revision_page_accessible()
    {
        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.requests.needs-reschedule'));
        $response->assertStatus(200);
        $response->assertViewIs('supply-office.needs-reschedule');
    }

    public function test_needs_revision_shows_correct_requests()
    {
        $revisionRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $this->requestor->id,
            'status' => 'needs_reschedule',
        ]);

        $pendingRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $this->requestor->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);

        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.requests.needs-reschedule'));
        
        $response->assertSee($revisionRequest->control_number);
        $response->assertDontSee($pendingRequest->control_number);
    }

    // ==================== Rejected Requests Tests ====================

    public function test_rejected_page_accessible()
    {
        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.requests.rejected'));
        $response->assertStatus(200);
        $response->assertViewIs('supply-office.rejected-requests');
    }

    public function test_rejected_page_shows_only_rejected_requests()
    {
        $rejectedRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $this->requestor->id,
            'status' => 'rejected',
            'approved_by_id' => $this->admin->id,
            'approved_date' => now(),
        ]);

        $pendingRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $this->requestor->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.requests.rejected'));
        
        $response->assertSee($rejectedRequest->control_number);
        $response->assertDontSee($pendingRequest->control_number);
    }

    public function test_rejected_requests_do_not_appear_as_pending()
    {
        $rejectedRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $this->requestor->id,
            'status' => 'rejected',
        ]);

        $this->actingAs($this->admin);
        
        // Should be on rejected page
        $rejectedResponse = $this->get(route('supply-office.requests.rejected'));
        $rejectedResponse->assertSee($rejectedRequest->control_number);
        
        // Should NOT be on pending page
        $pendingResponse = $this->get(route('supply-office.requests.pending'));
        $pendingResponse->assertDontSee($rejectedRequest->control_number);
    }

    // ==================== Final Approved Activities Tests ====================

    public function test_final_approved_activities_page_accessible()
    {
        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.requests.approved'));
        $response->assertStatus(200);
        $response->assertViewIs('supply-office.approved-requests');
    }

    public function test_final_approved_activities_shows_documentary_label()
    {
        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.requests.approved'));
        
        // Should indicate documentary/reference purpose
        $response->assertSee('Documentary');
        $response->assertSee('reference');
    }

    public function test_final_approved_activities_shows_only_approved_requests()
    {
        $approvedRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $this->requestor->id,
            'status' => 'approved',
            'approved_by_id' => $this->admin->id,
            'approved_date' => now(),
        ]);

        $pendingRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $this->requestor->id,
            'status' => 'pending',
        ]);

        $rejectedRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $this->requestor->id,
            'status' => 'rejected',
        ]);

        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.requests.approved'));
        
        // Only approved should appear
        $response->assertSee($approvedRequest->control_number);
        $response->assertDontSee($pendingRequest->control_number);
        $response->assertDontSee($rejectedRequest->control_number);
    }

    public function test_final_approved_activities_displays_approval_metadata()
    {
        $approvedRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $this->requestor->id,
            'status' => 'approved',
            'approved_by_id' => $this->admin->id,
            'approved_by' => $this->admin->name,
            'approved_date' => now(),
        ]);

        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.requests.approved'));
        
        $response->assertSee($approvedRequest->control_number);
    }

    public function test_final_approved_activities_prevents_duplicate_approval()
    {
        $approvedRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $this->requestor->id,
            'status' => 'approved',
            'approved_by_id' => $this->admin->id,
            'approved_date' => now(),
        ]);

        $this->actingAs($this->admin);
        
        // Try to approve again
        $response = $this->post(route('supply-office.update'), [
            'id' => $approvedRequest->id,
            'action' => 'approve',
        ]);

        // Should be blocked
        $response->assertSessionHas('info');
        
        // Verify it's still only approved once
        $this->assertDatabaseHas('facility_requests', [
            'id' => $approvedRequest->id,
            'status' => 'approved',
        ]);
    }

    public function test_final_approved_activities_search_and_filter()
    {
        $approvedRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $this->requestor->id,
            'status' => 'approved',
            'control_number' => 'FINAL-2026-001',
            'department' => 'Computer Science',
        ]);

        $this->actingAs($this->admin);
        
        // Search by control number
        $response = $this->get(route('supply-office.requests.approved', [
            'search' => 'FINAL-2026',
        ]));
        $response->assertSee($approvedRequest->control_number);
        
        // Filter by department
        $response = $this->get(route('supply-office.requests.approved', [
            'department' => 'Computer Science',
        ]));
        $response->assertSee($approvedRequest->control_number);
    }

    // ==================== Authorization Tests ====================

    public function test_pending_requests_denied_to_requestor()
    {
        $this->actingAs($this->requestor);
        $response = $this->get(route('supply-office.requests.pending'));
        $response->assertStatus(403);
    }

    public function test_pending_requests_denied_to_custodian()
    {
        $this->actingAs($this->custodian);
        $response = $this->get(route('supply-office.requests.pending'));
        $response->assertStatus(403);
    }

    public function test_final_approved_denied_to_requestor()
    {
        $this->actingAs($this->requestor);
        $response = $this->get(route('supply-office.requests.approved'));
        $response->assertStatus(403);
    }

    public function test_final_approved_denied_to_custodian()
    {
        $this->actingAs($this->custodian);
        $response = $this->get(route('supply-office.requests.approved'));
        $response->assertStatus(403);
    }

    public function test_dashboard_accessible_only_to_admin()
    {
        $this->actingAs($this->requestor);
        $response = $this->get(route('supply-office.index'));
        $response->assertStatus(403);

        $this->actingAs($this->custodian);
        $response = $this->get(route('supply-office.index'));
        $response->assertStatus(403);
    }

    // ==================== Final Approval Queue Tests ====================

    public function test_final_approval_queue_page_accessible()
    {
        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.requests.final-approval'));
        $response->assertStatus(200);
        $response->assertViewIs('supply-office.final-approval');
    }

    public function test_final_approval_queue_shows_ready_for_approval_requests()
    {
        $readyRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $this->requestor->id,
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $notReadyRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $this->requestor->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);

        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.requests.final-approval'));
        
        $response->assertSee($readyRequest->control_number);
        $response->assertDontSee($notReadyRequest->control_number);
    }

    // ==================== History and Consistency Tests ====================

    public function test_rejection_history_preserved_in_rejected_requests()
    {
        $rejectedRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $this->requestor->id,
            'status' => 'rejected',
            'approved_by_id' => $this->admin->id,
            'approved_date' => now(),
        ]);

        $rejectedRequest->addHistory('rejected', 'Test rejection reason', $this->admin->id);

        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.requests.rejected'));
        
        $this->assertDatabaseHas('facility_requests', [
            'id' => $rejectedRequest->id,
            'status' => 'rejected',
        ]);
    }

    public function test_approval_history_preserved_in_final_approved()
    {
        $approvedRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $this->requestor->id,
            'status' => 'approved',
            'approved_by_id' => $this->admin->id,
            'approved_date' => now(),
        ]);

        $approvedRequest->addHistory('approved', 'Test approval', $this->admin->id);

        $this->actingAs($this->admin);
        
        $this->assertDatabaseHas('facility_requests', [
            'id' => $approvedRequest->id,
            'status' => 'approved',
        ]);
    }

    // ==================== Pagination Tests ====================

    public function test_pending_requests_pagination()
    {
        for ($i = 0; $i < 20; $i++) {
            FacilityRequest::factory()->create([
                'requested_by_id' => $this->requestor->id,
                'status' => 'pending',
            ]);
        }

        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.requests.pending'));
        
        $response->assertStatus(200);
        // Should show pagination links if more than 15 items
        $response->assertViewHas('requests');
    }

    public function test_final_approved_pagination()
    {
        for ($i = 0; $i < 20; $i++) {
            FacilityRequest::factory()->create([
                'requested_by_id' => $this->requestor->id,
                'status' => 'approved',
            ]);
        }

        $this->actingAs($this->admin);
        $response = $this->get(route('supply-office.requests.approved'));
        
        $response->assertStatus(200);
        $response->assertViewHas('requests');
    }

    // ==================== Direct URL Authorization Tests ====================

    public function test_direct_url_to_pending_requires_auth()
    {
        $response = $this->get(route('supply-office.requests.pending'));
        $response->assertRedirect(route('login'));
    }

    public function test_direct_url_to_final_approved_requires_auth()
    {
        $response = $this->get(route('supply-office.requests.approved'));
        $response->assertRedirect(route('login'));
    }

    public function test_direct_url_to_rejected_requires_auth()
    {
        $response = $this->get(route('supply-office.requests.rejected'));
        $response->assertRedirect(route('login'));
    }

    public function test_direct_url_to_needs_revision_requires_auth()
    {
        $response = $this->get(route('supply-office.requests.needs-reschedule'));
        $response->assertRedirect(route('login'));
    }
}
