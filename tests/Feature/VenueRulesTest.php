<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\Holiday;
use App\Models\RevisionHistory;
use App\Models\User;
use App\Models\Venue;
use App\Services\AvailabilityService;
use App\Support\VenueRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * REQ-19: venue and requestor rules are enforced by the server on every route.
 * Outsiders cannot book a venue closed to them (the Gym); a venue that asks for it
 * (the Gym) needs the type of school activity, which is recorded and shown.
 */
class VenueRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $outsider;
    private User $student;
    private Venue $gym;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        Holiday::query()->delete();
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        $this->gym = Venue::create(['name' => 'Gymnasium', 'capacity' => 500, 'custodian_id' => $custodian->id, 'is_active' => true,
            'open_to_outsiders' => false, 'requires_activity_type' => true]);
        Venue::create(['name' => 'Covered Court', 'capacity' => 300, 'custodian_id' => $custodian->id, 'is_active' => true]);
        $this->outsider = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'outsider', 'office_or_organization' => 'Leyte Events Co.', 'contact_number' => '09170000000', 'verification_status' => 'verified']);
        $this->student = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $this->date = now()->addDays(10)->toDateString();
    }

    private function outsiderSubmit(string $venue)
    {
        return $this->actingAs($this->outsider)->post(route('requestor.store'), [
            'department' => 'Leyte Events Co.',
            'organization_name' => 'Leyte Events Co.',
            'name_of_activity' => 'Company Anniversary',
            'purpose' => 'Annual gathering',
            'expected_participants' => 120,
            'venue' => [$venue],
            'venue_schedules' => [$venue => ['start_date' => $this->date, 'end_date' => $this->date, 'start_time' => '13:00', 'end_time' => '17:00', 'reservation_duration' => 'specific_time']],
            'contact_person' => 'Rosa Client',
            'contact_number' => '09171112222',
            'contact_email' => 'rosa@example.test',
            'e_signature_file' => UploadedFile::fake()->create('signature.png', 50, 'image/png'),
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
            'proposal_submission_type' => 'final',
        ]);
    }

    private function studentSubmit(array $activity = [])
    {
        return $this->actingAs($this->student)->post(route('requestor.store'), [
            'department' => 'Test Department',
            'request_context' => 'personal',
            'name_of_activity' => 'Sports Fest',
            'purpose' => 'Sports Fest',
            'expected_participants' => 100,
            'venue' => ['Gymnasium'],
            'venue_schedules' => ['Gymnasium' => ['start_date' => $this->date, 'end_date' => $this->date, 'start_time' => '08:00', 'end_time' => '12:00', 'reservation_duration' => 'specific_time'] + $activity],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
            'proposal_submission_type' => 'final',
        ]);
    }

    public function test_an_outsider_cannot_book_the_gym_on_the_web_form(): void
    {
        $this->actingAs($this->outsider)->get(route('requestor.index', ['tab' => 'create']))
            ->assertOk()
            ->assertDontSee('value="Gymnasium"', false)
            ->assertSee('value="Covered Court"', false);

        $this->outsiderSubmit('Gymnasium')
            ->assertSessionHasErrors(['venue' => 'Gymnasium is not open to outside requestors. Choose another venue.']);
        $this->assertSame(0, FacilityRequest::count());

        $this->outsiderSubmit('Covered Court')->assertSessionHasNoErrors();
        $this->assertSame(1, FacilityRequest::count());
    }

    public function test_an_outsider_cannot_book_the_gym_through_the_api(): void
    {
        Sanctum::actingAs($this->outsider);
        $this->postJson('/api/facility-requests', [
            'name_of_activity' => 'Company Anniversary',
            'expected_participants' => 120,
            'start_date' => $this->date,
            'end_date' => $this->date,
            'start_time' => '13:00',
            'end_time' => '17:00',
            'department' => 'Leyte Events Co.',
            'venue' => ['Gymnasium'],
        ])->assertStatus(422)->assertJsonValidationErrors(['venue']);

        $this->assertSame(0, FacilityRequest::count());
    }

    public function test_pit_users_book_the_gym_with_an_activity_type(): void
    {
        $this->actingAs($this->student)->get(route('requestor.index', ['tab' => 'create']))
            ->assertOk()
            ->assertSee('name="venue_schedules[Gymnasium][activity_type]"', false);

        $this->studentSubmit()->assertSessionHasErrors('venue_schedules.Gymnasium.activity_type');
        $this->studentSubmit(['activity_type' => 'concert'])->assertSessionHasErrors('venue_schedules.Gymnasium.activity_type');
        $this->studentSubmit(['activity_type' => 'other'])
            ->assertSessionHasErrors(['venue_schedules.Gymnasium.activity_type' => 'Gymnasium: describe the school activity.']);
        $this->assertSame(0, FacilityRequest::count());

        $this->studentSubmit(['activity_type' => 'other', 'activity_type_detail' => 'Intramurals volleyball'])->assertSessionHasNoErrors();
        $request = FacilityRequest::query()->sole();
        $schedule = $request->getVenueSchedule('Gymnasium');
        $this->assertSame('other', $schedule['activity_type']);
        $this->assertSame('Intramurals volleyball', $schedule['activity_type_detail']);
        $this->assertStringContainsString('Activity: Other school activity: Intramurals volleyball', $request->getVenueScheduleSummaries()[0]['label']);

        $supply = User::factory()->create(['role' => 'supply_office']);
        $this->actingAs($supply)->get(route('request.show', $request))->assertOk()->assertSee('Intramurals volleyball');
    }

    public function test_the_api_also_asks_pit_users_for_the_activity_type(): void
    {
        Sanctum::actingAs($this->student);
        $payload = [
            'name_of_activity' => 'Prom Night',
            'expected_participants' => 200,
            'start_date' => $this->date,
            'end_date' => $this->date,
            'start_time' => '17:00',
            'end_time' => '22:00',
            'department' => 'IT',
            'venue' => ['Gymnasium'],
        ];
        $this->postJson('/api/facility-requests', $payload)->assertStatus(422)->assertJsonValidationErrors(['activity_type']);

        $this->postJson('/api/facility-requests', $payload + ['activity_type' => 'prom'])->assertCreated();
        $this->assertSame('prom', FacilityRequest::query()->sole()->getVenueSchedule('Gymnasium')['activity_type']);
    }

    public function test_a_schedule_change_cannot_move_an_outsider_into_the_gym(): void
    {
        $this->outsiderSubmit('Covered Court')->assertSessionHasNoErrors();
        $request = FacilityRequest::query()->sole();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('supply-office.requests.revise'), [
            'facility_request_id' => $request->id,
            'start_date' => $this->date,
            'end_date' => $this->date,
            'start_time' => '13:00',
            'end_time' => '17:00',
            'venue' => ['Gymnasium'],
            'revision_reason' => 'Moving this booking to the Gym.',
        ])->assertSessionHasErrors(['venue' => 'Gymnasium is not open to outside requestors. Choose another venue.']);

        $this->assertSame(0, RevisionHistory::count());
    }

    public function test_review_and_approval_refuse_an_outsider_request_for_a_closed_venue(): void
    {
        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-950',
            'date_requested' => now()->toDateString(),
            'department' => 'Leyte Events Co.',
            'name_of_activity' => 'Older request',
            'expected_participants' => 50,
            'start_date' => $this->date,
            'end_date' => $this->date,
            'start_time' => '13:00',
            'end_time' => '15:00',
            'venue' => ['Gymnasium'],
            'requested_by_id' => $this->outsider->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'approved',
        ]);

        $this->assertSame(
            'Gymnasium is not open to outside requestors. Choose another venue.',
            app(AvailabilityService::class)->checkFacilityRequest($request->fresh(), $request->id)
        );
    }

    public function test_venue_management_controls_the_rules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $court = Venue::where('name', 'Covered Court')->sole();

        $this->actingAs($admin)->put(route('supply-office.venues.update', $court), [
            'name' => 'Covered Court',
            'custodian_id' => $court->custodian_id,
            'capacity' => 300,
            'open_to_outsiders' => '0',
            'requires_activity_type' => '0',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($court->fresh()->open_to_outsiders);
        $this->outsiderSubmit('Covered Court')->assertSessionHasErrors('venue');

        $this->actingAs($admin)->get(route('supply-office.venues.index'))->assertOk()->assertSee('PIT users only');
    }

    public function test_a_built_in_gym_with_no_record_is_closed_to_outsiders(): void
    {
        $this->gym->delete();
        VenueRules::flush();

        $this->assertFalse(VenueRules::openToOutsiders('Gymnasium'));
        $this->assertTrue(VenueRules::requiresActivityType('Gymnasium'));
        $this->assertTrue(VenueRules::openToOutsiders('Covered Court'));
        $this->assertSame([], VenueRules::closedVenuesFor($this->student, ['Gymnasium']));
    }

    public function test_editing_a_gym_request_keeps_its_activity_type(): void
    {
        $this->studentSubmit(['activity_type' => 'seminar'])->assertSessionHasNoErrors();
        $request = FacilityRequest::query()->sole();
        $request->update(['venue_status' => 'pending', 'equipment_status' => 'pending', 'status' => 'pending']);

        $this->actingAs($this->student)->put(route('requestor.update', $request), [
            'department' => 'Test Department',
            'name_of_activity' => 'Sports Fest (renamed)',
            'expected_participants' => 100,
            'start_date' => $this->date,
            'end_date' => $this->date,
            'start_time' => '08:00',
            'end_time' => '12:00',
            'venue' => 'Gymnasium',
        ])->assertSessionHasNoErrors();

        $fresh = FacilityRequest::find($request->id);
        $this->assertSame('Sports Fest (renamed)', $fresh->name_of_activity);
        $this->assertSame('seminar', $fresh->getVenueSchedule('Gymnasium')['activity_type']);
    }
}
