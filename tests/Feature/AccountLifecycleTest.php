<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivated_requestor_cannot_login_or_submit_new_requests(): void
    {
        $user = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
            'is_active' => false,
            'password' => Hash::make('password'),
        ]);

        $this->post(route('login'), [
            'email' => $user->username,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($user)
            ->post(route('requestor.store'), [])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('authorization');
    }

    public function test_deactivation_preserves_historical_requests_and_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $request = FacilityRequest::factory()->create(['requested_by_id' => $user->id]);
        $history = $request->addHistory('created', 'Historical request', $user->id);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'username' => $user->username,
                'role' => 'requestor',
                'requestor_type' => 'student',
                'is_active' => '0',
            ])
            ->assertRedirect(route('admin.users'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => 0]);
        $this->assertDatabaseHas('facility_requests', ['id' => $request->id, 'requested_by_id' => $user->id]);
        $this->assertDatabaseHas('request_histories', ['id' => $history->id, 'user_id' => $user->id]);
    }

    public function test_admin_can_reactivate_account_without_replacing_identity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'outsider', 'is_active' => false]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'username' => $user->username,
                'role' => 'requestor',
                'requestor_type' => 'outsider',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.users'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => 1]);
    }

    public function test_requestor_profile_cannot_mass_assign_role_or_edit_another_user(): void
    {
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'faculty']);
        $other = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->post(route('requestor.settings.profile'), [
                'first_name' => 'Updated',
                'middle_name' => '',
                'surname' => 'Name',
                'suffix' => '',
                'contact_number' => '09170000000',
                'role' => 'admin',
                'is_active' => false,
            ])
            ->assertRedirect(route('requestor.settings'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'role' => 'requestor',
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('users', ['id' => $other->id, 'role' => 'admin']);
    }
}
