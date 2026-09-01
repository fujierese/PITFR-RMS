<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Notifications\RequestStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SystemConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function makeRequest(array $overrides = []): FacilityRequest
    {
        $requester = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
            'office_or_organization' => 'BITS Organization',
        ]);

        return FacilityRequest::create(array_merge([
            'control_number' => 'TEST-CONSISTENCY-' . uniqid(),
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Consistency Audit Activity',
            'expected_participants' => 40,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => ['Gymnasium'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'priority' => 'regular',
            'is_emergency' => false,
        ], $overrides));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'name' => 'Consistency Supply Office']);
    }

    public function test_approval_keeps_request_data_status_history_notification_and_calendar_consistent(): void
    {
        $request = $this->makeRequest([
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 2],
            'priority' => 'institutional',
            'is_emergency' => true,
        ]);
        $admin = $this->admin();
        $requester = User::findOrFail($request->requested_by_id);
        $original = $request->only([
            'control_number', 'requested_by_id', 'department', 'name_of_activity',
            'venue', 'equipment', 'equipment_quantities', 'start_date', 'end_date',
            'start_time', 'end_time', 'priority', 'is_emergency',
        ]);
        $original['start_date'] = $request->start_date->toDateString();
        $original['end_date'] = $request->end_date->toDateString();

        $this->actingAs($admin)->post(route('supply-office.update'), [
            'id' => $request->id,
            'action' => 'approve',
            'notes' => 'Consistency approval',
        ])->assertRedirect();

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame('approved', $request->venue_status);
        $this->assertSame('approved', $request->equipment_status);
        $this->assertSame($admin->getKey(), $request->approved_by_id);
        $this->assertSame($admin->name, $request->approved_by);
        $this->assertNotNull($request->approved_date);
        $actual = $request->only(array_keys($original));
        $actual['start_date'] = $request->start_date->toDateString();
        $actual['end_date'] = $request->end_date->toDateString();
        $this->assertSame($original, $actual);
        $this->assertSame(1, $request->histories()->where('action', 'approved')->count());
        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $request->id,
            'action' => 'approved',
            'user_id' => $admin->getKey(),
        ]);
        Notification::assertSentToTimes($requester, RequestStatusChanged::class, 1);

        $event = collect($this->getJson(route('calendar.events'))->json())
            ->firstWhere('id', $request->id);
        $this->assertNotNull($event);
        $this->assertSame('approved', $event['status']);
        $this->assertSame($request->start_date->toDateString(), substr($event['start'], 0, 10));
        // ✅ For timed events (with specific start/end times), FullCalendar uses exact end dates
        // ✅ For all-day events only, FullCalendar uses exclusive end dates (day after the actual end)
        // Since this request has start_time and end_time, it's a timed event, not all-day
        $this->assertSame($request->end_date->toDateString(), substr($event['end'], 0, 10));
    }

    public function test_rejection_clears_approval_metadata_and_records_one_consistent_transition(): void
    {
        $request = $this->makeRequest();
        $admin = $this->admin();
        $requester = User::findOrFail($request->requested_by_id);

        $payload = ['id' => $request->id, 'action' => 'reject', 'notes' => 'Consistency rejection'];
        $this->actingAs($admin)->post(route('supply-office.update'), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('supply-office.update'), $payload)->assertRedirect();

        $request->refresh();
        $this->assertSame('rejected', $request->status);
        $this->assertNull($request->approved_by);
        $this->assertNull($request->approved_by_id);
        $this->assertNull($request->approved_date);
        $this->assertSame(1, $request->histories()->where('action', 'rejected')->count());
        Notification::assertSentToTimes($requester, RequestStatusChanged::class, 1);

        Auth::logout();
        $eventIds = collect($this->getJson(route('calendar.events'))->json())->pluck('id');
        $this->assertFalse($eventIds->contains($request->id));
    }

    public function test_pending_incomplete_and_unauthorized_actions_do_not_create_success_state(): void
    {
        $request = $this->makeRequest([
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);
        $requester = User::findOrFail($request->requested_by_id);
        $unauthorized = User::factory()->create(['role' => 'requestor']);

        $this->actingAs($unauthorized)
            ->post(route('request.supply.final-approval', $request))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->post(route('request.supply.final-approval', $request))
            ->assertRedirect()
            ->assertSessionHasErrors();

        $request->refresh();
        $this->assertSame('pending', $request->status);
        $this->assertSame(0, $request->histories()->whereIn('action', ['approved', 'final_approved'])->count());
        Notification::assertNothingSent();

        $event = collect($this->getJson(route('calendar.events'))->json())
            ->firstWhere('id', $request->id);
        $this->assertNotNull($event);
        $this->assertSame('pending', $event['status']);
        $this->assertSame($requester->id, $request->requested_by_id);
    }

    public function test_requestor_cancellation_is_retained_audited_and_removed_from_calendar(): void
    {
        $request = $this->makeRequest();
        $requester = User::findOrFail($request->requested_by_id);

        $this->actingAs($requester)
            ->post(route('request.cancel', $request))
            ->assertRedirect();

        $cancelled = FacilityRequest::findOrFail($request->id);
        $this->assertNull($cancelled->deleted_at);
        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('cancelled', $cancelled->histories()->latest('occurred_at')->value('action'));
        $this->assertFalse(collect($this->getJson(route('calendar.events'))->json())->pluck('id')->contains($request->id));
    }

    public function test_revision_transition_records_actor_and_notification_without_final_approval(): void
    {
        $request = $this->makeRequest();
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        \App\Models\Venue::create(['name' => 'Gymnasium', 'custodian_id' => $custodian->id]);
        $requester = User::findOrFail($request->requested_by_id);

        $this->actingAs($custodian)
            ->post(route('request.custodian.revision', $request), ['notes' => 'Please revise venue details'])
            ->assertRedirect();

        $request->refresh();
        $this->assertSame('pending', $request->status);
        $this->assertSame('pending', $request->venue_status);
        $this->assertSame(1, $request->histories()->where('action', 'revision_requested')->count());
        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $request->id,
            'action' => 'revision_requested',
            'user_id' => $custodian->id,
        ]);
        Notification::assertSentToTimes($requester, RequestStatusChanged::class, 1);
    }
}
