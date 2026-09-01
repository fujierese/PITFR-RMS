<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\RequestStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
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

    public function test_approval_notification_uses_role_specific_wording(): void
    {
        $requester = User::factory()->create(['role' => 'requestor']);
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $equipmentCustodian = User::factory()->create(['role' => 'custodian-equipment']);

        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-099',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Approval Wording Test',
            'expected_participants' => 20,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => ['Main Hall'],
            'equipment' => ['Projector'],
            'equipment_quantities' => ['Projector' => 1],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'priority' => 'regular',
        ]);
        $request->syncRelationalItems();

        $requestorMessage = (new \App\Notifications\RequestStatusChanged($request, 'approved', '', $venueCustodian->name, $venueCustodian->name, [$equipmentCustodian->name], 'Supply Office'))->toArray($requester)['message'];
        $venueMessage = (new \App\Notifications\RequestStatusChanged($request, 'approved', '', $venueCustodian->name, $venueCustodian->name, [$equipmentCustodian->name], 'Supply Office'))->toArray($venueCustodian)['message'];
        $equipmentMessage = (new \App\Notifications\RequestStatusChanged($request, 'approved', '', $venueCustodian->name, $venueCustodian->name, [$equipmentCustodian->name], 'Supply Office'))->toArray($equipmentCustodian)['message'];

        $this->assertStringContainsString('Your request has been approved', $requestorMessage);
        $this->assertStringContainsString('venue portion', strtolower($venueMessage));
        $this->assertStringContainsString('equipment portion', strtolower($equipmentMessage));
    }

    public function test_return_notification_distinguishes_partial_and_fulfilled_status(): void
    {
        $requester = User::factory()->create(['role' => 'requestor']);
        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-100',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Return Status Test',
            'expected_participants' => 20,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => ['Main Hall'],
            'equipment' => ['Projector'],
            'equipment_quantities' => ['Projector' => 3],
            'requested_by_id' => $requester->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'priority' => 'regular',
            'equipment_returned_status' => 'partial',
            'equipment_return_damaged_quantity' => 2,
            'equipment_return_missing_quantity' => 1,
            'equipment_return_damage_remarks' => 'Screen damaged',
            'equipment_return_missing_remarks' => 'Cable missing',
        ]);
        $request->syncRelationalItems();

        $partialMessage = (new \App\Notifications\RequestStatusChanged($request, 'equipment_returned'))->toArray($requester)['message'];

        $request->update([
            'equipment_returned_status' => 'fulfilled',
            'equipment_return_damaged_quantity' => 0,
            'equipment_return_missing_quantity' => 0,
            'equipment_return_damage_remarks' => null,
            'equipment_return_missing_remarks' => null,
        ]);

        $fulfilledMessage = (new \App\Notifications\RequestStatusChanged($request, 'equipment_returned'))->toArray($requester)['message'];

        $this->assertStringContainsString('partial equipment return', strtolower($partialMessage));
        $this->assertStringContainsString('damaged', strtolower($partialMessage));
        $this->assertStringContainsString('missing', strtolower($partialMessage));
        $this->assertStringContainsString('all equipment has been accounted for', strtolower($fulfilledMessage));
    }

    public function test_revision_notification_lists_old_and_new_schedule(): void
    {
        $requester = User::factory()->create(['role' => 'requestor']);
        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-101',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Reschedule Test',
            'expected_participants' => 20,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => ['Main Hall'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requester->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'priority' => 'regular',
        ]);
        $request->syncRelationalItems();

        $message = (new \App\Notifications\ReservationRevised(
            $request,
            ['start_date' => now()->addDay()->toDateString(), 'start_time' => '09:00', 'end_time' => '11:00', 'venue' => ['Main Hall']],
            ['start_date' => now()->addDays(2)->toDateString(), 'start_time' => '10:00', 'end_time' => '12:00', 'venue' => ['Main Hall']],
            'Venue conflict',
            'Supply Office',
            true,
            false,
            'Room already occupied'
        ))->toDatabase($requester)['message'];

        $this->assertStringContainsString('Previous schedule', $message);
        $this->assertStringContainsString('New schedule', $message);
        $this->assertStringContainsString('09:00', $message);
        $this->assertStringContainsString('10:00', $message);
    }

    public function test_notifications_index_does_not_auto_mark_everything_as_read(): void
    {
        $user = User::factory()->create(['role' => 'requestor']);

        DB::table('notifications')->insert([
            [
                'id' => (string) Str::uuid(),
                'type' => \App\Notifications\RequestStatusChanged::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'request_id' => 1,
                    'control_number' => 'FER-2026-102',
                    'activity' => 'Unread Test',
                    'status' => 'approved',
                    'message' => 'Your request has been approved',
                ]),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'type' => \App\Notifications\RequestStatusChanged::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'request_id' => 2,
                    'control_number' => 'FER-2026-103',
                    'activity' => 'Unread Test 2',
                    'status' => 'rejected',
                    'message' => 'Your request has been rejected',
                ]),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertOk();
        $this->assertSame(2, $user->fresh()->unreadNotifications()->count());
    }
}
