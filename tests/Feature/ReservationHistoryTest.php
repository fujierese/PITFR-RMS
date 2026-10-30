<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use Carbon\Carbon;
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
            ->assertSeeInOrder([
                'href="' . route('calendar.index') . '"',
                'href="' . route('history.index') . '"',
            ], false)
            ->assertDontSee($past->control_number)
            ->assertDontSee($past->name_of_activity)
            ->assertDontSee($future->control_number)
            ->assertDontSee('Private Department')
            ->assertDontSee(route('request.show', $past->id));

        $this->get(route('request.show', $past->id))->assertForbidden();
    }

    public function test_history_includes_completed_ranges_but_not_reservations_still_in_progress_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2030-05-01 09:30:00'));

        try {
            $owner = User::factory()->create(['role' => 'requestor']);
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
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_history_uses_the_latest_per_venue_schedule_instead_of_stale_request_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2030-05-01 12:00:00'));

        try {
            $requester = User::factory()->create(['role' => 'requestor']);
            $ended = FacilityRequest::create([
                'control_number' => 'HISTORY-VENUE-END-PAST',
                'date_requested' => '2030-04-20',
                'department' => 'History Department',
                'name_of_activity' => 'Venue schedule ended',
                'expected_participants' => 10,
                'start_date' => '2030-05-10',
                'end_date' => '2030-05-10',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'venue' => ['CHIC'],
                'equipment' => [],
                'equipment_quantities' => [],
                'requested_by_id' => $requester->id,
                'status' => 'approved',
            ]);
            $ended->requestVenues()->update([
                'start_date' => '2030-04-29',
                'end_date' => '2030-04-29',
                'start_time' => '09:00',
                'end_time' => '10:00',
            ]);

            $multiVenue = FacilityRequest::create([
                'control_number' => 'HISTORY-VENUE-MULTI-FUTURE',
                'date_requested' => '2030-04-20',
                'department' => 'History Department',
                'name_of_activity' => 'Multi-venue schedule',
                'expected_participants' => 10,
                'start_date' => '2030-05-10',
                'end_date' => '2030-05-10',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'venue' => ['CHIC', 'Gymnasium'],
                'equipment' => [],
                'equipment_quantities' => [],
                'requested_by_id' => $requester->id,
                'status' => 'approved',
            ]);
            $multiVenue->requestVenues()->where('name', 'CHIC')->update([
                'start_date' => '2030-04-29',
                'end_date' => '2030-04-29',
                'start_time' => '09:00',
                'end_time' => '10:00',
            ]);
            $multiVenue->requestVenues()->where('name', 'Gymnasium')->update([
                'start_date' => '2030-05-02',
                'end_date' => '2030-05-02',
                'start_time' => '09:00',
                'end_time' => '10:00',
            ]);

            $this->actingAs($requester)
                ->get(route('history.index'))
                ->assertOk()
                ->assertSee($ended->control_number)
                ->assertDontSee($multiVenue->control_number);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_reservation_ending_today_moves_to_history_after_its_end_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2030-05-01 10:01:00'));

        try {
            $requester = User::factory()->create(['role' => 'requestor']);
            $reservation = FacilityRequest::create([
                'control_number' => 'HISTORY-ENDED-TODAY',
                'date_requested' => '2030-04-30',
                'department' => 'History Department',
                'name_of_activity' => 'Reservation ended today',
                'expected_participants' => 10,
                'start_date' => '2030-05-01',
                'end_date' => '2030-05-01',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'venue' => ['CHIC'],
                'equipment' => [],
                'equipment_quantities' => [],
                'requested_by_id' => $requester->id,
                'status' => 'approved',
            ]);

            $this->actingAs($requester)
                ->get(route('history.index'))
                ->assertOk()
                ->assertSee($reservation->control_number)
                ->assertSee('Reservation Completed');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_history_waits_for_additional_equipment_to_be_accounted_for(): void
    {
        $requester = User::factory()->create(['role' => 'requestor']);
        $pendingReturn = $this->createPastRequest($requester, 'HISTORY-RETURN-PENDING', 'Return pending', 'Lifecycle Test Venue');
        $pendingReturn->update(['equipment' => ['Portable Speaker']]);
        $completedReturn = $this->createPastRequest($requester, 'HISTORY-RETURN-COMPLETE', 'Return complete', 'Lifecycle Test Venue');
        $completedReturn->update([
            'equipment' => ['Portable Speaker'],
            'equipment_returned_status' => 'fulfilled',
        ]);

        $this->actingAs($requester)
            ->get(route('history.index'))
            ->assertOk()
            ->assertSee($completedReturn->control_number)
            ->assertDontSee($pendingReturn->control_number);
    }

    public function test_reservation_lifecycle_covers_multi_day_events_and_pending_equipment_returns(): void
    {
        Carbon::setTestNow(Carbon::parse('2030-04-10 14:00:00'));

        try {
            $requester = User::factory()->create(['role' => 'requestor']);
            $reservation = FacilityRequest::create([
                'control_number' => 'LIFECYCLE-MULTI-DAY',
                'date_requested' => '2030-04-09',
                'department' => 'Lifecycle Department',
                'name_of_activity' => 'Multi-day event',
                'expected_participants' => 10,
                'start_date' => '2030-04-10',
                'end_date' => '2030-04-12',
                'start_time' => '09:00',
                'end_time' => '17:00',
                'venue' => ['Lifecycle Test Venue'],
                'equipment' => ['Portable Speaker'],
                'equipment_quantities' => ['Portable Speaker' => 1],
                'requested_by_id' => $requester->id,
                'status' => 'approved',
            ]);

            $this->assertSame('ongoing', $reservation->getReservationLifecycleState());
            $this->actingAs($requester)
                ->get(route('request.show', $reservation))
                ->assertOk()
                ->assertSee('Ongoing Event');

            Carbon::setTestNow(Carbon::parse('2030-04-12 17:01:00'));
            $this->assertSame('equipment_return_pending', $reservation->getReservationLifecycleState());
            $this->assertFalse($reservation->isReservationComplete());
            $this->get(route('request.show', $reservation))
                ->assertOk()
                ->assertSee('Event Ended — Equipment Return Pending');

            $reservation->equipment_returned_status = 'fulfilled';
            $this->assertSame('completed', $reservation->getReservationLifecycleState());
            $this->assertTrue($reservation->isReservationComplete());

            $venueProvidedEquipment = $this->createPastRequest(
                $requester,
                'LIFECYCLE-VENUE-EQUIPMENT',
                'Venue-provided equipment',
                'Conference Hall & Interaction Center (CHIC)',
                ['Sound System']
            );
            $this->assertFalse($venueProvidedEquipment->hasAdditionalEquipmentToReturn());
            $this->assertTrue($venueProvidedEquipment->isReservationComplete());
        } finally {
            Carbon::setTestNow();
        }
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

    private function createPastRequest(
        User $requester,
        string $controlNumber,
        string $activity,
        string $venue,
        array $equipment = []
    ): FacilityRequest
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
            'equipment' => $equipment,
            'equipment_quantities' => [],
            'requested_by_id' => $requester->id,
            'status' => 'approved',
        ]);
    }
}
