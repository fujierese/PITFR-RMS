<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Support\RequestSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Additional Enhancements, search: one rule everywhere (partial, case-insensitive,
 * every word must match), and nobody finds records outside their role's scope.
 */
class RoleAwareSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;
    private User $bob;
    private User $courtCustodian;
    private User $projectorCustodian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student', 'name' => 'Alice Santos']);
        $this->bob = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student', 'name' => 'Bob Reyes']);
        $this->courtCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $this->projectorCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        Venue::create(['name' => 'Covered Court', 'capacity' => 300, 'custodian_id' => $this->courtCustodian->id, 'is_active' => true]);
        Venue::create(['name' => 'Gymnasium', 'capacity' => 500, 'custodian_id' => User::factory()->create(['role' => 'custodian-venue'])->id, 'is_active' => true]);
        Equipment::create(['name' => 'Projector', 'custodian_id' => $this->projectorCustodian->id, 'quantity' => 5, 'quantity_available' => 5]);
    }

    private function makeRequest(User $owner, string $control, string $activity, string $venue, array $equipment = [], array $extra = []): FacilityRequest
    {
        $date = now()->addDays(10)->toDateString();
        $request = FacilityRequest::create(array_merge([
            'control_number' => $control,
            'date_requested' => now()->toDateString(),
            'department' => 'College of Technology',
            'name_of_activity' => $activity,
            'purpose' => 'Annual ' . $activity,
            'expected_participants' => 50,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => [$venue],
            'equipment' => array_keys($equipment),
            'equipment_quantities' => $equipment,
            'requested_by_id' => $owner->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'supply_office_review_status' => 'pending',
            'equipment_status' => $equipment === [] ? 'approved' : 'pending',
        ], $extra));
        $request->syncRelationalItems();

        return $request;
    }

    public function test_terms_are_split_and_every_word_must_match(): void
    {
        $this->assertSame(['gym', 'prom'], RequestSearch::terms('  Gym   PROM '));
        $prom = $this->makeRequest($this->alice, 'FER-2026-101', 'Senior Prom', 'Gymnasium');
        $this->makeRequest($this->alice, 'FER-2026-102', 'Prom Rehearsal', 'Covered Court');

        $found = RequestSearch::apply(FacilityRequest::query(), 'gym prom')->pluck('id')->all();
        $this->assertSame([$prom->id], $found);
        $this->assertCount(2, RequestSearch::apply(FacilityRequest::query(), 'PROM')->get());
        $this->assertCount(1, RequestSearch::apply(FacilityRequest::query(), '2026-101')->get());
        $this->assertCount(0, RequestSearch::apply(FacilityRequest::query(), '100%')->get());
        $this->assertTrue(RequestSearch::matches($prom, 'senior gym'));
        $this->assertFalse(RequestSearch::matches($prom, 'senior court'));
    }

    public function test_requestor_finds_only_their_own_requests(): void
    {
        $this->makeRequest($this->alice, 'FER-2026-201', 'Coding Bootcamp', 'Covered Court');
        $bobs = $this->makeRequest($this->bob, 'FER-2026-202', 'Coding Contest', 'Covered Court');

        $this->actingAs($this->alice)->get(route('requestor.index', ['tab' => 'requests', 'search' => 'coding']))
            ->assertOk()
            ->assertSee('FER-2026-201')
            ->assertDontSee('FER-2026-202');
        // Even by the other person's control number.
        $this->actingAs($this->alice)->get(route('requestor.index', ['tab' => 'requests', 'search' => 'FER-2026-202']))
            ->assertOk()
            ->assertDontSee(route('request.show', $bobs->id), false)
            ->assertSee('No matching requests found.');
    }

    public function test_venue_custodian_finds_only_requests_for_their_venues(): void
    {
        $this->makeRequest($this->alice, 'FER-2026-301', 'Sports Fest', 'Covered Court');
        $gym = $this->makeRequest($this->bob, 'FER-2026-302', 'Sports Night', 'Gymnasium');

        $this->actingAs($this->courtCustodian)->get(route('custodian.index', ['search' => 'sports alice']))
            ->assertOk()
            ->assertSee('FER-2026-301')
            ->assertDontSee('FER-2026-302');
        $this->actingAs($this->courtCustodian)->get(route('custodian.index', ['search' => 'FER-2026-302']))
            ->assertOk()
            ->assertDontSee(route('request.show', $gym->id), false)
            ->assertSee('No requests match');
    }

    public function test_equipment_custodian_finds_by_equipment_name_within_their_equipment(): void
    {
        $this->makeRequest($this->alice, 'FER-2026-401', 'Film Showing', 'Covered Court', ['Projector' => 1], ['venue_status' => 'approved', 'supply_office_review_status' => 'confirmed']);
        $this->makeRequest($this->bob, 'FER-2026-402', 'Film Club', 'Covered Court', [], ['venue_status' => 'approved', 'supply_office_review_status' => 'confirmed']);

        $this->actingAs($this->projectorCustodian)->get(route('custodian.index', ['search' => 'projector']))
            ->assertOk()
            ->assertSee('FER-2026-401')
            ->assertDontSee('FER-2026-402');
    }

    public function test_supply_office_search_combines_with_filters_and_covers_every_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (range(1, 18) as $i) {
            $this->makeRequest($this->bob, sprintf('FER-2026-5%02d', $i), 'Seminar ' . $i, 'Covered Court');
        }
        $target = $this->makeRequest($this->alice, 'FER-2026-599', 'Robotics Seminar', 'Gymnasium', ['Projector' => 1]);

        // Found although it would be on a later page without the search.
        $this->actingAs($admin)->get(route('supply-office.requests.pending', ['search' => 'robotics santos']))
            ->assertOk()
            ->assertSee('FER-2026-599')
            ->assertDontSee('FER-2026-501');
        // Search plus filters work together.
        $this->actingAs($admin)->get(route('supply-office.requests.pending', ['search' => 'seminar', 'venue' => 'Gymnasium', 'equipment' => 'projector', 'review' => 'venue_pending']))
            ->assertOk()
            ->assertSee('FER-2026-599')
            ->assertDontSee('FER-2026-502');
        $this->actingAs($admin)->get(route('supply-office.requests.pending', ['search' => 'seminar', 'review' => 'venue_approved']))
            ->assertOk()
            ->assertDontSee('FER-2026-599');
        $this->assertNotNull($target);
    }
}
