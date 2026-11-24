<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * REQ-13: deactivated venues and equipment are rechecked on the server at submit and
 * at approval (urgent included), names stay unique and are not changed under live
 * requests, and an edit form opened before the request changed is refused.
 */
class InactiveAndRenamedResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $requestor;
    private User $venueCustodian;
    private Venue $gym;
    private Venue $balay;
    private Equipment $tables;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $this->gym = Venue::create(['name' => 'Gymnasium', 'capacity' => 500, 'custodian_id' => $this->venueCustodian->id, 'is_active' => true]);
        $this->balay = Venue::create(['name' => 'Balay Alumni', 'capacity' => 200, 'custodian_id' => $this->venueCustodian->id, 'is_active' => true]);
        $equipmentCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        $this->tables = Equipment::factory()->create([
            'name' => 'Tables', 'custodian_id' => $equipmentCustodian->id,
            'quantity' => 50, 'quantity_available' => 50, 'is_active' => true,
        ]);
        $this->requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
    }

    private function makeRequest(string $status = 'pending', ?string $venue = 'Gymnasium', int $daysAhead = 6): FacilityRequest
    {
        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-' . random_int(1000, 9999),
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Resource check',
            'expected_participants' => 20,
            'start_date' => now()->addDays($daysAhead)->toDateString(),
            'end_date' => now()->addDays($daysAhead)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => [$venue],
            'equipment' => ['Tables'],
            'equipment_quantities' => ['Tables' => 2],
            'requested_by_id' => $this->requestor->id,
            'status' => $status,
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);
        $request->syncRelationalItems();

        return $request;
    }

    private function submitForm(string $venue)
    {
        $date = now()->addDays(5)->toDateString();

        return $this->actingAs($this->requestor)->post(route('requestor.store'), [
            'department' => 'Test Department',
            'request_context' => 'personal',
            'name_of_activity' => 'Inactive venue',
            'expected_participants' => 20,
            'venue' => [$venue],
            'venue_schedules' => [$venue => [
                'start_date' => $date, 'end_date' => $date,
                'start_time' => '08:00', 'end_time' => '10:00',
                'reservation_duration' => 'specific_time',
            ]],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
            'proposal_submission_type' => 'final',
        ]);
    }

    public function test_a_deactivated_built_in_venue_is_not_offered_or_accepted(): void
    {
        $this->gym->update(['is_active' => false]);

        $this->actingAs($this->requestor)->get(route('requestor.index'))
            ->assertOk()
            ->assertViewHas('venueOptions', fn (array $options): bool => ! in_array('Gymnasium', $options, true)
                && in_array('Balay Alumni', $options, true)
                && in_array('Oval Grounds', $options, true));

        $this->submitForm('Gymnasium')->assertSessionHasErrors('venue.0');
        $this->assertSame(0, FacilityRequest::count());

        $this->submitForm('Balay Alumni')->assertSessionHasNoErrors();
    }

    public function test_urgent_checks_still_refuse_a_deactivated_venue_or_item(): void
    {
        $service = app(AvailabilityService::class);
        $candidate = new FacilityRequest([
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => ['Gymnasium'],
            'equipment' => ['Tables'],
            'equipment_quantities' => ['Tables' => 1],
        ]);

        $this->assertNull($service->checkFacilityRequest($candidate, null, true));

        $this->gym->update(['is_active' => false]);
        $this->assertSame(
            'Gymnasium is no longer available for booking. Choose another venue.',
            $service->checkFacilityRequest($candidate, null, true)
        );

        $this->gym->update(['is_active' => true]);
        $this->tables->update(['is_active' => false]);
        $this->assertSame(
            'Tables is no longer available for booking. Remove it from the request.',
            $service->checkFacilityRequest($candidate, null, true)
        );
    }

    public function test_approval_rechecks_by_stable_id_even_when_the_name_snapshot_differs(): void
    {
        $request = $this->makeRequest();
        $this->assertSame($this->gym->id, (int) $request->requestVenues()->value('venue_id'));

        // The venue record changes name and is then deactivated; the request still
        // carries its original name, but the ID still finds it.
        Venue::query()->whereKey($this->gym->id)->update(['name' => 'Gymnasium Annex', 'is_active' => false]);

        $this->assertSame(
            'Gymnasium Annex is no longer available for booking. Choose another venue.',
            app(AvailabilityService::class)->checkFacilityRequestForReview($request->fresh())
        );
    }

    public function test_venue_names_must_stay_unique_and_cannot_change_under_live_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('supply-office.venues.store'), [
            'name' => ' gymnasium ', 'custodian_id' => $this->venueCustodian->id,
        ])->assertSessionHasErrors('name');
        $this->assertSame(2, Venue::count());

        $this->actingAs($admin)->put(route('supply-office.venues.update', $this->balay), [
            'name' => 'Gymnasium', 'custodian_id' => $this->venueCustodian->id,
        ])->assertSessionHasErrors('name');

        $live = $this->makeRequest();
        $this->actingAs($admin)->put(route('supply-office.venues.update', $this->gym), [
            'name' => 'Main Gym', 'custodian_id' => $this->venueCustodian->id, 'capacity' => 500,
        ])->assertSessionHasErrors(['name' => '"Gymnasium" can\'t be renamed while pending or upcoming requests use it (' . $live->control_number . '). Rename it after they finish, or add a new venue and deactivate this one.']);
        $this->assertSame('Gymnasium', $this->gym->fresh()->name);

        // Capacity can still change while the name stays the same.
        $this->actingAs($admin)->put(route('supply-office.venues.update', $this->gym), [
            'name' => 'Gymnasium', 'custodian_id' => $this->venueCustodian->id, 'capacity' => 450,
        ])->assertSessionHasNoErrors();

        $live->update(['status' => 'rejected']);
        $this->actingAs($admin)->put(route('supply-office.venues.update', $this->gym), [
            'name' => 'Main Gym', 'custodian_id' => $this->venueCustodian->id, 'capacity' => 450,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Main Gym', $this->gym->fresh()->name);
    }

    public function test_equipment_cannot_be_renamed_to_a_duplicate_or_under_live_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $custodianId = $this->tables->custodian_id;
        Equipment::factory()->create(['name' => 'Chairs', 'custodian_id' => $custodianId, 'quantity' => 10, 'quantity_available' => 10, 'is_active' => true]);

        $this->actingAs($admin)->put(route('supply-office.equipment.update', $this->tables), [
            'name' => 'CHAIRS', 'custodian_id' => $custodianId, 'quantity' => 50,
        ])->assertSessionHasErrors('name');

        $this->makeRequest();
        $this->actingAs($admin)->put(route('supply-office.equipment.update', $this->tables), [
            'name' => 'Folding Tables', 'custodian_id' => $custodianId, 'quantity' => 50,
        ])->assertSessionHasErrors('name');

        $this->assertSame('Tables', $this->tables->fresh()->name);
    }

    public function test_a_stale_edit_form_is_refused_and_a_current_one_is_saved(): void
    {
        $request = $this->makeRequest();
        $this->travel(5)->seconds();
        $openedVersion = \App\Http\Controllers\RequestorController::editFormVersion($request->fresh());

        $payload = fn (string $version, string $title): array => [
            'form_version' => $version,
            'name_of_activity' => $title,
            'department' => 'IT',
            'start_date' => now()->addDays(6)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => 'Gymnasium',
            'equipment' => ['Tables'],
            'equipment_quantities' => ['Tables' => 2],
        ];

        $this->actingAs($this->requestor)->get(route('requestor.edit', $request))
            ->assertOk()
            ->assertSee('name="form_version" value="' . $openedVersion . '"', false);

        // Someone else changes the request after the form was opened.
        $this->travel(5)->seconds();
        $request->fresh()->update(['expected_participants' => 30]);

        $this->actingAs($this->requestor)->put(route('requestor.update', $request), $payload($openedVersion, 'Stale title'))
            ->assertRedirect(route('requestor.edit', $request))
            ->assertSessionHasErrors('form_version');
        $this->assertSame('Resource check', $request->fresh()->name_of_activity);

        $currentVersion = \App\Http\Controllers\RequestorController::editFormVersion($request->fresh());
        $this->actingAs($this->requestor)->put(route('requestor.update', $request), $payload($currentVersion, 'Fresh title'))
            ->assertSessionHasNoErrors();
        $this->assertSame('Fresh title', $request->fresh()->name_of_activity);
    }
}
