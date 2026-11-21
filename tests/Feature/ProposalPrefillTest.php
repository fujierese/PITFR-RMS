<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\Holiday;
use App\Models\RevisionHistory;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * CHG-02: after a proposal is declined, the next form starts from what the request
 * asked for: its venue is selected (matched by name, ignoring case and the
 * "Balay Alumni Hall" alias) and its dates and times are filled in.
 */
class ProposalPrefillTest extends TestCase
{
    use RefreshDatabase;

    private User $requestor;
    private User $admin;
    private Venue $balay;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Holiday::query()->delete();
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        $this->balay = Venue::create(['name' => 'Balay Alumni Hall', 'capacity' => 100, 'custodian_id' => $custodian->id, 'is_active' => true]);
        Venue::create(['name' => 'Covered Court', 'capacity' => 300, 'custodian_id' => $custodian->id, 'is_active' => true]);
        $this->requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->date = now()->addDays(6)->toDateString();
    }

    private function request(string $venue = 'Balay Alumni Hall'): FacilityRequest
    {
        return FacilityRequest::create([
            'control_number' => 'FER-2026-' . random_int(300, 399),
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Alumni Night',
            'expected_participants' => 40,
            'start_date' => $this->date,
            'end_date' => $this->date,
            'start_time' => '13:00',
            'end_time' => '15:00',
            'venue' => [$venue],
            'requested_by_id' => $this->requestor->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'approved',
        ]);
    }

    public function test_after_declining_a_proposal_the_edit_form_starts_from_the_requested_venue(): void
    {
        $request = $this->request();
        $other = now()->addDays(7)->toDateString();
        $this->actingAs($this->admin)->post(route('supply-office.requests.revise'), [
            'facility_request_id' => $request->id,
            'start_date' => $other, 'end_date' => $other, 'start_time' => '09:00', 'end_time' => '11:00',
            'venue' => ['Covered Court'],
            'revision_reason' => 'The hall is closed for repairs that day.',
        ])->assertSessionHasNoErrors();

        $revision = RevisionHistory::query()->sole();
        $this->actingAs($this->requestor)->post(route('request.revision.respond', [$request, $revision]), ['response' => 'decline']);
        $this->assertSame('needs_reschedule', $request->fresh()->status);

        $this->actingAs($this->requestor)->get(route('requestor.edit', $request))
            ->assertOk()
            ->assertSee('<option value="Balay Alumni" selected>Balay Alumni</option>', false)
            ->assertSee('value="' . $this->date . '"', false)
            ->assertSee('value="13:00"', false);
    }

    public function test_the_proposal_form_ticks_the_requested_venue_whatever_its_spelling(): void
    {
        $request = $this->request('balay alumni');

        $this->actingAs($this->admin)->get(route('request.show', $request->id))
            ->assertOk()
            ->assertSee('value="Balay Alumni Hall" data-revision-venue class="rounded border-slate-300 text-amber-600" checked', false);
    }

    public function test_a_requested_venue_that_is_no_longer_bookable_is_pointed_out(): void
    {
        $request = $this->request();
        $request->update(['status' => 'needs_reschedule', 'venue_status' => 'needs_reschedule']);
        $this->balay->update(['is_active' => false]);

        $this->actingAs($this->requestor)->get(route('requestor.edit', $request))
            ->assertOk()
            ->assertSee('You asked for Balay Alumni Hall, which can no longer be booked. Choose another venue.');
    }
}
