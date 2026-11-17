<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentCustodianCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_equipment_without_a_custodian_is_never_approved_automatically(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian-equipment']);
        Equipment::factory()->create(['name' => 'Coverage Projector', 'custodian_id' => $custodian->id]);
        // 'Coverage Speaker' has no equipment record, so nobody is assigned to it.
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);

        $request = FacilityRequest::create([
            'control_number' => 'TEST-COVERAGE-' . uniqid(),
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => 'Coverage Test',
            'expected_participants' => 20,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => ['Coverage Hall'],
            'equipment' => ['Coverage Projector', 'Coverage Speaker'],
            'equipment_quantities' => ['Coverage Projector' => 1, 'Coverage Speaker' => 1],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'pending',
            'priority' => 'regular',
        ]);

        $this->assertSame(['Coverage Speaker'], $request->getEquipmentItemsWithoutCustodian());

        // The only assigned custodian approves their item; the unassigned item keeps the request incomplete.
        $request->equipment_custodian_statuses = [$custodian->id => 'approved'];
        $request->recomputeEquipmentStatus();
        $this->assertSame('pending', $request->refresh()->equipment_status);

        // Once a custodian is assigned and approves, the equipment stage completes.
        $second = User::factory()->create(['role' => 'custodian-equipment']);
        Equipment::factory()->create(['name' => 'Coverage Speaker', 'custodian_id' => $second->id]);
        $request = $request->fresh();
        $this->assertSame([], $request->getEquipmentItemsWithoutCustodian());
        $request->equipment_custodian_statuses = [$custodian->id => 'approved', $second->id => 'approved'];
        $request->recomputeEquipmentStatus();
        $this->assertSame('approved', $request->refresh()->equipment_status);
    }
}
