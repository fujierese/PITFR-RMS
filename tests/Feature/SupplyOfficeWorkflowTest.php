<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Notifications\RequestStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SupplyOfficeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function makeRequest(array $overrides = []): FacilityRequest
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);

        return FacilityRequest::create(array_merge([
            'control_number' => 'TEST-SUPPLY-' . uniqid(),
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Supply Office Workflow Test',
            'expected_participants' => 25,
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

    public function test_supply_office_can_view_ready_requests_and_finalize_through_the_dashboard_route(): void
    {
        $request = $this->makeRequest();
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Dashboard Approver']);
        $requester = User::findOrFail($request->requested_by_id);

        $this->actingAs($admin)
            ->get(route('supply-office.requests.final-approval'))
            ->assertOk()
            ->assertSee($request->control_number)
            ->assertSee('Gymnasium');

        $response = $this->actingAs($admin)->post(route('supply-office.update'), [
            'id' => $request->id,
            'action' => 'approve',
            'notes' => 'Approved from Supply Office queue',
        ]);

        $response->assertRedirect(route('supply-office.index'));
        $request->refresh();

        $this->assertSame('approved', $request->status);
        $this->assertSame($admin->getKey(), $request->approved_by_id);
        $this->assertSame($admin->name, $request->approved_by);
        $this->assertNotNull($request->approved_date);
        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $request->id,
            'action' => 'approved',
            'user_id' => $admin->getKey(),
        ]);
        Notification::assertSentToTimes($requester, RequestStatusChanged::class, 1);
    }

    public function test_supply_office_rejection_records_audit_and_notification_without_approval_fields(): void
    {
        $request = $this->makeRequest();
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Rejecting Approver']);
        $requester = User::findOrFail($request->requested_by_id);

        $this->actingAs($admin)->post(route('supply-office.update'), [
            'id' => $request->id,
            'action' => 'reject',
            'notes' => 'Rejected by Supply Office',
        ])->assertRedirect(route('supply-office.index'));

        $request->refresh();
        $this->assertSame('rejected', $request->status);
        $this->assertNull($request->approved_by_id);
        $this->assertNull($request->approved_by);
        $this->assertNull($request->approved_date);
        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $request->id,
            'action' => 'rejected',
            'user_id' => $admin->getKey(),
        ]);
        Notification::assertSentToTimes($requester, RequestStatusChanged::class, 1);
    }

    public function test_supply_office_dashboard_route_rejects_unauthorized_roles(): void
    {
        $request = $this->makeRequest();
        $unauthorizedUsers = [
            User::factory()->create(['role' => 'requestor']),
            User::factory()->create(['role' => 'custodian-venue']),
            User::factory()->create(['role' => 'custodian-equipment']),
        ];

        foreach ($unauthorizedUsers as $user) {
            $this->actingAs($user)
                ->post(route('supply-office.update'), [
                    'id' => $request->id,
                    'action' => 'approve',
                ])
                ->assertForbidden();
        }

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame(0, $request->fresh()->histories()->where('action', 'approved')->count());
        Notification::assertNothingSent();
    }

    public function test_repeated_supply_office_approval_does_not_duplicate_audit_or_notification(): void
    {
        $request = $this->makeRequest();
        $admin = User::factory()->create(['role' => 'admin']);
        $requester = User::findOrFail($request->requested_by_id);
        $payload = ['id' => $request->id, 'action' => 'approve'];

        $this->actingAs($admin)->post(route('supply-office.update'), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('supply-office.update'), $payload)->assertRedirect();

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame(1, $request->histories()->where('action', 'approved')->count());
        Notification::assertSentToTimes($requester, RequestStatusChanged::class, 1);
    }
}
