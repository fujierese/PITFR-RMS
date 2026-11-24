<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\NewFacilityRequestNotification;
use App\Notifications\RequestStatusChanged;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * B: OUTSIDER → IGP → VENUE CUSTODIAN → SUPPLY OFFICE → IGP → OUTSIDER, with payment
 * handled outside PITFR and the Supply Office keeping final authority.
 */
class OutsiderInquiryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $outsider;

    private User $igp;

    private User $custodian;

    private User $supplyOffice;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->date = now()->addDays(10)->toDateString();
        $this->outsider = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'outsider',
            'name' => 'Rosa Client',
            'contact_number' => '09170000000',
            'office_or_organization' => 'Leyte Events Co.',
            'verification_status' => 'verified',
        ]);
        $this->igp = User::factory()->create(['role' => 'igp', 'name' => 'Ina IGP']);
        $this->custodian = User::factory()->create(['role' => 'custodian-venue', 'name' => 'Gym Custodian']);
        $this->supplyOffice = User::factory()->create(['role' => 'admin', 'username' => config('pitfr.final_approver_username')]);
        Venue::create(['name' => 'Covered Court', 'capacity' => 500, 'custodian_id' => $this->custodian->id, 'is_active' => true]);
    }

    private function submitInquiry(array $overrides = []): FacilityRequest
    {
        $this->actingAs($this->outsider)->post(route('requestor.store'), array_merge([
            'department' => 'Leyte Events Co.',
            'organization_name' => 'Leyte Events Co.',
            'name_of_activity' => 'Company Anniversary',
            'purpose' => 'Annual gathering',
            'expected_participants' => 120,
            'venue' => ['Covered Court'],
            'venue_schedules' => ['Covered Court' => [
                'start_date' => $this->date, 'end_date' => $this->date,
                'start_time' => '13:00', 'end_time' => '17:00',
                'reservation_duration' => 'specific_time',
            ]],
            'contact_person' => 'Rosa Client',
            'contact_number' => '09171112222',
            'contact_email' => 'rosa@example.test',
            'additional_remarks' => 'We will bring our own sound system.',
            'e_signature_file' => UploadedFile::fake()->create('signature.png', 50, 'image/png'),
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
            'proposal_submission_type' => 'final',
        ], $overrides))->assertSessionHasNoErrors();

        return FacilityRequest::query()->where('requested_by_id', $this->outsider->id)->latest('id')->firstOrFail();
    }

    private function approveBySupplyOffice(FacilityRequest $inquiry, ?string $holdUntil = null): void
    {
        $this->actingAs($this->custodian)->post(route('custodian.update'), ['id' => $inquiry->id, 'action' => 'approve'])->assertSessionHasNoErrors();
        $this->actingAs($this->supplyOffice)->post(route('supply-office.requests.availability-review'), array_filter([
            'id' => $inquiry->id,
            'decision' => 'available',
            'outsider_hold_until' => $holdUntil,
        ]))->assertSessionHasNoErrors();
    }

    public function test_full_workflow_from_inquiry_to_confirmed_reservation(): void
    {
        // 1. Outsider submits: the inquiry waits for IGP; custodians do not see it yet.
        $inquiry = $this->submitInquiry();
        $this->assertSame('submitted', $inquiry->outsider_stage);
        $this->assertSame('rosa@example.test', $inquiry->contact_email);
        $this->assertSame('We will bring our own sound system.', $inquiry->additional_remarks);
        Notification::assertSentTo($this->igp, RequestStatusChanged::class, fn ($n) => $n->status === 'outsider_inquiry_submitted');

        $this->actingAs($this->custodian)->get(route('request.show', $inquiry->id))->assertForbidden();
        $this->actingAs($this->custodian)->get(route('custodian.index'))->assertOk()->assertDontSee($inquiry->control_number);
        $this->actingAs($this->custodian)->post(route('custodian.update'), ['id' => $inquiry->id, 'action' => 'approve'])
            ->assertSessionHasErrors('action');

        // 2. IGP opens and forwards it; the venue custodian is notified.
        $this->actingAs($this->igp)->get(route('request.show', $inquiry->id))->assertOk()
            ->assertSee('Forward to venue custodian')
            ->assertSee('09171112222');
        $this->assertSame('igp_review', $inquiry->fresh()->outsider_stage);
        $this->actingAs($this->igp)->post(route('igp.forward', $inquiry), ['igp_review_notes' => 'Details complete.'])->assertSessionHasNoErrors();
        $this->assertSame('custodian_review', $inquiry->fresh()->outsider_stage);
        Notification::assertSentTo($this->custodian, NewFacilityRequestNotification::class);
        Notification::assertSentTo($this->outsider, RequestStatusChanged::class, fn ($n) => $n->status === 'outsider_inquiry_forwarded');

        // 3. Custodian endorses; the Supply Office is notified.
        $this->actingAs($this->custodian)->get(route('custodian.index'))->assertOk()->assertSee($inquiry->control_number);
        $this->actingAs($this->custodian)->post(route('custodian.update'), ['id' => $inquiry->id, 'action' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('endorsed', $inquiry->fresh()->outsider_stage);
        Notification::assertSentTo($this->supplyOffice, RequestStatusChanged::class, fn ($n) => $n->status === 'outsider_inquiry_endorsed');

        // 4. Supply Office approves, subject to requirements, holding the schedule.
        $this->actingAs($this->supplyOffice)->post(route('supply-office.requests.availability-review'), [
            'id' => $inquiry->id,
            'decision' => 'available',
            'outsider_hold_until' => now()->addDays(5)->toDateString(),
        ])->assertSessionHasNoErrors();
        $inquiry->refresh();
        $this->assertSame('approved', $inquiry->outsider_stage);
        $this->assertSame('pending', $inquiry->status, 'Approval must not confirm the reservation automatically.');
        $this->assertTrue($inquiry->hasActiveOutsiderHold());
        Notification::assertSentTo($this->igp, RequestStatusChanged::class, fn ($n) => $n->status === 'outsider_inquiry_approved');

        // 5. Confirmation is blocked until IGP coordinates the requirements.
        $this->actingAs($this->supplyOffice)->post(route('request.supply.final-approval', $inquiry))->assertSessionHasErrors();
        $this->assertSame('pending', $inquiry->fresh()->status);

        // 6. IGP sends the requirements, then reports them complete.
        $this->actingAs($this->igp)->post(route('igp.coordinate', $inquiry), [
            'igp_coordination_notes' => 'Pay the facility fee at the Cashier by Friday.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('igp_coordination', $inquiry->fresh()->outsider_stage);
        Notification::assertSentTo($this->outsider, RequestStatusChanged::class, fn ($n) => $n->status === 'outsider_requirements_sent');
        $this->actingAs($this->igp)->post(route('igp.requirements', $inquiry), ['notes' => 'OR No. 55102 issued by the Cashier.'])->assertSessionHasNoErrors();
        Notification::assertSentTo($this->supplyOffice, RequestStatusChanged::class, fn ($n) => $n->status === 'outsider_requirements_reported');

        // 7. Supply Office confirms.
        $this->actingAs($this->supplyOffice)->post(route('request.supply.final-approval', $inquiry))->assertSessionHasNoErrors();
        $inquiry->refresh();
        $this->assertSame('approved', $inquiry->status);
        $this->assertSame('confirmed', $inquiry->outsider_stage);

        // History records every important step.
        foreach (['outsider_inquiry_submitted', 'igp_review_started', 'igp_forwarded_to_custodian', 'outsider_inquiry_endorsed', 'outsider_inquiry_approved', 'igp_coordination_started', 'igp_requirements_reported'] as $action) {
            $this->assertDatabaseHas('request_histories', ['facility_request_id' => $inquiry->id, 'action' => $action]);
        }
    }

    public function test_confirmed_outsider_reservation_blocks_internal_bookings(): void
    {
        $inquiry = $this->submitInquiry();
        $inquiry->update(['outsider_stage' => 'igp_coordination', 'status' => 'approved', 'venue_status' => 'approved', 'supply_office_review_status' => 'confirmed', 'equipment_status' => 'approved']);

        $result = app(AvailabilityService::class)->checkVenueAvailability('Covered Court', Carbon::parse($this->date . ' 14:00'), Carbon::parse($this->date . ' 15:00'));
        $this->assertFalse($result['available']);
    }

    public function test_hold_blocks_internal_bookings_until_the_deadline_and_cancelling_releases_it(): void
    {
        $inquiry = $this->submitInquiry();
        $this->actingAs($this->igp)->post(route('igp.forward', $inquiry))->assertSessionHasNoErrors();
        $this->approveBySupplyOffice($inquiry, now()->addDays(3)->toDateString());

        $slot = [Carbon::parse($this->date . ' 14:00'), Carbon::parse($this->date . ' 15:00')];
        $this->assertFalse(app(AvailabilityService::class)->checkVenueAvailability('Covered Court', ...$slot)['available'], 'Held schedule must block internal requests.');

        // After the deadline the hold lapses on its own.
        $this->travelTo(now()->addDays(4));
        $this->assertTrue(app(AvailabilityService::class)->checkVenueAvailability('Covered Court', ...$slot)['available']);
        $this->travelBack();

        // The Supply Office can cancel the approval, which releases the schedule.
        $this->actingAs($this->supplyOffice)->post(route('supply-office.outsider.cancel-approval', $inquiry), ['reason' => 'Requirements not completed by the deadline.'])
            ->assertSessionHasNoErrors();
        $inquiry->refresh();
        $this->assertSame('cancelled', $inquiry->outsider_stage);
        $this->assertNull($inquiry->outsider_hold_until);
        Notification::assertSentTo($this->igp, RequestStatusChanged::class, fn ($n) => $n->status === 'request_cancelled');
    }

    public function test_approval_without_deadline_does_not_hold_the_schedule(): void
    {
        $inquiry = $this->submitInquiry();
        $this->actingAs($this->igp)->post(route('igp.forward', $inquiry))->assertSessionHasNoErrors();
        $this->approveBySupplyOffice($inquiry);

        $this->assertFalse($inquiry->fresh()->hasActiveOutsiderHold());
        $this->assertTrue(app(AvailabilityService::class)->checkVenueAvailability('Covered Court', Carbon::parse($this->date . ' 14:00'), Carbon::parse($this->date . ' 15:00'))['available']);
    }

    public function test_custodian_non_endorsement_goes_to_the_supply_office(): void
    {
        $inquiry = $this->submitInquiry();
        $this->actingAs($this->igp)->post(route('igp.forward', $inquiry))->assertSessionHasNoErrors();

        $this->actingAs($this->custodian)->post(route('custodian.update'), [
            'id' => $inquiry->id, 'action' => 'reject', 'notes' => 'Gym floor maintenance that week.',
        ])->assertSessionHasNoErrors();

        $inquiry->refresh();
        $this->assertSame('not_endorsed', $inquiry->outsider_stage);
        $this->assertSame('pending', $inquiry->status, 'The custodian cannot reject; the Supply Office decides.');
        Notification::assertSentTo($this->supplyOffice, RequestStatusChanged::class, fn ($n) => $n->status === 'outsider_inquiry_not_endorsed');
    }

    public function test_igp_cannot_approve_reject_or_see_internal_requests(): void
    {
        $inquiry = $this->submitInquiry();
        $internal = FacilityRequest::factory()->create(['requested_by_id' => User::factory()->create(['role' => 'requestor', 'requestor_type' => 'faculty'])->id]);

        $this->actingAs($this->igp)->post(route('supply-office.update'), ['id' => $inquiry->id, 'action' => 'approve'])->assertForbidden();
        $this->actingAs($this->igp)->post(route('custodian.update'), ['id' => $inquiry->id, 'action' => 'approve'])->assertForbidden();
        $this->actingAs($this->igp)->get(route('request.show', $internal->id))->assertForbidden();
        $this->actingAs($this->igp)->get(route('supply-office.index'))->assertForbidden();
    }

    public function test_outsider_sees_only_their_own_inquiries_and_cannot_use_igp_actions(): void
    {
        $inquiry = $this->submitInquiry();
        $other = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'outsider']);

        $this->actingAs($other)->get(route('request.show', $inquiry->id))->assertForbidden();
        $this->actingAs($this->outsider)->post(route('igp.forward', $inquiry))->assertForbidden();
        $this->actingAs($this->outsider)->get(route('request.show', $inquiry->id))->assertOk()
            ->assertSee('Outsider reservation inquiry')
            ->assertSee('Submitted');
    }

    public function test_internal_requests_are_unaffected(): void
    {
        $faculty = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'faculty']);
        $this->actingAs($faculty)->post(route('requestor.store'), [
            'department' => 'IT',
            'name_of_activity' => 'Faculty Seminar',
            'expected_participants' => 30,
            'venue' => ['Covered Court'],
            'venue_schedules' => ['Covered Court' => ['start_date' => $this->date, 'end_date' => $this->date, 'start_time' => '08:00', 'end_time' => '10:00', 'reservation_duration' => 'specific_time']],
            'e_signature_file' => UploadedFile::fake()->create('signature.png', 50, 'image/png'),
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 50, 'application/pdf'),
            'proposal_submission_type' => 'final',
        ]);

        $request = FacilityRequest::query()->where('requested_by_id', $faculty->id)->first();
        $this->assertNotNull($request);
        $this->assertNull($request->outsider_stage);
        $this->actingAs($this->custodian)->get(route('custodian.index'))->assertOk()->assertSee($request->control_number);
        Notification::assertNotSentTo($this->igp, RequestStatusChanged::class);
    }
}
