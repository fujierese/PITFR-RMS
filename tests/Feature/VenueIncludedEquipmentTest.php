<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * REQ-09: equipment a venue provides is shown as "Included with venue", is never
 * counted as an extra, and an extra unit for another venue is still tracked.
 */
class VenueIncludedEquipmentTest extends TestCase
{
    use RefreshDatabase;

    private User $requestor;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create(['name' => 'Balay Alumni', 'capacity' => 200, 'custodian_id' => $venueCustodian->id, 'is_active' => true]);
        Venue::create(['name' => 'Gymnasium', 'capacity' => 500, 'custodian_id' => $venueCustodian->id, 'is_active' => true]);
        $equipmentCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        Equipment::factory()->create([
            'name' => 'Sound System',
            'custodian_id' => $equipmentCustodian->id,
            'quantity' => 3,
            'quantity_available' => 3,
        ]);
        $this->requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
    }

    private function submit(string $activity, array $venues, array $venueEquipment = [], array $quantities = [])
    {
        $date = now()->addDays(5)->toDateString();
        $schedules = [];
        foreach ($venues as $venue) {
            $schedules[$venue] = [
                'start_date' => $date,
                'end_date' => $date,
                'start_time' => '08:00',
                'end_time' => '10:00',
                'reservation_duration' => 'specific_time',
            ];
        }

        return $this->actingAs($this->requestor)->post(route('requestor.store'), [
            'department' => 'Test Department',
            'request_context' => 'personal',
            'name_of_activity' => $activity,
            'expected_participants' => 20,
            'venue' => $venues,
            'venue_schedules' => $schedules,
            'venue_equipment' => $venueEquipment,
            'venue_equipment_quantities' => $quantities,
            'e_signature_file' => UploadedFile::fake()->create('signature.png', 100, 'image/png'),
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
        ]);
    }

    public function test_extra_unit_for_another_venue_stays_additional_and_included_one_is_not_counted(): void
    {
        $this->submit(
            'Two Venue Sound',
            ['Balay Alumni', 'Gymnasium'],
            ['Balay Alumni' => ['Sound System'], 'Gymnasium' => ['Sound System']],
            ['Balay Alumni' => ['Sound System' => 1], 'Gymnasium' => ['Sound System' => 2]],
        )->assertSessionHasNoErrors();

        $request = FacilityRequest::query()->where('name_of_activity', 'Two Venue Sound')->firstOrFail();

        $this->assertSame(['Gymnasium' => ['Sound System' => 2]], $request->getVenueEquipmentAllocations());
        $this->assertSame(['Sound System' => 2], $request->getAdditionalEquipmentQuantities());
        $this->assertFalse($request->isVenueIncludedEquipment('Sound System'));

        $this->actingAs($this->requestor)
            ->get(route('request.show', $request))
            ->assertOk()
            ->assertSee('Included with venue')
            ->assertSee('Additional requested')
            ->assertSee('Qty 2');
    }

    public function test_venue_only_request_has_no_additional_equipment(): void
    {
        $this->submit('Balay Only', ['Balay Alumni'])->assertSessionHasNoErrors();

        $request = FacilityRequest::query()->where('name_of_activity', 'Balay Only')->firstOrFail();

        $this->assertSame([], $request->getAdditionalEquipmentQuantities());
        $this->assertTrue($request->isVenueIncludedEquipment('Sound System'));
        $this->assertFalse($request->hasAdditionalEquipmentToReturn());
    }

    public function test_shared_stock_is_totalled_by_equipment_record_across_venues(): void
    {
        // 2 + 2 of the same item at the same time exceeds the 3 in stock, even if
        // one venue spells the name differently.
        $this->submit(
            'Spelling Variant',
            ['Balay Alumni', 'Gymnasium'],
            ['Balay Alumni' => ['Tables'], 'Gymnasium' => ['Sound System']],
            ['Balay Alumni' => ['Tables' => 1], 'Gymnasium' => ['Sound System' => 2]],
        )->assertSessionHasNoErrors();

        Equipment::factory()->create(['name' => 'Tables', 'quantity' => 3, 'quantity_available' => 3]);
        $request = FacilityRequest::query()->where('name_of_activity', 'Spelling Variant')->firstOrFail();
        $request->setAttribute('venue_equipment_allocations', [
            'Balay Alumni' => ['tables' => 2],
            'Gymnasium' => ['Tables' => 2],
        ]);

        $message = app(\App\Services\AvailabilityService::class)->checkFacilityRequest($request, $request->id, true);
        $this->assertNotNull($message);
    }

    public function test_request_form_shows_included_items_in_each_venue_list(): void
    {
        $this->actingAs($this->requestor)
            ->get(route('requestor.index', ['tab' => 'create']))
            ->assertOk()
            ->assertSee('data-included-equipment="Sound System"', false)
            ->assertSee('Included with venue');
    }
}
