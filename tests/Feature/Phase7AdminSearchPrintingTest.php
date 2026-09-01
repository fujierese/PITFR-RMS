<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase7AdminSearchPrintingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $student;
    protected User $faculty;
    protected FacilityRequest $approvedRequest;
    protected FacilityRequest $pendingRequest;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin User',
        ]);

        $this->student = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
            'name' => 'John Student',
        ]);

        $this->faculty = User::factory()->create([
            'role' => 'faculty',
            'requestor_type' => 'faculty',
            'name' => 'Jane Faculty',
        ]);

        // Create approved request with e-signature
        $this->approvedRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $this->student->id,
            'control_number' => 'FER-2026-0001',
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'e_signature_file' => 'test_signature_001.png',
        ]);

        // Create pending request
        $this->pendingRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $this->faculty->id,
            'control_number' => 'FER-2026-0002',
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
            'e_signature_file' => null,
        ]);
    }

    /** @test */
    public function admin_can_print_facility_request(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('request.print', $this->approvedRequest->id));

        $response->assertStatus(200);
        $response->assertViewIs('request.print');
        $response->assertViewHas('request', $this->approvedRequest);
    }

    /** @test */
    public function student_can_print_approved_owned_request(): void
    {
        $this->actingAs($this->student);

        $response = $this->get(route('request.print', $this->approvedRequest->id));

        $response->assertStatus(200);
        $response->assertViewIs('request.print');
    }

    /** @test */
    public function faculty_cannot_print_facility_request(): void
    {
        $this->actingAs($this->faculty);

        $response = $this->get(route('request.print', $this->approvedRequest->id));

        $response->assertStatus(403);
    }

    /** @test */
    public function requestor_cannot_print_unapproved_owned_request(): void
    {
        $this->actingAs($this->faculty)
            ->get(route('request.print', $this->pendingRequest->id))
            ->assertForbidden();
    }

    /** @test */
    public function custodian_cannot_print_facility_request(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian-venue']);

        $this->actingAs($custodian)
            ->get(route('request.print', $this->approvedRequest->id))
            ->assertStatus(403);
    }

    /** @test */
    public function assigned_custodian_can_print_approved_request(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        $venueName = $this->approvedRequest->getVenueNames()[0] ?? 'Gymnasium';
        Venue::create(['name' => $venueName, 'custodian_id' => $custodian->id]);

        $this->actingAs($custodian)
            ->get(route('request.print', $this->approvedRequest->id))
            ->assertOk()
            ->assertViewIs('request.print');
    }

    /** @test */
    public function admin_sees_print_request_on_request_details(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('request.show', $this->approvedRequest->id));

        $response->assertOk();
        $response->assertSee('Print Request');
    }

    /** @test */
    public function requestor_does_not_see_print_request_on_request_details(): void
    {
        $this->actingAs($this->student);

        $response = $this->get(route('request.show', $this->approvedRequest->id));

        $response->assertOk();
        $response->assertDontSee('Print Request');
    }

    /** @test */
    public function print_form_displays_e_signature_when_present(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('request.print', $this->approvedRequest->id));

        $response->assertStatus(200);
        $response->assertSee(route('request.signature', ['id' => $this->approvedRequest->id]), false);
        $response->assertDontSee('/storage/documents/e_signature/', false);
        $response->assertSee('Electronic Signature', false);
    }

    /** @test */
    public function print_form_displays_blank_signature_line_when_no_e_signature(): void
    {
        $this->actingAs($this->admin);
        $this->pendingRequest->update([
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $response = $this->get(route('request.print', $this->pendingRequest->id));

        $response->assertStatus(200);
        // Check for either signature line or signature-over-printed-name text
        $this->assertTrue(
            str_contains($response->getContent(), 'signature-line') ||
            str_contains(strtolower($response->getContent()), 'printed name'),
            'Neither signature-line div nor printed name text found in print view'
        );
    }

    /** @test */
    public function protected_signature_route_serves_the_private_requestor_signature(): void
    {
        Storage::disk('local')->put(
            'documents/e_signature/test_signature_001.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
        );

        $this->actingAs($this->admin)
            ->get(route('request.signature', $this->approvedRequest->id))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    /** @test */
    public function admin_search_by_control_number(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('supply-office.index'), [
            'search' => 'FER-2026-0001',
        ]);

        $response->assertStatus(200);
        $response->assertViewHas('requests');
        
        $requests = $response->viewData('requests');
        $this->assertTrue($requests->contains('id', $this->approvedRequest->id), 'Approved request not found in search results');
    }

    /** @test */
    public function admin_search_by_control_number_case_insensitive(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('supply-office.index'), [
            'search' => 'fer-2026-0001',
        ]);

        $response->assertStatus(200);
        $requests = $response->viewData('requests');
        $this->assertTrue($requests->contains('id', $this->approvedRequest->id));
    }

    /** @test */
    public function supply_office_provides_department_filter_options(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('supply-office.index'));

        $response->assertStatus(200);
        $response->assertViewHas('allDepartments');
        $response->assertViewHas('allVenues');
        
        $departments = $response->viewData('allDepartments');
        $venues = $response->viewData('allVenues');
        
        $this->assertIsIterable($departments);
        $this->assertIsIterable($venues);
    }

    /** @test */
    public function calendar_events_include_status_information(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson(route('calendar.events'));

        $response->assertStatus(200);
        $events = $response->json();
        
        if (!empty($events)) {
            foreach ($events as $event) {
                $this->assertArrayHasKey('extendedProps', $event);
                $this->assertArrayHasKey('backgroundColor', $event);
            }
        }
    }

    /** @test */
    public function calendar_approved_events_have_green_status_color(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson(route('calendar.events'));

        $response->assertStatus(200);
        $events = $response->json();
        
        $approvedEvent = collect($events)->firstWhere('extendedProps.status', 'approved');
        
        if ($approvedEvent) {
            $this->assertEquals('#10B981', $approvedEvent['backgroundColor']);
            $this->assertEquals('approved-event', $approvedEvent['className']);
        }
    }

    /** @test */
    public function calendar_pending_events_have_yellow_status_color(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson(route('calendar.events'));

        $response->assertStatus(200);
        $events = $response->json();
        
        $pendingEvent = collect($events)->firstWhere('extendedProps.status', 'pending');
        
        if ($pendingEvent) {
            $this->assertEquals('#F59E0B', $pendingEvent['backgroundColor']);
            $this->assertEquals('pending-event', $pendingEvent['className']);
        }
    }

    /** @test */
    public function control_number_is_displayed_in_calendar_event_title(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson(route('calendar.events'));

        $response->assertStatus(200);
        $events = $response->json();
        
        $approvedEvent = collect($events)->firstWhere('extendedProps.status', 'approved');
        
        if ($approvedEvent) {
            $this->assertStringContainsString('FER-2026-0001', $approvedEvent['title']);
        }
    }
}
