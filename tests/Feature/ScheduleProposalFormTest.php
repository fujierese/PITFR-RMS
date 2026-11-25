<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * REQ-08: the "Suggest a new schedule" forms list every active venue, accept times
 * with seconds, check each venue on its own, and let the requestor see the calendar.
 */
class ScheduleProposalFormTest extends TestCase
{
    use RefreshDatabase;

    private User $gymCustodian;

    private User $courtCustodian;

    private FacilityRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->gymCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $this->courtCustodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create(['name' => 'Gymnasium', 'custodian_id' => $this->gymCustodian->id, 'is_active' => true, 'capacity' => 500]);
        Venue::create(['name' => 'Covered Court', 'custodian_id' => $this->courtCustodian->id, 'is_active' => true, 'capacity' => 300]);
        Venue::create(['name' => 'Old Hall', 'custodian_id' => $this->courtCustodian->id, 'is_active' => false, 'capacity' => 100]);

        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $date = now()->addDays(5)->toDateString();
        $this->request = FacilityRequest::create([
            'control_number' => 'TEST-REQ08-' . uniqid(),
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Proposal Form Test',
            'expected_participants' => 25,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'venue' => ['Gymnasium'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'approved',
            'supply_office_review_status' => 'pending',
            'priority' => 'regular',
            'is_emergency' => false,
        ]);
    }

    private function proposal(array $overrides = []): array
    {
        $date = now()->addDays(6)->toDateString();

        return array_merge([
            'facility_request_id' => $this->request->id,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '13:00',
            'end_time' => '15:00',
            'venue' => ['Gymnasium'],
            'revision_reason' => 'The gymnasium is closed for floor repairs that morning.',
        ], $overrides);
    }

    public function test_custodian_form_lists_every_active_venue_with_calendar_check_and_clean_times(): void
    {
        $this->actingAs($this->gymCustodian)
            ->get(route('request.show', $this->request))
            ->assertOk()
            ->assertSee('value="Covered Court" data-revision-venue', false)
            ->assertSee('value="Gymnasium" data-revision-venue', false)
            ->assertDontSee('value="Old Hall" data-revision-venue', false)
            ->assertSee('data-revision-calendar', false)
            ->assertSee('Check venue calendar')
            ->assertSee('name="start_time" value="09:00"', false)
            ->assertDontSee('name="start_time" value="09:00:00"', false);
    }

    public function test_time_with_seconds_is_accepted(): void
    {
        $this->actingAs($this->gymCustodian)
            ->from(route('request.show', $this->request))
            ->post(route('custodian.requests.revise'), $this->proposal(['start_time' => '13:00:00', 'end_time' => '15:00:00']))
            ->assertSessionHasNoErrors();

        $revision = $this->request->revisionHistories()->where('status', 'pending')->firstOrFail();
        $this->assertSame('13:00', substr((string) $revision->new_start_time, 0, 5));
    }

    public function test_bad_time_gets_a_clear_message(): void
    {
        $this->actingAs($this->gymCustodian)
            ->from(route('request.show', $this->request))
            ->post(route('custodian.requests.revise'), $this->proposal(['start_time' => '1pm']))
            ->assertSessionHasErrors(['start_time' => 'Enter the start time as hours and minutes, for example 9:00 AM.']);
    }

    public function test_custodian_can_propose_another_custodians_venue_who_must_then_confirm(): void
    {
        $this->actingAs($this->gymCustodian)
            ->from(route('request.show', $this->request))
            ->post(route('custodian.requests.revise'), $this->proposal(['venue' => ['Covered Court']]))
            ->assertSessionHasNoErrors();

        $revision = $this->request->revisionHistories()->where('status', 'pending')->firstOrFail();
        $this->assertSame(['Covered Court'], $revision->new_venue);
        $this->assertSame('pending', $revision->responses[(string) $this->courtCustodian->id]);
    }

    public function test_inactive_venue_cannot_be_proposed(): void
    {
        $this->actingAs($this->gymCustodian)
            ->from(route('request.show', $this->request))
            ->post(route('custodian.requests.revise'), $this->proposal(['venue' => ['Old Hall']]))
            ->assertSessionHasErrors(['venue.0' => 'One of the chosen venues is not available for booking.']);
    }

    public function test_a_suggestion_colliding_with_an_approved_booking_is_refused_and_names_the_venue(): void
    {
        $date = now()->addDays(6)->toDateString();
        $other = User::factory()->create(['role' => 'requestor']);
        FacilityRequest::create([
            'control_number' => 'TEST-REQ08-OTHER',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Other booking',
            'expected_participants' => 20,
            'start_date' => $date, 'end_date' => $date,
            'start_time' => '13:30', 'end_time' => '14:30',
            'venue' => ['Covered Court'],
            'requested_by_id' => $other->id,
            'status' => 'approved', 'venue_status' => 'approved', 'equipment_status' => 'approved',
        ]);

        $this->actingAs($this->gymCustodian)
            ->from(route('request.show', $this->request))
            ->post(route('custodian.requests.revise'), $this->proposal(['venue' => ['Gymnasium', 'Covered Court']]))
            ->assertSessionHasErrors('conflict');

        // Unified workflow: a suggestion may not collide with an approved booking. Only the
        // conflicting venue is named.
        $message = session('errors')->first('conflict');
        $this->assertStringContainsString('Covered Court:', $message);
        $this->assertStringNotContainsString('Gymnasium:', $message);
        $this->assertSame(0, $this->request->revisionHistories()->count());

        // Without the conflicting venue the suggestion goes through and the requestor sees it.
        $this->actingAs($this->gymCustodian)
            ->from(route('request.show', $this->request))
            ->post(route('custodian.requests.revise'), $this->proposal(['venue' => ['Gymnasium']]))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->request->requester ?? User::findOrFail($this->request->requested_by_id))
            ->get(route('request.show', $this->request))
            ->assertOk()
            ->assertSee('Availability of each proposed venue')
            ->assertSee('Available')
            ->assertSee('Check suggested schedule')
            ->assertSee('1:00 PM');
    }

    public function test_memorandum_priority_transfer_no_longer_crashes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->request->update(['venue_status' => 'approved']);

        $this->actingAs($admin)
            ->post(route('supply-office.requests.revise'), $this->proposal([
                'venue' => ['Covered Court'],
                'memorandum_priority' => '1',
                'memorandum_office' => 'CHED',
            ]))
            ->assertSessionHasNoErrors();

        $revision = $this->request->revisionHistories()->where('status', 'pending')->firstOrFail();
        $this->assertTrue((bool) $revision->memorandum_priority);
        $this->assertSame('not_required', $revision->responses[(string) $this->request->requested_by_id]);
    }
}
