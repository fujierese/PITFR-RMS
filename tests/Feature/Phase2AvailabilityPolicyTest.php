<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2AvailabilityPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_venue_request_does_not_block_availability(): void
    {
        [$requestor, $venue] = $this->resourceSetup('Pending Venue');
        $this->request($requestor, $venue, 'pending', '09:00', '12:00');

        $result = app(AvailabilityService::class)->checkVenueAvailability(
            $venue->name,
            Carbon::parse('2026-08-28 09:00'),
            Carbon::parse('2026-08-28 12:00')
        );

        $this->assertTrue($result['available']);
    }

    public function test_adjacent_approved_venue_requests_do_not_overlap(): void
    {
        [$requestor, $venue] = $this->resourceSetup('Adjacent Venue');
        $this->request($requestor, $venue, 'approved', '09:00', '12:00');

        $result = app(AvailabilityService::class)->checkVenueAvailability(
            $venue->name,
            Carbon::parse('2026-08-28 12:00'),
            Carbon::parse('2026-08-28 15:00')
        );

        $this->assertTrue($result['available']);
    }

    public function test_venue_check_can_exclude_the_request_being_revised(): void
    {
        [$requestor, $venue] = $this->resourceSetup('Self Exclusion Venue');
        $request = $this->request($requestor, $venue, 'approved', '09:00', '12:00');

        $result = app(AvailabilityService::class)->checkVenueAvailability(
            $venue->name,
            Carbon::parse('2026-08-28 09:00'),
            Carbon::parse('2026-08-28 12:00'),
            $request->id
        );

        $this->assertTrue($result['available']);
    }

    public function test_approved_equipment_quantity_is_counted_across_overlapping_requests(): void
    {
        [$requestor, $venue, $equipment] = $this->resourceSetup('Equipment Venue', true);
        $this->request($requestor, $venue, 'approved', '09:00', '12:00', $equipment, 3);

        $withinLimit = app(AvailabilityService::class)->checkEquipmentAvailability(
            $equipment->name,
            2,
            Carbon::parse('2026-08-28 10:00'),
            Carbon::parse('2026-08-28 11:00')
        );
        $overLimit = app(AvailabilityService::class)->checkEquipmentAvailability(
            $equipment->name,
            3,
            Carbon::parse('2026-08-28 10:00'),
            Carbon::parse('2026-08-28 11:00')
        );

        $this->assertTrue($withinLimit['available']);
        $this->assertFalse($overLimit['available']);
    }

    public function test_pending_equipment_request_does_not_consume_availability(): void
    {
        [$requestor, $venue, $equipment] = $this->resourceSetup('Pending Equipment Venue', true);
        $this->request($requestor, $venue, 'pending', '09:00', '12:00', $equipment, 3);

        $result = app(AvailabilityService::class)->checkEquipmentAvailability(
            $equipment->name,
            3,
            Carbon::parse('2026-08-28 10:00'),
            Carbon::parse('2026-08-28 11:00')
        );

        $this->assertTrue($result['available']);
    }

    public function test_partially_returned_approved_equipment_remains_blocked(): void
    {
        [$requestor, $venue, $equipment] = $this->resourceSetup('Partial Return Venue', true);
        $request = $this->request($requestor, $venue, 'approved', '09:00', '12:00', $equipment, 3);
        $request->update(['equipment_returned_status' => 'partial']);

        $result = app(AvailabilityService::class)->checkEquipmentAvailability(
            $equipment->name,
            3,
            Carbon::parse('2026-08-28 10:00'),
            Carbon::parse('2026-08-28 11:00')
        );

        $this->assertFalse($result['available']);
    }

    private function resourceSetup(string $venueName, bool $withEquipment = false): array
    {
        $requestor = User::factory()->create(['role' => 'requestor']);
        $custodian = User::factory()->create(['role' => 'custodian']);
        $venue = Venue::create([
            'name' => $venueName,
            'custodian_id' => $custodian->id,
            'capacity' => 100,
            'is_active' => true,
        ]);

        if (!$withEquipment) {
            return [$requestor, $venue];
        }

        $equipment = Equipment::create([
            'name' => 'Test Equipment',
            'custodian_id' => $custodian->id,
            'quantity' => 5,
            'quantity_available' => 5,
            'is_active' => true,
        ]);

        return [$requestor, $venue, $equipment];
    }

    private function request(
        User $requestor,
        Venue $venue,
        string $status,
        string $startTime,
        string $endTime,
        ?Equipment $equipment = null,
        int $quantity = 0
    ): FacilityRequest {
        $equipmentItems = $equipment ? [$equipment->name] : [];

        return FacilityRequest::create([
            'control_number' => 'FER-' . uniqid(),
            'date_requested' => '2026-08-28',
            'department' => 'Test Department',
            'name_of_activity' => 'Test Activity',
            'expected_participants' => 20,
            'start_date' => '2026-08-28',
            'end_date' => '2026-08-28',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'venue' => [$venue->name],
            'equipment' => $equipmentItems,
            'equipment_quantities' => $equipment ? [$equipment->name => $quantity] : [],
            'requested_by_id' => $requestor->id,
            'status' => $status,
            'venue_status' => $status === 'approved' ? 'approved' : 'pending',
            'equipment_status' => $status === 'approved' ? 'approved' : 'pending',
        ]);
    }
}
