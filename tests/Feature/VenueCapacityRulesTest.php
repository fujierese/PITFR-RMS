<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Decision D2 (REQ-01, REQ-02): each venue is a separate booking with its own
 * participant count; a venue below that count is blocked unless the requestor asks
 * for a capacity exception and the Supply Office approves it.
 */
class VenueCapacityRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create(['name' => 'Covered Court', 'custodian_id' => $custodian->id, 'capacity' => 50]);
        Venue::create(['name' => 'Gymnasium', 'custodian_id' => $custodian->id, 'capacity' => 500]);
        $this->requester = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'outsider',
            'office_or_organization' => 'Barangay Council',
        ]);
    }

    private function payload(array $venues, array $overrides = []): array
    {
        $date = now()->addDays(5)->toDateString();
        $schedules = [];
        foreach ($venues as $venue) {
            $schedules[$venue] = [
                'start_date' => $date, 'end_date' => $date,
                'start_time' => '09:00', 'end_time' => '11:00',
                'reservation_duration' => 'specific_time',
            ];
        }

        return array_merge([
            'organization_name' => 'Barangay Council',
            'name_of_activity' => 'Community Assembly',
            'expected_participants' => 80,
            'start_date' => $date, 'end_date' => $date,
            'start_time' => '09:00', 'end_time' => '11:00',
            'venue' => $venues,
            'venue_schedules' => $schedules,
        ], $overrides);
    }

    public function test_venue_below_the_participant_count_is_blocked(): void
    {
        $this->actingAs($this->requester)
            ->post(route('requestor.store'), $this->payload(['Covered Court']))
            ->assertSessionHasErrors('venue_participants.Covered Court');

        $this->assertDatabaseCount('facility_requests', 0);
    }

    public function test_participants_split_across_venues_fit_each_capacity(): void
    {
        $this->actingAs($this->requester)
            ->post(route('requestor.store'), $this->payload(['Covered Court', 'Gymnasium'], [
                'venue_participants' => ['Covered Court' => 30, 'Gymnasium' => 50],
            ]))
            ->assertSessionHasNoErrors();

        $request = FacilityRequest::firstOrFail();
        $this->assertSame(['Covered Court' => 30, 'Gymnasium' => 50], $request->venue_participants);
        $this->assertNull($request->capacity_exception_status);
        $this->assertSame(30, $request->participantsForVenue('Covered Court'));
    }

    public function test_participants_at_one_venue_cannot_exceed_the_total(): void
    {
        $this->actingAs($this->requester)
            ->post(route('requestor.store'), $this->payload(['Gymnasium'], [
                'venue_participants' => ['Gymnasium' => 120],
            ]))
            ->assertSessionHasErrors('venue_participants.Gymnasium');
    }

    public function test_capacity_exception_needs_supply_office_approval_at_availability_review(): void
    {
        $this->actingAs($this->requester)
            ->post(route('requestor.store'), $this->payload(['Covered Court'], [
                'capacity_exceptions' => ['Covered Court' => 'Only 40 people are inside at any one time.'],
            ]))
            ->assertSessionHasNoErrors();

        $request = FacilityRequest::firstOrFail();
        $this->assertSame('pending', $request->capacity_exception_status);
        $this->assertTrue($request->hasPendingCapacityException());

        $request->update(['venue_status' => 'approved']);
        $supply = User::factory()->create(['role' => 'supply_office']);

        $this->actingAs($supply)
            ->post(route('supply-office.requests.availability-review'), ['id' => $request->id, 'decision' => 'available'])
            ->assertSessionHasErrors('capacity_exception_approved');
        $this->assertSame('pending', $request->fresh()->supply_office_review_status);

        $this->actingAs($supply)
            ->post(route('supply-office.requests.availability-review'), [
                'id' => $request->id, 'decision' => 'available', 'capacity_exception_approved' => '1',
            ])
            ->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame('confirmed', $request->supply_office_review_status);
        $this->assertSame('approved', $request->capacity_exception_status);
        $this->assertSame($supply->id, (int) $request->capacity_exception_reviewed_by_id);
        $this->assertNotNull($request->capacity_exception_reviewed_at);
    }

    public function test_create_page_shows_per_venue_participants_and_exception_option(): void
    {
        $this->actingAs($this->requester)
            ->get(route('requestor.index', ['tab' => 'create']))
            ->assertOk()
            ->assertSee('Participants at this venue')
            ->assertSee('Request a capacity exception')
            ->assertSee('data-venue-capacity="50"', false);
    }
}
