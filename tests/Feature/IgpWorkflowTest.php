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
            'outsider_stage' => 'approved',
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

    public function test_payment_and_refund_recording_are_not_part_of_pitfr(): void
    {
        // B.5: payment collection, verification, receipts and refunds stay offline.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('igp.payment'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('igp.refund'));
    }

    public function test_only_igp_role_can_open_the_igp_dashboard(): void
    {
        $igp = User::factory()->create(['role' => 'igp']);
        $requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $reservation = $this->makeOutsiderReservation();

        $this->actingAs($requestor)->get(route('igp.index'))->assertForbidden();
        $this->actingAs($igp)->get(route('igp.index'))
            ->assertOk()
            ->assertSee($reservation->control_number)
            ->assertSee('Approved Requests') // Part 4 section name
            ->assertSee('Notifications')
            ->assertSee('Account Settings')
            ->assertSee('Logout')
            ->assertSee('Reservation History')
            ->assertDontSee('Record payment');
        $this->get(route('igp.settings'))->assertOk()->assertSee('Account settings');
    }

    public function test_igp_can_update_profile_from_account_settings(): void
    {
        $igp = User::factory()->create([
            'role' => 'igp',
            'first_name' => 'Initial',
            'middle_name' => null, // the factory sometimes adds one, which made this test flaky
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

    public function test_final_supply_office_approval_requires_igp_coordination_for_outsider_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'username' => 'asala@gmail.com']);
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
