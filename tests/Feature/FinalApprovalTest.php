<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Notifications\RequestStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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
            'venue' => ['Covered Court'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'priority' => 'regular',
        ], $overrides));
    }

    private function finalApprover(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'username' => 'asala@gmail.com',
            'name' => 'Arlene L. Sala',
        ]);
    }

    public function test_outsider_request_cannot_be_confirmed_before_igp_coordination(): void
    {
        $outsider = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'outsider']);
        $request = $this->createRequest([
            'requested_by_id' => $outsider->id,
            'supply_office_review_status' => 'confirmed',
            'outsider_stage' => 'approved',
        ]);
        $approver = $this->finalApprover();

        $this->actingAs($approver)
            ->get(route('request.show', $request->id))
            ->assertOk()
            ->assertSee('until IGP informs the client of the payment and other requirements');

        $this->actingAs($approver)
            ->post(route('request.supply.final-approval', $request))
            ->assertSessionHasErrors();
        $this->assertSame('pending', $request->refresh()->status);
        Notification::assertNothingSent();

        $request->update(['outsider_stage' => 'igp_coordination']);
        $this->actingAs($approver)
            ->post(route('request.supply.final-approval', $request))
            ->assertSessionHasNoErrors();
        $this->assertSame('approved', $request->refresh()->status);
        $this->assertSame('confirmed', $request->outsider_stage);
    }

    public function test_status_notification_is_sent_only_after_the_workflow_change_commits(): void
    {
        $request = $this->createRequest();
        $requester = User::findOrFail($request->requested_by_id);
        $controller = app(\App\Http\Controllers\RequestActionController::class);
        $notify = new \ReflectionMethod($controller, 'notifyRequestorForStatusChange');

        \Illuminate\Support\Facades\DB::beginTransaction();
        $notify->invoke($controller, $request, 'approved');
        \Illuminate\Support\Facades\DB::rollBack();
        Notification::assertNotSentTo($requester, RequestStatusChanged::class);

        \Illuminate\Support\Facades\DB::transaction(fn () => $notify->invoke($controller, $request, 'approved'));
        Notification::assertSentToTimes($requester, RequestStatusChanged::class, 1);
    }

    public function test_eligible_request_is_final_approved_by_system_admin_once(): void
    {
        $request = $this->createRequest();
        $approver = $this->finalApprover();

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

    public function test_final_approval_records_signature_snapshot_for_system_admin(): void
    {
        Storage::fake('local');

        $request = $this->createRequest();
        $approver = $this->finalApprover();
        $approver->update(['e_signature_file' => 'final-approver-signature.png']);

        Storage::disk('local')->put(
            'documents/e_signature/users/final-approver-signature.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
        );

        $this->actingAs($approver)
            ->post(route('request.supply.final-approval', $request), ['notes' => 'Final approval granted'])
            ->assertRedirect();

        $request->refresh();

        $this->assertNotNull($request->final_approval_signature);
        // Account-based approval: no signature image is copied (APR-01).
        $this->assertNull($request->final_approval_signature_file);
    }

    public function test_supply_office_queue_contains_only_requests_ready_for_final_approval(): void
    {
        $ready = $this->createRequest(['control_number' => 'TEST-FINAL-READY']);
        $incomplete = $this->createRequest([
            'control_number' => 'TEST-FINAL-INCOMPLETE',
            'equipment_status' => 'pending',
        ]);

        $response = $this->actingAs($this->finalApprover())
            ->get(route('supply-office.requests.final-approval'));

        $response->assertOk()->assertSee($ready->control_number)->assertDontSee($incomplete->control_number);
    }

    public function test_direct_final_approval_rejects_incomplete_venue_or_equipment_endorsement(): void
    {
        $approver = $this->finalApprover();

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
        $approver = $this->finalApprover();

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
        $approver = $this->finalApprover();

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

    public function test_only_system_admin_can_finalize_approval(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $equipmentCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        $supplyOfficeStaff = User::factory()->create(['role' => 'supply_office']);
        $otherAdmin = User::factory()->create(['role' => 'admin', 'username' => 'other-admin']);
        $admin = $this->finalApprover();

        foreach ([$requester, $venueCustodian, $equipmentCustodian, $supplyOfficeStaff, $otherAdmin] as $unauthorized) {
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

    public function test_non_designated_admin_cannot_see_final_approval_controls(): void
    {
        $request = $this->createRequest(['supply_office_review_status' => 'confirmed']);
        $admin = User::factory()->create([
            'role' => 'admin',
            'username' => 'other-admin',
        ]);

        $this->actingAs($admin)
            ->get(route('request.show', $request))
            ->assertOk()
            ->assertSee('Awaiting Admin Final Approval')
            ->assertDontSee(route('request.supply.final-approval', $request), false);

        $this->post(route('request.supply.final-approval', $request))->assertForbidden();
    }

    public function test_supply_office_staff_cannot_access_final_approval_queue_or_actions(): void
    {
        $request = $this->createRequest();
        $staff = User::factory()->create(['role' => 'supply_office']);

        $this->actingAs($staff)
            ->get(route('supply-office.requests.final-approval'))
            ->assertForbidden();

        $this->post(route('request.supply.final-approval', $request))
            ->assertForbidden();

        $this->post(route('request.supply.decline', $request))
            ->assertForbidden();

        $this->post(route('supply-office.update'), [
            'id' => $request->id,
            'action' => 'approve',
        ])->assertForbidden();

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_final_approved_activity_is_returned_by_calendar_with_schedule_data(): void
    {
        $request = $this->createRequest();
        $this->actingAs($this->finalApprover())
            ->post(route('request.supply.final-approval', $request))
            ->assertRedirect();

        $response = $this->getJson(route('calendar.events'));
        $response->assertOk();
        $event = collect($response->json())->firstWhere('extendedProps.facilityRequestId', $request->id);

        $this->assertNotNull($event);
        $this->assertSame('approved', $event['status']);
        $this->assertSame('Approved', $event['extendedProps']['status']);
        $this->assertSame($request->start_date->toDateString(), substr($event['start'], 0, 10));
        $this->assertSame($request->end_date->toDateString(), substr($event['end'], 0, 10));
    }
}
