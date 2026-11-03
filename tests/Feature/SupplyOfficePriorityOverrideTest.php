<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\RequestStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SupplyOfficePriorityOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        Notification::fake();
        Queue::fake();
    }

    private function createRequestWithVenue(User $requester, array $attributes = []): FacilityRequest
    {
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create([
            'name' => 'Conference Hall & Interaction Center (CHIC)',
            'custodian_id' => $venueCustodian->id,
        ]);

        $request = FacilityRequest::create(array_merge([
            'control_number' => 'FER-2026-001',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Seminar',
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
            'is_emergency' => false,
        ], $attributes));

        $request->syncRelationalItems();

        return $request;
    }

    public function test_conflict_detection_redirects_to_priority_override_confirmation(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $conflictingRequest = $this->createRequestWithVenue($requester, [
            'control_number' => 'FER-2026-002',
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);
        $urgentRequest = $this->createRequestWithVenue($requester, [
            'control_number' => 'FER-2026-003',
            'priority' => 'institutional',
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $adminUser = User::factory()->create(['role' => 'admin', 'username' => 'asala@gmail.com']);

        $response = $this->actingAs($adminUser)
            ->post(route('supply-office.update'), [
                'id' => $urgentRequest->id,
                'action' => 'approve',
                'notes' => 'Urgent approval',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertRedirectContains('priority-override/confirm');
        $this->assertSame('approved', $conflictingRequest->fresh()->status);
        $this->assertSame('pending', $urgentRequest->fresh()->status);
    }

    public function test_confirmation_page_loads_and_displays_conflict_details(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $conflictingRequest = $this->createRequestWithVenue($requester, [
            'control_number' => 'FER-2026-004',
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);
        $urgentRequest = $this->createRequestWithVenue($requester, [
            'control_number' => 'FER-2026-005',
            'priority' => 'institutional',
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $adminUser = User::factory()->create(['role' => 'admin', 'username' => 'asala@gmail.com']);

        $response = $this->actingAs($adminUser)
            ->get(route('supply-office.priority-override.confirm', [
                'urgent_request_id' => $urgentRequest->id,
                'conflicting_request_id' => $conflictingRequest->id,
                'urgent_requester_name' => 'Jane',
                'conflicting_requester_name' => 'John',
                'urgent_activity_name' => 'Urgent Seminar',
                'conflicting_activity_name' => 'Existing Seminar',
                'venue' => 'Conference Hall & Interaction Center (CHIC)',
                'date' => now()->addDay()->toDateString(),
                'time' => '09:00',
                'priority' => 'institutional',
            ]));

        $response->assertOk();
        $response->assertSee('Priority Override Confirmation');
        $response->assertSee('Urgent Request');
        $response->assertSee('Conflicting Approved Request');
        $response->assertSee('Confirm Override');
        $response->assertSee('Cancel Override');
    }

    public function test_submit_override_requires_override_reason(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $conflictingRequest = $this->createRequestWithVenue($requester, [
            'control_number' => 'FER-2026-006',
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);
        $urgentRequest = $this->createRequestWithVenue($requester, [
            'control_number' => 'FER-2026-007',
            'priority' => 'institutional',
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $adminUser = User::factory()->create(['role' => 'admin', 'username' => 'asala@gmail.com']);

        $response = $this->actingAs($adminUser)
            ->post(route('supply-office.priority-override.submit'), [
                'urgent_request_id' => $urgentRequest->id,
                'conflicting_request_id' => $conflictingRequest->id,
            ]);

        $response->assertSessionHasErrors(['override_reason']);
    }

    public function test_confirm_override_marks_conflicting_request_needs_reschedule_and_approves_urgent_request(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $conflictingRequest = $this->createRequestWithVenue($requester, [
            'control_number' => 'FER-2026-008',
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);
        $urgentRequest = $this->createRequestWithVenue($requester, [
            'control_number' => 'FER-2026-009',
            'priority' => 'institutional',
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $adminUser = User::factory()->create(['role' => 'admin', 'username' => 'asala@gmail.com']);

        $response = $this->actingAs($adminUser)
            ->post(route('supply-office.priority-override.submit'), [
                'urgent_request_id' => $urgentRequest->id,
                'conflicting_request_id' => $conflictingRequest->id,
                'override_reason' => 'Institutional priority override',
                'new_start_date' => now()->addDays(2)->toDateString(),
                'new_end_date' => now()->addDays(2)->toDateString(),
                'new_start_time' => '13:00',
                'new_end_time' => '16:00',
            ]);

        $response->assertRedirect(route('supply-office.index'));
        $response->assertSessionHas('success', 'Priority transfer proposal created. The approved reservation remains active until the proposal is accepted.');

        $conflictingRequest->refresh();
        $urgentRequest->refresh();

        $this->assertSame('approved', $conflictingRequest->status);
        $this->assertSame('approved', $conflictingRequest->venue_status);
        $this->assertSame('approved', $conflictingRequest->equipment_status);

        $this->assertSame('pending', $urgentRequest->status);
        $this->assertSame('approved', $urgentRequest->venue_status);
        $this->assertSame('approved', $urgentRequest->equipment_status);
        $this->assertSame($conflictingRequest->id, $conflictingRequest->id);

        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $conflictingRequest->id,
            'action' => 'priority_transfer_proposed',
        ]);
        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $urgentRequest->id,
            'action' => 'urgent_priority_transfer_pending',
        ]);

        Notification::assertSentTo($requester, RequestStatusChanged::class);
    }

    public function test_override_warns_when_conflicting_request_changed_before_commit(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $conflictingRequest = $this->createRequestWithVenue($requester, [
            'control_number' => 'FER-2026-010',
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);
        $urgentRequest = $this->createRequestWithVenue($requester, [
            'control_number' => 'FER-2026-011',
            'priority' => 'institutional',
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $adminUser = User::factory()->create(['role' => 'admin', 'username' => 'asala@gmail.com']);

        $conflictingRequest->update(['status' => 'rejected']);

        $response = $this->actingAs($adminUser)
            ->post(route('supply-office.priority-override.submit'), [
                'urgent_request_id' => $urgentRequest->id,
                'conflicting_request_id' => $conflictingRequest->id,
                'override_reason' => 'Institutional priority override',
                'new_start_date' => now()->addDays(2)->toDateString(),
                'new_end_date' => now()->addDays(2)->toDateString(),
                'new_start_time' => '13:00',
                'new_end_time' => '16:00',
            ]);

        $response->assertSessionHas('warning', 'The conflicting request is no longer an active approved reservation.');

        $this->assertSame('rejected', $conflictingRequest->fresh()->status);
        $this->assertSame('pending', $urgentRequest->fresh()->status);
    }
}
