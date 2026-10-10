<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\Equipment;
use App\Models\User;
use App\Models\Venue;
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

    public function test_supply_office_assigns_each_resource_only_to_matching_custodian_types(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $venueCustodian = User::factory()->create([
            'role' => 'custodian-venue',
            'name' => 'Venue Custodian Person',
        ]);
        $equipmentCustodian = User::factory()->create([
            'role' => 'custodian-equipment',
            'name' => 'Equipment Custodian Person',
        ]);

        $this->actingAs($admin)
            ->get(route('supply-office.venues.index'))
            ->assertOk()
            ->assertSee('Venue Custodian Person — Venue Custodian');
        $this->get(route('supply-office.equipment.index'))
            ->assertOk()
            ->assertSee('Equipment Custodian Person — Equipment Custodian');

        $this->from(route('supply-office.venues.index'))
            ->post(route('supply-office.venues.store'), [
                'name' => 'Main Auditorium',
                'capacity' => 500,
                'custodian_id' => $venueCustodian->id,
            ])
            ->assertRedirect(route('supply-office.venues.index'));
        $this->assertDatabaseHas('venues', [
            'name' => 'Main Auditorium',
            'custodian_id' => $venueCustodian->id,
        ]);
        $venue = Venue::where('name', 'Main Auditorium')->firstOrFail();
        $this->get(route('supply-office.venues.index', ['edit_venue' => $venue->id]))
            ->assertOk()
            ->assertSee('Editing: Main Auditorium')
            ->assertSee('Assigned venue custodian')
            ->assertSee('Save changes')
            ->assertSee('Cancel');

        $this->from(route('supply-office.venues.index'))
            ->post(route('supply-office.venues.store'), [
                'name' => 'Wrong Type Venue',
                'capacity' => 100,
                'custodian_id' => $equipmentCustodian->id,
            ])
            ->assertRedirect(route('supply-office.venues.index'))
            ->assertSessionHasErrors('custodian_id');
        $this->assertDatabaseMissing('venues', ['name' => 'Wrong Type Venue']);

        $this->from(route('supply-office.equipment.index'))
            ->post(route('supply-office.equipment.store'), [
            'name' => 'Sound System',
            'quantity' => 4,
            'custodian_id' => $equipmentCustodian->id,
            ])->assertRedirect(route('supply-office.equipment.index'));
        $this->assertDatabaseHas('equipment', [
            'name' => 'Sound System',
            'custodian_id' => $equipmentCustodian->id,
        ]);
        $equipment = Equipment::where('name', 'Sound System')->firstOrFail();
        $this->get(route('supply-office.equipment.index', ['edit_equipment' => $equipment->id]))
            ->assertOk()
            ->assertSee('Editing: Sound System')
            ->assertSee('Assigned equipment custodian')
            ->assertSee('Save changes')
            ->assertSee('Cancel');

        $this->from(route('supply-office.equipment.index'))
            ->post(route('supply-office.equipment.store'), [
                'name' => 'Wrong Type Equipment',
                'quantity' => 2,
                'custodian_id' => $venueCustodian->id,
            ])
            ->assertRedirect(route('supply-office.equipment.index'))
            ->assertSessionHasErrors('custodian_id');
        $this->assertDatabaseMissing('equipment', ['name' => 'Wrong Type Equipment']);
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

    public function test_supply_office_dashboard_paginates_the_review_queue(): void
    {
        for ($index = 0; $index < 16; $index++) {
            $this->makeRequest();
        }

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('supply-office.index'))
            ->assertOk()
            ->assertSee('16 waiting')
            ->assertSee('queue_page=2');

        $this->actingAs($admin)
            ->get(route('supply-office.index', ['queue_page' => 2]))
            ->assertOk()
            ->assertSee('16 waiting');
    }

    public function test_supply_office_dashboard_report_card_uses_the_count_provided_by_the_controller(): void
    {
        $this->makeRequest();
        $this->makeRequest();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('supply-office.index'))
            ->assertOk()
            ->assertSee('Usage and activity reports')
            ->assertSee('<p class="mt-3 text-2xl font-semibold text-slate-900">2</p>', false);
    }

    public function test_advanced_filters_are_visible_when_requested_and_filter_the_review_queue(): void
    {
        $matchingRequest = $this->makeRequest([
            'control_number' => 'TEST-SUPPLY-FILTER-MATCH',
            'department' => 'Information Technology',
            'venue' => ['Gymnasium'],
            'priority' => 'regular',
            'start_date' => '2026-10-10',
        ]);
        $this->makeRequest([
            'control_number' => 'TEST-SUPPLY-FILTER-NO-MATCH',
            'department' => 'Business Administration',
            'venue' => ['Auditorium'],
            'priority' => 'institutional',
            'start_date' => '2026-10-12',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('supply-office.index'))
            ->assertOk()
            ->assertSee('id="advanced-filter-toggle"', false)
            ->assertSee('aria-controls="advanced-request-filters"', false)
            ->assertSee('id="advanced-request-filters"', false)
            ->assertSee(' hidden ', false)
            ->assertSee('name="department"', false)
            ->assertSee('name="venue"', false)
            ->assertSee('name="date_from"', false)
            ->assertSee('name="date_to"', false)
            ->assertSee('name="priority"', false);

        $this->get(route('supply-office.index', [
            'department' => 'Information Technology',
            'venue' => 'Gymnasium',
            'date_from' => '2026-10-09',
            'date_to' => '2026-10-11',
            'priority' => 'regular',
        ]))
            ->assertOk()
            ->assertSee($matchingRequest->control_number)
            ->assertDontSee('TEST-SUPPLY-FILTER-NO-MATCH')
        ->assertSee('id="advanced-request-filters"', false)
            ->assertSee('aria-expanded="true"', false);
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

    public function test_supply_office_needs_revision_records_human_decision_without_changing_priority(): void
    {
        $request = $this->makeRequest(['priority' => 'institutional']);
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Revision Reviewer']);
        $requester = User::findOrFail($request->requested_by_id);

        $this->actingAs($admin)->post(route('supply-office.requests.needs-revision'), [
            'id' => $request->id,
            'notes' => 'Please confirm the venue schedule.',
        ])->assertRedirect(route('supply-office.index'));

        $request->refresh();
        $this->assertSame('needs_reschedule', $request->status);
        $this->assertSame('institutional', $request->priority);
        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $request->id,
            'action' => 'needs_revision',
            'user_id' => $admin->getKey(),
        ]);
        Notification::assertSentToTimes($requester, RequestStatusChanged::class, 1);
    }
}
