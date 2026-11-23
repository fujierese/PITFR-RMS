<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * REQ-11: one rule on every route. A request needs at least one active venue and a
 * complete schedule; equipment is optional and is checked for the same schedule.
 * Equipment-only, empty and "Others" venue requests are rejected.
 */
class RequestResourceRuleTest extends TestCase
{
    use RefreshDatabase;

    private const VENUE = 'Conference Hall & Interaction Center (CHIC)';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create(['name' => self::VENUE, 'custodian_id' => $custodian->id, 'is_active' => true]);
        Venue::create(['name' => 'Balay Alumni', 'custodian_id' => $custodian->id, 'is_active' => false]);
        Equipment::create(['name' => 'Sound System', 'custodian_id' => $custodian->id, 'quantity' => 2, 'quantity_available' => 2, 'is_active' => true]);
    }

    private function apiPayload(array $overrides = []): array
    {
        return array_merge([
            'name_of_activity' => 'Resource rule',
            'expected_participants' => 10,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'department' => 'IT',
        ], $overrides);
    }

    private function requestor(): User
    {
        return User::factory()->create(['role' => 'requestor', 'requestor_type' => 'faculty']);
    }

    public function test_api_rejects_a_request_with_no_venue_and_no_equipment(): void
    {
        Sanctum::actingAs($this->requestor());

        $this->postJson('/api/facility-requests', $this->apiPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('venue');
        $this->assertSame(0, FacilityRequest::count());
    }

    public function test_api_rejects_an_equipment_only_request(): void
    {
        Sanctum::actingAs($this->requestor());

        $this->postJson('/api/facility-requests', $this->apiPayload([
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 1],
        ]))->assertUnprocessable()->assertJsonValidationErrors('venue');
        $this->assertSame(0, FacilityRequest::count());
    }

    public function test_api_rejects_an_others_venue_and_an_inactive_venue(): void
    {
        Sanctum::actingAs($this->requestor());

        $this->postJson('/api/facility-requests', $this->apiPayload([
            'venue' => [self::VENUE],
            'other_venue' => 'My backyard',
        ]))->assertUnprocessable()->assertJsonValidationErrors('other_venue');

        $this->postJson('/api/facility-requests', $this->apiPayload([
            'venue' => ['Balay Alumni'],
        ]))->assertUnprocessable()->assertJsonValidationErrors('venue.0');

        $this->assertSame(0, FacilityRequest::count());
    }

    public function test_api_requires_a_schedule_for_a_venue_request(): void
    {
        Sanctum::actingAs($this->requestor());

        $this->postJson('/api/facility-requests', $this->apiPayload([
            'venue' => [self::VENUE],
            'start_date' => null,
            'start_time' => null,
            'end_time' => null,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['start_date', 'start_time', 'end_time']);
    }

    public function test_api_accepts_venue_only_and_venue_with_equipment(): void
    {
        Sanctum::actingAs($this->requestor());

        $this->postJson('/api/facility-requests', $this->apiPayload([
            'venue' => [self::VENUE],
        ]))->assertSuccessful();

        $this->postJson('/api/facility-requests', $this->apiPayload([
            'venue' => [self::VENUE],
            'start_date' => now()->addDays(4)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 1],
        ]))->assertSuccessful();

        $requests = FacilityRequest::query()->orderBy('id')->get();
        $this->assertCount(2, $requests);
        $this->assertSame([self::VENUE], $requests[0]->getVenueNames());
        $this->assertSame('approved', $requests[0]->equipment_status, 'No equipment means nothing waits for an equipment custodian.');
        $this->assertSame('pending', $requests[1]->equipment_status);
    }

    public function test_web_form_rejects_a_request_with_no_venue(): void
    {
        $this->actingAs($this->requestor())
            ->post(route('requestor.store'), [
                'name_of_activity' => 'Equipment only',
                'expected_participants' => 10,
                'equipment' => ['Sound System'],
                'equipment_quantities' => ['Sound System' => 1],
            ])
            ->assertSessionHasErrors(['venue' => 'Choose a venue. Every request needs at least one venue; equipment is requested together with a venue.']);
        $this->assertSame(0, FacilityRequest::count());
    }

    private function editableRequest(User $requester): FacilityRequest
    {
        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-111',
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Editable',
            'expected_participants' => 20,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => [self::VENUE],
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 1],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);
        $request->syncRelationalItems();

        return $request;
    }

    private function editPayload(array $overrides = []): array
    {
        return array_merge([
            'name_of_activity' => 'Editable',
            'department' => 'IT',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'start_time' => '13:00',
            'end_time' => '15:00',
            'venue' => self::VENUE,
        ], $overrides);
    }

    public function test_editing_cannot_drop_the_venue_through_others_or_an_inactive_venue(): void
    {
        $requester = $this->requestor();
        $request = $this->editableRequest($requester);

        foreach (['Others (specify)', 'Balay Alumni', ''] as $venue) {
            $this->actingAs($requester)
                ->put(route('requestor.update', $request), $this->editPayload([
                    'venue' => $venue,
                    'other_venue' => $venue === 'Others (specify)' ? 'Somewhere' : null,
                ]))
                ->assertSessionHas('errors', fn ($errors): bool => collect($errors->keys())
                    ->contains(fn (string $key): bool => $key === 'venue' || str_starts_with($key, 'venue.') || $key === 'other_venue'));
        }

        $this->assertSame([self::VENUE], $request->fresh()->getVenueNames());
        $this->assertSame('09:00', $request->fresh()->start_time);
    }

    /** Equipment is optional when editing (only the venue's own default items remain). */
    public function test_editing_allows_removing_all_equipment(): void
    {
        $requester = $this->requestor();
        $request = $this->editableRequest($requester);

        $this->actingAs($requester)
            ->put(route('requestor.update', $request), $this->editPayload())
            ->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame([self::VENUE], $request->getVenueNames());
        $this->assertNotContains('Sound System', array_keys(array_filter((array) $request->equipment_quantities, fn ($qty) => $qty > 1)));
        $this->assertSame('13:00', $request->start_time);
    }

    public function test_edit_page_lists_only_active_venues_and_no_others_option(): void
    {
        $requester = $this->requestor();
        $request = $this->editableRequest($requester);

        $this->actingAs($requester)
            ->get(route('requestor.edit', $request))
            ->assertOk()
            ->assertSee(self::VENUE)
            ->assertDontSee('Others (specify)')
            ->assertDontSee('<option value="Balay Alumni"', false);
    }
}
