<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\RevisionHistory;
use App\Models\User;
use App\Models\Venue;
use App\Services\SupplyOfficeDecisions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Unified reservation workflow: tentative overlaps (pending requests) only warn and never
 * block one another; actual conflicts (approved bookings, closures) block confirmation,
 * suggestions and approval; Mark Unavailable and Decline are gone; only the final approver
 * approves.
 */
class UnifiedReservationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $requestor;
    private User $supply;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $this->supply = User::factory()->create(['role' => 'supply_office', 'name' => 'Supply Staff']);
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create(['name' => 'Covered Court', 'capacity' => 300, 'custodian_id' => $custodian->id, 'is_active' => true]);
        Venue::create(['name' => 'Mini Hall', 'capacity' => 100, 'custodian_id' => $custodian->id, 'is_active' => true]);
        $this->date = now()->addDays(10)->toDateString();
    }

    private function booking(string $number, string $status, array $extra = [], string $start = '09:00', string $end = '11:00'): FacilityRequest
    {
        $request = FacilityRequest::create(array_merge([
            'control_number' => $number,
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Booking ' . $number,
            'expected_participants' => 20,
            'start_date' => $this->date,
            'end_date' => $this->date,
            'start_time' => $start,
            'end_time' => $end,
            'venue' => ['Covered Court'],
            'equipment' => [],
            'requested_by_id' => $this->requestor->id,
            'status' => $status,
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'supply_office_review_status' => $status === 'approved' ? 'confirmed' : 'pending',
        ], $extra));
        $request->syncRelationalItems();

        return $request->fresh();
    }

    private function confirm(FacilityRequest $request)
    {
        return $this->actingAs($this->supply)->post(route('supply-office.requests.availability-review'), ['id' => $request->id, 'decision' => 'available']);
    }

    public function test_overlapping_pending_requests_can_both_be_confirmed_with_a_named_warning(): void
    {
        $first = $this->booking('FER-UW-1', 'pending');
        $second = $this->booking('FER-UW-2', 'pending', [], '10:00', '12:00');

        $this->confirm($first)->assertSessionHasNoErrors()->assertSessionHas('warning', fn ($w) => str_contains((string) $w, 'FER-UW-2'));
        $this->confirm($second)->assertSessionHasNoErrors()->assertSessionHas('warning', fn ($w) => str_contains((string) $w, 'FER-UW-1'));

        $this->assertSame('confirmed', $first->fresh()->supply_office_review_status);
        $this->assertSame('confirmed', $second->fresh()->supply_office_review_status);
        $this->assertSame('pending', $first->fresh()->status, 'Availability confirmation is not final approval.');
        $this->assertStringContainsString('Tentative overlap (not a conflict)', (string) $first->histories()->where('action', 'availability_confirmed')->value('detail'));
    }

    public function test_once_one_is_approved_the_other_is_an_actual_conflict(): void
    {
        $pending = $this->booking('FER-UW-3', 'pending');
        $this->booking('FER-UW-4', 'approved', [], '10:30', '12:00');

        $this->confirm($pending)->assertSessionHasErrors('decision');
        $this->assertSame('pending', $pending->fresh()->supply_office_review_status);
    }

    public function test_the_calendar_check_for_the_supply_office_separates_conflicts_from_warnings(): void
    {
        $request = $this->booking('FER-UW-5', 'pending');
        $this->booking('FER-UW-6', 'pending', [], '10:00', '12:00');

        $this->actingAs($this->supply)->postJson(route('calendar.request-availability-check', $request), ['venue' => 'Covered Court'])
            ->assertOk()->assertJsonPath('available', true);

        $this->booking('FER-UW-7', 'approved', [], '08:00', '09:30');
        $this->actingAs($this->supply)->postJson(route('calendar.request-availability-check', $request), ['venue' => 'Covered Court'])
            ->assertOk()->assertJsonPath('available', false);
    }

    public function test_mark_unavailable_is_removed(): void
    {
        $request = $this->booking('FER-UW-8', 'pending');

        $this->actingAs($this->supply)->post(route('supply-office.requests.availability-review'), ['id' => $request->id, 'decision' => 'unavailable', 'notes' => 'No'])
            ->assertSessionHasErrors('decision');
        $this->assertSame('pending', $request->fresh()->supply_office_review_status);
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_the_supply_office_card_shows_actions_only_after_the_calendar_check(): void
    {
        $request = $this->booking('FER-UW-9', 'pending');

        $html = $this->actingAs($this->supply)->get(route('request.show', $request))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-availability-confirm="admin-supply-availability-' . $request->id . '"[^>]*class="hidden/', $html);
        $this->assertStringContainsString('data-availability-conflict-action="admin-supply-availability-' . $request->id . '"', $html);
        $this->assertStringContainsString('Suggest a New Schedule', $html);
        $this->assertStringNotContainsString('Mark Unavailable', $html);
    }

    private function suggest(FacilityRequest $request, string $start, string $end)
    {
        return $this->actingAs($this->supply)->post(route('supply-office.requests.revise'), [
            'facility_request_id' => $request->id,
            'venue' => ['Covered Court'],
            'start_date' => $this->date,
            'end_date' => $this->date,
            'start_time' => $start,
            'end_time' => $end,
            'revision_reason' => 'The original time is not available for this venue.',
        ]);
    }

    public function test_a_suggestion_cannot_collide_with_an_approved_booking_but_pending_only_warns(): void
    {
        $request = $this->booking('FER-UW-10', 'pending');
        $this->booking('FER-UW-11', 'approved', [], '13:00', '15:00');
        $this->booking('FER-UW-12', 'pending', [], '16:00', '18:00');

        $this->suggest($request, '14:00', '16:00')->assertSessionHasErrors('conflict');
        $this->assertSame(0, RevisionHistory::where('facility_request_id', $request->id)->count());

        $this->suggest($request, '16:30', '17:30')->assertSessionHasNoErrors()
            ->assertSessionHas('warning', fn ($w) => str_contains((string) $w, 'FER-UW-12'));
        $this->assertSame(1, RevisionHistory::where('facility_request_id', $request->id)->count());
        $this->assertSame('09:00', substr((string) $request->fresh()->start_time, 0, 5), 'A suggestion does not change the original schedule.');
    }

    public function test_requestor_form_names_a_tentative_overlap_but_blocks_an_approved_one(): void
    {
        $this->booking('FER-UW-13', 'pending');
        $query = ['venues' => ['Covered Court'], 'start_date' => $this->date, 'end_date' => $this->date, 'start_time' => '10:00', 'end_time' => '11:30'];

        $result = $this->actingAs($this->requestor)->getJson(route('requestor.venue.availability', $query))->assertOk()->json('venues.0');
        $this->assertSame('pending_conflict', $result['status']);
        $this->assertTrue($result['can_submit']);
        $this->assertStringContainsString('FER-UW-13', $result['pending_notice']);

        $this->booking('FER-UW-14', 'approved', [], '11:00', '12:00');
        $result = $this->actingAs($this->requestor)->getJson(route('requestor.venue.availability', $query))->assertOk()->json('venues.0');
        $this->assertSame('unavailable', $result['status']);
        $this->assertFalse($result['can_submit']);
    }

    public function test_decline_is_not_offered_or_accepted(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'username' => config('pitfr.final_approver_username')]);
        $request = $this->booking('FER-UW-15', 'pending', ['supply_office_review_status' => 'confirmed']);

        $this->assertFalse(app(SupplyOfficeDecisions::class)->actionsFor($request)['decline']);
        $this->actingAs($admin)->get(route('request.show', $request))->assertOk()->assertDontSee('Decline Reservation');
        $this->actingAs($admin)->post(route('request.supply.decline', $request), ['decline_reason' => 'policy', 'notes' => 'Not allowed for this activity.'])
            ->assertSessionHasErrors('decision');
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_supply_office_personnel_cannot_give_final_approval(): void
    {
        $request = $this->booking('FER-UW-16', 'pending', ['supply_office_review_status' => 'confirmed']);

        $this->actingAs($this->supply)->post(route('request.supply.final-approval', $request))->assertForbidden();
        $this->assertSame('pending', $request->fresh()->status);
    }
}
