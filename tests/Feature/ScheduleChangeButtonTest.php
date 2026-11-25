<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\Holiday;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * CHG-01 (revised by the follow-up fixes): the Supply Office's Reschedule and Assign
 * Alternative Venue appear only for an actual conflict with an approved reservation;
 * the custodian's "Suggest a new schedule" only after the calendar check finds a conflict.
 */
class ScheduleChangeButtonTest extends TestCase
{
    use RefreshDatabase;

    private User $supply;
    private User $custodian;
    private User $requestor;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Holiday::query()->delete();
        $this->supply = User::factory()->create(['role' => 'supply_office']);
        $this->custodian = User::factory()->create(['role' => 'custodian-venue']);
        $this->requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        Venue::create(['name' => 'Covered Court', 'capacity' => 300, 'custodian_id' => $this->custodian->id, 'is_active' => true]);
        $this->date = now()->addDays(9)->toDateString();
    }

    private function booking(string $number, string $status, array $extra = []): FacilityRequest
    {
        return FacilityRequest::create($extra + [
            'control_number' => $number,
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Booking ' . $number,
            'expected_participants' => 20,
            'start_date' => $this->date,
            'end_date' => $this->date,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => ['Covered Court'],
            'requested_by_id' => $this->requestor->id,
            'status' => $status,
            'venue_status' => $status === 'approved' ? 'approved' : 'pending',
            'equipment_status' => 'approved',
            'supply_office_review_status' => 'pending',
        ]);
    }

    public function test_no_button_when_nothing_calls_for_a_change(): void
    {
        $request = $this->booking('FER-2026-601', 'pending');

        $this->actingAs($this->supply)->get(route('request.show', $request))
            ->assertOk()
            ->assertDontSee('id="open-reschedule-form"', false);
    }

    /**
     * Follow-up fixes: Reschedule / Assign Alternative Venue are offered only for an actual
     * conflict with an approved reservation, and Approve is then not available.
     */
    public function test_a_pending_request_overlapping_an_approved_one_gets_resolution_actions_not_approve(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'username' => config('pitfr.final_approver_username')]);
        Venue::create(['name' => 'Mini Hall', 'capacity' => 100, 'custodian_id' => $this->custodian->id, 'is_active' => true]);
        $this->booking('FER-2026-611', 'approved');
        $request = $this->booking('FER-2026-612', 'pending', ['venue_status' => 'approved', 'supply_office_review_status' => 'confirmed']);

        $this->actingAs($admin)->get(route('request.show', $request))
            ->assertOk()
            ->assertSee('data-approve-blocked', false)
            ->assertDontSee('Approve Reservation')
            ->assertSee('Assign Alternative Venue')
            ->assertSee('Mini Hall')
            ->assertSee('Decline Reservation')
            ->assertDontSee('id="open-reschedule-form"', false);
    }

    public function test_no_schedule_actions_without_an_approved_conflict(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'username' => config('pitfr.final_approver_username')]);
        $approved = $this->booking('FER-2026-621', 'approved');
        // A pending urgent request does not create a conflict until it is approved.
        $this->booking('FER-2026-622', 'pending', ['is_emergency' => true, 'priority' => 'institutional']);
        // A requestor's change request is not a conflict either.
        $approved->requestChangeRequests()->create([
            'requested_by_id' => $this->requestor->id,
            'reason' => 'Our guest speaker moved the date.',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->get(route('request.show', $approved))
            ->assertOk()
            ->assertDontSee('id="open-reschedule-form"', false)
            ->assertDontSee('Assign Alternative Venue')
            ->assertDontSee('data-conflict-panel', false);
    }

    public function test_custodian_suggestion_waits_for_a_conflict(): void
    {
        $request = $this->booking('FER-2026-641', 'pending');

        $this->actingAs($this->custodian)->get(route('request.show', $request))
            ->assertOk()
            ->assertSee('data-availability-conflict-action="custodian-venue-' . $request->id . '"', false);
    }
}
