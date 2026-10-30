<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\ReservationSchedule;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarEventPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_embedded_calendar_is_a_date_picker_without_dashboard_navigation(): void
    {
        $response = $this->get(route('calendar.embed', [
            'date' => '2026-10-26',
            'venue' => 'Balay Alumni',
            'start_date' => '2026-10-26',
            'end_date' => '2026-10-28',
            'start_time' => '09:00',
            'end_time' => '12:30',
            'select' => 1,
        ]));

        $response->assertOk()
            ->assertSee('id="calendar"', false)
            ->assertSee('Select a start date, then an end date.', false)
            ->assertSee('selectionStartDate = clickedDate', false)
            ->assertSee('selectionEndDate = clickedDate', false)
            ->assertSee('calendar-range-between', false)
            ->assertSee('requested-reservation-date', false)
            ->assertSee('requested-reservation-window', false)
            ->assertSee('Requested schedule highlighted in cyan', false)
            ->assertSee('timeGridWeek', false)
            ->assertSee("pitfr-requested-schedule-highlighted", false)
            ->assertSee('venue-calendar-date-selected', false)
            ->assertDontSee('dashboard-sidebar');
    }

    public function test_venue_calendar_button_passes_the_full_requested_schedule_to_the_embed(): void
    {
        $url = route('calendar.embed', [
            'venue' => 'Balay Alumni',
            'date' => '2026-10-26',
            'start_date' => '2026-10-26',
            'end_date' => '2026-10-28',
            'start_time' => '09:00',
            'end_time' => '12:30',
        ]);

        $this->blade(
            '<x-venue-calendar-button venue="Balay Alumni" date="2026-10-26" end-date="2026-10-28" start-time="09:00" end-time="12:30" />'
        )->assertSee('data-venue-calendar-url="' . e($url) . '"', false);
    }

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
        $request->supply_office_reviewed_by_id = $admin->id;
        $request->save();
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
        $this->assertSame($admin->name, $event['extendedProps']['assistedBy']);
        $this->assertSame(route('request.show', $request->id), $event['extendedProps']['requestUrl']);
    }

    public function test_venue_custodian_calendar_marks_unconfirmed_venue_requests_as_tentative_and_linkable(): void
    {
        $requestor = User::factory()->create(['role' => 'requestor']);
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create([
            'name' => 'Gymnasium',
            'capacity' => 100,
            'custodian_id' => $custodian->id,
        ]);
        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-CUSTODIAN-CALENDAR',
            'date_requested' => now()->toDateString(),
            'department' => 'BSIT',
            'name_of_activity' => 'Tentative Workshop',
            'expected_participants' => 25,
            'start_date' => '2026-11-12',
            'end_date' => '2026-11-12',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => ['Gymnasium'],
            'requested_by_id' => $requestor->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);

        $response = $this->actingAs($custodian)->getJson(route('calendar.events'));
        $response->assertOk();

        $event = collect($response->json())
            ->firstWhere('extendedProps.facilityRequestId', $request->id);

        $this->assertNotNull($event);
        $this->assertSame('Awaiting venue confirmation', $event['extendedProps']['displayStatus']);
        $this->assertTrue($event['extendedProps']['awaitingVenueConfirmation']);
        $this->assertSame(route('request.show', $request->id), $event['extendedProps']['requestUrl']);

        $this->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('Awaiting venue confirmation')
            ->assertSee('Tentative request, not an approved booking. Select it to review and confirm availability.');
    }

    public function test_venue_custodian_calendar_only_shows_schedules_for_their_assigned_venues(): void
    {
        $requestor = User::factory()->create(['role' => 'requestor']);
        $chicCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $courtCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $chic = Venue::create([
            'name' => 'Conference Hall & Interaction Center (CHIC)',
            'capacity' => 100,
            'custodian_id' => $chicCustodian->id,
        ]);
        $court = Venue::create([
            'name' => 'Covered Court',
            'capacity' => 100,
            'custodian_id' => $courtCustodian->id,
        ]);
        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-MULTI-VENUE-CALENDAR',
            'date_requested' => now()->toDateString(),
            'department' => 'BSIT',
            'name_of_activity' => 'Two venue schedules',
            'expected_participants' => 25,
            'start_date' => '2026-11-23',
            'end_date' => '2026-11-23',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'venue' => [$chic->name, $court->name],
            'requested_by_id' => $requestor->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);
        $request->requestVenues()->forceDelete();
        $request->requestVenues()->createMany([
            [
                'venue_id' => $chic->id,
                'name' => $chic->name,
                'start_date' => '2026-11-23',
                'end_date' => '2026-11-23',
                'start_time' => '12:00',
                'end_time' => '17:00',
                'reservation_duration' => 'specific_time',
            ],
            [
                'venue_id' => $court->id,
                'name' => $court->name,
                'start_date' => '2026-11-23',
                'end_date' => '2026-11-23',
                'start_time' => '08:00',
                'end_time' => '12:00',
                'reservation_duration' => 'specific_time',
            ],
        ]);
        $this->assertSame('12:00', $request->fresh()->getVenueSchedule($chic->name)['start_time']);
        $this->assertSame('08:00', $request->fresh()->getVenueSchedule($court->name)['start_time']);

        $chicEvents = $this->actingAs($chicCustodian)
            ->getJson(route('calendar.events'))
            ->assertOk()
            ->json();
        $this->assertCount(1, $chicEvents);
        $this->assertSame($chic->name, $chicEvents[0]['extendedProps']['venue']);
        $this->assertSame('2026-11-23 12:00:00', $chicEvents[0]['start_datetime']);

        $courtEvents = $this->actingAs($courtCustodian)
            ->getJson(route('calendar.events'))
            ->assertOk()
            ->json();
        $this->assertCount(1, $courtEvents);
        $this->assertSame($court->name, $courtEvents[0]['extendedProps']['venue']);
        $this->assertSame('2026-11-23 08:00:00', $courtEvents[0]['start_datetime']);
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
        $events = $payload->where('extendedProps.facilityRequestId', $request->id);

        $this->assertCount(2, $events);
        foreach ($events as $event) {
            // Timed multi-day reservations preserve their real range for each venue.
            $this->assertSame('2026-08-10T08:00:00', $event['start']);
            $this->assertSame('2026-08-12T17:00:00', $event['end']);
            $this->assertFalse($event['allDay']);
            $this->assertSame('College of Information and Computing Sciences', $event['extendedProps']['department']);
            $this->assertSame('PIT Innovation Hub', $event['extendedProps']['organization']);
        }
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

    public function test_calendar_events_use_their_venue_color_and_the_legend_explains_the_colors(): void
    {
        $requestor = User::factory()->create(['role' => 'requestor']);
        $venues = [
            'Conference Hall & Interaction Center (CHIC)' => '#DC2626',
            'Balay Alumni' => '#2563EB',
            'Gymnasium' => '#92400E',
            'Covered Court' => '#16A34A',
            'Oval Grounds' => '#16A34A',
        ];

        foreach ($venues as $venue => $expectedColor) {
            FacilityRequest::create([
                'control_number' => 'FER-2026-COLOR-' . substr(sha1($venue), 0, 8),
                'date_requested' => now()->toDateString(),
                'department' => 'BSIT',
                'name_of_activity' => 'Venue Color ' . $venue,
                'expected_participants' => 12,
                'start_date' => '2026-09-04',
                'end_date' => '2026-09-04',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'requested_by_id' => $requestor->id,
                'venue' => [$venue],
                'status' => 'pending',
                'venue_status' => 'pending',
                'equipment_status' => 'pending',
            ]);
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $events = collect($this->actingAs($admin)->getJson(route('calendar.events'))->assertOk()->json());

        foreach ($venues as $venue => $expectedColor) {
            $event = $events->first(fn (array $candidate): bool => ($candidate['extendedProps']['purpose'] ?? null) === 'Venue Color ' . $venue);
            $this->assertNotNull($event);
            $this->assertSame($expectedColor, $event['backgroundColor']);
        }

        \Illuminate\Support\Facades\Auth::logout();
        $publicEvents = collect($this->getJson(route('calendar.events'))->assertOk()->json());
        foreach ($venues as $venue => $expectedColor) {
            $event = $publicEvents->firstWhere('title', 'Venue Color ' . $venue);
            $this->assertNotNull($event);
            $this->assertSame($expectedColor, $event['backgroundColor']);
        }

        $this->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('CHIC', false)
            ->assertSee('Balay Alumni', false)
            ->assertSee('Covered Courts / Oval', false)
            ->assertSee('style="background-color: #92400e;"', false)
            ->assertSee('.venue-chic { color: #dc2626; }', false)
            ->assertSee('.venue-balay { color: #2563eb; }', false)
            ->assertSee('.venue-gymnasium { color: #92400e; }', false)
            ->assertSee('.venue-covered-court { color: #16a34a; }', false)
            ->assertSee('background-color: transparent !important;', false)
            ->assertSee('border: 1px solid #000000 !important;', false)
            ->assertSee('Assisted by:', false)
            ->assertDontSee('Venue color:', false);
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
