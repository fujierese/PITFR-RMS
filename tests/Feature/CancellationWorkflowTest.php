<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CancellationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function approvedReservation(string $date): FacilityRequest
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);

        return FacilityRequest::create([
            'control_number' => 'CANCEL-' . uniqid(),
            'date_requested' => now()->toDateString(),
            'department' => 'Information Technology',
            'name_of_activity' => 'Cancellation flow',
            'expected_participants' => 20,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'venue' => [],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requester->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'supply_office_review_status' => 'confirmed',
        ]);
    }

    public function test_approved_reservation_stays_active_until_supply_office_confirms_cancellation(): void
    {
        Notification::fake();
        $reservation = $this->approvedReservation(now()->addDays(2)->toDateString());
        $requester = $reservation->requester;
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($requester)
            ->post(route('request.cancel', $reservation), ['reason' => 'Event postponed.'])
            ->assertRedirect();

        $reservation->refresh();
        $this->assertSame('approved', $reservation->status);
        $this->assertSame('pending', $reservation->cancellation_status);
        $this->assertSame($requester->id, $reservation->cancellation_requested_by_id);

        $this->actingAs($admin)
            ->post(route('supply-office.requests.cancellation-review', $reservation), [
                'decision' => 'confirm',
                'notes' => 'Cancellation confirmed.',
            ])
            ->assertRedirect(route('supply-office.index'));

        $reservation->refresh();
        $this->assertSame('cancelled', $reservation->status);
        $this->assertSame('confirmed', $reservation->cancellation_status);
        $this->assertSame($admin->id, $reservation->cancellation_reviewed_by_id);
        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $reservation->id,
            'action' => 'cancelled',
            'user_id' => $admin->id,
        ]);
    }

    public function test_same_day_cancellation_is_rejected_for_web_requests(): void
    {
        $reservation = $this->approvedReservation(now()->toDateString());

        $this->actingAs($reservation->requester)
            ->post(route('request.cancel', $reservation))
            ->assertSessionHasErrors('cancellation');

        $this->assertSame('approved', $reservation->fresh()->status);
        $this->assertNull($reservation->fresh()->cancellation_status);
    }

    public function test_api_cancellation_respects_the_cutoff_and_keeps_approved_reservations_active(): void
    {
        Notification::fake();
        $sameDayReservation = $this->approvedReservation(now()->toDateString());

        $this->actingAs($sameDayReservation->requester)
            ->deleteJson("/api/facility-requests/{$sameDayReservation->id}")
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertSame('approved', $sameDayReservation->fresh()->status);

        $futureReservation = $this->approvedReservation(now()->addDays(2)->toDateString());

        $this->actingAs($futureReservation->requester)
            ->deleteJson("/api/facility-requests/{$futureReservation->id}", ['reason' => 'Event moved.'])
            ->assertOk()
            ->assertJsonPath('cancellation_status', 'pending');

        $this->assertSame('approved', $futureReservation->fresh()->status);
        $this->assertSame('pending', $futureReservation->fresh()->cancellation_status);
    }
}
