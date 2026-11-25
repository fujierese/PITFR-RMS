<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Follow-up fixes: the venue custodian's "Check Venue Schedule" card. One card; only
 * "Check venue calendar" at first; the server check covers the custodian's own venue;
 * approved bookings and closures conflict, waiting requests only warn; confirmation is
 * re-checked on the server.
 */
class VenueCustodianCheckCardTest extends TestCase
{
    use RefreshDatabase;

    private User $custodian;
    private User $otherCustodian;
    private User $requestor;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->custodian = User::factory()->create(['role' => 'custodian-venue', 'name' => 'Court Keeper']);
        $this->otherCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $this->requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        Venue::create(['name' => 'Covered Court', 'capacity' => 300, 'custodian_id' => $this->custodian->id, 'is_active' => true]);
        Venue::create(['name' => 'Mini Hall', 'capacity' => 100, 'custodian_id' => $this->otherCustodian->id, 'is_active' => true]);
        $this->date = now()->addDays(8)->toDateString();
    }

    private function booking(string $number, string $status, array $venues = ['Covered Court'], string $start = '09:00', string $end = '11:00'): FacilityRequest
    {
        $request = FacilityRequest::create([
            'control_number' => $number,
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Booking ' . $number,
            'expected_participants' => 20,
            'start_date' => $this->date,
            'end_date' => $this->date,
            'start_time' => $start,
            'end_time' => $end,
            'venue' => $venues,
            'requested_by_id' => $this->requestor->id,
            'status' => $status,
            'venue_status' => $status === 'approved' ? 'approved' : 'pending',
            'equipment_status' => 'approved',
            'supply_office_review_status' => $status === 'approved' ? 'confirmed' : 'pending',
        ]);
        $request->syncRelationalItems();

        return $request->fresh();
    }

    private function check(FacilityRequest $request, string $venue = 'Covered Court')
    {
        return $this->actingAs($this->custodian)->postJson(route('calendar.request-availability-check', $request), ['venue' => $venue]);
    }

    public function test_one_card_with_only_the_calendar_action_at_first(): void
    {
        $request = $this->booking('FER-VC-1', 'pending', ['Covered Court', 'Mini Hall']);

        $html = $this->actingAs($this->custodian)->get(route('request.show', $request))->assertOk()->getContent();
        $this->assertStringContainsString('data-venue-check-card', $html);
        $this->assertStringContainsString('Check venue calendar', $html);
        $this->assertStringContainsString('Your Review Is Needed', $html);
        // Confirm and Suggest stay hidden until the check has run.
        $this->assertMatchesRegularExpression('/data-availability-confirm="custodian-venue-' . $request->id . '" class="hidden/', $html);
        // Only the custodian's own venue is listed in the card.
        $card = substr($html, strpos($html, 'data-venue-check-card'), 1500);
        $this->assertStringContainsString('Covered Court', $card);
        $this->assertStringNotContainsString('Mini Hall', $card);
    }

    public function test_no_conflict_and_a_waiting_request_is_only_a_warning(): void
    {
        $request = $this->booking('FER-VC-2', 'pending');
        $this->check($request)->assertOk()->assertJsonPath('available', true)->assertJsonPath('message', 'No conflict found.');

        $this->booking('FER-VC-3', 'pending', ['Covered Court'], '10:00', '12:00'); // waiting for review, overlapping
        $warning = $this->check($request)->assertOk()
            ->assertJsonPath('available', true)
            ->json('warning');
        $this->assertStringStartsWith('Another request is waiting for review for this time: FER-VC-3 (', $warning);
        $this->assertStringContainsString('10:00 AM', $warning);

        $this->actingAs($this->custodian)->post(route('request.custodian.verify', $request))->assertSessionHasNoErrors();
        $this->assertSame('approved', $request->fresh()->venue_status, 'A waiting request does not block confirmation.');
    }

    public function test_an_approved_overlap_is_a_conflict_and_confirmation_is_rechecked(): void
    {
        $request = $this->booking('FER-VC-4', 'pending');
        $this->check($request)->assertOk()->assertJsonPath('available', true);

        // A booking is approved in the meantime (partial overlap).
        $this->booking('FER-VC-5', 'approved', ['Covered Court'], '10:30', '12:00');
        $response = $this->check($request)->assertOk()->assertJsonPath('available', false)->assertJsonPath('conflict', true);
        $this->assertStringStartsWith('Conflict:', $response->json('message'));

        $this->actingAs($this->custodian)->post(route('request.custodian.verify', $request))->assertSessionHasErrors();
        $this->assertSame('pending', $request->fresh()->venue_status);
    }

    public function test_the_other_venue_does_not_affect_this_custodians_check(): void
    {
        $request = $this->booking('FER-VC-6', 'pending', ['Covered Court', 'Mini Hall']);
        $this->booking('FER-VC-7', 'approved', ['Mini Hall']);

        $this->check($request)->assertOk()->assertJsonPath('available', true);
    }

    public function test_after_confirming_the_card_shows_who_and_when(): void
    {
        $request = $this->booking('FER-VC-8', 'pending');
        $this->actingAs($this->custodian)->post(route('request.custodian.verify', $request))->assertSessionHasNoErrors();

        $this->actingAs($this->custodian)->get(route('request.show', $request))
            ->assertOk()
            ->assertSee('Schedule confirmed by Court Keeper')
            ->assertDontSee('data-venue-check-card', false);
    }
}
