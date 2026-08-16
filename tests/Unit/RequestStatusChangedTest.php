<?php

namespace Tests\Unit;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Notifications\RequestStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestStatusChangedTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_payload_contains_status_and_request_details(): void
    {
        $requester = User::factory()->create();
        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-070',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Notification Test',
            'expected_participants' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => [],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);

        $notification = new RequestStatusChanged($request, 'needs_reschedule', 'Institutional urgency');
        $payload = $notification->toArray($requester);

        $this->assertSame('needs_reschedule', $payload['status']);
        $this->assertSame('Institutional urgency', $payload['notes']);
        $this->assertSame($request->id, $payload['request_id']);
        $this->assertSame($request->control_number, $payload['control_number']);
    }

    public function test_mail_payload_contains_status_label_and_notes(): void
    {
        $requester = User::factory()->create();
        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-071',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Mail Test',
            'expected_participants' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => [],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);

        $notification = new RequestStatusChanged($request, 'needs_reschedule', 'Institutional urgency');
        $mail = $notification->toMail($requester);

        $this->assertStringContainsString('Rescheduling', $mail->subject);
        $this->assertStringContainsString('Institutional urgency', $mail->render());
    }

    public function test_broadcast_payload_includes_notification_context(): void
    {
        $requester = User::factory()->create();
        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-072',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Broadcast Test',
            'expected_participants' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => [],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);

        $notification = new RequestStatusChanged($request, 'needs_reschedule', 'Institutional urgency');
        $broadcast = $notification->toBroadcast($requester);

        $this->assertSame($request->id, $broadcast->data['request_id']);
        $this->assertSame('needs_reschedule', $broadcast->data['status']);
        $this->assertSame('Institutional urgency', $broadcast->data['notes']);
    }
}
