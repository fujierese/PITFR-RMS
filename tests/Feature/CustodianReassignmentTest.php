<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue APR-10: an endorsement from a custodian who is no longer responsible for the
 * venue or equipment stops counting on requests still awaiting final approval.
 */
class CustodianReassignmentTest extends TestCase
{
    use RefreshDatabase;

    private function endorsedRequest(array $attributes = []): FacilityRequest
    {
        return FacilityRequest::factory()->create(array_merge([
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'supply_office_review_status' => 'approved',
            'venue' => ['Gymnasium'],
            'equipment' => [],
        ], $attributes));
    }

    public function test_venue_confirmation_reopens_when_the_venue_custodian_changes(): void
    {
        $oldCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $newCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $venue = Venue::create(['name' => 'Gymnasium', 'custodian_id' => $oldCustodian->id, 'capacity' => 100]);

        $pending = $this->endorsedRequest();
        $pending->recordApprovalSignature('venue', $oldCustodian);
        $finalApproved = $this->endorsedRequest(['status' => 'approved']);
        $otherVenue = $this->endorsedRequest(['venue' => ['Covered Court']]);

        $venue->update(['custodian_id' => $newCustodian->id]);

        $pending->refresh();
        $this->assertSame('pending', $pending->venue_status);
        $this->assertSame([], $pending->getStageApprovalSnapshots('venue'));
        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $pending->id,
            'action' => 'venue_endorsement_reopened',
        ]);
        // Already final-approved reservations and other venues are not touched.
        $this->assertSame('approved', $finalApproved->fresh()->venue_status);
        $this->assertSame('approved', $otherVenue->fresh()->venue_status);
    }

    public function test_venue_confirmation_stays_when_the_new_custodian_already_confirmed(): void
    {
        $oldCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $newCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $venue = Venue::create(['name' => 'Gymnasium', 'custodian_id' => $oldCustodian->id, 'capacity' => 100]);

        $request = $this->endorsedRequest();
        $request->recordApprovalSignature('venue', $newCustodian);

        $venue->update(['custodian_id' => $newCustodian->id]);

        $this->assertSame('approved', $request->fresh()->venue_status);
    }

    public function test_venue_edit_without_custodian_change_keeps_confirmations(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        $venue = Venue::create(['name' => 'Gymnasium', 'custodian_id' => $custodian->id, 'capacity' => 100]);
        $request = $this->endorsedRequest();

        $venue->update(['capacity' => 200]);

        $this->assertSame('approved', $request->fresh()->venue_status);
    }

    public function test_equipment_endorsement_reopens_when_the_equipment_custodian_changes(): void
    {
        $oldCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        $newCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        $equipment = Equipment::create([
            'name' => 'Sound System',
            'custodian_id' => $oldCustodian->id,
            'quantity' => 2,
            'quantity_available' => 2,
        ]);
        $request = $this->endorsedRequest([
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 1],
            'equipment_custodian_statuses' => [(string) $oldCustodian->id => 'approved'],
        ]);

        $equipment->update(['custodian_id' => $newCustodian->id]);

        $request->refresh();
        $this->assertSame('pending', $request->equipment_status);
        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $request->id,
            'action' => 'equipment_endorsement_reopened',
        ]);
    }
}
