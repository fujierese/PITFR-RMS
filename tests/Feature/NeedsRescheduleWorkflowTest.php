<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NeedsRescheduleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function createNeedsRescheduleRequest(User $requester): FacilityRequest
    {
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create([
            'name' => 'Conference Hall & Interaction Center (CHIC)',
            'custodian_id' => $venueCustodian->id,
        ]);

        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-020',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Original Activity',
            'expected_participants' => 50,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 1],
            'requested_by_id' => $requester->id,
            'status' => 'needs_reschedule',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
            'priority' => 'institutional',
            'approved_by' => 'Administrator',
            'approved_date' => now(),
            'notes' => 'Override reason',
            'venue_notes' => 'Venue note',
            'equipment_notes' => 'Equipment note',
            'equipment_custodian_statuses' => [],
        ]);

        $request->syncRelationalItems();

        return $request;
    }

    public function test_owner_can_access_reschedule_edit_form(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $request = $this->createNeedsRescheduleRequest($requester);

        $response = $this->actingAs($requester)
            ->get(route('requestor.edit', $request));

        $response->assertOk();
        $response->assertSee('Reschedule Request');
    }

    public function test_non_owner_cannot_access_reschedule_edit_form(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $otherUser = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'faculty']);
        $request = $this->createNeedsRescheduleRequest($requester);

        $response = $this->actingAs($otherUser)
            ->get(route('requestor.edit', $request));

        $response->assertForbidden();
    }

    public function test_needs_reschedule_update_restricts_editing_to_scheduling_fields(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $request = $this->createNeedsRescheduleRequest($requester);

        $response = $this->actingAs($requester)
            ->put(route('requestor.update', $request), [
                'start_date' => now()->addDay()->toDateString(),
                'end_date' => now()->addDay()->toDateString(),
                'start_time' => '10:00',
                'end_time' => '13:00',
                'venue' => 'Conference Hall & Interaction Center (CHIC)',
                'equipment' => ['Sound System'],
                'equipment_quantities' => ['Sound System' => 1],
                'name_of_activity' => 'Should not change',
                'department' => 'Changed Department',
            ]);

        $response->assertRedirect(route('request.show', $request));
        $request->refresh();

        $this->assertSame('pending', $request->status);
        $this->assertSame('pending', $request->venue_status);
        $this->assertSame('pending', $request->equipment_status);
        $this->assertSame('Original Activity', $request->name_of_activity);
        $this->assertSame('IT Department', $request->department);
        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $request->id,
            'action' => 'needs_reschedule',
        ]);
    }

    public function test_needs_reschedule_update_returns_request_to_normal_approval_flow(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $request = $this->createNeedsRescheduleRequest($requester);

        $this->actingAs($requester)
            ->put(route('requestor.update', $request), [
                'start_date' => now()->addDay()->toDateString(),
                'end_date' => now()->addDay()->toDateString(),
                'start_time' => '10:00',
                'end_time' => '13:00',
                'venue' => 'Conference Hall & Interaction Center (CHIC)',
                'equipment' => ['Sound System'],
                'equipment_quantities' => ['Sound System' => 1],
            ]);

        $request->refresh();

        $this->assertSame('pending', $request->status);
        $this->assertSame('pending', $request->venue_status);
        $this->assertSame('pending', $request->equipment_status);
        $this->assertNull($request->approved_by);
        $this->assertNull($request->approved_date);
    }

    public function test_requestor_dashboard_shows_edit_button_for_needs_reschedule_requests(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $request = $this->createNeedsRescheduleRequest($requester);

        $response = $this->actingAs($requester)
            ->get(route('requestor.index', ['tab' => 'requests']));

        $response->assertOk();
        $response->assertSee(route('requestor.edit', $request));
    }

    public function test_admin_role_can_access_the_administration_dashboard(): void
    {
        $adminUser = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($adminUser)
            ->get(route('admin.index'));

        $response->assertOk();
        $response->assertSee('Administration');
    }
}
