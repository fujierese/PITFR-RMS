<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestorSearchBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_are_sorted_by_reservation_date_and_time_ascending_by_default(): void
    {
        $user = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
        ]);

        foreach ([
            ['name' => 'December Request', 'date' => now()->addDays(30)->toDateString(), 'time' => '09:00'],
            ['name' => 'September Afternoon', 'date' => now()->addDays(2)->toDateString(), 'time' => '14:00'],
            ['name' => 'September Morning', 'date' => now()->addDays(2)->toDateString(), 'time' => '08:00'],
        ] as $item) {
            FacilityRequest::create([
                'requested_by_id' => $user->id,
                'control_number' => 'FER-' . str_replace(' ', '-', $item['name']),
                'name_of_activity' => $item['name'],
                'department' => 'College of Engineering',
                'status' => 'pending',
                'date_requested' => '2026-08-01',
                'expected_participants' => 20,
                'start_date' => $item['date'],
                'end_date' => $item['date'],
                'start_time' => $item['time'],
                'end_time' => '23:00',
                'venue' => ['Main Gym'],
                'equipment' => [],
            ]);
        }

        $response = $this->actingAs($user)->get(route('requestor.index', ['tab' => 'requests']));
        $response->assertOk();

        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'September Afternoon'), strpos($content, 'September Morning'));
        $this->assertLessThan(strpos($content, 'December Request'), strpos($content, 'September Afternoon'));
    }

    public function test_search_matches_partial_case_insensitive_across_multiple_fields(): void
    {
        $user = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
        ]);

        $request = FacilityRequest::create([
            'requested_by_id' => $user->id,
            'control_number' => 'FER-2026-001',
            'name_of_activity' => 'Inter-Purok Basketball League 2026',
            'department' => 'College of Engineering',
            'status' => 'approved',
            'date_requested' => '2026-08-01',
            'expected_participants' => 50,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => ['Main Gym'],
            'equipment' => ['Sound System'],
        ]);

        $request->requestVenues()->create(['name' => 'Main Gym']);
        $request->requestEquipment()->create(['name' => 'Sound System']);

        $this->actingAs($user);

        $response = $this->get(route('requestor.index', ['tab' => 'requests', 'search' => 'league']));
        $response->assertStatus(200);
        $response->assertSee($request->name_of_activity);

        $response = $this->get(route('requestor.index', ['tab' => 'requests', 'search' => 'basket']));
        $response->assertStatus(200);
        $response->assertSee($request->name_of_activity);

        $response = $this->get(route('requestor.index', ['tab' => 'requests', 'search' => '2026']));
        $response->assertStatus(200);
        $response->assertSee($request->name_of_activity);

        $response = $this->get(route('requestor.index', ['tab' => 'requests', 'search' => 'system']));
        $response->assertStatus(200);
        $response->assertSee('Sound System');
    }

    public function test_my_requests_hides_regular_priority_and_marks_urgent_requests(): void
    {
        $user = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
        ]);

        FacilityRequest::factory()->create([
            'requested_by_id' => $user->id,
            'priority' => 'regular',
            'status' => 'rejected',
            'venue_status' => 'rejected',
            'equipment_status' => 'pending',
        ]);
        $urgentRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $user->id,
            'priority' => 'regular',
            'is_emergency' => true,
            'status' => 'cancelled',
            'venue_status' => 'cancelled',
            'equipment_status' => 'cancelled',
        ]);

        $response = $this->actingAs($user)->get(route('requestor.index', ['tab' => 'requests']));

        $response->assertOk();
        $response->assertSee('Urgent');
        $response->assertSee('bg-rose-500 text-white', false);
        $response->assertDontSee('>Regular<', false);
        $response->assertSee($urgentRequest->control_number);

        $this->get(route('request.show', $urgentRequest))
            ->assertOk()
            ->assertSee('Request Priority')
            ->assertSee('Regular');
    }

    public function test_my_requests_groups_equipment_by_venue_and_shows_venue_custodian_assignments(): void
    {
        $requestor = User::factory()->create(['role' => 'requestor']);
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue', 'name' => 'Arlene Sala']);
        $equipmentCustodian = User::factory()->create(['role' => 'custodian-equipment', 'name' => 'Rogelio Guillermo']);
        $chic = Venue::create(['name' => 'CHIC', 'custodian_id' => $venueCustodian->id]);
        $court = Venue::create(['name' => 'Covered Court', 'custodian_id' => $venueCustodian->id]);
        $soundSystem = Equipment::factory()->create(['name' => 'Sound System', 'custodian_id' => $equipmentCustodian->id]);
        $chairs = Equipment::factory()->create(['name' => 'Chairs', 'custodian_id' => $equipmentCustodian->id]);

        $request = FacilityRequest::create([
            'requested_by_id' => $requestor->id,
            'control_number' => 'FER-EQUIPMENT-GROUPS-001',
            'name_of_activity' => 'Equipment display test',
            'department' => 'Information Technology',
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
            'date_requested' => now()->toDateString(),
            'expected_participants' => 20,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => ['CHIC', 'Covered Court'],
            'equipment' => ['Sound System', 'Chairs'],
            'equipment_quantities' => ['Sound System' => 3, 'Chairs' => 2],
        ]);
        $requestChic = $request->requestVenues()->create(['venue_id' => $chic->id, 'name' => 'CHIC']);
        $requestCourt = $request->requestVenues()->create(['venue_id' => $court->id, 'name' => 'Covered Court']);
        $request->requestEquipment()->create([
            'request_venue_id' => $requestChic->id,
            'equipment_id' => $soundSystem->id,
            'name' => 'Sound System',
            'quantity' => 1,
        ]);
        $request->requestEquipment()->create([
            'request_venue_id' => $requestChic->id,
            'equipment_id' => $soundSystem->id,
            'name' => 'Sound System',
            'quantity' => 2,
        ]);
        $request->requestEquipment()->create([
            'request_venue_id' => $requestCourt->id,
            'equipment_id' => $chairs->id,
            'name' => 'Chairs',
            'quantity' => 2,
        ]);

        $this->actingAs($requestor)
            ->get(route('requestor.index', ['tab' => 'requests']))
            ->assertOk()
            ->assertSee('CHIC')
            ->assertSee('Sound System')
            ->assertSee('×3')
            ->assertSee('Covered Court')
            ->assertSee('Chairs')
            ->assertSee('Custodian in charge:')
            ->assertSee('Arlene Sala')
            ->assertSee('Equipment custodian:')
            ->assertSee('Rogelio Guillermo')
            ->assertDontSee('Sound System ×1');
    }

    public function test_cancelled_activity_history_is_reflected_as_cancelled_in_request_list_and_details(): void
    {
        $user = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
        ]);
        $request = FacilityRequest::factory()->create([
            'requested_by_id' => $user->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);
        $request->addHistory('cancelled', 'Request cancelled by requester ' . $user->name, $user->id);
        $request->addHistory('request_cancelled_notification_sent', 'Cancellation notification recorded.', $user->id);

        $this->assertSame('cancelled', $request->fresh()->status);

        $listResponse = $this->actingAs($user)
            ->get(route('requestor.index', ['tab' => 'requests', 'status' => 'cancelled']));

        $listResponse->assertOk()
            ->assertSee($request->control_number)
            ->assertSee('Cancelled')
            ->assertSee('bg-slate-200 text-slate-700 ring-slate-300', false);

        $detailResponse = $this->get(route('request.show', $request));
        $detailResponse->assertOk()
            ->assertSee('Overall Status')
            ->assertSee('Cancelled')
            ->assertSee('Current stage: Cancelled')
            ->assertSee('the approval workflow has ended')
            ->assertDontSee('Current stage: Venue Review')
            ->assertSee('Reservation cancelled')
            ->assertSee('No further approval is required.')
            ->assertDontSee('Pending Venue Custodian Approval');
    }

    public function test_migration_reconciles_stored_status_for_requests_with_cancellation_history(): void
    {
        $user = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
        ]);
        $request = FacilityRequest::factory()->create([
            'requested_by_id' => $user->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);
        $request->addHistory('cancelled', 'Request cancelled by requester ' . $user->name, $user->id);

        $migration = require database_path('migrations/2026_10_04_000001_reconcile_cancelled_request_statuses.php');
        $migration->up();

        $this->assertDatabaseHas('facility_requests', [
            'id' => $request->id,
            'status' => 'cancelled',
            'venue_status' => 'cancelled',
            'equipment_status' => 'cancelled',
        ]);
    }

    public function test_rejected_request_shows_the_stage_that_rejected_it(): void
    {
        $user = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
        ]);
        $equipmentCustodian = User::factory()->create([
            'role' => 'custodian-equipment',
            'name' => 'Jaime Suralta',
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Final Reviewer',
        ]);
        $equipmentRejectedRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $user->id,
            'status' => 'rejected',
            'venue_status' => 'approved',
            'equipment_status' => 'rejected',
        ]);
        $equipmentRejectedRequest->addHistory(
            'equipment_status_rejected',
            'Request rejected by Jaime Suralta',
            $equipmentCustodian->id
        );
        $finalRejectedRequest = FacilityRequest::factory()->create([
            'requested_by_id' => $user->id,
            'status' => 'rejected',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);
        $finalRejectedRequest->addHistory(
            'final_rejected',
            'Final decline issued by Final Reviewer',
            $admin->id
        );

        $this->actingAs($user)
            ->get(route('request.show', $equipmentRejectedRequest))
            ->assertOk()
            ->assertSee('Current stage: Rejected at Equipment Review')
            ->assertSee('Reservation rejected at Equipment Review')
            ->assertSee('Jaime Suralta (Equipment Custodian)')
            ->assertDontSee('Supply Office, Supply Office');

        $this->get(route('request.show', $finalRejectedRequest))
            ->assertOk()
            ->assertSee('Current stage: Rejected at Final Approval')
            ->assertSee('Final Reviewer (Supply Office)')
            ->assertDontSee('Supply Office, Supply Office');
    }
}
