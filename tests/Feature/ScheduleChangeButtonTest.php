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
 * CHG-01: the Supply Office's schedule-change button shows only when something calls
 * for it (a conflict, an urgent request needing the slot, an unavailable schedule, or
 * a requestor's change request); the custodian's "Suggest a new schedule" only after
 * the calendar check finds a conflict.
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

    public function test_button_shows_when_the_request_conflicts(): void
    {
        $this->booking('FER-2026-611', 'approved');
        $request = $this->booking('FER-2026-612', 'pending');

        $this->actingAs($this->supply)->get(route('request.show', $request))
            ->assertOk()
            ->assertSee('id="open-reschedule-form"', false)
            ->assertSee('Schedule change available: The selected venue conflicts with an existing reservation.');
    }

    public function test_button_shows_on_an_approved_booking_an_urgent_request_needs(): void
    {
        $approved = $this->booking('FER-2026-621', 'approved');
        $this->actingAs($this->supply)->get(route('request.show', $approved))->assertDontSee('id="open-reschedule-form"', false);

        $this->booking('FER-2026-622', 'pending', ['is_emergency' => true, 'priority' => 'institutional']);

        $this->actingAs($this->supply)->get(route('request.show', $approved))
            ->assertOk()
            ->assertSee('id="open-reschedule-form"', false)
            ->assertSee("Urgent request FER-2026-622 needs this booking's venue and time.");
    }

    public function test_button_shows_when_the_requestor_asked_for_a_change(): void
    {
        $approved = $this->booking('FER-2026-631', 'approved');
        $approved->requestChangeRequests()->create([
            'requested_by_id' => $this->requestor->id,
            'reason' => 'Our guest speaker moved the date.',
            'status' => 'pending',
        ]);

        $this->actingAs($this->supply)->get(route('request.show', $approved))
            ->assertOk()
            ->assertSee('id="open-reschedule-form"', false)
            ->assertSee('Schedule change available: The requestor asked for a change.');
    }

    public function test_custodian_suggestion_waits_for_a_conflict(): void
    {
        $request = $this->booking('FER-2026-641', 'pending');

        $this->actingAs($this->custodian)->get(route('request.show', $request))
            ->assertOk()
            ->assertSee('data-availability-conflict-action="custodian-venue-' . $request->id . '"', false);
    }
}
