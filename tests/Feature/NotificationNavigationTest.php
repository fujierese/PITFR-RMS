<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\RevisionHistory;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * QA-3: a notification opens what it is about. A suggested new schedule opens the
 * request at the suggestion (with Accept and Decline); a request that is gone, not
 * visible to the user, or a suggestion already answered gets a clear message.
 */
class NotificationNavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $requestor;
    private FacilityRequest $request;
    private RevisionHistory $revision;

    protected function setUp(): void
    {
        parent::setUp();
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create(['name' => 'Covered Court', 'capacity' => 300, 'custodian_id' => $custodian->id, 'is_active' => true]);
        $this->requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $date = now()->addDays(6)->toDateString();
        $this->request = FacilityRequest::create([
            'control_number' => 'FER-2026-777',
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Notification check',
            'expected_participants' => 20,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => ['Covered Court'],
            'requested_by_id' => $this->requestor->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'approved',
        ]);
        $other = now()->addDays(8)->toDateString();
        $this->actingAs($admin)->post(route('supply-office.requests.revise'), [
            'facility_request_id' => $this->request->id,
            'start_date' => $other, 'end_date' => $other, 'start_time' => '13:00', 'end_time' => '15:00',
            'venue' => ['Covered Court'],
            'revision_reason' => 'The court is being repainted that day.',
        ])->assertSessionHasNoErrors();
        $this->revision = RevisionHistory::query()->sole();
    }

    private function rescheduleNotificationId(): string
    {
        return $this->requestor->notifications()->get()
            ->first(fn ($notification) => ($notification->data['status'] ?? null) === 'reschedule_proposed')->id;
    }

    public function test_the_reschedule_notification_opens_the_suggested_schedule(): void
    {
        $id = $this->rescheduleNotificationId();

        $this->actingAs($this->requestor)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee(route('notifications.open', $id), false)
            ->assertSee('FER-2026-777');

        $this->actingAs($this->requestor)->get(route('notifications.open', $id))
            ->assertRedirect(route('request.show', $this->request) . '#schedule-proposal');
        $this->assertNotNull($this->requestor->notifications()->find($id)->read_at);

        $this->actingAs($this->requestor)->get(route('request.show', $this->request))
            ->assertOk()
            ->assertSee('id="schedule-proposal"', false)
            ->assertSee('Suggested new schedule');
    }

    public function test_a_reschedule_notification_marked_as_read_still_opens_the_suggestion(): void
    {
        $id = $this->rescheduleNotificationId();
        $this->actingAs($this->requestor)->postJson(route('notifications.read', $id))->assertOk();

        $this->actingAs($this->requestor)->get(route('notifications.index'))
            ->assertSee(route('notifications.open', $id), false);
        $this->actingAs($this->requestor)->get(route('notifications.open', $id))
            ->assertRedirect(route('request.show', $this->request) . '#schedule-proposal');
        $this->actingAs($this->requestor)->get(route('request.show', $this->request))
            ->assertSee('id="schedule-proposal"', false)
            ->assertSee('Suggested new schedule');
    }

    public function test_an_answered_suggestion_says_so(): void
    {
        $this->actingAs($this->requestor)->post(route('request.revision.respond', [$this->request, $this->revision]), ['response' => 'decline']);

        $this->actingAs($this->requestor)->get(route('notifications.open', $this->rescheduleNotificationId()))
            ->assertRedirect(route('request.show', $this->request))
            ->assertSessionHas('warning', 'This suggested new schedule was declined. The request details below are up to date.');
    }

    public function test_a_deleted_request_or_someone_elses_notification_gets_a_clear_message(): void
    {
        $id = $this->rescheduleNotificationId();
        $stranger = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $this->actingAs($stranger)->get(route('notifications.open', $id))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('warning', 'That notification could not be found. It may have been removed.');

        $this->request->delete();
        $this->actingAs($this->requestor)->get(route('notifications.open', $id))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('warning', 'Request FER-2026-777 is no longer available. It may have been deleted.');
    }

    public function test_request_status_notifications_still_open_their_request(): void
    {
        $this->requestor->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => \App\Notifications\RequestStatusChanged::class,
            'data' => ['request_id' => $this->request->id, 'status' => 'venue_approved', 'control_number' => 'FER-2026-777'],
        ]);
        $id = $this->requestor->notifications()->get()->first(fn ($n) => ($n->data['status'] ?? null) === 'venue_approved')->id;

        $this->actingAs($this->requestor)->get(route('notifications.open', $id))
            ->assertRedirect(route('request.show', $this->request));
    }
}
