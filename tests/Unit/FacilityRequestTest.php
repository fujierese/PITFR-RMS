<?php

namespace Tests\Unit;

use App\Models\FacilityRequest;
use App\Models\RequestHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_overlap_detection_detects_intersecting_date_ranges(): void
    {
        $requestA = new FacilityRequest();
        $requestA->start_date = '2026-08-01';
        $requestA->end_date = '2026-08-01';
        $requestA->start_time = '09:00';
        $requestA->end_time = '12:00';

        $requestB = new FacilityRequest();
        $requestB->start_date = '2026-08-01';
        $requestB->end_date = '2026-08-01';
        $requestB->start_time = '10:00';
        $requestB->end_time = '13:00';

        $this->assertTrue($requestA->overlapsRequest($requestB));
    }

    public function test_overlap_detection_detects_non_overlapping_ranges(): void
    {
        $requestA = new FacilityRequest();
        $requestA->start_date = '2026-08-01';
        $requestA->end_date = '2026-08-01';
        $requestA->start_time = '09:00';
        $requestA->end_time = '12:00';

        $requestB = new FacilityRequest();
        $requestB->start_date = '2026-08-01';
        $requestB->end_date = '2026-08-01';
        $requestB->start_time = '12:00';
        $requestB->end_time = '13:00';

        $this->assertFalse($requestA->overlapsRequest($requestB));
    }

    public function test_venue_matching_uses_request_venues(): void
    {
        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-060',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Venue Match',
            'expected_participants' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => User::factory()->create()->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);

        $request->syncRelationalItems();

        $query = FacilityRequest::query()->matchesVenue('Conference Hall & Interaction Center (CHIC)');

        $this->assertTrue($query->whereKey($request->id)->exists());
    }

    public function test_add_history_persists_request_history_record(): void
    {
        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-061',
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'History Test',
            'expected_participants' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => [],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => User::factory()->create()->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);

        $request->addHistory('approved', 'Approved by tester');

        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $request->id,
            'action' => 'approved',
        ]);
    }
}
