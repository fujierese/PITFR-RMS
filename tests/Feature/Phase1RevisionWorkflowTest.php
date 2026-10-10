<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\RevisionHistory;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class Phase1RevisionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /**
     * Create a basic approved request for testing revisions
     */
    private function createApprovedRequest(User $requester): FacilityRequest
    {
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $equipmentCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        $admin = User::factory()->create(['role' => 'admin']);

        $venue = Venue::create([
            'name' => 'Test Venue',
            'custodian_id' => $venueCustodian->id,
            'capacity' => 100,
        ]);

        $equipment = Equipment::create([
            'name' => 'Test Equipment',
            'custodian_id' => $equipmentCustodian->id,
            'quantity' => 10,
            'quantity_available' => 10,
        ]);

        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-100',
            'date_requested' => now()->toDateString(),
            'department' => 'Test Department',
            'name_of_activity' => 'Original Activity',
            'expected_participants' => 50,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => ['Test Venue'],
            'equipment' => ['Test Equipment'],
            'equipment_quantities' => ['Test Equipment' => 2],
            'requested_by_id' => $requester->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'approved_by_id' => $admin->id,
            'approved_by' => $admin->name,
            'approved_date' => now(),
        ]);

        $request->syncRelationalItems();

        return $request;
    }

    private function acceptPendingRevision(FacilityRequest $request, User $requester): void
    {
        $revision = RevisionHistory::where('facility_request_id', $request->id)->where('status', 'pending')->firstOrFail();

        $this->actingAs($requester)
            ->post(route('request.revision.respond', [$request, $revision]), ['response' => 'accept'])
            ->assertRedirect(route('request.show', $request));
    }

    public function test_admin_can_revise_approved_request(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createApprovedRequest($requester);

        $response = $this->actingAs($admin)
            ->post(route('supply-office.requests.revise'), [
                'facility_request_id' => $request->id,
                'start_date' => now()->addDays(2)->toDateString(),
                'end_date' => now()->addDays(2)->toDateString(),
                'start_time' => '10:00',
                'end_time' => '13:00',
                'venue' => ['Test Venue'],
                'equipment' => ['Test Equipment'],
                'equipment_quantities' => ['Test Equipment' => 2],
                'revision_reason' => 'Rescheduling due to venue conflict.',
            ]);

        $response->assertRedirect(route('supply-office.index'));
        $response->assertSessionHas('success');

        $request->refresh();
        $this->assertSame(now()->addDay()->toDateString(), $request->start_date->toDateString());
        $this->assertSame('09:00', $request->start_time);

        $this->acceptPendingRevision($request, $requester);
        $request->refresh();
        $this->assertSame(now()->addDays(2)->toDateString(), $request->start_date->toDateString());
        $this->assertSame('10:00', $request->start_time);
        $this->assertSame('13:00', $request->end_time);
        Notification::assertSentTo([$requester], \App\Notifications\ReservationRevised::class);
        Notification::assertSentTo(
            User::where('role', 'custodian-venue')->get(),
            \App\Notifications\ReservationRevised::class
        );
    }

    public function test_revision_creates_history_record(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createApprovedRequest($requester);

        $newDate = now()->addDays(2)->toDateString();

        $this->actingAs($admin)
            ->post(route('supply-office.requests.revise'), [
                'facility_request_id' => $request->id,
                'start_date' => $newDate,
                'end_date' => $newDate,
                'start_time' => '10:00',
                'end_time' => '13:00',
                'venue' => ['Test Venue'],
                'equipment' => ['Test Equipment'],
                'equipment_quantities' => ['Test Equipment' => 2],
                'revision_reason' => 'Rescheduling due to venue conflict.',
            ]);

        $this->assertDatabaseHas('revision_histories', [
            'facility_request_id' => $request->id,
            'revised_by_id' => $admin->id,
            'revision_reason' => 'Rescheduling due to venue conflict.',
        ]);

        $revision = RevisionHistory::where('facility_request_id', $request->id)->first();
        $this->assertNotNull($revision);
        $this->assertSame('pending', $revision->status);
        $this->assertSame($newDate, \Carbon\Carbon::parse($revision->new_start_date)->toDateString());
    }

    public function test_revision_preserves_old_values(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createApprovedRequest($requester);

        $oldDate = $request->start_date->toDateString();
        $oldTime = $request->start_time;

        $this->actingAs($admin)
            ->post(route('supply-office.requests.revise'), [
                'facility_request_id' => $request->id,
                'start_date' => now()->addDays(3)->toDateString(),
                'end_date' => now()->addDays(3)->toDateString(),
                'start_time' => '14:00',
                'end_time' => '17:00',
                'venue' => ['Test Venue'],
                'equipment' => ['Test Equipment'],
                'equipment_quantities' => ['Test Equipment' => 2],
                'revision_reason' => 'Admin reschedule.',
            ]);

        $revision = RevisionHistory::where('facility_request_id', $request->id)->first();
        $this->assertSame($oldDate, \Carbon\Carbon::parse($revision->old_start_date)->toDateString());
        $this->assertSame($oldTime, $revision->old_start_time);
    }

    public function test_revision_with_conflict_requires_override(): void
    {
        $requester1 = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $requester2 = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);

        $request1 = $this->createApprovedRequest($requester1);

        // Create conflicting request on same date/time
        $conflicting = FacilityRequest::create([
            'control_number' => 'FER-2026-101',
            'date_requested' => now()->toDateString(),
            'department' => 'Test Department',
            'name_of_activity' => 'Conflicting Activity',
            'expected_participants' => 30,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '13:00',
            'venue' => ['Test Venue'],
            'equipment' => ['Test Equipment'],
            'equipment_quantities' => ['Test Equipment' => 3],
            'requested_by_id' => $requester2->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'approved_by_id' => $admin->id,
            'approved_by' => $admin->name,
            'approved_date' => now(),
        ]);
        $conflicting->syncRelationalItems();

        // Try to revise request1 to conflicting time without override
        $response = $this->actingAs($admin)
            ->post(route('supply-office.requests.revise'), [
                'facility_request_id' => $request1->id,
                'start_date' => now()->addDays(2)->toDateString(),
                'end_date' => now()->addDays(2)->toDateString(),
                'start_time' => '10:00',
                'end_time' => '13:00',
                'venue' => ['Test Venue'],
                'equipment' => ['Test Equipment'],
                'equipment_quantities' => ['Test Equipment' => 2],
                'revision_reason' => 'Rescheduling.',
                'override_conflict' => false,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('conflict');
    }

    public function test_admin_can_override_conflict_with_reason(): void
    {
        $requester1 = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $requester2 = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);

        $request1 = $this->createApprovedRequest($requester1);

        // Create conflicting request
        $conflicting = FacilityRequest::create([
            'control_number' => 'FER-2026-102',
            'date_requested' => now()->toDateString(),
            'department' => 'Test Department',
            'name_of_activity' => 'Conflicting Activity',
            'expected_participants' => 30,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '13:00',
            'venue' => ['Test Venue'],
            'equipment' => ['Test Equipment'],
            'equipment_quantities' => ['Test Equipment' => 3],
            'requested_by_id' => $requester2->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'approved_by_id' => $admin->id,
            'approved_by' => $admin->name,
            'approved_date' => now(),
        ]);
        $conflicting->syncRelationalItems();

        // Override the conflict
        $response = $this->actingAs($admin)
            ->post(route('supply-office.requests.revise'), [
                'facility_request_id' => $request1->id,
                'start_date' => now()->addDays(2)->toDateString(),
                'end_date' => now()->addDays(2)->toDateString(),
                'start_time' => '10:00',
                'end_time' => '13:00',
                'venue' => ['Test Venue'],
                'equipment' => ['Test Equipment'],
                'equipment_quantities' => ['Test Equipment' => 2],
                'revision_reason' => 'Rescheduling.',
                'override_conflict' => true,
                'override_reason' => 'VIP event requires this slot.',
            ]);

        $response->assertRedirect(route('supply-office.index'));

        $revision = RevisionHistory::where('facility_request_id', $request1->id)->first();
        $this->assertTrue($revision->conflict_detected);
        $this->assertTrue($revision->override_conflict);
        $this->assertSame('VIP event requires this slot.', $revision->override_reason);
    }

    public function test_revision_locked_when_equipment_return_started(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createApprovedRequest($requester);

        // Mark equipment as partially returned
        $request->update(['equipment_returned_status' => 'partial']);

        $response = $this->actingAs($admin)
            ->post(route('supply-office.requests.revise'), [
                'facility_request_id' => $request->id,
                'start_date' => now()->addDays(3)->toDateString(),
                'end_date' => now()->addDays(3)->toDateString(),
                'start_time' => '10:00',
                'end_time' => '13:00',
                'venue' => ['Test Venue'],
                'equipment' => ['Test Equipment'],
                'equipment_quantities' => ['Test Equipment' => 2],
                'revision_reason' => 'Reschedule.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('revision');
    }

    public function test_non_admin_cannot_revise(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $request = $this->createApprovedRequest($requester);

        $response = $this->actingAs($requester)
            ->post(route('supply-office.requests.revise'), [
                'facility_request_id' => $request->id,
                'start_date' => now()->addDays(2)->toDateString(),
                'end_date' => now()->addDays(2)->toDateString(),
                'start_time' => '10:00',
                'end_time' => '13:00',
                'venue' => ['Test Venue'],
                'equipment' => ['Test Equipment'],
                'equipment_quantities' => ['Test Equipment' => 2],
                'revision_reason' => 'Reschedule.',
            ]);

        $response->assertForbidden();
    }

    public function test_revision_requires_reason(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createApprovedRequest($requester);

        $response = $this->actingAs($admin)
            ->post(route('supply-office.requests.revise'), [
                'facility_request_id' => $request->id,
                'start_date' => now()->addDays(2)->toDateString(),
                'end_date' => now()->addDays(2)->toDateString(),
                'start_time' => '10:00',
                'end_time' => '13:00',
                'venue' => ['Test Venue'],
                'equipment' => ['Test Equipment'],
                'equipment_quantities' => ['Test Equipment' => 2],
                'revision_reason' => 'Short',
            ]);

        $response->assertSessionHasErrors('revision_reason');
    }

    public function test_requestor_receives_revision_notification(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createApprovedRequest($requester);

        Notification::fake();

        $this->actingAs($admin)
            ->post(route('supply-office.requests.revise'), [
                'facility_request_id' => $request->id,
                'start_date' => now()->addDays(2)->toDateString(),
                'end_date' => now()->addDays(2)->toDateString(),
                'start_time' => '10:00',
                'end_time' => '13:00',
                'venue' => ['Test Venue'],
                'equipment' => ['Test Equipment'],
                'equipment_quantities' => ['Test Equipment' => 2],
                'revision_reason' => 'Venue reschedule required.',
            ]);

        Notification::assertSentTo(
            [$requester],
            \App\Notifications\ReservationRescheduleProposed::class
        );
    }

    public function test_requestor_can_decline_proposal_without_changing_current_schedule(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createApprovedRequest($requester);
        $oldDate = $request->start_date->toDateString();

        $this->actingAs($admin)->post(route('supply-office.requests.revise'), [
            'facility_request_id' => $request->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'start_time' => '14:00',
            'end_time' => '17:00',
            'venue' => ['Test Venue'],
            'equipment' => ['Test Equipment'],
            'equipment_quantities' => ['Test Equipment' => 2],
            'revision_reason' => 'Schedule change requested by office.',
        ])->assertRedirect(route('supply-office.index'));

        $revision = RevisionHistory::where('facility_request_id', $request->id)->firstOrFail();
        $this->actingAs($requester)
            ->post(route('request.revision.respond', [$request, $revision]), ['response' => 'decline'])
            ->assertRedirect(route('request.show', $request))
            ->assertSessionHas('success');

        $this->assertSame('rejected', $revision->fresh()->status);
        $this->assertSame($oldDate, $request->fresh()->start_date->toDateString());
        $this->assertSame('09:00', $request->fresh()->start_time);
    }

    public function test_requestor_cannot_accept_proposal_if_availability_changed(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $otherRequester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createApprovedRequest($requester);
        $proposalDate = now()->addDays(4)->toDateString();

        $this->actingAs($admin)->post(route('supply-office.requests.revise'), [
            'facility_request_id' => $request->id,
            'start_date' => $proposalDate,
            'end_date' => $proposalDate,
            'start_time' => '14:00',
            'end_time' => '17:00',
            'venue' => ['Test Venue'],
            'equipment' => ['Test Equipment'],
            'equipment_quantities' => ['Test Equipment' => 2],
            'revision_reason' => 'Schedule change requested by office.',
        ]);

        FacilityRequest::create([
            'control_number' => 'FER-CONFLICT-' . uniqid(),
            'date_requested' => now()->toDateString(),
            'department' => 'Test Department',
            'name_of_activity' => 'New conflicting reservation',
            'expected_participants' => 30,
            'start_date' => $proposalDate,
            'end_date' => $proposalDate,
            'start_time' => '14:00',
            'end_time' => '17:00',
            'venue' => ['Test Venue'],
            'equipment' => ['Test Equipment'],
            'equipment_quantities' => ['Test Equipment' => 9],
            'requested_by_id' => $otherRequester->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ])->syncRelationalItems();

        $revision = RevisionHistory::where('facility_request_id', $request->id)->firstOrFail();
        $this->actingAs($requester)
            ->from(route('request.show', $request))
            ->post(route('request.revision.respond', [$request, $revision]), ['response' => 'accept'])
            ->assertRedirect(route('request.show', $request))
            ->assertSessionHasErrors('revision');

        $this->assertSame('pending', $revision->fresh()->status);
        $this->assertSame(now()->addDay()->toDateString(), $request->fresh()->start_date->toDateString());
    }

    public function test_accepting_venue_change_notifies_both_previous_and_new_custodians(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createApprovedRequest($requester);
        $previousCustodianId = Venue::where('name', 'Test Venue')->value('custodian_id');
        $newCustodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create([
            'name' => 'Alternate Venue',
            'custodian_id' => $newCustodian->id,
            'capacity' => 100,
        ]);

        $this->actingAs($admin)->post(route('supply-office.requests.revise'), [
            'facility_request_id' => $request->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'start_time' => '14:00',
            'end_time' => '17:00',
            'venue' => ['Alternate Venue'],
            'equipment' => ['Test Equipment'],
            'equipment_quantities' => ['Test Equipment' => 2],
            'revision_reason' => 'The original venue is no longer available.',
        ]);
        $revision = RevisionHistory::where('facility_request_id', $request->id)->firstOrFail();

        $this->actingAs($requester)
            ->post(route('request.revision.respond', [$request, $revision]), ['response' => 'accept'])
            ->assertRedirect(route('request.show', $request));

        $previousCustodian = User::findOrFail($previousCustodianId);
        Notification::assertSentTo([$previousCustodian], \App\Notifications\ReservationRevised::class);
        Notification::assertSentTo([$newCustodian], \App\Notifications\ReservationRevised::class);
    }

    public function test_only_reservation_owner_can_respond_to_a_pending_proposal(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $otherRequestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createApprovedRequest($requester);

        $this->actingAs($admin)->post(route('supply-office.requests.revise'), [
            'facility_request_id' => $request->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'start_time' => '14:00',
            'end_time' => '17:00',
            'venue' => ['Test Venue'],
            'equipment' => ['Test Equipment'],
            'equipment_quantities' => ['Test Equipment' => 2],
            'revision_reason' => 'Schedule change requested by office.',
        ]);
        $revision = RevisionHistory::where('facility_request_id', $request->id)->firstOrFail();

        $this->actingAs($otherRequestor)
            ->post(route('request.revision.respond', [$request, $revision]), ['response' => 'accept'])
            ->assertForbidden();

        $this->assertSame('pending', $revision->fresh()->status);
    }

    public function test_pending_proposal_is_shown_to_requestor_and_blocks_another_proposal(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createApprovedRequest($requester);
        $proposal = [
            'facility_request_id' => $request->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'start_time' => '14:00',
            'end_time' => '17:00',
            'venue' => ['Test Venue'],
            'equipment' => ['Test Equipment'],
            'equipment_quantities' => ['Test Equipment' => 2],
            'revision_reason' => 'Schedule change requested by office.',
        ];

        $this->actingAs($admin)->post(route('supply-office.requests.revise'), $proposal);
        $revision = RevisionHistory::where('facility_request_id', $request->id)->firstOrFail();
        $this->assertSame('pending', $revision->status);
        $this->assertSame($requester->id, $request->fresh()->requested_by_id);
        $this->assertTrue($requester->isRequestee());

        $this->actingAs($requester)
            ->get(route('request.show', $request))
            ->assertOk()
            ->assertViewHas('pendingRevision', fn ($pendingRevision) => $pendingRevision?->id === $revision->id)
            ->assertSee('Schedule change proposed')
            ->assertSee('Accept proposed schedule')
            ->assertSee('Decline proposal')
            ->assertSee($proposal['revision_reason']);

        $this->actingAs($admin)
            ->from(route('supply-office.index'))
            ->post(route('supply-office.requests.revise'), $proposal)
            ->assertRedirect(route('supply-office.index'))
            ->assertSessionHasErrors('revision');

        $this->assertSame(1, RevisionHistory::where('facility_request_id', $request->id)->count());
        $this->assertSame('pending', $revision->fresh()->status);
    }

    public function test_multiple_revisions_create_separate_history_records(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createApprovedRequest($requester);

        // First revision
        $this->actingAs($admin)
            ->post(route('supply-office.requests.revise'), [
                'facility_request_id' => $request->id,
                'start_date' => now()->addDays(2)->toDateString(),
                'end_date' => now()->addDays(2)->toDateString(),
                'start_time' => '10:00',
                'end_time' => '13:00',
                'venue' => ['Test Venue'],
                'equipment' => ['Test Equipment'],
                'equipment_quantities' => ['Test Equipment' => 2],
                'revision_reason' => 'First revision.',
            ]);

        $pendingRevision = RevisionHistory::where('facility_request_id', $request->id)->where('status', 'pending')->firstOrFail();
        $this->actingAs($requester)
            ->post(route('request.revision.respond', [$request, $pendingRevision]), ['response' => 'accept'])
            ->assertRedirect(route('request.show', $request));

        // Second revision
        $this->actingAs($admin)
            ->post(route('supply-office.requests.revise'), [
                'facility_request_id' => $request->id,
                'start_date' => now()->addDays(3)->toDateString(),
                'end_date' => now()->addDays(3)->toDateString(),
                'start_time' => '14:00',
                'end_time' => '17:00',
                'venue' => ['Test Venue'],
                'equipment' => ['Test Equipment'],
                'equipment_quantities' => ['Test Equipment' => 2],
                'revision_reason' => 'Second revision.',
            ]);

        $revisions = RevisionHistory::where('facility_request_id', $request->id)->orderBy('created_at')->get();
        $this->assertCount(2, $revisions);
        $this->assertSame('First revision.', $revisions[0]->revision_reason);
        $this->assertSame('Second revision.', $revisions[1]->revision_reason);
    }

    public function test_requestor_can_view_revision_history(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createApprovedRequest($requester);

        $this->actingAs($admin)
            ->post(route('supply-office.requests.revise'), [
                'facility_request_id' => $request->id,
                'start_date' => now()->addDays(2)->toDateString(),
                'end_date' => now()->addDays(2)->toDateString(),
                'start_time' => '10:00',
                'end_time' => '13:00',
                'venue' => ['Test Venue'],
                'equipment' => ['Test Equipment'],
                'equipment_quantities' => ['Test Equipment' => 2],
                'revision_reason' => 'Admin reschedule.',
            ]);

        // Requestor should be able to see request details with revision history
        $request->refresh();
        $revisions = $request->revisionHistories;
        $this->assertCount(1, $revisions);
        $this->assertSame('Admin reschedule.', $revisions[0]->revision_reason);
    }

    public function test_revision_updates_reservation_schedule(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createApprovedRequest($requester);

        $oldSchedule = $request->reservationSchedule;
        $oldStartDatetime = $oldSchedule->start_datetime;

        $this->actingAs($admin)
            ->post(route('supply-office.requests.revise'), [
                'facility_request_id' => $request->id,
                'start_date' => now()->addDays(2)->toDateString(),
                'end_date' => now()->addDays(2)->toDateString(),
                'start_time' => '10:00',
                'end_time' => '13:00',
                'venue' => ['Test Venue'],
                'equipment' => ['Test Equipment'],
                'equipment_quantities' => ['Test Equipment' => 2],
                'revision_reason' => 'Reschedule.',
            ]);

        $this->acceptPendingRevision($request, $requester);
        $request->refresh();
        $newSchedule = $request->reservationSchedule;

        $this->assertNotSame($oldStartDatetime->toDateTimeString(), $newSchedule->start_datetime->toDateTimeString());
    }
}
