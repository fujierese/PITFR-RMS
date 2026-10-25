<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IgpWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeOutsiderReservation(array $overrides = []): FacilityRequest
    {
        $requester = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'outsider',
        ]);

        return FacilityRequest::create(array_merge([
            'control_number' => 'IGP-' . uniqid(),
            'date_requested' => now()->toDateString(),
            'department' => 'External Organization',
            'name_of_activity' => 'Outsider reservation',
            'expected_participants' => 20,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'venue' => ['CHIC'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'supply_office_review_status' => 'confirmed',
        ], $overrides));
    }

    public function test_igp_login_redirects_to_igp_dashboard(): void
    {
        $igp = User::factory()->create([
            'role' => 'igp',
            'username' => 'igp-login-' . uniqid() . '@test.com',
            'password' => Hash::make('ValidPassword123!'),
        ]);

        $this->post(route('login'), [
            'email' => $igp->username,
            'password' => 'ValidPassword123!',
        ])->assertRedirect(route('igp.index'));
    }

    public function test_igp_can_record_payment_only_after_supply_office_availability_confirmation(): void
    {
        $igp = User::factory()->create(['role' => 'igp']);
        $reservation = $this->makeOutsiderReservation(['supply_office_review_status' => 'pending']);

        $this->actingAs($igp)
            ->post(route('igp.payment', $reservation), ['igp_receipt_number' => 'OR-204'])
            ->assertStatus(409);

        $reservation->refresh();
        $this->assertNull($reservation->igp_receipt_number);

        $reservation->update(['supply_office_review_status' => 'confirmed']);
        $this->actingAs($igp)
            ->post(route('igp.payment', $reservation), ['igp_receipt_number' => 'OR-204'])
            ->assertRedirect();

        $reservation->refresh();
        $this->assertSame('paid', $reservation->igp_payment_status);
        $this->assertSame('OR-204', $reservation->igp_receipt_number);
        $this->assertSame($igp->id, $reservation->igp_paid_by_id);
        $this->assertNotNull($reservation->igp_paid_at);
        $this->assertDatabaseHas('request_histories', [
            'facility_request_id' => $reservation->id,
            'action' => 'igp_payment_recorded',
            'user_id' => $igp->id,
        ]);
    }

    public function test_only_igp_role_can_open_the_payment_dashboard_and_record_a_refund_after_cancellation(): void
    {
        $igp = User::factory()->create(['role' => 'igp']);
        $requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $reservation = $this->makeOutsiderReservation([
            'igp_payment_status' => 'paid',
            'igp_receipt_number' => 'OR-205',
            'igp_paid_by_id' => $igp->id,
            'cancellation_status' => 'confirmed',
        ]);

        $this->actingAs($requestor)->get(route('igp.index'))->assertForbidden();
        $this->actingAs($igp)->get(route('igp.index'))
            ->assertOk()
            ->assertSee($reservation->control_number)
            ->assertSee('Notifications')
            ->assertSee('Account Settings')
            ->assertSee('Logout')
            ->assertSee('Reservation History');
        $this->get(route('igp.settings'))->assertOk()->assertSee('Account settings');
        $this->actingAs($igp)
            ->post(route('igp.refund', $reservation), [
                'igp_refund_reference' => 'RF-18',
                'igp_refund_notes' => 'Refund completed by IGP.',
            ])
            ->assertRedirect();

        $reservation->refresh();
        $this->assertSame('refunded', $reservation->igp_refund_status);
        $this->assertSame('RF-18', $reservation->igp_refund_reference);
        $this->assertSame($igp->id, $reservation->igp_refunded_by_id);
        $this->assertNotNull($reservation->igp_refunded_at);
    }

    public function test_igp_can_update_profile_from_account_settings(): void
    {
        $igp = User::factory()->create([
            'role' => 'igp',
            'first_name' => 'Initial',
            'surname' => 'Coordinator',
        ]);

        $this->actingAs($igp)
            ->post(route('igp.settings.profile'), [
                'first_name' => 'Updated',
                'surname' => 'Coordinator',
                'contact_number' => '555-0100',
            ])
            ->assertRedirect(route('igp.settings'));

        $igp->refresh();
        $this->assertSame('Updated Coordinator', $igp->name);
        $this->assertSame('555-0100', $igp->contact_number);
    }

    public function test_final_supply_office_approval_requires_igp_payment_for_outsider_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reservation = $this->makeOutsiderReservation();

        $this->actingAs($admin)
            ->post(route('supply-office.update'), [
                'id' => $reservation->id,
                'action' => 'approve',
            ])
            ->assertSessionHasErrors('action');

        $this->assertSame('pending', $reservation->fresh()->status);
    }
}
