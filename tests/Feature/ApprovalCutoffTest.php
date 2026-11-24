<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Support\ApprovalCutoff;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Additional Part 2, approval cutoff: approvals must happen before the reservation
 * starts (Asia/Manila); pending requests whose start passes become "expired".
 */
class ApprovalCutoffTest extends TestCase
{
    use RefreshDatabase;

    private User $venueCustodian;
    private User $equipmentCustodian;
    private User $admin;
    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        // 10:00 AM in Manila.
        $this->travelTo(Carbon::parse('2026-11-24 10:00:00', 'Asia/Manila'));
        $this->venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $this->equipmentCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        $this->admin = User::factory()->create(['role' => 'admin', 'username' => config('pitfr.final_approver_username')]);
        $this->requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        Venue::create(['name' => 'Covered Court', 'capacity' => 300, 'custodian_id' => $this->venueCustodian->id, 'is_active' => true]);
        Equipment::create(['name' => 'Projector', 'custodian_id' => $this->equipmentCustodian->id, 'quantity' => 3, 'quantity_available' => 3]);
    }

    private function makeRequest(string $date, string $start, array $extra = []): FacilityRequest
    {
        $request = FacilityRequest::create(array_merge([
            'control_number' => 'FER-CUT-' . random_int(1000, 9999),
            'date_requested' => '2026-11-20',
            'department' => 'IT',
            'name_of_activity' => 'Cutoff test',
            'expected_participants' => 20,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => $start,
            'end_time' => '23:00',
            'venue' => ['Covered Court'],
            'requested_by_id' => $this->requester->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'supply_office_review_status' => 'pending',
            'equipment_status' => 'approved',
        ], $extra));
        $request->syncRelationalItems();

        return $request->fresh();
    }

    public function test_cutoff_rules(): void
    {
        $this->assertNotNull(ApprovalCutoff::reason($this->makeRequest('2026-11-23', '15:00')), 'yesterday');
        $this->assertNotNull(ApprovalCutoff::reason($this->makeRequest('2026-11-24', '09:30')), 'today, already started');
        $this->assertNull(ApprovalCutoff::reason($this->makeRequest('2026-11-24', '13:00')), 'today, later');
        $this->assertNull(ApprovalCutoff::reason($this->makeRequest('2026-11-25', '08:00')), 'tomorrow');
        $this->assertNotNull(ApprovalCutoff::reason($this->makeRequest('2026-11-28', '08:00', ['status' => 'expired'])), 'expired');
    }

    public function test_venue_custodian_cannot_confirm_after_the_start(): void
    {
        $request = $this->makeRequest('2026-11-24', '09:00');

        $this->actingAs($this->venueCustodian)->get(route('request.show', $request))
            ->assertOk()
            ->assertSee('data-approval-cutoff', false)
            ->assertDontSee('Confirm venue is available');
        $this->actingAs($this->venueCustodian)->post(route('request.custodian.verify', $request))
            ->assertSessionHasErrors('approval');
        $this->assertSame('pending', $request->fresh()->venue_status);
    }

    public function test_same_day_request_can_still_be_confirmed_before_it_starts(): void
    {
        $request = $this->makeRequest('2026-11-24', '15:00');

        $this->actingAs($this->venueCustodian)->post(route('request.custodian.verify', $request))
            ->assertSessionDoesntHaveErrors('approval');
        $this->assertSame('approved', $request->fresh()->venue_status);
    }

    public function test_equipment_and_supply_office_approvals_are_refused_after_the_start(): void
    {
        $request = $this->makeRequest('2026-11-24', '08:00', [
            'venue_status' => 'approved', 'supply_office_review_status' => 'confirmed',
            'equipment' => ['Projector'], 'equipment_quantities' => ['Projector' => 1], 'equipment_status' => 'pending',
        ]);
        $this->actingAs($this->equipmentCustodian)->post(route('request.custodian.verify', $request))
            ->assertSessionHasErrors('approval');
        $this->assertSame('pending', $request->fresh()->getCustodianEquipmentStatus($this->equipmentCustodian->id));

        $ready = $this->makeRequest('2026-11-24', '08:00', ['venue_status' => 'approved', 'supply_office_review_status' => 'confirmed']);
        $this->actingAs($this->admin)->post(route('request.supply.final-approval', $ready))
            ->assertSessionHasErrors('approval');
        $this->assertSame('pending', $ready->fresh()->status);

        $availability = $this->makeRequest('2026-11-23', '08:00', ['venue_status' => 'approved']);
        $this->actingAs($this->admin)->post(route('supply-office.requests.availability-review'), ['id' => $availability->id, 'decision' => 'available'])
            ->assertSessionHasErrors('approval');
        $this->assertSame('pending', $availability->fresh()->supply_office_review_status);
    }

    public function test_pending_requests_expire_when_their_start_passes(): void
    {
        $started = $this->makeRequest('2026-11-24', '09:00', ['venue_status' => 'approved']);
        $later = $this->makeRequest('2026-11-24', '16:00');
        $approved = $this->makeRequest('2026-11-24', '08:00', ['status' => 'approved', 'venue_status' => 'approved', 'supply_office_review_status' => 'confirmed']);

        $this->artisan('facility-requests:review-deadlines')->assertSuccessful();

        $this->assertSame('expired', $started->fresh()->status);
        $this->assertSame('approved', $started->fresh()->venue_status, 'Other statuses are not overwritten.');
        $this->assertSame('pending', $later->fresh()->status);
        $this->assertSame('approved', $approved->fresh()->status);

        $this->actingAs($this->admin)->post(route('request.supply.final-approval', $started))->assertSessionHasErrors('approval');
        $this->actingAs($this->requester)->get(route('request.show', $started))
            ->assertOk()
            ->assertSee('This request expired');
    }
}
