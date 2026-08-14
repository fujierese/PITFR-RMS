<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\ReservationSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarEventPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_events_include_rich_request_details_for_modal_display(): void
    {
        $requestor = User::factory()->create([
            'name' => 'Sample Requestor',
            'username' => 'sample-requestor',
            'role' => 'requestor',
            'contact_number' => '09171234567',
        ]);

        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-001',
            'date_requested' => now()->toDateString(),
            'department' => 'BSIT',
            'name_of_activity' => 'Project Review',
            'expected_participants' => 25,
            'start_date' => '2026-01-15',
            'end_date' => '2026-01-15',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'requested_by_id' => $requestor->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'priority' => 'institutional',
            'is_emergency' => true,
            'emergency_justification' => 'Needs immediate review',
        ]);

        ReservationSchedule::create([
            'facility_request_id' => $request->id,
            'start_datetime' => '2026-01-15 09:00:00',
            'end_datetime' => '2026-01-15 11:00:00',
        ]);

        $response = $this->getJson(route('calendar.events'));

        $response->assertOk();

        $payload = collect($response->json());
        $event = $payload->firstWhere('id', $request->id);

        $this->assertNotNull($event);
        $this->assertSame($requestor->name, $event['extendedProps']['requestor']);
        $this->assertArrayNotHasKey('requestorContact', $event['extendedProps']);
        $this->assertArrayNotHasKey('requestorEmail', $event['extendedProps']);
        $this->assertSame('institutional', $event['extendedProps']['priority']);
        $this->assertTrue($event['extendedProps']['isUrgent']);
        $this->assertSame(route('request.show', $request->id), $event['extendedProps']['requestUrl']);
    }

    public function test_calendar_events_use_reservation_schedule_for_multi_day_range_and_requestor_metadata(): void
    {
        $requestor = User::factory()->create([
            'name' => 'Faculty Requestor',
            'username' => 'faculty-requestor',
            'role' => 'requestor',
            'requestor_type' => 'faculty',
            'department' => 'College of Information and Computing Sciences',
            'office_or_organization' => 'PIT Innovation Hub',
            'contact_number' => '09991234567',
        ]);

        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-002',
            'date_requested' => now()->toDateString(),
            'department' => 'College of Information and Computing Sciences',
            'name_of_activity' => 'Faculty Workshop',
            'expected_participants' => 30,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-12',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'venue' => ['PIT Multi-Purpose Gymnasium', 'CHIC Conference Hall'],
            'requested_by_id' => $requestor->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $request->requestVenues()->createMany([
            ['name' => 'PIT Multi-Purpose Gymnasium'],
            ['name' => 'CHIC Conference Hall'],
        ]);

        ReservationSchedule::create([
            'facility_request_id' => $request->id,
            'start_datetime' => '2026-08-10 08:00:00',
            'end_datetime' => '2026-08-12 17:00:00',
        ]);

        $response = $this->getJson(route('calendar.events'));

        $response->assertOk();

        $payload = collect($response->json());
        $event = $payload->firstWhere('id', $request->id);

        $this->assertNotNull($event);
        // Timed multi-day reservations must preserve their real start/end range and remain timed.
        $this->assertSame('2026-08-10T08:00:00', $event['start']);
        $this->assertSame('2026-08-12T17:00:00', $event['end']);
        $this->assertFalse($event['allDay']);
        $this->assertSame('College of Information and Computing Sciences', $event['extendedProps']['department']);
        $this->assertSame('PIT Innovation Hub', $event['extendedProps']['organization']);
        $this->assertSame('PIT Multi-Purpose Gymnasium, CHIC Conference Hall', $event['venue']);
    }

    public function test_calendar_events_preserve_exact_multi_day_time_range_boundaries(): void
    {
        $requestor = User::factory()->create([
            'name' => 'Time Range Requestor',
            'username' => 'time-range-requestor',
            'role' => 'requestor',
            'contact_number' => '09181234567',
        ]);

        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-005',
            'date_requested' => now()->toDateString(),
            'department' => 'BSIT',
            'name_of_activity' => 'Time Window Reservation',
            'expected_participants' => 18,
            'start_date' => '2026-08-14',
            'end_date' => '2026-08-16',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'requested_by_id' => $requestor->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        ReservationSchedule::create([
            'facility_request_id' => $request->id,
            'start_datetime' => '2026-08-14 09:00:00',
            'end_datetime' => '2026-08-16 17:00:00',
        ]);

        $response = $this->getJson(route('calendar.events'));
        $response->assertOk();

        $event = collect($response->json())->firstWhere('id', $request->id);

        $this->assertNotNull($event);
        // Timed multi-day reservations must preserve the real reservation window and remain timed.
        $this->assertFalse($event['allDay']);
        $this->assertSame('2026-08-14T09:00:00', $event['start']);
        $this->assertSame('2026-08-16T17:00:00', $event['end']);
    }

    public function test_public_calendar_hides_requestor_contact_details(): void
    {
        $requestor = User::factory()->create([
            'name' => 'Private Requestor',
            'username' => 'private-requestor',
            'role' => 'requestor',
            'contact_number' => '09181234567',
        ]);

        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-003',
            'date_requested' => now()->toDateString(),
            'department' => 'BSIT',
            'name_of_activity' => 'Private Activity',
            'expected_participants' => 12,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'requested_by_id' => $requestor->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        ReservationSchedule::create([
            'facility_request_id' => $request->id,
            'start_datetime' => '2026-09-01 10:00:00',
            'end_datetime' => '2026-09-01 12:00:00',
        ]);

        $response = $this->getJson(route('calendar.events'));
        $response->assertOk();

        $payload = collect($response->json());
        $event = $payload->firstWhere('id', $request->id);

        $this->assertNotNull($event);
        $this->assertArrayNotHasKey('requestorContact', $event['extendedProps']);
        $this->assertArrayNotHasKey('requestorEmail', $event['extendedProps']);
    }

    public function test_authorized_users_can_view_requestor_contact_on_request_detail(): void
    {
        $requestor = User::factory()->create([
            'name' => 'Visible Requestor',
            'username' => 'visible-requestor',
            'role' => 'requestor',
            'contact_number' => '09999876543',
        ]);

        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-004',
            'date_requested' => now()->toDateString(),
            'department' => 'BSIT',
            'name_of_activity' => 'Visible Activity',
            'expected_participants' => 20,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-05',
            'start_time' => '08:30',
            'end_time' => '10:30',
            'requested_by_id' => $requestor->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        ReservationSchedule::create([
            'facility_request_id' => $request->id,
            'start_datetime' => '2026-10-05 08:30:00',
            'end_datetime' => '2026-10-05 10:30:00',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('request.show', $request));

        $response->assertOk();
        $response->assertSee('Contact Number');
        $response->assertSee($requestor->contact_number);
    }
}
