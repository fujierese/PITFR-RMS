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
        $this->assertSame($requestor->contact_number, $event['extendedProps']['requestorContact']);
        $this->assertSame('institutional', $event['extendedProps']['priority']);
        $this->assertTrue($event['extendedProps']['isUrgent']);
        $this->assertSame(route('request.show', $request->id), $event['extendedProps']['requestUrl']);
    }
}
