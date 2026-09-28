<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestorSearchBackendTest extends TestCase
{
    use RefreshDatabase;

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
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
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
}
