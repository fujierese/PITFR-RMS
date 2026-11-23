<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\RevisionHistory;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * QA-4: the Venue Check stage reflects what happened. A venue that needs another
 * schedule or has a suggested new schedule is not shown as a failed check, and every
 * schedule change is listed with its outcome.
 */
class WorkflowProgressAfterRescheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $requestor;
    private User $admin;
    private FacilityRequest $request;
    private string $newDate;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create(['name' => 'Covered Court', 'capacity' => 300, 'custodian_id' => $custodian->id, 'is_active' => true]);
        $this->requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $date = now()->addDays(6)->toDateString();
        $this->newDate = now()->addDays(8)->toDateString();
        $this->request = FacilityRequest::create([
            'control_number' => 'FER-2026-888',
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Workflow check',
            'expected_participants' => 20,
            'start_date' => $date, 'end_date' => $date, 'start_time' => '09:00', 'end_time' => '11:00',
            'venue' => ['Covered Court'],
            'requested_by_id' => $this->requestor->id,
            'status' => 'pending',
            'venue_status' => 'needs_reschedule',
            'equipment_status' => 'approved',
        ]);
    }

    private function page()
    {
        return $this->actingAs($this->requestor)->get(route('request.show', $this->request))->assertOk();
    }

    private function propose(): RevisionHistory
    {
        $this->actingAs($this->admin)->post(route('supply-office.requests.revise'), [
            'facility_request_id' => $this->request->id,
            'start_date' => $this->newDate, 'end_date' => $this->newDate, 'start_time' => '13:00', 'end_time' => '15:00',
            'venue' => ['Covered Court'],
            'revision_reason' => 'The court is closed for repairs that day.',
        ])->assertSessionHasNoErrors();

        return RevisionHistory::query()->latest('id')->firstOrFail();
    }

    public function test_a_venue_needing_another_schedule_is_not_shown_as_failed(): void
    {
        $this->page()
            ->assertSee('data-step-note="venue">Awaiting Supply Office coordination', false)
            ->assertSee('Awaiting Supply Office Coordination')
            ->assertDontSee('Venue Unavailable — Supply Office Coordination');
    }

    public function test_a_suggested_schedule_then_acceptance_is_shown_as_rescheduled(): void
    {
        $revision = $this->propose();
        $this->page()
            ->assertSee('data-step-note="venue">Suggested new schedule: waiting for response', false)
            ->assertSee('Schedule changes')
            ->assertSee('Waiting for response');

        // The requestor accepts; the suggestion still waits for the venue custodian.
        $this->actingAs($this->requestor)->post(route('request.revision.respond', [$this->request, $revision]), ['response' => 'accept']);
        $this->page()->assertSee('data-step-note="venue">Suggested new schedule: waiting for response', false);

        foreach (array_map('intval', $revision->fresh()->affected_user_ids ?? []) as $userId) {
            if ($userId !== (int) $this->requestor->id) {
                $this->actingAs(User::findOrFail($userId))->post(route('request.revision.respond', [$this->request, $revision]), ['response' => 'accept']);
            }
        }
        $this->assertSame('accepted', $revision->fresh()->status);

        $this->page()
            ->assertSee('data-step-note="venue">Schedule rescheduled', false)
            ->assertDontSee('Awaiting Supply Office coordination')
            ->assertSee('Accepted')
            ->assertSee('To: ' . \Carbon\Carbon::parse($this->newDate)->format('M j, Y') . ', 1:00 PM – 3:00 PM · Covered Court');
    }

    public function test_a_declined_suggestion_is_shown_as_declined(): void
    {
        $revision = $this->propose();
        $this->actingAs($this->requestor)->post(route('request.revision.respond', [$this->request, $revision]), ['response' => 'decline']);

        $this->page()
            ->assertSee('data-step-note="venue">Rescheduling declined', false)
            ->assertSee('Declined');
    }
}
