<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\ReservationConflict;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\ReservationDecision;
use App\Services\SupplyOfficeDecisions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Additional Part 2: context-aware Supply Office actions and human conflict
 * resolution for approved urgent reservations.
 */
class UrgentConflictResolutionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $courtCustodian;
    private User $hallCustodian;
    private User $normalRequester;
    private User $urgentRequester;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'username' => config('pitfr.final_approver_username')]);
        $this->courtCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $this->hallCustodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create(['name' => 'Covered Court', 'capacity' => 300, 'custodian_id' => $this->courtCustodian->id, 'is_active' => true]);
        Venue::create(['name' => 'Mini Hall', 'capacity' => 100, 'custodian_id' => $this->hallCustodian->id, 'is_active' => true]);
        $this->normalRequester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $this->urgentRequester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'faculty']);
        $this->date = now()->addDays(5)->toDateString();
    }

    private function makeRequest(User $owner, string $control, array $extra = []): FacilityRequest
    {
        $request = FacilityRequest::create(array_merge([
            'control_number' => $control,
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Activity ' . $control,
            'expected_participants' => 40,
            'start_date' => $this->date,
            'end_date' => $this->date,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => ['Covered Court'],
            'requested_by_id' => $owner->id,
            'status' => 'pending',
            'venue_status' => 'approved',
            'supply_office_review_status' => 'confirmed',
            'equipment_status' => 'approved',
        ], $extra));
        $request->syncRelationalItems();

        return $request->fresh();
    }

    private function approvedNormal(): FacilityRequest
    {
        return $this->makeRequest($this->normalRequester, 'FER-NORM-1', ['status' => 'approved']);
    }

    private function urgentReady(): FacilityRequest
    {
        return $this->makeRequest($this->urgentRequester, 'FER-URG-1', [
            'is_emergency' => true,
            'emergency_justification' => 'Institute-wide assembly',
            'proposal_file' => 'memo.pdf',
            'urgent_document_status' => 'validated',
            'expected_participants' => 250,
        ]);
    }

    public function test_normal_reservation_without_conflict_offers_only_approve_and_decline(): void
    {
        $request = $this->makeRequest($this->normalRequester, 'FER-NORM-2');

        $this->actingAs($this->admin)->get(route('request.show', $request))
            ->assertOk()
            ->assertSee('Approve Reservation')
            ->assertSee('Decline Reservation')
            ->assertDontSee('Assign Alternative Venue')
            ->assertDontSee('Request Revision')
            ->assertDontSee('id="open-reschedule-form"', false)
            ->assertDontSee('data-conflict-panel', false);
    }

    public function test_submitted_urgent_request_does_not_open_conflict_actions(): void
    {
        $normal = $this->approvedNormal();
        $this->makeRequest($this->urgentRequester, 'FER-URG-2', ['is_emergency' => true, 'venue_status' => 'pending', 'supply_office_review_status' => 'pending']);

        $this->actingAs($this->admin)->get(route('request.show', $normal))
            ->assertOk()
            ->assertDontSee('data-conflict-panel', false);
        $this->assertSame(0, ReservationConflict::count());
    }

    public function test_urgent_approved_without_overlap_opens_nothing(): void
    {
        $urgent = $this->urgentReady();

        $this->actingAs($this->admin)->post(route('request.supply.final-approval', $urgent))->assertSessionHasNoErrors();

        $this->assertSame('approved', $urgent->fresh()->status);
        $this->assertSame(0, ReservationConflict::count());
    }

    public function test_urgent_overlap_needs_review_then_opens_a_conflict_without_changing_the_other_booking(): void
    {
        $normal = $this->approvedNormal();
        $urgent = $this->urgentReady()->fresh();
        $urgent->update(['supply_office_review_status' => 'pending']);

        // Without the review: refused, as before.
        $this->actingAs($this->admin)->post(route('supply-office.requests.availability-review'), [
            'id' => $urgent->id, 'decision' => 'available', 'urgent_document_validated' => '1',
        ])->assertSessionHasErrors('decision');

        $this->actingAs($this->admin)->post(route('supply-office.requests.availability-review'), [
            'id' => $urgent->id, 'decision' => 'available', 'urgent_document_validated' => '1', 'urgent_conflict_reviewed' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $urgent->fresh()->supply_office_review_status);

        $this->actingAs($this->admin)->post(route('request.supply.final-approval', $urgent))->assertSessionHasNoErrors();
        $this->assertSame('approved', $urgent->fresh()->status);

        // The other booking is unchanged, but now has an open conflict and a short notice.
        $normal->refresh();
        $this->assertSame('approved', $normal->status);
        $this->assertSame(['Covered Court'], $normal->getVenueNames());
        $conflict = ReservationConflict::sole();
        $this->assertSame('open', $conflict->status);
        $this->assertSame($normal->id, $conflict->affected_request_id);
        $notice = $this->normalRequester->notifications()->where('type', ReservationDecision::class)->sole();
        $this->assertSame('Your reservation requires administrative adjustment.', $notice->data['body']);

        // Running the check again does not duplicate anything.
        app(SupplyOfficeDecisions::class)->recordUrgentConflicts($urgent->fresh(), $this->admin);
        $this->assertSame(1, ReservationConflict::count());
        $this->assertSame(1, $this->normalRequester->notifications()->where('type', ReservationDecision::class)->count());

        $this->actingAs($this->admin)->get(route('request.show', $normal))
            ->assertOk()
            ->assertSee('data-conflict-panel', false)
            ->assertSee('Assign Alternative Venue')
            ->assertSee('Mini Hall')
            ->assertSee('Decline Reservation');
    }

    public function test_supply_office_assigns_an_alternative_venue_to_the_affected_booking(): void
    {
        [$normal, $urgent] = $this->openConflict();

        $this->actingAs($this->admin)->post(route('request.supply.decision', $normal), [
            'decision' => 'alternative_venue', 'venue' => 'Mini Hall',
            'reason' => 'Covered Court is needed for the institute assembly',
            'explanation' => 'Your meeting fits Mini Hall at the same date and time.',
        ])->assertSessionHasNoErrors();

        $normal->refresh();
        $this->assertSame('approved', $normal->status);
        $this->assertSame(['Mini Hall'], $normal->getVenueNames());
        $this->assertSame('resolved', ReservationConflict::sole()->status);
        $this->assertSame('alternative_venue', ReservationConflict::sole()->resolution);
        $this->assertDatabaseHas('request_histories', ['facility_request_id' => $normal->id, 'action' => 'alternative_venue_assigned']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supply_office_alternative_venue']);

        // Requestor: short notice that opens the full explanation.
        $notice = $this->normalRequester->notifications()->where('type', ReservationDecision::class)->get()
            ->first(fn ($n) => $n->data['status'] === 'alternative_venue_assigned');
        $this->assertSame('An alternative venue has been assigned.', $notice->data['body']);
        $this->actingAs($this->normalRequester)->get(route('notifications.open', $notice->id))
            ->assertRedirect(route('request.show', $normal) . '#administrative-decisions');
        $this->actingAs($this->normalRequester)->get(route('request.show', $normal))
            ->assertOk()
            ->assertSee('id="administrative-decisions"', false)
            ->assertSee('Your meeting fits Mini Hall at the same date and time.')
            ->assertDontSee($urgent->name_of_activity);

        // Both venues' custodians are told.
        $this->assertTrue($this->hallCustodian->notifications()->where('type', ReservationDecision::class)->exists());
        $this->assertTrue($this->courtCustodian->notifications()->where('type', ReservationDecision::class)->exists());

        // Another requestor cannot open it.
        $stranger = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $this->actingAs($stranger)->get(route('request.show', $normal))->assertForbidden();
    }

    public function test_supply_office_can_keep_the_venue_for_the_normal_booking_and_move_the_urgent_one(): void
    {
        [$normal, $urgent] = $this->openConflict();
        $options = app(SupplyOfficeDecisions::class)->feasibleSchedules($urgent);
        $this->assertNotEmpty($options);

        $this->actingAs($this->admin)->post(route('request.supply.decision', $urgent), [
            'decision' => 'reschedule', 'start_date' => $options[0]['start_date'],
            'reason' => 'The court is committed to the graduation rehearsal',
            'explanation' => 'Your assembly moves to the next free day at the same time.',
        ])->assertSessionHasNoErrors();

        $this->assertSame($options[0]['start_date'], $urgent->fresh()->start_date->toDateString());
        $this->assertSame($this->date, $normal->fresh()->start_date->toDateString());
        $this->assertSame('resolved', ReservationConflict::sole()->status);
        $this->assertTrue($this->urgentRequester->notifications()->where('type', ReservationDecision::class)->exists());
    }

    public function test_alternative_venue_is_not_offered_when_none_is_free(): void
    {
        [$normal] = $this->openConflict();
        $this->makeRequest($this->normalRequester, 'FER-HALL-1', ['status' => 'approved', 'venue' => ['Mini Hall']]);

        $this->assertSame([], app(SupplyOfficeDecisions::class)->alternativeVenues($normal->fresh()));
        $this->actingAs($this->admin)->get(route('request.show', $normal))->assertDontSee('Assign Alternative Venue');
        $this->actingAs($this->admin)->post(route('request.supply.decision', $normal), [
            'decision' => 'alternative_venue', 'venue' => 'Mini Hall', 'reason' => 'x x x', 'explanation' => 'This should not be accepted.',
        ])->assertSessionHasErrors('decision');
    }

    public function test_reschedule_is_not_offered_when_no_date_is_free(): void
    {
        $request = $this->makeRequest($this->normalRequester, 'FER-NORM-5');
        $this->assertNull(app(SupplyOfficeDecisions::class)->scheduleConcern($request));
        $this->assertSame([], app(SupplyOfficeDecisions::class)->actionsFor($request)['schedules']);
    }

    public function test_decline_needs_a_reason_and_an_explanation(): void
    {
        $request = $this->makeRequest($this->normalRequester, 'FER-NORM-3');

        $this->actingAs($this->admin)->post(route('request.supply.decline', $request), [])
            ->assertSessionHasErrors(['decline_reason', 'notes']);
        $this->assertSame('pending', $request->fresh()->status);

        $this->actingAs($this->admin)->post(route('request.supply.decline', $request), [
            'decline_reason' => 'policy', 'notes' => 'Overnight use of the court is not allowed.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $request->fresh()->status);
        $this->actingAs($this->normalRequester)->get(route('request.show', $request))
            ->assertSee('Overnight use of the court is not allowed.');
    }

    public function test_request_revision_appears_only_when_something_needs_correcting(): void
    {
        $fine = $this->makeRequest($this->normalRequester, 'FER-NORM-4');
        $this->assertSame([], app(SupplyOfficeDecisions::class)->revisionReasons($fine));

        $missingMemo = $this->makeRequest($this->urgentRequester, 'FER-URG-9', ['is_emergency' => true]);
        $this->assertNotEmpty(app(SupplyOfficeDecisions::class)->revisionReasons($missingMemo));
        $this->actingAs($this->admin)->post(route('request.supply.decision', $missingMemo), [
            'decision' => 'revision', 'reason' => 'Attach the memorandum order', 'explanation' => 'Upload the signed memorandum order, then resubmit.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('needs_reschedule', $missingMemo->fresh()->status);
        $this->actingAs($this->admin)->post(route('request.supply.decision', $fine), [
            'decision' => 'revision', 'reason' => 'x x x', 'explanation' => 'Nothing to correct here.',
        ])->assertSessionHasErrors('decision');
    }

    /** @return array{0: FacilityRequest, 1: FacilityRequest} */
    private function openConflict(): array
    {
        $normal = $this->approvedNormal();
        $urgent = $this->urgentReady();
        $urgent->addHistory('urgent_conflict_acknowledged', 'Reviewed', $this->admin->id);
        $this->actingAs($this->admin)->post(route('request.supply.final-approval', $urgent))->assertSessionHasNoErrors();
        $this->assertSame(1, ReservationConflict::where('status', 'open')->count());

        return [$normal->fresh(), $urgent->fresh()];
    }
}
