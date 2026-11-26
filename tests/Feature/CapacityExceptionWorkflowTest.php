<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\NewFacilityRequestNotification;
use App\Notifications\RequestStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Part 2: the Venue Custodian decides capacity exceptions for their own venue; the
 * requestor confirms any alternative venue; the Supply Office cannot decide or bypass
 * it; each venue is verified by its own custodian.
 */
class CapacityExceptionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $gymCustodian;
    private User $hallCustodian;
    private User $courtCustodian;
    private User $requestor;
    private User $supply;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->gymCustodian = User::factory()->create(['role' => 'custodian-venue', 'name' => 'Gym Keeper']);
        $this->hallCustodian = User::factory()->create(['role' => 'custodian-venue', 'name' => 'Hall Keeper']);
        $this->courtCustodian = User::factory()->create(['role' => 'custodian-venue', 'name' => 'Court Keeper']);
        $this->requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $this->supply = User::factory()->create(['role' => 'supply_office']);
        Venue::create(['name' => 'Gymnasium', 'capacity' => 50, 'custodian_id' => $this->gymCustodian->id, 'is_active' => true]);
        Venue::create(['name' => 'Mini Hall', 'capacity' => 200, 'custodian_id' => $this->hallCustodian->id, 'is_active' => true]);
        Venue::create(['name' => 'Covered Court', 'capacity' => 300, 'custodian_id' => $this->courtCustodian->id, 'is_active' => true]);
        $this->date = now()->addDays(9)->toDateString();
    }

    private function request(array $venues = ['Gymnasium'], array $extra = []): FacilityRequest
    {
        $request = FacilityRequest::create(array_merge([
            'control_number' => 'FER-CAP-' . random_int(1000, 9999),
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Assembly',
            'expected_participants' => 120,
            'start_date' => $this->date,
            'end_date' => $this->date,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => $venues,
            'equipment' => [],
            'requested_by_id' => $this->requestor->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'approved',
            'supply_office_review_status' => 'pending',
            'capacity_exceptions' => in_array('Gymnasium', $venues, true) ? ['Gymnasium' => 'The whole department attends.'] : null,
            'capacity_exception_status' => in_array('Gymnasium', $venues, true) ? 'pending' : null,
        ], $extra));
        $request->syncRelationalItems();

        return $request->fresh();
    }

    private function decide(User $user, FacilityRequest $request, array $data)
    {
        return $this->actingAs($user)->post(route('request.capacity-exception.decide', $request->id), $data + ['venue' => 'Gymnasium']);
    }

    public function test_only_the_assigned_venue_custodian_can_decide(): void
    {
        $request = $this->request();

        $this->actingAs($this->gymCustodian)->get(route('request.show', $request))->assertOk()
            ->assertSee('Capacity Exception Requested')->assertSee('The whole department attends.')
            ->assertSee('Approve Capacity Exception')->assertSee('Do Not Approve Capacity Exception');
        $this->actingAs($this->supply)->get(route('request.show', $request))->assertOk()
            ->assertSee('Capacity Exception Requested')->assertDontSee('Approve Capacity Exception');

        $this->decide($this->hallCustodian, $request, ['decision' => 'approved'])->assertForbidden();
        $this->decide($this->supply, $request, ['decision' => 'approved'])->assertForbidden();
        $this->decide(User::factory()->create(['role' => 'igp']), $request, ['decision' => 'approved'])->assertForbidden();
        $this->assertSame('pending', $request->fresh()->capacity_exception_status);
    }

    public function test_the_venue_cannot_be_confirmed_and_the_supply_office_cannot_bypass_an_undecided_exception(): void
    {
        $request = $this->request();
        $this->actingAs($this->gymCustodian)->post(route('request.custodian.verify', $request))->assertSessionHasErrors('capacity');
        $this->assertSame('pending', $request->fresh()->venue_status);

        // Even with the venue step done, the Supply Office cannot confirm or approve it.
        $request->update(['venue_status' => 'approved']);
        $this->actingAs($this->supply)->post(route('supply-office.requests.availability-review'), ['id' => $request->id, 'decision' => 'available'])
            ->assertSessionHasErrors('decision');
        $this->assertSame('pending', $request->fresh()->supply_office_review_status);

        $request->update(['supply_office_review_status' => 'confirmed']);
        $admin = User::factory()->create(['role' => 'admin', 'username' => config('pitfr.final_approver_username')]);
        $this->actingAs($admin)->post(route('request.supply.final-approval', $request))->assertSessionHasErrors();
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_approval_is_recorded_and_lets_the_venue_proceed(): void
    {
        $request = $this->request();

        $this->decide($this->gymCustodian, $request, ['decision' => 'approved', 'remarks' => 'Bleachers can be opened.'])->assertSessionHasNoErrors();
        $fresh = $request->fresh();
        $this->assertSame('approved', $fresh->capacity_exception_status);
        $this->assertSame('Gym Keeper', $fresh->capacityDecision('Gymnasium')['by_name']);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $this->gymCustodian->id, 'action' => 'capacity_exception_approved']);
        $this->assertSame('pending', $fresh->status, 'A capacity exception approval is not a reservation approval.');

        $this->decide($this->gymCustodian, $request, ['decision' => 'approved'])->assertForbidden(); // already decided
        $this->actingAs($this->gymCustodian)->post(route('request.custodian.verify', $request))->assertSessionHasNoErrors();
        $this->assertSame('approved', $request->fresh()->venue_status);
    }

    public function test_not_approved_needs_a_reason_and_the_requestor_chooses_another_venue(): void
    {
        $request = $this->request();

        $this->decide($this->gymCustodian, $request, ['decision' => 'not_approved', 'next_step' => 'requestor_choose'])->assertSessionHasErrors('reason');
        $this->decide($this->gymCustodian, $request, ['decision' => 'not_approved', 'reason' => 'Fire code allows only 50 people.', 'next_step' => 'requestor_choose'])
            ->assertSessionHasNoErrors();

        $fresh = $request->fresh();
        $this->assertSame('rejected', $fresh->capacity_exception_status);
        $this->assertSame('needs_reschedule', $fresh->venue_status);
        $this->assertTrue($fresh->isEditableByRequestor());
        Notification::assertSentTo($this->requestor, RequestStatusChanged::class);

        $this->actingAs($this->requestor)->get(route('request.show', $request))->assertOk()
            ->assertSee('Fire code allows only 50 people.')
            ->assertSee('Choose another venue')
            ->assertSee('Mini Hall') // fits 120 and is free
            ->assertDontSee('Accept Mini Hall');
    }

    public function test_a_suggested_venue_is_validated_and_needs_the_requestor_and_its_custodian(): void
    {
        $request = $this->request();
        // Mini Hall is booked (approved) at that time, so it cannot be suggested.
        $this->request(['Mini Hall'], ['control_number' => 'FER-CAP-BUSY', 'status' => 'approved', 'venue_status' => 'approved', 'supply_office_review_status' => 'confirmed']);

        $this->decide($this->gymCustodian, $request, [
            'decision' => 'not_approved', 'reason' => 'Too many people for the gym.', 'next_step' => 'suggest_venue',
            'suggested_venue' => 'Mini Hall', 'recommendation' => 'The hall fits everyone.',
        ])->assertSessionHasErrors('suggested_venue');

        $this->decide($this->gymCustodian, $request, [
            'decision' => 'not_approved', 'reason' => 'Too many people for the gym.', 'next_step' => 'suggest_venue',
            'suggested_venue' => 'Covered Court', 'recommendation' => 'The court fits everyone.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['Gymnasium'], $request->fresh()->getVenueNames(), 'The venue is not replaced without the requestor.');

        $this->actingAs($this->requestor)->get(route('request.show', $request))->assertSee('Accept Covered Court');
        $this->actingAs($this->supply)->post(route('request.capacity-exception.accept', $request->id), ['venue' => 'Gymnasium'])->assertForbidden();
        $this->actingAs($this->requestor)->post(route('request.capacity-exception.accept', $request->id), ['venue' => 'Gymnasium'])->assertSessionHasNoErrors();

        $fresh = $request->fresh();
        $this->assertSame(['Covered Court'], $fresh->getVenueNames());
        $this->assertSame('pending', $fresh->venue_status);
        $this->assertNull($fresh->capacity_exception_status);
        $this->assertTrue($fresh->capacityExceptionResolved());
        Notification::assertSentTo($this->courtCustodian, NewFacilityRequestNotification::class);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $this->requestor->id, 'action' => 'capacity_alternative_accepted']);

        // The new venue needs its own custodian's verification.
        $this->actingAs($this->gymCustodian)->post(route('request.custodian.verify', $fresh))->assertForbidden();
        $this->actingAs($this->courtCustodian)->post(route('request.custodian.verify', $fresh))->assertSessionHasNoErrors();
        $this->assertSame('approved', $request->fresh()->venue_status);
    }

    public function test_each_venue_is_confirmed_by_its_own_custodian(): void
    {
        $request = $this->request(['Mini Hall', 'Covered Court']);

        $this->actingAs($this->hallCustodian)->post(route('request.custodian.verify', $request))->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains((string) $m, 'Covered Court'));
        $this->assertSame('pending', $request->fresh()->venue_status, 'One custodian does not confirm the other venue.');
        $this->assertNotNull($request->fresh()->venueConfirmation('Mini Hall'));
        $this->actingAs($this->hallCustodian)->post(route('request.custodian.verify', $request))->assertSessionHas('info');

        $this->actingAs($this->courtCustodian)->post(route('request.custodian.verify', $request))->assertSessionHasNoErrors();
        $this->assertSame('approved', $request->fresh()->venue_status);
    }
}
