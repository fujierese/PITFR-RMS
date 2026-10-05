<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
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
            ->assertSessionHasErrors('email');
    }

    public function test_global_web_middleware_logs_out_a_deactivated_session(): void
    {
        $user = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
            'is_active' => true,
        ]);

        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $this->get(route('requestor.index'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_inactive_sanctum_token_is_rejected_and_revoked(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $issuedToken = $user->createToken('active-before-deactivation');
        $user->update(['is_active' => false]);

        $this->withToken($issuedToken->plainTextToken)
            ->getJson('/api/user')
            ->assertForbidden()
            ->assertJson(['message' => 'This account is inactive.']);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $issuedToken->accessToken->id]);
    }

    public function test_google_callback_does_not_authenticate_an_inactive_linked_account(): void
    {
        $user = User::factory()->create([
            'role' => 'requestor',
            'is_active' => false,
            'google_id' => 'inactive-google-account',
        ]);
        $googleUser = \Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $googleUser->shouldReceive('getId')->andReturn('inactive-google-account');
        $googleUser->shouldReceive('getEmail')->andReturn($user->username);
        $provider = \Mockery::mock();
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_google_callback_does_not_link_to_an_inactive_account(): void
    {
        $user = User::factory()->create([
            'role' => 'requestor',
            'is_active' => false,
            'google_id' => null,
        ]);
        $googleUser = \Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $googleUser->shouldReceive('getId')->andReturn('new-google-account');
        $googleUser->shouldReceive('getEmail')->andReturn($user->username);
        $provider = \Mockery::mock();
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'google_id' => null]);
        $this->assertGuest();
    }

    public function test_deactivation_preserves_historical_requests_and_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $request = FacilityRequest::factory()->create(['requested_by_id' => $user->id]);
        $history = $request->addHistory('created', 'Historical request', $user->id);
        $token = $user->createToken('revoke-on-deactivation')->accessToken;
        $sessionId = 'session-for-deactivated-user';
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);
        config(['session.driver' => 'database']);

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
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
        $this->assertDatabaseHas('facility_requests', ['id' => $request->id, 'requested_by_id' => $user->id]);
        $this->assertDatabaseHas('request_histories', ['id' => $history->id, 'user_id' => $user->id]);
    }

    public function test_deactivation_route_revokes_sanctum_tokens(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $token = $user->createToken('revoke-on-deactivation')->accessToken;

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users'));

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
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
