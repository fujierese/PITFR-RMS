<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\RequestStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function createOverrideScenario(): array
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create([
            'name' => 'Conference Hall & Interaction Center (CHIC)',
            'custodian_id' => $venueCustodian->id,
        ]);

        $conflictingRequest = FacilityRequest::create([
            'control_number' => 'FER-2026-050',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Existing Seminar',
            'expected_participants' => 50,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requester->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'priority' => 'regular',
        ]);
        $conflictingRequest->syncRelationalItems();

        $urgentRequest = FacilityRequest::create([
            'control_number' => 'FER-2026-051',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Urgent Seminar',
            'expected_participants' => 50,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'priority' => 'institutional',
        ]);
        $urgentRequest->syncRelationalItems();

        return [$requester, $conflictingRequest, $urgentRequest];
    }

    public function test_override_notification_contains_override_reason_and_request_number(): void
    {
        [$requester, $conflictingRequest, $urgentRequest] = $this->createOverrideScenario();
        $adminUser = User::factory()->create(['role' => 'admin']);

        $this->actingAs($adminUser)
            ->post(route('supply-office.priority-override.submit'), [
                'urgent_request_id' => $urgentRequest->id,
                'conflicting_request_id' => $conflictingRequest->id,
                'override_reason' => 'Institutional urgency',
            ]);

        Notification::assertSentTo(
            $requester,
            RequestStatusChanged::class,
            function (RequestStatusChanged $notification, array $channels) use ($requester, $conflictingRequest): bool {
                $data = $notification->toArray($requester);

                return $data['status'] === 'needs_reschedule'
                    && str_contains($notification->toArray($requester)['notes'], 'Institutional urgency')
                    && $data['control_number'] === $conflictingRequest->control_number;
            }
        );
    }

    public function test_notification_failure_does_not_rollback_transaction(): void
    {
        [$requester, $conflictingRequest, $urgentRequest] = $this->createOverrideScenario();
        $adminUser = User::factory()->create(['role' => 'admin']);

        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('Notification failure'));

        $response = $this->actingAs($adminUser)
            ->post(route('supply-office.priority-override.submit'), [
                'urgent_request_id' => $urgentRequest->id,
                'conflicting_request_id' => $conflictingRequest->id,
                'override_reason' => 'Institutional urgency',
            ]);

        $response->assertRedirect(route('supply-office.index'));
        $this->assertSame('needs_reschedule', $conflictingRequest->fresh()->status);
        $this->assertSame('approved', $urgentRequest->fresh()->status);
    }
}
