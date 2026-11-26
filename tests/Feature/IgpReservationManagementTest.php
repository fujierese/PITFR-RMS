<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Support\IgpInquiries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Part 4: the IGP Coordinator's four-section Reservation Management dashboard and
 * sidebar, following one outsider inquiry through the whole workflow, and IGP's limits.
 */
class IgpReservationManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $igp;
    private User $outsider;
    private User $supply;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->igp = User::factory()->create(['role' => 'igp']);
        $this->outsider = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'outsider']);
        $this->supply = User::factory()->create(['role' => 'supply_office']);
        Venue::create(['name' => 'Covered Court', 'capacity' => 500, 'custodian_id' => User::factory()->create(['role' => 'custodian-venue'])->id, 'is_active' => true]);
    }

    private function inquiry(array $extra = []): FacilityRequest
    {
        $date = now()->addDays(12)->toDateString();
        $request = FacilityRequest::create(array_merge([
            'control_number' => 'FER-IGP4-' . random_int(1000, 9999),
            'date_requested' => now()->toDateString(),
            'department' => 'Leyte Events Co.',
            'name_of_activity' => 'Company Anniversary',
            'expected_participants' => 100,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '13:00',
            'end_time' => '17:00',
            'venue' => ['Covered Court'],
            'equipment' => [],
            'requested_by_id' => $this->outsider->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'approved',
            'supply_office_review_status' => 'pending',
            'outsider_stage' => 'submitted',
        ], $extra));
        $request->syncRelationalItems();

        return $request->fresh();
    }

    private function section(FacilityRequest $request): string
    {
        return IgpInquiries::sectionOf($request->fresh());
    }

    public function test_sidebar_and_dashboard_have_the_four_sections(): void
    {
        $this->inquiry();

        $html = $this->actingAs($this->igp)->get(route('igp.index'))->assertOk()->getContent();
        foreach (['Reservation Management', 'New Inquiries', 'Approved Requests', 'Ongoing Coordination', 'Completed / Closed', 'Calendar', 'Reservation History', 'Notifications', 'Account Settings', 'Logout'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        $this->assertStringNotContainsString('With the venue custodian or Supply Office', $html);
        $this->assertStringContainsString(route('igp.index', ['section' => 'approved']), $html);
        $this->assertStringContainsString('data-igp-card="new"', $html);

        // A sidebar item opens only its section.
        $approvedOnly = $this->actingAs($this->igp)->get(route('igp.index', ['section' => 'approved']))->assertOk()->getContent();
        $this->assertStringContainsString('data-igp-section="approved"', $approvedOnly);
        $this->assertStringNotContainsString('data-igp-section="new"', $approvedOnly);
    }

    public function test_one_inquiry_moves_through_the_sections_and_counts_only_igp_work(): void
    {
        // 1. Newly submitted.
        $inquiry = $this->inquiry();
        $this->assertSame('new', $this->section($inquiry));
        $this->assertSame(1, IgpInquiries::counts()['new']);

        // 2. Forwarded to the venue custodian: visible, read-only, not counted.
        $this->actingAs($this->igp)->post(route('igp.forward', $inquiry), ['igp_review_notes' => 'Complete details.'])->assertSessionHasNoErrors();
        $this->assertSame('custodian_review', $inquiry->fresh()->outsider_stage);
        $this->assertSame('in_review', $this->section($inquiry));
        $this->assertSame(0, IgpInquiries::counts()['new']);
        $this->actingAs($this->igp)->get(route('igp.index'))->assertSee('Forwarded for review')->assertSee($inquiry->control_number);

        // 3-4. Venue custodian review, then Supply Office review: still not IGP work.
        $inquiry->fresh()->update(['venue_status' => 'approved']);
        $this->assertSame('in_review', $this->section($inquiry));
        $this->assertSame([0, 0, 0], [IgpInquiries::counts()['new'], IgpInquiries::counts()['approved'], IgpInquiries::counts()['ongoing']]);

        // 5-6. Supply Office approval: awaiting initial IGP coordination.
        $inquiry->fresh()->update(['supply_office_review_status' => 'confirmed']);
        $this->assertSame('approved', $inquiry->fresh()->outsider_stage);
        $this->assertSame('approved', $this->section($inquiry));
        $this->assertSame(1, IgpInquiries::counts()['approved']);

        // 7. Coordination with the outsider.
        $this->actingAs($this->igp)->post(route('igp.coordinate', $inquiry), ['igp_coordination_notes' => 'Pay the venue fee at the Cashier.'])->assertSessionHasNoErrors();
        $this->assertSame('ongoing', $this->section($inquiry));
        $this->assertSame(1, IgpInquiries::counts()['ongoing']);

        // 8. Requirements reported: stays in Ongoing until the Supply Office confirms, no longer counted.
        $this->actingAs($this->igp)->post(route('igp.requirements', $inquiry), ['notes' => 'OR #12345 issued.'])->assertSessionHasNoErrors();
        $this->assertSame('ongoing', $this->section($inquiry));
        $this->assertSame(0, IgpInquiries::counts()['ongoing']);
        $this->assertSame('Requirements Reported — Awaiting Supply Office Confirmation', IgpInquiries::statusLabel($inquiry->fresh()));

        // 9. Supply Office confirms: closed because of the actual status.
        $inquiry->fresh()->update(['status' => 'approved']);
        $this->assertSame('closed', $this->section($inquiry));
        $this->assertSame('Confirmed', IgpInquiries::statusLabel($inquiry->fresh()));
        $this->actingAs($this->igp)->get(route('igp.index', ['section' => 'closed']))->assertSee($inquiry->control_number)->assertSee('Confirmed');
    }

    public function test_rejected_cancelled_and_expired_inquiries_are_closed(): void
    {
        $rejected = $this->inquiry(['status' => 'rejected']);
        $cancelled = $this->inquiry(['status' => 'cancelled']);
        $expired = $this->inquiry(['status' => 'expired', 'outsider_stage' => 'custodian_review']);

        foreach ([$rejected, $cancelled, $expired] as $request) {
            $this->assertSame('closed', $this->section($request));
        }
        $this->assertSame('Expired', IgpInquiries::statusLabel($expired->fresh()));
        // Finishing communication alone does not close an inquiry.
        $talkedTo = $this->inquiry(['outsider_stage' => 'igp_coordination', 'supply_office_review_status' => 'confirmed', 'venue_status' => 'approved', 'igp_requirements_reported_at' => now()]);
        $this->assertSame('ongoing', $this->section($talkedTo));
    }

    public function test_igp_cannot_take_custodian_or_supply_office_actions(): void
    {
        $inquiry = $this->inquiry(['outsider_stage' => 'custodian_review']);

        // Forwarding again, approving, confirming availability or approving the reservation are refused.
        $this->actingAs($this->igp)->post(route('igp.forward', $inquiry))->assertStatus(409);
        $this->actingAs($this->igp)->post(route('request.custodian.verify', $inquiry))->assertForbidden();
        $this->actingAs($this->igp)->post(route('supply-office.requests.availability-review'), ['id' => $inquiry->id, 'decision' => 'available'])->assertForbidden();
        $this->actingAs($this->igp)->post(route('request.supply.final-approval', $inquiry))->assertForbidden();
        $this->assertSame('pending', $inquiry->fresh()->status);
        $this->assertSame('pending', $inquiry->fresh()->venue_status);

        // Viewing an inquiry under review shows no decision buttons.
        $this->actingAs($this->igp)->get(route('request.show', $inquiry))->assertOk()
            ->assertDontSee('Approve Reservation')
            ->assertDontSee('Confirm Availability')
            ->assertDontSee(route('request.custodian.verify', $inquiry), false)
            ->assertDontSee(route('supply-office.requests.availability-review'), false)
            ->assertDontSee(route('request.supply.final-approval', $inquiry->id), false);
    }
}
