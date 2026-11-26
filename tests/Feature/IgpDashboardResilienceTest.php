<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * IGP dashboard HTTP 500: the dashboard must load for every kind of outsider record
 * found in production (legacy rows without a stage, missing requester, odd schedules,
 * expired, held, cancelled).
 */
class IgpDashboardResilienceTest extends TestCase
{
    use RefreshDatabase;

    private function outsiderRequest(array $extra): FacilityRequest
    {
        $date = now()->addDays(6)->toDateString();

        return FacilityRequest::create(array_merge([
            'control_number' => 'FER-IGP-' . random_int(10000, 99999),
            'date_requested' => now()->toDateString(),
            'department' => 'Leyte Events Co.',
            'name_of_activity' => 'Company Anniversary',
            'expected_participants' => 100,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '13:00',
            'end_time' => '17:00',
            'venue' => ['Covered Court'],
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'approved',
            'supply_office_review_status' => 'pending',
        ], $extra));
    }

    public function test_igp_dashboard_loads_with_every_kind_of_outsider_record(): void
    {
        Venue::create(['name' => 'Covered Court', 'capacity' => 500, 'custodian_id' => User::factory()->create(['role' => 'custodian-venue'])->id, 'is_active' => true]);
        $igp = User::factory()->create(['role' => 'igp']);
        $outsider = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'outsider']);

        $this->outsiderRequest(['requested_by_id' => $outsider->id, 'outsider_stage' => 'submitted']);
        $this->outsiderRequest(['requested_by_id' => $outsider->id]); // legacy: no stage
        $this->outsiderRequest(['requested_by_id' => $outsider->id, 'outsider_stage' => 'approved', 'outsider_hold_until' => now()->addDays(3)->toDateString()]);
        $this->outsiderRequest(['requested_by_id' => $outsider->id, 'status' => 'expired', 'outsider_stage' => 'custodian_review']);
        $this->outsiderRequest(['requested_by_id' => $outsider->id, 'status' => 'cancelled', 'outsider_stage' => 'cancelled']);
        $this->outsiderRequest(['requested_by_id' => $outsider->id, 'outsider_stage' => 'igp_review', 'start_time' => null, 'end_time' => null]);
        $orphan = $this->outsiderRequest(['requested_by_id' => $outsider->id, 'outsider_stage' => 'submitted']);
        \DB::table('facility_requests')->where('id', $orphan->id)->update(['requested_by_id' => null]);
        $this->outsiderRequest(['requested_by_id' => $outsider->id, 'outsider_stage' => 'unknown_stage']);

        $this->actingAs($igp)->get(route('igp.index'))->assertOk();
        $this->actingAs($igp)->get(route('igp.index', ['search' => 'anniversary']))->assertOk();
    }

    public function test_igp_login_redirects_to_a_working_dashboard(): void
    {
        $igp = User::factory()->create(['role' => 'igp']);

        $this->assertSame(route('igp.index'), $igp->getDashboardRoute());
        $this->actingAs($igp)->get($igp->getDashboardRoute())->assertOk();
    }

    public function test_dashboard_loads_when_the_outsider_stage_column_is_missing(): void
    {
        // Live HTTP 500: "Unknown column 'outsider_stage'" (its migration had not run there).
        \Illuminate\Support\Facades\Schema::table('facility_requests', function ($table): void {
            $table->dropIndex(['outsider_stage']);
            $table->dropColumn('outsider_stage');
        });
        $igp = User::factory()->create(['role' => 'igp']);
        $outsider = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'outsider']);
        Venue::create(['name' => 'Covered Court', 'capacity' => 500, 'custodian_id' => User::factory()->create(['role' => 'custodian-venue'])->id, 'is_active' => true]);
        $this->outsiderRequest(['requested_by_id' => $outsider->id]);

        $this->actingAs($igp)->get(route('igp.index'))->assertOk()->assertSee('Company Anniversary');
    }

    public function test_igp_logs_in_and_lands_on_a_working_dashboard(): void
    {
        // Part 3: the complete IGP login -> dashboard redirect.
        $igp = User::factory()->create([
            'role' => 'igp',
            'username' => 'igp' . uniqid() . '@test.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $response = $this->post(route('login'), ['email' => $igp->username, 'password' => 'password123']);
        $response->assertRedirect(route('igp.index'));
        $this->assertAuthenticatedAs($igp);
        $this->get(route('igp.index'))->assertOk()->assertSee('Reservation Management');

        // Other roles are unaffected and still cannot open it.
        $requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $this->actingAs($requestor)->get(route('igp.index'))->assertForbidden();
    }
}
