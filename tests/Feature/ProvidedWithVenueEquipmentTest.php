<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Notifications\EquipmentReservationNotice;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Follow-up fixes: equipment provided with a venue is stored on the request (flagged),
 * shown as "Provided with venue", never taken from inventory, and routed to its
 * custodian like requested equipment, with one notice per custodian per event.
 */
class ProvidedWithVenueEquipmentTest extends TestCase
{
    use RefreshDatabase;

    private const CHIC = 'Conference Hall & Interaction Center (CHIC)';

    private User $student;
    private User $soundCustodian;
    private User $projectorCustodian;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->student = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student', 'name' => 'Ana Santos', 'office_or_organization' => null]);
        $this->soundCustodian = User::factory()->create(['role' => 'custodian-equipment', 'name' => 'Sound Keeper']);
        $this->projectorCustodian = User::factory()->create(['role' => 'custodian-equipment', 'name' => 'Projector Keeper']);
        Equipment::query()->whereRaw('LOWER(name) = ?', ['sound system'])->delete();
        Equipment::create(['name' => 'Sound System', 'custodian_id' => $this->soundCustodian->id, 'quantity' => 2, 'quantity_available' => 2]);
        Equipment::create(['name' => 'Tables', 'custodian_id' => $this->soundCustodian->id, 'quantity' => 30, 'quantity_available' => 30]);
    }

    private function submit(array $extra = [])
    {
        $date = now()->addDays(10)->toDateString();

        return $this->actingAs($this->student)->post(route('requestor.store'), array_merge([
            'requested_by_position' => 'Student',
            'department' => 'Computer Science',
            'name_of_activity' => 'Tech Talk',
            'purpose' => 'Talk',
            'expected_participants' => 40,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => self::CHIC,
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
            'proposal_submission_type' => 'final',
        ], $extra));
    }

    public function test_provided_items_are_stored_flagged_and_not_taken_from_inventory(): void
    {
        $this->submit()->assertSessionHasNoErrors();
        $request = FacilityRequest::query()->latest('id')->firstOrFail();

        $provided = $request->getProvidedEquipmentQuantities();
        $this->assertArrayHasKey('Sound System', $provided);
        $this->assertArrayNotHasKey('Sound System', $request->getEquipmentQuantities(), 'Not a requested (inventory) item.');
        $this->assertDatabaseHas('request_equipment', ['facility_request_id' => $request->id, 'name' => 'Sound System', 'provided_with_venue' => true]);

        // Stock for the same time is untouched.
        $range = $request->getVenueScheduleRanges(self::CHIC)[0];
        $this->assertTrue(app(AvailabilityService::class)->checkEquipmentAvailability('Sound System', 2, $range['start'], $range['end'])['available']);

        // Re-syncing never duplicates rows.
        $request->syncRelationalItems();
        $request->syncRelationalItems();
        $this->assertSame(1, \DB::table('request_equipment')->where('facility_request_id', $request->id)->where('name', 'Sound System')->where('provided_with_venue', true)->count());

        $this->actingAs($this->student)->get(route('request.show', $request))
            ->assertOk()->assertSee('Provided with venue')->assertSee('data-provided-item', false)->assertSee('Sound System × 1');
    }

    public function test_the_custodian_of_a_provided_item_is_notified_once_with_the_details_and_must_review(): void
    {
        $this->submit(['venue_equipment' => [self::CHIC => ['Tables']], 'venue_equipment_quantities' => [self::CHIC => ['Tables' => 1]]])->assertSessionHasNoErrors();
        $request = FacilityRequest::query()->latest('id')->firstOrFail();

        $notices = $this->soundCustodian->notifications()->where('type', EquipmentReservationNotice::class)->get();
        $this->assertCount(1, $notices, 'One notice covers both the requested and the provided item.');
        $body = $notices->first()->data['body'];
        $this->assertStringContainsString('Ana Santos', $body);
        $this->assertStringContainsString('Tables ×1', $body);
        $this->assertStringContainsString('Sound System ×1 (provided with venue)', $body);
        $this->assertStringContainsString('CHIC', $body);
        $this->assertStringContainsString($request->start_date->format('M j, Y'), $body);
        $this->assertSame(0, $this->projectorCustodian->notifications()->count(), 'Only custodians of the items are told.');

        $this->assertSame('pending', $request->equipment_status, 'The custodian still reviews it.');
        $this->assertArrayHasKey('Sound System', $request->getAssignedEquipmentForCustodian($this->soundCustodian->id));

        // Running the routing again sends nothing new.
        app(\App\Services\EquipmentCustodianRouting::class)->notify($request, 'submitted');
        $this->assertSame(1, $this->soundCustodian->notifications()->where('type', EquipmentReservationNotice::class)->count());
    }

    public function test_items_without_a_custodian_go_to_the_supply_office(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        // Owned only by a venue custodian, who cannot review equipment: no equipment custodian.
        Equipment::create(['name' => 'Monobloc chairs', 'custodian_id' => User::factory()->create(['role' => 'custodian-venue'])->id, 'quantity' => 50, 'quantity_available' => 50]);
        $date = now()->addDays(10)->toDateString();
        $request = FacilityRequest::create([
            'control_number' => 'FER-NOC-1', 'date_requested' => now()->toDateString(), 'department' => 'IT',
            'name_of_activity' => 'Assembly', 'expected_participants' => 40,
            'start_date' => $date, 'end_date' => $date, 'start_time' => '09:00', 'end_time' => '11:00',
            'venue' => ['Covered Court'], 'equipment' => ['Monobloc chairs'], 'equipment_quantities' => ['Monobloc chairs' => 2],
            'requested_by_id' => $this->student->id, 'status' => 'pending', 'venue_status' => 'pending', 'equipment_status' => 'pending',
        ]);
        $request->syncRelationalItems();

        app(\App\Services\EquipmentCustodianRouting::class)->notify($request, 'submitted');

        $this->assertTrue($admin->notifications()->where('type', EquipmentReservationNotice::class)->get()
            ->contains(fn ($n) => str_contains($n->data['body'], 'Monobloc chairs ×2')));
    }

    public function test_cancelling_tells_the_custodian(): void
    {
        $this->submit()->assertSessionHasNoErrors();
        $request = FacilityRequest::query()->latest('id')->firstOrFail();

        $this->actingAs($this->student)->post(route('request.cancel', $request));

        $this->assertTrue($this->soundCustodian->notifications()->where('type', EquipmentReservationNotice::class)->get()
            ->contains(fn ($n) => $n->data['status'] === 'equipment_cancelled'));
    }
}
