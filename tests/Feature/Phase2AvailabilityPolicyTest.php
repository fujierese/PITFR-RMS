<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_same_day_whole_day_booking_can_be_followed_by_a_booking_starting_at_five_pm(): void
    {
        foreach (['approved', 'pending'] as $existingStatus) {
            [$requestor, $venue] = $this->resourceSetup('Evening Adjacent Venue ' . $existingStatus);
            $this->request(
                $requestor,
                $venue,
                $existingStatus,
                '08:00',
                '17:00',
                startDate: '2026-11-02',
                endDate: '2026-11-02',
                reservationDuration: 'whole_day',
            );
            $eveningRequest = $this->request(
                $requestor,
                $venue,
                'pending',
                '17:00',
                '23:59',
                startDate: '2026-11-02',
                endDate: '2026-11-02',
            );

            $this->assertNull(
                app(AvailabilityService::class)->checkVenueScheduleForReview($eveningRequest, $venue->name),
                "A booking starting at 5:00 PM must not conflict with an 8:00 AM–5:00 PM booking ({$existingStatus})."
            );
        }
    }

    public function test_multiday_whole_day_request_does_not_conflict_with_later_pending_reservation(): void
    {
        [$requestor, $venue] = $this->resourceSetup('Multiday Whole Day Venue');
        $this->request($requestor, $venue, 'pending', '17:00', '23:59');
        $wholeDayRequest = $this->request(
            $requestor,
            $venue,
            'pending',
            '08:00',
            '17:00',
            startDate: '2026-08-28',
            endDate: '2026-08-29',
            reservationDuration: 'whole_day',
        );

        $conflict = app(AvailabilityService::class)->checkVenueScheduleForReview($wholeDayRequest, $venue->name);

        $this->assertNull($conflict);
    }

    public function test_multiday_whole_day_request_only_warns_about_an_overlapping_pending_reservation(): void
    {
        [$requestor, $venue] = $this->resourceSetup('Overlapping Whole Day Venue');
        $this->request($requestor, $venue, 'pending', '16:00', '18:00');
        $wholeDayRequest = $this->request(
            $requestor,
            $venue,
            'pending',
            '08:00',
            '17:00',
            startDate: '2026-08-28',
            endDate: '2026-08-29',
            reservationDuration: 'whole_day',
        );

        // Unified workflow: a pending overlap is tentative (a warning), not an actual conflict.
        $this->assertNull(app(AvailabilityService::class)->checkVenueScheduleForReview($wholeDayRequest, $venue->name));
        $this->assertStringStartsWith(
            'Another request is waiting for review for this time:',
            (string) app(AvailabilityService::class)->checkVenueForCustodian($wholeDayRequest, $venue->name)['warning'],
        );
    }

    public function test_multiday_whole_day_request_does_not_conflict_with_later_approved_reservation(): void
    {
        [$requestor, $venue] = $this->resourceSetup('Approved Adjacent Whole Day Venue');
        $lateReservation = $this->request($requestor, $venue, 'approved', '17:00', '23:59');
        $wholeDayRequest = $this->request(
            $requestor,
            $venue,
            'pending',
            '08:00',
            '17:00',
            startDate: '2026-08-28',
            endDate: '2026-08-29',
            reservationDuration: 'whole_day',
        );

        $conflict = app(AvailabilityService::class)->checkFacilityRequest($wholeDayRequest);

        $this->assertNull($conflict);
        $this->assertFalse($wholeDayRequest->overlapsRequest($lateReservation));
        $this->assertCount(0, app(AvailabilityService::class)->getApprovedVenueConflicts($wholeDayRequest));
    }

    public function test_multiday_whole_day_equipment_availability_ignores_later_reservation(): void
    {
        [$requestor, $venue, $equipment] = $this->resourceSetup('Whole Day Equipment Venue', true);
        $this->request($requestor, $venue, 'approved', '17:00', '23:59', $equipment, 3);
        $wholeDayRequest = $this->request(
            $requestor,
            $venue,
            'pending',
            '08:00',
            '17:00',
            $equipment,
            3,
            '2026-08-28',
            '2026-08-29',
            'whole_day',
        );

        $conflict = app(AvailabilityService::class)->checkFacilityRequest($wholeDayRequest);

        $this->assertNull($conflict);
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

    public function test_equipment_availability_warning_matches_reserved_and_remaining_inventory(): void
    {
        [$requestor, $venue, $equipment] = $this->resourceSetup('Availability Warning Venue', true);
        $this->request($requestor, $venue, 'approved', '09:00', '12:00', $equipment, 3);
        $this->request($requestor, $venue, 'pending', '09:30', '11:30', $equipment, 1);

        $response = $this->actingAs($requestor)->get(route('equipment.availability', [
            'name' => $equipment->name,
            'start_date' => '2026-08-28',
            'end_date' => '2026-08-28',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]));

        $response->assertOk()
            ->assertJsonPath('total', 5)
            ->assertJsonPath('reserved_quantity', 3)
            ->assertJsonPath('remaining_quantity', 2)
            ->assertJsonPath('available', 2)
            ->assertJsonCount(1, 'overlapping_requests')
            ->assertJsonPath('overlapping_requests.0.quantity', 3);

        $this->assertSame(
            $response->json('total'),
            $response->json('reserved_quantity') + $response->json('remaining_quantity'),
        );
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

    public function test_transactional_resource_locks_are_created_for_catalog_resources(): void
    {
        [, $venue, $equipment] = $this->resourceSetup('Lockable Venue', true);
        $request = new FacilityRequest([
            'venue' => [$venue->name],
            'equipment' => [$equipment->name],
            'equipment_quantities' => [$equipment->name => 1],
        ]);

        DB::transaction(function () use ($request): void {
            app(AvailabilityService::class)->lockResourcesForFacilityRequests($request);
        });

        $this->assertDatabaseCount('reservation_resource_locks', 2);
    }

    public function test_resource_locks_create_rows_for_missing_catalog_names_too(): void
    {
        $request = new FacilityRequest([
            'venue' => ['Custom Venue'],
            'equipment' => ['Custom Equipment'],
            'equipment_quantities' => ['Custom Equipment' => 1],
        ]);

        DB::transaction(function () use ($request): void {
            app(AvailabilityService::class)->lockResourcesForFacilityRequests($request);
        });

        $this->assertDatabaseCount('reservation_resource_locks', 2);
        $this->assertDatabaseHas('reservation_resource_locks', [
            'resource_type' => 'venue',
            'resource_hash' => hash('sha256', 'custom venue'),
        ]);
        $this->assertDatabaseHas('reservation_resource_locks', [
            'resource_type' => 'equipment',
            'resource_hash' => hash('sha256', 'custom equipment'),
        ]);
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
        int $quantity = 0,
        string $startDate = '2026-08-28',
        ?string $endDate = null,
        string $reservationDuration = 'specific_time',
    ): FacilityRequest {
        $equipmentItems = $equipment ? [$equipment->name] : [];
        $endDate ??= $startDate;

        return FacilityRequest::create([
            'control_number' => 'FER-' . uniqid(),
            'date_requested' => $startDate,
            'department' => 'Test Department',
            'name_of_activity' => 'Test Activity',
            'expected_participants' => 20,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'reservation_duration' => $reservationDuration,
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
