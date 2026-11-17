<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\RequestStatusChanged;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Issue APR-14: reminders, escalation and expiry for requests waiting too long.
 */
class ReviewDeadlinesTest extends TestCase
{
    use RefreshDatabase;

    private User $venueCustodian;
    private User $supplyOffice;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Carbon::setTestNow('2026-11-18 08:00:00');

        $this->venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $this->supplyOffice = User::factory()->create(['role' => 'supply_office']);
        Venue::create(['name' => 'Gymnasium', 'custodian_id' => $this->venueCustodian->id, 'capacity' => 100]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pendingRequest(array $attributes = [], string $submittedAt = '2026-11-15 08:00:00'): FacilityRequest
    {
        $request = FacilityRequest::factory()->create(array_merge([
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'approved',
            'supply_office_review_status' => 'pending',
            'venue' => ['Gymnasium'],
            'equipment' => [],
            'start_date' => '2026-12-10',
            'end_date' => '2026-12-10',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ], $attributes));
        \Illuminate\Support\Facades\DB::table('request_histories')->where('facility_request_id', $request->id)->delete();
        \Illuminate\Support\Facades\DB::table('request_histories')->insert([
            'facility_request_id' => $request->id,
            'action' => 'submitted',
            'occurred_at' => $submittedAt,
            'created_at' => $submittedAt,
            'updated_at' => $submittedAt,
        ]);
        \Illuminate\Support\Facades\DB::table('reservation_schedules')->where('facility_request_id', $request->id)->delete();

        return $request;
    }

    public function test_stage_waiting_past_the_reminder_time_reminds_the_responsible_custodian_once(): void
    {
        $this->pendingRequest([], '2026-11-16 06:00:00'); // 50 hours ago

        $this->artisan('facility-requests:review-deadlines')->assertSuccessful();
        $this->artisan('facility-requests:review-deadlines')->assertSuccessful();

        Notification::assertSentToTimes($this->venueCustodian, RequestStatusChanged::class, 1);
        Notification::assertSentTo($this->venueCustodian, RequestStatusChanged::class,
            fn (RequestStatusChanged $n): bool => $n->status === 'review_reminder' && str_contains($n->notes, 'venue confirmation'));
        Notification::assertNotSentTo($this->supplyOffice, RequestStatusChanged::class);
    }

    public function test_recent_requests_get_no_reminder(): void
    {
        $this->pendingRequest([], '2026-11-17 20:00:00'); // 12 hours ago

        $this->artisan('facility-requests:review-deadlines')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_overdue_stage_is_escalated_to_the_supply_office(): void
    {
        $request = $this->pendingRequest([], '2026-11-13 08:00:00'); // 5 days ago

        $this->artisan('facility-requests:review-deadlines')->assertSuccessful();

        Notification::assertSentTo($this->supplyOffice, RequestStatusChanged::class,
            fn (RequestStatusChanged $n): bool => $n->status === 'review_escalation');
        $this->assertDatabaseHas('request_histories', ['facility_request_id' => $request->id, 'action' => 'review_escalated']);
    }

    public function test_reminder_goes_to_the_supply_office_when_its_review_is_the_open_stage(): void
    {
        $this->pendingRequest(['venue_status' => 'approved'], '2026-11-16 06:00:00');

        $this->artisan('facility-requests:review-deadlines')->assertSuccessful();

        Notification::assertSentTo($this->supplyOffice, RequestStatusChanged::class,
            fn (RequestStatusChanged $n): bool => $n->status === 'review_reminder' && str_contains($n->notes, 'availability review'));
        Notification::assertNotSentTo($this->venueCustodian, RequestStatusChanged::class);
    }

    public function test_request_not_approved_before_its_start_time_expires_and_releases_stock(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $equipmentCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        $equipment = Equipment::create([
            'name' => 'Sound System', 'custodian_id' => $equipmentCustodian->id, 'quantity' => 3, 'quantity_available' => 1,
        ]);
        $request = $this->pendingRequest([
            'requested_by_id' => $requester->id,
            'start_date' => '2026-11-18',
            'end_date' => '2026-11-18',
            'start_time' => '07:00',
            'end_time' => '10:00',
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 2],
            'equipment_status' => 'approved',
        ]);

        $this->artisan('facility-requests:review-deadlines')->assertSuccessful();

        $request->refresh();
        $this->assertSame('cancelled', $request->status);
        $this->assertSame(3, $equipment->fresh()->quantity_available);
        $this->assertDatabaseHas('request_histories', ['facility_request_id' => $request->id, 'action' => 'cancelled']);
        Notification::assertSentTo($requester, RequestStatusChanged::class,
            fn (RequestStatusChanged $n): bool => $n->status === 'request_expired');
    }

    public function test_approved_requests_are_never_expired_or_reminded(): void
    {
        $request = $this->pendingRequest([
            'status' => 'approved', 'venue_status' => 'approved', 'supply_office_review_status' => 'approved',
            'start_date' => '2026-11-18', 'end_date' => '2026-11-18', 'start_time' => '07:00',
        ], '2026-11-01 08:00:00');

        $this->artisan('facility-requests:review-deadlines')->assertSuccessful();

        $this->assertSame('approved', $request->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_dry_run_lists_actions_without_changing_anything(): void
    {
        $request = $this->pendingRequest(['start_date' => '2026-11-18', 'end_date' => '2026-11-18', 'start_time' => '07:00']);

        $this->artisan('facility-requests:review-deadlines', ['--dry-run' => true])
            ->expectsOutputToContain('EXPIRE')
            ->assertSuccessful();

        $this->assertSame('pending', $request->fresh()->status);
        Notification::assertNothingSent();
    }
}
