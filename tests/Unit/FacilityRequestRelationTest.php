<?php

namespace Tests\Unit;

use App\Models\FacilityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityRequestRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_relational_getters_use_relation_rows_when_legacy_json_is_present(): void
    {
        $requestor = User::create([
            'username' => 'requestor-relations',
            'password' => 'secret',
            'name' => 'Requestor Relations',
            'role' => 'requestor',
        ]);

        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-010',
            'date_requested' => '2026-08-01',
            'department' => 'ICT',
            'name_of_activity' => 'Relation Test',
            'expected_participants' => 10,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-01',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => ['Legacy Venue'],
            'equipment' => ['Legacy Equipment'],
            'equipment_quantities' => ['Legacy Equipment' => 7],
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
            'requested_by_id' => $requestor->id,
        ]);

        $request->requestVenues()->create(['name' => 'Relational Venue']);
        $request->requestEquipment()->create(['name' => 'Laptop', 'quantity' => 2]);

        $this->assertSame(['Relational Venue'], $request->getVenueNames());
        $this->assertSame(['Laptop'], $request->getEquipmentItems());
        $this->assertSame(['Laptop' => 2], $request->getEquipmentQuantities());
    }

    public function test_query_scopes_match_relational_and_legacy_resources(): void
    {
        $requestor = User::create([
            'username' => 'requestor-scopes',
            'password' => 'secret',
            'name' => 'Requestor Scopes',
            'role' => 'requestor',
        ]);

        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-011',
            'date_requested' => '2026-08-02',
            'department' => 'ICT',
            'name_of_activity' => 'Scope Test',
            'expected_participants' => 8,
            'start_date' => '2026-08-02',
            'end_date' => '2026-08-02',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => ['Legacy Venue'],
            'equipment' => ['Legacy Equipment'],
            'equipment_quantities' => ['Legacy Equipment' => 4],
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
            'requested_by_id' => $requestor->id,
        ]);

        $request->requestVenues()->create(['name' => 'Relational Venue']);
        $request->requestEquipment()->create(['name' => 'Laptop', 'quantity' => 2]);

        $this->assertTrue(FacilityRequest::query()->matchesVenue('Relational Venue')->whereKey($request->id)->exists());
        $this->assertTrue(FacilityRequest::query()->matchesVenue('Legacy Venue')->whereKey($request->id)->exists());
        $this->assertTrue(FacilityRequest::query()->matchesEquipment('Laptop')->whereKey($request->id)->exists());
        $this->assertTrue(FacilityRequest::query()->matchesEquipment('Legacy Equipment')->whereKey($request->id)->exists());
    }
}
