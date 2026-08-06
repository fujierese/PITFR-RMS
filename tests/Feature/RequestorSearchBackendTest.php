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
}
