<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_in_users_can_see_past_record_summaries_but_not_other_users_details(): void
    {
        $owner = User::factory()->create(['role' => 'requestor']);
        $viewer = User::factory()->create(['role' => 'requestor']);
        $past = FacilityRequest::create([
            'control_number' => 'HISTORY-PAST-1',
            'date_requested' => now()->subDays(5)->toDateString(),
            'department' => 'Private Department',
            'name_of_activity' => 'Past public summary',
            'expected_participants' => 12,
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->subDays(2)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'venue' => ['CHIC'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $owner->id,
            'status' => 'approved',
        ]);
        $future = FacilityRequest::create([
            'control_number' => 'HISTORY-FUTURE-1',
            'date_requested' => now()->toDateString(),
            'department' => 'Private Department',
            'name_of_activity' => 'Future reservation',
            'expected_participants' => 12,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'venue' => ['CHIC'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $owner->id,
            'status' => 'approved',
        ]);

        $this->actingAs($viewer)
            ->get(route('history.index'))
            ->assertOk()
            ->assertSee('Reservation History')
            ->assertDontSee($past->control_number)
            ->assertDontSee($past->name_of_activity)
            ->assertDontSee($future->control_number)
            ->assertDontSee('Private Department')
            ->assertDontSee(route('request.show', $past->id));

        $this->get(route('request.show', $past->id))->assertForbidden();
    }

    public function test_history_includes_completed_ranges_but_not_reservations_still_ending_today(): void
    {
        $owner = User::factory()->create(['role' => 'requestor']);
        $viewer = User::factory()->create(['role' => 'requestor']);
        $completed = FacilityRequest::create([
            'control_number' => 'HISTORY-COMPLETED',
            'date_requested' => now()->subDays(3)->toDateString(),
            'department' => 'History Department',
            'name_of_activity' => 'Completed reservation',
            'expected_participants' => 10,
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->subDays(1)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => ['CHIC'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $owner->id,
            'status' => 'approved',
        ]);
        FacilityRequest::create([
            'control_number' => 'HISTORY-ONGOING',
            'date_requested' => now()->subDays(2)->toDateString(),
            'department' => 'History Department',
            'name_of_activity' => 'Reservation ends today',
            'expected_participants' => 10,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => ['CHIC'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $owner->id,
            'status' => 'approved',
        ]);

        $this->actingAs($owner)
            ->get(route('history.index'))
            ->assertOk()
            ->assertSee($completed->control_number)
            ->assertDontSee('HISTORY-ONGOING');
    }

    public function test_igp_history_is_limited_to_outsider_reservations(): void
    {
        $igp = User::factory()->create(['role' => 'igp']);
        $outsider = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'outsider']);
        $staff = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'staff']);

        $outsiderRequest = $this->createPastRequest($outsider, 'HISTORY-IGP-OUTSIDER', 'Outsider history item', 'CHIC');
        $staffRequest = $this->createPastRequest($staff, 'HISTORY-IGP-STAFF', 'Staff history item', 'CHIC');

        $this->actingAs($igp)
            ->get(route('history.index'))
            ->assertOk()
            ->assertSee($outsiderRequest->control_number)
            ->assertDontSee($staffRequest->control_number);
    }

    public function test_custodian_history_is_limited_to_assigned_venue_reservations(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        $requester = User::factory()->create(['role' => 'requestor']);
        \App\Models\Venue::create([
            'name' => 'Assigned History Venue',
            'custodian_id' => $custodian->id,
            'is_active' => true,
        ]);

        $assignedRequest = $this->createPastRequest($requester, 'HISTORY-CUSTODIAN-ASSIGNED', 'Assigned venue history', 'Assigned History Venue');
        $unassignedRequest = $this->createPastRequest($requester, 'HISTORY-CUSTODIAN-OTHER', 'Other venue history', 'Different Venue');

        $this->actingAs($custodian)
            ->get(route('history.index'))
            ->assertOk()
            ->assertSee($assignedRequest->control_number)
            ->assertDontSee($unassignedRequest->control_number);
    }

    private function createPastRequest(User $requester, string $controlNumber, string $activity, string $venue): FacilityRequest
    {
        return FacilityRequest::create([
            'control_number' => $controlNumber,
            'date_requested' => now()->subDays(5)->toDateString(),
            'department' => 'History Test Department',
            'name_of_activity' => $activity,
            'expected_participants' => 12,
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->subDays(2)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'venue' => [$venue],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requester->id,
            'status' => 'approved',
        ]);
    }
}
