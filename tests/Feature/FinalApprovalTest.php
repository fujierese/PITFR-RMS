<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Notifications\RequestStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FinalApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function createRequest(array $overrides = []): FacilityRequest
    {
        $requester = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
        ]);

        return FacilityRequest::create(array_merge([
            'control_number' => 'TEST-FINAL-' . uniqid(),
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Final Approval Test',
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
        ], $overrides));
    }

    private function supplyOffice(): User
    {
        return User::factory()->create([
            'role' => 'supply_office',
            'name' => 'Supply Office Final Approver',
        ]);
    }

    public function test_eligible_request_is_final_approved_by_supply_office_once(): void
    {
        $request = $this->createRequest();
        $approver = $this->supplyOffice();

        $response = $this->actingAs($approver)->post(
            route('request.supply.final-approval', $request),
            ['notes' => 'Final approval granted']
        );

        $response->assertRedirect();
        $request->refresh();

        $this->assertSame('approved', $request->status);
        $this->assertSame($approver->id, $request->approved_by_id);
        $this->assertSame($approver->name, $request->approved_by);
        $this->assertNotNull($request->approved_date);
        $this->assertSame(1, $request->histories()->where('action', 'final_approved')->count());
        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $request->id,
            'action' => 'final_approved',
            'user_id' => $approver->id,
            'detail' => 'Final approval granted by ' . $approver->name,
        ]);
        Notification::assertSentTo(
            User::findOrFail($request->requested_by_id),
            RequestStatusChanged::class,
            fn (RequestStatusChanged $notification): bool => $notification->status === 'approved'
                && $notification->facilityRequest->id === $request->id
        );
        Notification::assertSentToTimes(User::findOrFail($request->requested_by_id), RequestStatusChanged::class, 1);
    }

    public function test_supply_office_queue_contains_only_requests_ready_for_final_approval(): void
    {
        $ready = $this->createRequest(['control_number' => 'TEST-FINAL-READY']);
        $incomplete = $this->createRequest([
            'control_number' => 'TEST-FINAL-INCOMPLETE',
            'equipment_status' => 'pending',
        ]);

        $response = $this->actingAs($this->supplyOffice())
            ->get(route('supply-office.requests.final-approval'));

        $response->assertOk()->assertSee($ready->control_number)->assertDontSee($incomplete->control_number);
    }

    public function test_direct_final_approval_rejects_incomplete_venue_or_equipment_endorsement(): void
    {
        $approver = $this->supplyOffice();

        foreach ([
            ['venue_status' => 'pending', 'equipment_status' => 'approved'],
            ['venue_status' => 'rejected', 'equipment_status' => 'approved'],
            ['venue_status' => 'approved', 'equipment_status' => 'pending'],
            ['venue_status' => 'approved', 'equipment_status' => 'rejected'],
            ['venue_status' => 'pending', 'equipment_status' => 'pending'],
        ] as $statuses) {
            $request = $this->createRequest($statuses);

            $response = $this->actingAs($approver)->post(
                route('request.supply.final-approval', $request),
                ['notes' => 'Premature approval attempt']
            );

            $response->assertRedirect()->assertSessionHasErrors();
            $request->refresh();
            $this->assertSame('pending', $request->status);
            $this->assertSame(0, $request->histories()->where('action', 'final_approved')->count());
            Notification::assertNothingSent();
        }
    }

    public function test_rejected_revision_and_cancelled_requests_cannot_be_final_approved(): void
    {
        $approver = $this->supplyOffice();

        foreach (['rejected', 'needs_reschedule'] as $status) {
            $request = $this->createRequest(['status' => $status]);

            $response = $this->actingAs($approver)->post(
                route('request.supply.final-approval', $request)
            );

            $response->assertRedirect()->assertSessionHasErrors();
            $this->assertSame($status, $request->fresh()->status);
            $this->assertSame(0, $request->fresh()->histories()->where('action', 'final_approved')->count());
        }

        $cancelled = $this->createRequest();
        $cancelled->delete();

        $this->actingAs($approver)
            ->post(route('request.supply.final-approval', $cancelled->id))
            ->assertNotFound();
    }

    public function test_already_final_approved_request_is_idempotent(): void
    {
        $request = $this->createRequest();
        $approver = $this->supplyOffice();

        $this->actingAs($approver)
            ->post(route('request.supply.final-approval', $request))
            ->assertRedirect();

        $firstApprovedDate = $request->fresh()->approved_date;
        $this->actingAs($approver)
            ->post(route('request.supply.final-approval', $request))
            ->assertRedirect()
            ->assertSessionHas('info');

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame(1, $request->histories()->where('action', 'final_approved')->count());
        $this->assertEquals($firstApprovedDate, $request->approved_date);
        Notification::assertSentToTimes(User::findOrFail($request->requested_by_id), RequestStatusChanged::class, 1);
    }

    public function test_only_administrative_supply_office_role_can_finalize_approval(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $equipmentCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ([$requester, $venueCustodian, $equipmentCustodian] as $unauthorized) {
            $request = $this->createRequest(['requested_by_id' => $requester->id]);

            $this->actingAs($unauthorized)
                ->post(route('request.supply.final-approval', $request))
                ->assertForbidden();

            $this->assertSame('pending', $request->fresh()->status);
            $this->assertSame(0, $request->fresh()->histories()->where('action', 'final_approved')->count());
        }

        $adminRequest = $this->createRequest(['requested_by_id' => $requester->id]);
        $this->actingAs($admin)
            ->post(route('request.supply.final-approval', $adminRequest))
            ->assertRedirect();
        $this->assertSame('approved', $adminRequest->fresh()->status);
    }

    public function test_final_approved_activity_is_returned_by_calendar_with_schedule_data(): void
    {
        $request = $this->createRequest();
        $this->actingAs($this->supplyOffice())
            ->post(route('request.supply.final-approval', $request))
            ->assertRedirect();

        $response = $this->getJson(route('calendar.events'));
        $response->assertOk();
        $event = collect($response->json())->firstWhere('id', $request->id);

        $this->assertNotNull($event);
        $this->assertSame('approved', $event['status']);
        $this->assertSame('Approved', $event['extendedProps']['status']);
        $this->assertSame($request->start_date->toDateString(), substr($event['start'], 0, 10));
        $this->assertSame($request->end_date->toDateString(), substr($event['end'], 0, 10));
    }
}
