<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PastReservationListVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeReservation(User $requester, string $controlNumber, array $overrides = []): FacilityRequest
    {
        $date = now()->subDays(2)->toDateString();

        return FacilityRequest::create(array_merge([
            'control_number' => $controlNumber,
            'date_requested' => now()->subDays(5)->toDateString(),
            'department' => 'Information Technology',
            'name_of_activity' => $controlNumber,
            'expected_participants' => 20,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'venue' => ['Past List Test Venue'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requester->getKey(),
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ], $overrides));
    }

    public function test_past_reservations_are_hidden_from_requestor_and_supply_office_status_lists(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->makeReservation($requester, 'OLD-PENDING');
        $this->makeReservation($requester, 'OLD-FINAL', [
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'supply_office_review_status' => 'confirmed',
        ]);
        $this->makeReservation($requester, 'OLD-APPROVED', [
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);
        $this->makeReservation($requester, 'OLD-REJECTED', ['status' => 'rejected']);
        $this->makeReservation($requester, 'OLD-REVISION', ['status' => 'needs_reschedule']);

        $this->actingAs($requester)
            ->get(route('requestor.index', ['tab' => 'requests']))
            ->assertOk()
            ->assertDontSee('OLD-PENDING')
            ->assertDontSee('OLD-APPROVED')
            ->assertDontSee('OLD-REJECTED');

        $this->actingAs($admin);
        foreach ([
            'supply-office.requests.pending',
            'supply-office.requests.final-approval',
            'supply-office.requests.approved',
            'supply-office.requests.rejected',
            'supply-office.requests.needs-reschedule',
        ] as $routeName) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertDontSee('OLD-PENDING')
                ->assertDontSee('OLD-FINAL')
                ->assertDontSee('OLD-APPROVED')
                ->assertDontSee('OLD-REJECTED')
                ->assertDontSee('OLD-REVISION');
        }
    }

    public function test_past_reservations_are_hidden_from_custodian_igp_and_api_lists(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'outsider']);
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        $igp = User::factory()->create(['role' => 'igp']);
        $venue = Venue::create([
            'name' => 'Past List Test Venue',
            'custodian_id' => $custodian->getKey(),
        ]);
        $pastRequest = $this->makeReservation($requester, 'OLD-CUSTODIAN-IGP', [
            'supply_office_review_status' => 'confirmed',
        ]);

        $this->actingAs($custodian)
            ->get(route('custodian.index'))
            ->assertOk()
            ->assertDontSee($pastRequest->control_number);

        $this->actingAs($igp)
            ->get(route('igp.index'))
            ->assertOk()
            ->assertDontSee($pastRequest->control_number);

        Sanctum::actingAs($custodian);
        $this->getJson('/api/facility-requests')
            ->assertOk()
            ->assertJsonMissing(['control_number' => $pastRequest->control_number]);
    }

    public function test_ongoing_reservations_that_end_today_remain_visible(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $todayReservation = $this->makeReservation($requester, 'ENDS-TODAY', [
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $this->actingAs($requester)
            ->get(route('requestor.index', ['tab' => 'requests']))
            ->assertOk()
            ->assertSee($todayReservation->control_number);
    }

    public function test_ended_reservation_stays_in_my_requests_until_equipment_is_accounted_for_then_moves_to_history(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $reservation = $this->makeReservation($requester, 'RETURN-LIFECYCLE', [
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'equipment' => ['Portable Speaker'],
            'equipment_quantities' => ['Portable Speaker' => 1],
        ]);

        $this->actingAs($requester)
            ->get(route('requestor.index', ['tab' => 'requests']))
            ->assertOk()
            ->assertSee($reservation->control_number)
            ->assertSee('Event Ended — Equipment Return Pending');

        $reservation->update(['equipment_returned_status' => 'fulfilled']);

        $this->get(route('requestor.index', ['tab' => 'requests']))
            ->assertOk()
            ->assertDontSee($reservation->control_number);

        $this->get(route('history.index'))
            ->assertOk()
            ->assertSee($reservation->control_number)
            ->assertSee('Reservation Completed');
    }

    public function test_request_lists_display_the_full_inclusive_date_range(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'outsider']);
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        $admin = User::factory()->create(['role' => 'admin']);
        $igp = User::factory()->create(['role' => 'igp']);
        $startDate = now()->addDays(2);
        $endDate = now()->addDays(4);
        $reservation = $this->makeReservation($requester, 'RANGE-DISPLAY', [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'supply_office_review_status' => 'confirmed',
        ]);
        Venue::create([
            'name' => 'Past List Test Venue',
            'custodian_id' => $custodian->getKey(),
        ]);
        $expectedRange = $startDate->format('M d, Y') . ' – ' . $endDate->format('M d, Y');

        $this->actingAs($requester)
            ->get(route('requestor.index', ['tab' => 'requests']))
            ->assertOk()
            ->assertSee('Venue Schedules')
            ->assertSee($expectedRange);

        $this->actingAs($admin)
            ->get(route('supply-office.requests.pending'))
            ->assertOk()
            ->assertSee('Venue Schedules')
            ->assertSee($expectedRange);

        $this->actingAs($igp)
            ->get(route('igp.index'))
            ->assertOk()
            ->assertSee($expectedRange);

        $this->assertSame(
            $startDate->format('M d, Y') . ' – ' . $endDate->format('M d, Y'),
            $reservation->getInclusiveDatesLabel()
        );
    }
}
