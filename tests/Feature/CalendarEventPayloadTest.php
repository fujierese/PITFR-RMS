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

        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->getJson(route('calendar.events'));

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

        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->getJson(route('calendar.events'));

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

    public function test_requestor_calendar_shows_other_reservations_as_view_only(): void
    {
        $owner = User::factory()->create([
            'name' => 'Personal Requestor',
            'username' => 'personal-requestor',
            'role' => 'requestor',
            'contact_number' => '09181234568',
        ]);

        $other = User::factory()->create([
            'name' => 'Other Requestor',
            'username' => 'other-requestor',
            'role' => 'requestor',
            'contact_number' => '09181234569',
        ]);

        $myRequest = FacilityRequest::create([
            'control_number' => 'FER-2026-010',
            'date_requested' => now()->toDateString(),
            'department' => 'BSIT',
            'name_of_activity' => 'My Reservation',
            'expected_participants' => 12,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'requested_by_id' => $owner->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $myRequest->reservationSchedule()->create([
            'start_datetime' => now()->addDay()->setTime(9, 0),
            'end_datetime' => now()->addDay()->setTime(11, 0),
        ]);

        $otherRequest = FacilityRequest::create([
            'control_number' => 'FER-2026-011',
            'date_requested' => now()->toDateString(),
            'department' => 'BSIT',
            'name_of_activity' => 'Other Reservation',
            'expected_participants' => 15,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '13:00',
            'end_time' => '15:00',
            'requested_by_id' => $other->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $otherRequest->reservationSchedule()->create([
            'start_datetime' => now()->addDay()->setTime(13, 0),
            'end_datetime' => now()->addDay()->setTime(15, 0),
        ]);

        FacilityRequest::create([
            'control_number' => 'FER-2026-012',
            'date_requested' => now()->toDateString(),
            'department' => 'BSIT',
            'name_of_activity' => 'Rejected Reservation',
            'expected_participants' => 15,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '16:00',
            'end_time' => '17:00',
            'requested_by_id' => $other->id,
            'status' => 'rejected',
            'venue_status' => 'rejected',
            'equipment_status' => 'pending',
        ]);

        $response = $this->actingAs($owner)->getJson(route('calendar.events'));

        $response->assertOk();
        $eventIds = collect($response->json())->pluck('id')->all();
        $this->assertContains($myRequest->id, $eventIds);

        $eventsById = collect($response->json())->keyBy('id');
        $this->assertTrue($eventsById[$myRequest->id]['extendedProps']['isOwner']);
        $otherEvent = collect($response->json())->firstWhere('title', 'Other Reservation');
        $this->assertNotNull($otherEvent);
        $this->assertStringStartsWith('public-', $otherEvent['id']);
        $this->assertArrayNotHasKey('extendedProps', $otherEvent);
        $this->assertArrayNotHasKey('requestor', $otherEvent);
        $this->assertNotContains('Rejected Reservation', collect($response->json())->pluck('title')->all());
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

        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->getJson(route('calendar.events'));
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
        $event = $payload->firstWhere('title', 'Private Activity');

        $this->assertNotNull($event);
        $this->assertSame('Private Activity', $event['title']);
        $this->assertSame('2026-09-01T10:00:00', $event['start']);
        $this->assertSame('2026-09-01T12:00:00', $event['end']);
        $this->assertSame('approved', $event['status']);
        $this->assertSame('#10B981', $event['backgroundColor']);
        $this->assertSame('#059669', $event['borderColor']);
        $this->assertSame([
            'id', 'title', 'start', 'end', 'allDay', 'status', 'venue',
            'backgroundColor', 'borderColor', 'textColor',
        ], array_keys($event));
        $serializedEvent = json_encode($event);
        $this->assertStringNotContainsString($requestor->name, $serializedEvent);
        $this->assertStringNotContainsString($request->control_number, $serializedEvent);
        $this->assertStringNotContainsString('/request/', $serializedEvent);

        $this->get(route('request.show', $request))->assertRedirect(route('login'));

        $apiEvent = collect($this->getJson('/api/reservations')->json())
            ->firstWhere('title', 'Private Activity');
        $this->assertSame($event, $apiEvent);
    }

    public function test_public_pending_calendar_event_uses_the_pending_legend_color(): void
    {
        $requestor = User::factory()->create(['role' => 'requestor']);
        FacilityRequest::create([
            'control_number' => 'FER-2026-PENDING-COLOR',
            'date_requested' => now()->toDateString(),
            'department' => 'BSIT',
            'name_of_activity' => 'Pending Calendar Activity',
            'expected_participants' => 12,
            'start_date' => '2026-09-02',
            'end_date' => '2026-09-02',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'requested_by_id' => $requestor->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);

        $event = collect($this->getJson(route('calendar.events'))->assertOk()->json())
            ->firstWhere('title', 'Pending Calendar Activity');

        $this->assertNotNull($event);
        $this->assertSame('pending', $event['status']);
        $this->assertSame('#F59E0B', $event['backgroundColor']);
        $this->assertSame('#D97706', $event['borderColor']);
    }

    public function test_cancelled_requests_are_hidden_by_default_and_only_admins_can_include_them(): void
    {
        $requestor = User::factory()->create(['role' => 'requestor']);
        $cancelledRequest = FacilityRequest::create([
            'control_number' => 'FER-2026-CANCELLED-CALENDAR',
            'date_requested' => now()->toDateString(),
            'department' => 'BSIT',
            'name_of_activity' => 'Cancelled Calendar Activity',
            'expected_participants' => 12,
            'start_date' => '2026-09-03',
            'end_date' => '2026-09-03',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'requested_by_id' => $requestor->id,
            'status' => 'cancelled',
            'venue_status' => 'cancelled',
            'equipment_status' => 'cancelled',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $adminDefault = $this->actingAs($admin)->getJson(route('calendar.events'));
        $adminDefault->assertOk();
        $this->assertNotContains($cancelledRequest->id, collect($adminDefault->json())->pluck('id')->all());

        $adminIncludingCancelled = $this->getJson(route('calendar.events', ['include_cancelled' => '1']));
        $adminIncludingCancelled->assertOk();
        $event = collect($adminIncludingCancelled->json())->firstWhere('id', $cancelledRequest->id);

        $this->assertNotNull($event);
        $this->assertSame('cancelled', $event['status']);
        $this->assertSame('#E2E8F0', $event['backgroundColor']);
        $this->assertSame('#94A3B8', $event['borderColor']);
        $this->assertSame('#475569', $event['textColor']);
        $this->get(route('calendar.index'))->assertOk()->assertSee('id="include-cancelled-events"', false);

        $nonAdminResponse = $this->actingAs($requestor)
            ->getJson(route('calendar.events', ['include_cancelled' => '1']));
        $nonAdminResponse->assertOk();
        $this->assertNotContains($cancelledRequest->id, collect($nonAdminResponse->json())->pluck('id')->all());
        $this->get(route('calendar.index'))->assertOk()->assertDontSee('id="include-cancelled-events"', false);
    }

    public function test_public_calendar_preserves_whole_day_as_8_am_to_exclusive_midnight(): void
    {
        $requestor = User::factory()->create([
            'name' => 'Whole Day Requestor',
            'username' => 'whole-day-requestor',
            'role' => 'requestor',
        ]);

        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-WHOLE-DAY',
            'date_requested' => now()->toDateString(),
            'department' => 'BSIT',
            'name_of_activity' => 'Whole Day Reservation',
            'expected_participants' => 20,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
            'start_time' => '08:00',
            'end_time' => '23:59',
            'reservation_duration' => 'whole_day',
            'venue' => ['Gymnasium'],
            'requested_by_id' => $requestor->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $response = $this->getJson(route('calendar.events'));
        $response->assertOk();

        $event = collect($response->json())->firstWhere('title', 'Whole Day Reservation');

        $this->assertNotNull($event);
        $this->assertSame('2026-09-10T08:00:00', $event['start']);
        $this->assertSame('2026-09-11T00:00:00', $event['end']);
        $this->assertTrue($event['allDay']);
        $this->assertSame('Gymnasium', $event['venue']);
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

    public function test_conflict_check_does_not_return_private_request_details(): void
    {
        $requestor = User::factory()->create([
            'name' => 'Conflict Requestor',
            'username' => 'conflict-requestor',
            'role' => 'requestor',
        ]);
        $conflictDate = now()->toDateString();

        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-CONFLICT',
            'date_requested' => now()->toDateString(),
            'department' => 'Private Department',
            'name_of_activity' => 'Private Conflict Activity',
            'expected_participants' => 80,
            'start_date' => $conflictDate,
            'end_date' => $conflictDate,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'venue' => ['Gymnasium'],
            'requested_by_id' => $requestor->id,
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'equipment_returned_status' => 'pending',
            'priority' => 'institutional',
        ]);
        $request->requestVenues()->create(['name' => 'Gymnasium']);

        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->postJson(route('calendar.check-conflicts'), [
            'venues' => ['Gymnasium'],
            'start_date' => $conflictDate,
            'start_time' => '10:00',
            'end_date' => $conflictDate,
            'end_time' => '12:00',
        ]);

        $response->assertOk();
        $this->assertIsArray($response->json('conflicts'));
        $serializedResponse = $response->getContent();
        $this->assertStringNotContainsString($requestor->name, $serializedResponse);
        $this->assertStringNotContainsString($request->control_number, $serializedResponse);
        $this->assertStringNotContainsString($request->name_of_activity, $serializedResponse);
        $this->assertStringNotContainsString('Private Department', $serializedResponse);
        $this->assertStringNotContainsString('institutional', $serializedResponse);
    }

    public function test_requestor_cannot_use_admin_conflict_check(): void
    {
        $requestor = User::factory()->create(['role' => 'requestor']);

        $this->actingAs($requestor)
            ->postJson(route('calendar.check-conflicts'), [
                'venues' => ['Gymnasium'],
                'start_date' => now()->toDateString(),
                'start_time' => '10:00',
                'end_date' => now()->toDateString(),
                'end_time' => '12:00',
            ])
            ->assertForbidden();
    }
}
