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

    public function test_supply_office_staff_can_confirm_availability_and_are_attributed_in_history_and_calendar(): void
    {
        $request = $this->makeRequest([
            'supply_office_review_status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'pending',
        ]);
        $staff = User::factory()->create([
            'name' => 'Jose Julius J. Paloma',
            'role' => 'supply_office',
            'additional_roles' => ['custodian-venue'],
            'department' => 'Supply Office',
        ]);

        $this->actingAs($staff)
            ->get(route('supply-office.index'))
            ->assertOk()
            ->assertSee('Check calendar')
            ->assertSee('id="staff-venue-calendar-modal"', false);

        $this->actingAs($staff)
            ->post(route('supply-office.requests.availability-review'), [
                'id' => $request->id,
                'decision' => 'available',
                'urgent_document_validated' => '1',
            ])
            ->assertRedirect(route('supply-office.index'))
            ->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame($staff->id, $request->supply_office_reviewed_by_id);
        $history = $request->histories()->where('action', 'availability_confirmed')->firstOrFail();
        $this->assertSame($staff->id, $history->user_id);
        $this->assertStringContainsString($staff->name, $history->detail);

        $requester = User::findOrFail($request->requested_by_id);
        $this->actingAs($requester)
            ->get(route('request.show', $request->id))
            ->assertOk()
            ->assertSee('Recorded by ' . $staff->name);

        $calendar = $this->actingAs($staff)->getJson(route('calendar.events'));
        $calendar->assertOk();
        $event = collect($calendar->json())->firstWhere('extendedProps.facilityRequestId', $request->id);
        $this->assertSame($staff->name, $event['extendedProps']['assistedBy']);
    }

    public function test_initial_availability_actions_wait_for_calendar_validation(): void
    {
        $request = $this->makeRequest([
            'supply_office_review_status' => 'pending',
            'venue_status' => 'approved',
        ]);
        $staff = User::factory()->create(['role' => 'supply_office']);
        $group = 'supply-availability-' . $request->id;

        $this->actingAs($staff)
            ->get(route('supply-office.index'))
            ->assertOk()
            ->assertSee('data-availability-check-url="' . route('calendar.request-availability-check', $request) . '"', false)
            ->assertSee('data-availability-group="' . $group . '"', false)
            ->assertSee('data-availability-actions="' . $group . '" class="hidden flex flex-wrap gap-2"', false)
            ->assertSee('data-availability-confirm="' . $group . '"', false);

        $this->actingAs($staff)
            ->postJson(route('calendar.request-availability-check', $request), ['venue' => 'Gymnasium'])
            ->assertOk()
            ->assertJsonPath('available', true);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->postJson(route('calendar.request-availability-check', $request), ['venue' => 'Gymnasium'])
            ->assertOk()
            ->assertJsonPath('available', true);
    }

    public function test_pending_venue_schedule_conflict_blocks_confirmation_and_is_returned_by_calendar_check(): void
    {
        $request = $this->makeRequest([
            'supply_office_review_status' => 'pending',
            'venue_status' => 'approved',
        ]);
        $this->makeRequest([
            'control_number' => 'TEST-PENDING-VENUE-CONFLICT',
            'start_date' => $request->start_date->toDateString(),
            'end_date' => $request->end_date->toDateString(),
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'venue' => ['Gymnasium'],
        ]);
        $staff = User::factory()->create(['role' => 'supply_office']);

        $this->actingAs($staff)
            ->postJson(route('calendar.request-availability-check', $request), ['venue' => 'Gymnasium'])
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('message', 'Another request for this venue and time is still awaiting review. Resolve that request before confirming availability.');

        $this->actingAs($staff)
            ->from(route('supply-office.index'))
            ->post(route('supply-office.requests.availability-review'), [
                'id' => $request->id,
                'decision' => 'available',
            ])
            ->assertRedirect(route('supply-office.index'))
            ->assertSessionHasErrors('decision');

        $this->assertSame('pending', $request->fresh()->supply_office_review_status);
    }

    public function test_requestors_cannot_run_staff_availability_checks(): void
    {
        $request = $this->makeRequest([
            'supply_office_review_status' => 'pending',
            'venue_status' => 'approved',
        ]);
        $requestor = User::findOrFail($request->requested_by_id);

        $this->actingAs($requestor)
            ->postJson(route('calendar.request-availability-check', $request), ['venue' => 'Gymnasium'])
            ->assertForbidden();
    }

    public function test_supply_office_cannot_confirm_until_venue_custodian_has_confirmed(): void
    {
        $request = $this->makeRequest([
            'supply_office_review_status' => 'pending',
            'venue_status' => 'pending',
        ]);
        $staff = User::factory()->create(['role' => 'supply_office']);

        $this->actingAs($staff)
            ->from(route('supply-office.index'))
            ->post(route('supply-office.requests.availability-review'), [
                'id' => $request->id,
                'decision' => 'available',
            ])
            ->assertRedirect(route('supply-office.index'))
            ->assertSessionHasErrors();

        $this->assertSame('pending', $request->fresh()->venue_status);
        $this->assertSame('pending', $request->fresh()->supply_office_review_status);
    }

    public function test_venue_custodian_cannot_confirm_a_schedule_with_a_recorded_conflict(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create([
            'name' => 'Gymnasium',
            'custodian_id' => $custodian->id,
            'is_active' => true,
        ]);
        $request = $this->makeRequest([
            'venue_status' => 'pending',
            'supply_office_review_status' => 'pending',
        ]);
        $this->makeRequest([
            'control_number' => 'TEST-VENUE-CONFLICT',
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'start_date' => $request->start_date->toDateString(),
            'end_date' => $request->end_date->toDateString(),
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);

        $this->actingAs($custodian)
            ->postJson(route('calendar.request-availability-check', $request), ['venue' => 'Gymnasium'])
            ->assertOk()
            ->assertJsonPath('available', false);

        $this->actingAs($custodian)
            ->from(route('request.show', $request))
            ->post(route('request.custodian.verify', $request))
            ->assertRedirect(route('request.show', $request))
            ->assertSessionHasErrors();

        $this->assertSame('pending', $request->fresh()->venue_status);
    }

    public function test_supply_office_cannot_finally_approve_before_availability_is_confirmed(): void
    {
        $request = $this->makeRequest([
            'supply_office_review_status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('supply-office.index'))
            ->post(route('supply-office.update'), [
                'id' => $request->id,
                'action' => 'approve',
            ])
            ->assertRedirect(route('supply-office.index'))
            ->assertSessionHasErrors();

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_venue_custodian_can_review_calendar_and_propose_a_reschedule(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create([
            'name' => 'Gymnasium',
            'custodian_id' => $custodian->id,
            'is_active' => true,
        ]);
        $request = $this->makeRequest([
            'venue_status' => 'pending',
            'supply_office_review_status' => 'pending',
        ]);

        $this->actingAs($custodian)
            ->get(route('request.show', $request))
            ->assertOk()
            ->assertSee('Check venue calendar')
            ->assertSee('calendar/embed?venue=Gymnasium', false)
            ->assertSee('id="staff-venue-calendar-modal"', false)
            ->assertSee('<dialog', false)
            ->assertDontSee('Open full calendar in a new tab')
            ->assertSee('Confirm Venue Availability')
            ->assertSee('data-availability-confirm="custodian-venue-' . $request->id . '"', false)
            ->assertSee('class="hidden"', false)
            ->assertSee('data-availability-check-url="' . route('calendar.request-availability-check', $request) . '"', false)
            ->assertSee('data-swal-title="Confirm venue availability?"', false)
            ->assertSee('Propose Reschedule')
            ->assertSee('The system will recheck availability before sending the proposal.');

        $this->actingAs($custodian)
            ->postJson(route('calendar.request-availability-check', $request), ['venue' => 'Gymnasium'])
            ->assertOk()
            ->assertJsonPath('available', true);

        $newDate = now()->addDays(4)->toDateString();
        $this->actingAs($custodian)
            ->from(route('request.show', $request))
            ->post(route('custodian.requests.revise'), [
                'facility_request_id' => $request->id,
                'start_date' => $newDate,
                'end_date' => $newDate,
                'start_time' => '12:00',
                'end_time' => '14:00',
                'venue' => ['Gymnasium'],
                'revision_reason' => 'Requested schedule conflicts with another booking.',
            ])
            ->assertRedirect(route('request.show', $request))
            ->assertSessionHasNoErrors();

        $revision = $request->revisionHistories()->where('status', 'pending')->firstOrFail();
        $this->assertSame($custodian->id, $revision->revised_by_id);
        $this->assertSame('accepted', $revision->responses[(string) $custodian->id]);
        $this->assertSame('pending', $revision->responses[(string) $request->requested_by_id]);
        $this->assertSame('pending', $request->fresh()->venue_status);

        $this->actingAs($custodian)
            ->post(route('request.custodian.verify', $request))
            ->assertSessionHasErrors();
        $this->assertSame('pending', $request->fresh()->venue_status);

        $requester = User::findOrFail($request->requested_by_id);
        $this->actingAs($requester)
            ->post(route('request.revision.respond', [$request, $revision]), ['response' => 'accept'])
            ->assertRedirect(route('request.show', $request))
            ->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame($newDate, $request->start_date->toDateString());
        $this->assertSame('pending', $request->status);
        $this->assertSame('pending', $request->venue_status);
        $this->assertSame('pending', $request->supply_office_review_status);
    }

    public function test_urgent_availability_requires_document_confirmation_only_for_available_decision(): void
    {
        $urgentRequest = $this->makeRequest([
            'is_emergency' => true,
            'proposal_file' => 'urgent-memo.pdf',
            'urgent_document_status' => 'pending',
            'supply_office_review_status' => 'pending',
        ]);
        $staff = User::factory()->create(['role' => 'supply_office']);

        $this->actingAs($staff)
            ->from(route('supply-office.index'))
            ->post(route('supply-office.requests.availability-review'), [
                'id' => $urgentRequest->id,
                'decision' => 'available',
            ])
            ->assertRedirect(route('supply-office.index'))
            ->assertSessionHasErrors([
                'urgent_document_validated' => 'Confirm that you reviewed the urgent supporting document before confirming availability.',
            ]);

        $this->assertSame('pending', $urgentRequest->fresh()->supply_office_review_status);
        $this->assertSame('pending', $urgentRequest->fresh()->urgent_document_status);

        $this->actingAs($staff)
            ->from(route('supply-office.index'))
            ->post(route('supply-office.requests.availability-review'), [
                'id' => $urgentRequest->id,
                'decision' => 'available',
                'urgent_document_validated' => '1',
            ])
            ->assertRedirect(route('supply-office.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('validated', $urgentRequest->fresh()->urgent_document_status);
        $this->assertSame('confirmed', $urgentRequest->fresh()->supply_office_review_status);
    }

    public function test_urgent_document_confirmation_is_not_required_when_availability_is_unavailable(): void
    {
        $urgentRequest = $this->makeRequest([
            'is_emergency' => true,
            'urgent_document_status' => 'pending',
            'supply_office_review_status' => 'pending',
        ]);
        $staff = User::factory()->create(['role' => 'supply_office']);

        $this->actingAs($staff)
            ->from(route('supply-office.index'))
            ->post(route('supply-office.requests.availability-review'), [
                'id' => $urgentRequest->id,
                'decision' => 'unavailable',
                'notes' => 'The requested venue is unavailable.',
            ])
            ->assertRedirect(route('supply-office.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('needs_reschedule', $urgentRequest->fresh()->status);
        $this->assertSame('unavailable', $urgentRequest->fresh()->supply_office_review_status);
        $this->assertSame('pending', $urgentRequest->fresh()->urgent_document_status);
    }

    public function test_schedule_proposal_has_explicit_confirmation_and_success_feedback(): void
    {
        $request = $this->makeRequest(['status' => 'approved']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('request.show', $request))
            ->assertOk()
            ->assertSee('Send this schedule proposal?', false)
            ->assertSee('Yes, send proposal', false)
            ->assertSee('Proposal sent', false)
            ->assertSee('View updated request', false);
    }

    public function test_ajax_schedule_proposal_errors_are_returned_as_json_instead_of_redirecting(): void
    {
        $request = $this->makeRequest(['status' => 'approved']);
        $admin = User::factory()->create(['role' => 'admin']);
        Venue::create(['name' => 'Gymnasium', 'capacity' => 100, 'custodian_id' => $admin->id, 'is_active' => true]);

        $this->actingAs($admin)
            ->postJson(route('supply-office.requests.revise'), [
                'facility_request_id' => $request->id,
                'start_date' => now()->addDays(3)->toDateString(),
                'end_date' => now()->addDays(3)->toDateString(),
                'start_time' => '05:00',
                'end_time' => '17:00',
                'venue' => ['Gymnasium'],
                'revision_reason' => 'The existing schedule needs an alternative time.',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.end_time.0', 'Reservations cannot start or end between 12:00 AM and 5:00 AM. Choose times after 5:00 AM.')
            ->assertJsonMissingPath('errors.urgent_document_validated');
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
            $this->makeRequest(['supply_office_review_status' => 'pending']);
        }

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('supply-office.index'))
            ->assertOk()
            ->assertSee('16 waiting')
            ->assertSee('availability_page=2');

        $this->actingAs($admin)
            ->get(route('supply-office.index', ['availability_page' => 2]))
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
