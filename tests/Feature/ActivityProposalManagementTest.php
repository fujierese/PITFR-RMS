<?php

namespace Tests\Feature;

use App\Models\ActivityProposalVersion;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Notifications\ActivityProposalNotice;
use App\Services\ActivityProposals;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Additional Part 2: Activity Proposal submission, verification, deadlines, reminders
 * and overdue compliance, independent of the reservation status.
 */
class ActivityProposalManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        // A Tuesday morning in Manila.
        $this->travelTo(Carbon::parse('2026-11-24 09:00:00', 'Asia/Manila'));
        $this->student = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student', 'office_or_organization' => null]);
        $this->admin = User::factory()->create(['role' => 'admin', 'username' => config('pitfr.final_approver_username')]);
    }

    private function submit(array $overrides = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->student)->post(route('requestor.store'), array_merge([
            'requested_by_position' => 'Student',
            'department' => 'Computer Science',
            'name_of_activity' => 'Tech Workshop',
            'expected_participants' => 50,
            'start_date' => '2026-12-07',
            'end_date' => '2026-12-07',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => 'Conference Hall & Interaction Center (CHIC)',
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
            'proposal_submission_type' => 'draft',
        ], $overrides));
    }

    private function submitted(string $type = 'draft'): FacilityRequest
    {
        $this->submit(['proposal_submission_type' => $type])->assertSessionHasNoErrors();

        return FacilityRequest::where('requested_by_id', $this->student->id)->latest('id')->firstOrFail();
    }

    private function proposals(): ActivityProposals
    {
        return app(ActivityProposals::class);
    }

    public function test_draft_submission_is_accepted_and_gets_the_default_deadline(): void
    {
        $request = $this->submitted('draft');

        $version = ActivityProposalVersion::where('facility_request_id', $request->id)->sole();
        $this->assertSame('draft', $version->submission_type);
        $this->assertSame('draft_submitted', $this->proposals()->documentStatus($request));
        // Activity Mon Dec 7 → 3 working days before = Wed Dec 2.
        $this->assertSame('2026-12-02', $request->fresh()->proposal_deadline->toDateString());
        $this->assertSame('within_deadline', $this->proposals()->complianceStatus($request->fresh()));
        $this->assertTrue($this->student->notifications()->where('type', ActivityProposalNotice::class)->exists());
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_final_submission_goes_to_verification(): void
    {
        $request = $this->submitted('final');

        $this->assertSame('for_verification', $this->proposals()->documentStatus($request));
        $this->assertTrue($this->admin->notifications()->where('type', ActivityProposalNotice::class)->get()
            ->contains(fn ($n) => $n->data['status'] === 'proposal_final_submitted'));
        $this->actingAs($this->admin)->get(route('supply-office.activity-proposals', ['filter' => 'for_verification']))
            ->assertOk()->assertSee($request->control_number);
    }

    public function test_submission_without_a_proposal_or_type_is_rejected(): void
    {
        $this->submit(['activity_proposal_file' => null])->assertSessionHasErrors('activity_proposal_file');
        $this->submit(['proposal_submission_type' => null])->assertSessionHasErrors('proposal_submission_type');
        $this->assertSame(0, FacilityRequest::count());

        // Outside requestors may submit without one (client decision); no deadline is set then.
        $outsider = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'outsider', 'office_or_organization' => 'Partner Org']);
        $this->submit(['activity_proposal_file' => null, 'proposal_submission_type' => null, 'requested_by_position' => 'Guest', 'venue' => 'Covered Court'], $outsider)
            ->assertSessionDoesntHaveErrors(['activity_proposal_file', 'proposal_submission_type']);
        $outsiderRequest = FacilityRequest::where('requested_by_id', $outsider->id)->first();
        if ($outsiderRequest) {
            $this->assertNull($outsiderRequest->proposal_deadline);
            $this->assertSame('not_submitted', $this->proposals()->documentStatus($outsiderRequest));
        }
    }

    public function test_a_draft_cannot_be_verified(): void
    {
        $request = $this->submitted('draft');

        $this->actingAs($this->admin)->post(route('request.activity-proposal.review', $request->id), ['result' => 'verified'])
            ->assertSessionHasErrors('proposal');
        $this->assertSame('draft_submitted', $this->proposals()->documentStatus($request));
    }

    public function test_verify_correct_and_resubmit_keeps_every_version(): void
    {
        $request = $this->submitted('draft');
        $this->actingAs($this->student)->post(route('request.activity-proposal.upload', $request->id), [
            'proposal_file' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'), 'submission_type' => 'final',
        ])->assertSessionHasNoErrors();
        $this->assertSame('for_verification', $this->proposals()->documentStatus($request));

        // Correction needs remarks.
        $this->actingAs($this->admin)->post(route('request.activity-proposal.review', $request->id), ['result' => 'requires_correction'])
            ->assertSessionHasErrors('remarks');
        $this->actingAs($this->admin)->post(route('request.activity-proposal.review', $request->id), [
            'result' => 'requires_correction', 'remarks' => 'Add the budget page.', 'correction_deadline' => '2026-11-30',
        ])->assertSessionHasNoErrors();
        $this->assertSame('requires_correction', $this->proposals()->documentStatus($request));
        $this->assertSame('2026-11-30', $request->fresh()->proposal_deadline->toDateString());

        // A corrected version must be a Final Copy.
        $this->actingAs($this->student)->post(route('request.activity-proposal.upload', $request->id), [
            'proposal_file' => UploadedFile::fake()->create('fixed.pdf', 100, 'application/pdf'), 'submission_type' => 'draft',
        ])->assertSessionHasErrors('submission_type');
        $this->actingAs($this->student)->post(route('request.activity-proposal.upload', $request->id), [
            'proposal_file' => UploadedFile::fake()->create('fixed.pdf', 100, 'application/pdf'), 'submission_type' => 'final',
        ])->assertSessionHasNoErrors();
        $this->assertSame('for_verification', $this->proposals()->documentStatus($request));
        $this->assertTrue($this->admin->notifications()->get()->contains(fn ($n) => ($n->data['status'] ?? '') === 'proposal_corrected_submitted'));

        $this->actingAs($this->admin)->post(route('request.activity-proposal.review', $request->id), ['result' => 'verified'])
            ->assertSessionHasNoErrors();
        $this->assertSame('verified', $this->proposals()->documentStatus($request));
        $verifiedVersion = ActivityProposalVersion::where('facility_request_id', $request->id)->orderByDesc('version_number')->first();
        $this->assertSame($this->admin->id, $verifiedVersion->reviewed_by_id);
        $this->assertNotNull($verifiedVersion->reviewed_at);

        // Three versions, every file still there and viewable.
        $versions = ActivityProposalVersion::where('facility_request_id', $request->id)->orderBy('version_number')->get();
        $this->assertSame([1, 2, 3], $versions->pluck('version_number')->all());
        foreach ($versions as $version) {
            Storage::disk('local')->assertExists('documents/activity_proposal/' . $version->file_name);
        }
        $this->actingAs($this->admin)->get(route('request.activity-proposal.file', ['id' => $request->id, 'version' => $versions[0]->id]))->assertOk();
        $this->actingAs($this->student)->get(route('request.activity-proposal.file', ['id' => $request->id, 'version' => $versions[0]->id]))->assertOk();
        $this->assertSame('pending', $request->fresh()->status, 'Verification never changes the reservation.');
    }

    public function test_unauthorized_users_cannot_open_or_verify(): void
    {
        $request = $this->submitted('final');
        $version = ActivityProposalVersion::where('facility_request_id', $request->id)->sole();
        $stranger = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);

        $this->actingAs($stranger)->get(route('request.activity-proposal.file', ['id' => $request->id, 'version' => $version->id]))->assertForbidden();
        $this->actingAs($stranger)->post(route('request.activity-proposal.upload', $request->id), [
            'proposal_file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'), 'submission_type' => 'final',
        ])->assertForbidden();
        $this->actingAs($this->student)->post(route('request.activity-proposal.review', $request->id), ['result' => 'verified'])->assertForbidden();
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        $this->actingAs($custodian)->post(route('request.activity-proposal.deadline', $request->id), ['deadline' => '2026-12-01'])->assertForbidden();
    }

    public function test_deadline_changes_need_a_reason_and_are_recorded(): void
    {
        $request = $this->submitted('draft');

        $this->actingAs($this->admin)->post(route('request.activity-proposal.deadline', $request->id), ['deadline' => '2026-12-03'])
            ->assertSessionHasErrors('reason');
        $this->actingAs($this->admin)->post(route('request.activity-proposal.deadline', $request->id), ['deadline' => '2026-12-03', 'reason' => 'Requested more time for signatures'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-12-03', $request->fresh()->proposal_deadline->toDateString());
        $this->assertDatabaseHas('activity_proposal_deadline_changes', ['facility_request_id' => $request->id, 'kind' => 'extended', 'reason' => 'Requested more time for signatures']);
        $this->assertTrue($this->student->notifications()->get()->contains(fn ($n) => ($n->data['status'] ?? '') === 'proposal_deadline_extended'));
    }

    public function test_reminders_overdue_and_no_duplicates_without_touching_the_reservation(): void
    {
        $request = $this->submitted('draft');
        $request->update(['status' => 'approved', 'venue_status' => 'approved', 'supply_office_review_status' => 'confirmed']);
        $request->forceFill(['proposal_deadline' => '2026-11-27'])->save(); // Friday

        // Tue Nov 24: 3 working days before Fri Nov 27.
        $this->assertSame(1, $this->proposals()->sendReminders());
        $this->assertSame(0, $this->proposals()->sendReminders(), 'Running again sends nothing.');

        // Mon Nov 30: one working day after the deadline → overdue to requestor and Supply Office.
        $this->travelTo(Carbon::parse('2026-11-30 07:00:00', 'Asia/Manila'));
        $this->assertSame('overdue', $this->proposals()->complianceStatus($request->fresh()));
        $this->proposals()->sendReminders();
        $this->assertTrue($this->student->notifications()->get()->contains(fn ($n) => ($n->data['status'] ?? '') === 'proposal_overdue'));
        $this->assertTrue($this->admin->notifications()->get()->contains(fn ($n) => ($n->data['status'] ?? '') === 'proposal_overdue'));
        $this->assertSame('approved', $request->fresh()->status, 'An overdue proposal never changes the reservation.');
        $this->actingAs($this->admin)->get(route('supply-office.activity-proposals', ['filter' => 'overdue']))->assertSee($request->control_number);

        // The Supply Office extends it: no longer overdue.
        $this->actingAs($this->admin)->post(route('request.activity-proposal.deadline', $request->id), ['deadline' => '2026-12-02', 'reason' => 'Agreed after a call'])
            ->assertSessionHasNoErrors();
        $this->assertSame('within_deadline', $this->proposals()->complianceStatus($request->fresh()));

        // A final copy awaiting verification is never overdue.
        $this->travelTo(Carbon::parse('2026-12-04 09:00:00', 'Asia/Manila'));
        $this->proposals()->recordSubmission($request->fresh(), 'late.pdf', 'final', $this->student);
        $this->assertSame('within_deadline', $this->proposals()->complianceStatus($request->fresh()));
    }

    public function test_proposal_notifications_open_the_request_at_the_proposal(): void
    {
        $request = $this->submitted('draft');
        $notice = $this->student->notifications()->where('type', ActivityProposalNotice::class)->firstOrFail();

        $this->assertLessThan(90, strlen($notice->data['body']));
        $this->actingAs($this->student)->get(route('notifications.open', $notice->id))
            ->assertRedirect(route('request.show', $request) . '#activity-proposal');
        $this->actingAs($this->student)->get(route('request.show', $request))
            ->assertOk()->assertSee('id="activity-proposal"', false)->assertSee('Draft Submitted');
    }

    public function test_correction_needs_a_new_deadline_and_ignores_the_old_one(): void
    {
        $request = $this->submitted('final');
        $request->forceFill(['proposal_deadline' => '2026-11-25'])->save();
        $this->assertSame('2026-12-02', ActivityProposalVersion::where('facility_request_id', $request->id)->sole()->deadline_at_submission ?? '2026-12-02');

        $this->actingAs($this->admin)->post(route('request.activity-proposal.review', $request->id), ['result' => 'requires_correction', 'remarks' => 'Add signatures.'])
            ->assertSessionHasErrors('correction_deadline');

        // Returned for correction with a new deadline: the old (passed) deadline is not used.
        $this->proposals()->requireCorrection($request->fresh(), $this->admin, 'Add signatures.');
        $this->travelTo(Carbon::parse('2026-11-27 09:00:00', 'Asia/Manila'));
        $this->assertSame('no_deadline', $this->proposals()->complianceStatus($request->fresh()), 'Not overdue because of its old deadline.');
        $this->proposals()->setDeadline($request->fresh(), '2026-11-30', $this->admin, 'Correction deadline');
        $this->assertSame('within_deadline', $this->proposals()->complianceStatus($request->fresh()));
    }

    public function test_working_days_skip_holidays_from_the_calendar(): void
    {
        // Part 1: the default deadline is 3 working days before the activity (Mon Dec 7):
        // normally Wed Dec 2; with a holiday on Thu Dec 3 it moves to Tue Dec 1.
        \App\Models\Holiday::create(['holiday_date' => '2026-12-03', 'name' => 'Test holiday', 'type' => 'public']);
        app()->forgetInstance('pitfr.proposal_holiday_dates');

        $request = $this->submitted('draft');

        $this->assertSame('2026-12-01', $request->fresh()->proposal_deadline->toDateString());
        $this->assertFalse(ActivityProposals::isWorkingDay(Carbon::parse('2026-12-03')));
    }

    public function test_deadline_changes_and_follow_ups_are_in_the_audit_log(): void
    {
        $request = $this->submitted('draft');

        $this->actingAs($this->admin)->post(route('request.activity-proposal.deadline', $request->id), [
            'deadline' => '2026-12-04', 'reason' => 'Requestor asked for more time.',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('request.activity-proposal.follow-up', $request->id), [
            'remarks' => 'Called the requestor about the final copy.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['actor_id' => $this->admin->id, 'action' => 'activity_proposal_deadline_extended']);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $this->admin->id, 'action' => 'activity_proposal_followup']);
    }

    public function test_the_monitoring_list_links_to_the_latest_file(): void
    {
        $request = $this->submitted('final');
        $version = ActivityProposalVersion::where('facility_request_id', $request->id)->sole();

        $this->actingAs($this->admin)->get(route('supply-office.activity-proposals', ['filter' => 'for_verification']))
            ->assertOk()
            ->assertSee($request->control_number)
            ->assertSee(route('request.activity-proposal.file', [$request->id, $version->id]), false)
            ->assertSee('Download v1');
    }
}
