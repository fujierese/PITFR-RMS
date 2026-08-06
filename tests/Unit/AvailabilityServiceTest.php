<?php

namespace Tests\Unit;

use App\Models\FacilityRequest;
use App\Models\Holiday;
use App\Models\User;
use App\Models\Venue;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_holiday_blocks_venue_availability(): void
    {
        $service = new AvailabilityService();

        $custodian = User::create([
            'username' => 'custodian-holiday',
            'password' => 'secret',
            'name' => 'Custodian Holiday',
            'role' => 'custodian',
        ]);

        Venue::create([
            'name' => 'Conference Hall & Interaction Center (CHIC)',
            'capacity' => 150,
            'custodian_id' => $custodian->id,
        ]);
        Holiday::create([
            'holiday_date' => '2026-07-30',
            'name' => 'National Day',
            'type' => 'public',
        ]);

        $result = $service->checkVenueAvailability(
            'Conference Hall & Interaction Center (CHIC)',
            now()->parse('2026-07-30 09:00'),
            now()->parse('2026-07-30 10:00')
        );

        $this->assertFalse($result['available']);
        $this->assertStringContainsString('holiday', strtolower($result['message'] ?? ''));
    }

    public function test_relational_venue_assignments_block_conflicts(): void
    {
        $service = new AvailabilityService();

        $custodian = User::create([
            'username' => 'custodian-relational',
            'password' => 'secret',
            'name' => 'Custodian Relational',
            'role' => 'custodian',
        ]);

        Venue::create([
            'name' => 'Conference Hall & Interaction Center (CHIC)',
            'capacity' => 150,
            'custodian_id' => $custodian->id,
        ]);

        $requestor = User::create([
            'username' => 'requestor-relational',
            'password' => 'secret',
            'name' => 'Requestor Relational',
            'role' => 'requestor',
        ]);

        FacilityRequest::create([
            'control_number' => 'FER-2026-001',
            'date_requested' => '2026-08-10',
            'department' => 'ICT',
            'name_of_activity' => 'Test Activity',
            'expected_participants' => 50,
            'requested_by_id' => $requestor->id,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => [],
            'equipment_quantities' => [],
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $result = $service->checkVenueAvailability(
            'Conference Hall & Interaction Center (CHIC)',
            now()->parse('2026-08-10 09:30'),
            now()->parse('2026-08-10 10:30')
        );

        $this->assertFalse($result['available']);
        $this->assertStringContainsString('conflict', strtolower($result['message'] ?? ''));
    }
}
