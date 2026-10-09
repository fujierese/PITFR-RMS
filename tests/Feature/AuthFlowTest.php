<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_with_registered_email(): void
    {
        $user = User::factory()->create([
            'username' => 'login' . uniqid() . '@test.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $this->post(route('login'), [
            'email' => $user->username,
            'password' => 'password123',
        ])->assertRedirect(route('requestor.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_email_login_is_rejected(): void
    {
        $user = User::factory()->create([
            'username' => 'invalid' . uniqid() . '@test.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => $user->username,
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_passwordless_provisioned_account_cannot_log_in_or_access_protected_routes(): void
    {
        $user = User::factory()->create([
            'username' => 'pending-setup-' . uniqid() . '@test.com',
            'role' => 'requestor',
            'requestor_type' => 'staff',
            'password' => null,
            'is_active' => true,
        ]);

        $this->from(route('login'))->post(route('login'), [
            'email' => $user->username,
            'password' => 'AnyPassword123!',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->get(route('requestor.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_log_in_with_username_and_hashed_password(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin',
            'role' => 'admin',
            'password' => Hash::make('admin'),
        ]);

        $this->post(route('login'), [
            'email' => 'admin',
            'password' => 'admin',
        ])->assertRedirect(route('supply-office.index'));

        $this->assertAuthenticatedAs($admin);
        $this->assertTrue(Hash::check('admin', $admin->fresh()->password));
    }

    public function test_google_login_is_blocked_when_oauth_credentials_are_missing(): void
    {
        Config::set('services.google.client_id', null);
        Config::set('services.google.client_secret', null);

        $this->get(route('google.redirect'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');
    }

    public function test_google_callback_rejects_unverified_google_emails(): void
    {
        $googleUser = \Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $googleUser->shouldReceive('getId')->andReturn('google-user-123');
        $googleUser->shouldReceive('getEmail')->andReturn('verified@example.com');
        $googleUser->shouldReceive('getName')->andReturn('Verified User');
        $googleUser->user = ['email_verified' => false, 'given_name' => 'Verified', 'family_name' => 'User'];

        $provider = \Mockery::mock();
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_google_callback_rejects_non_requestor_accounts(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin.google@test.com',
            'role' => 'admin',
            'is_active' => true,
            'google_id' => null,
        ]);

        $googleUser = \Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $googleUser->shouldReceive('getId')->andReturn('google-admin-123');
        $googleUser->shouldReceive('getEmail')->andReturn($admin->username);
        $googleUser->shouldReceive('getName')->andReturn('Google Admin');
        $googleUser->user = ['email_verified' => true, 'given_name' => 'Google', 'family_name' => 'Admin'];

        $provider = \Mockery::mock();
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'google_id' => null]);
        $this->assertGuest();
    }

    public function test_google_callback_cannot_link_or_authenticate_passwordless_provisioned_account(): void
    {
        $user = User::factory()->create([
            'username' => 'pending-google-' . uniqid() . '@test.com',
            'role' => 'requestor',
            'requestor_type' => 'faculty',
            'password' => null,
            'google_id' => null,
            'is_active' => true,
        ]);

        $googleUser = \Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $googleUser->shouldReceive('getId')->andReturn('unlinked-google-user');
        $googleUser->shouldReceive('getEmail')->andReturn($user->username);
        $googleUser->shouldReceive('getName')->andReturn('Pending Setup');
        $googleUser->user = ['email_verified' => true, 'given_name' => 'Pending', 'family_name' => 'Setup'];

        $provider = \Mockery::mock();
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'password' => null,
            'google_id' => null,
        ]);
    }

    public function test_google_callback_cannot_authenticate_passwordless_already_linked_account(): void
    {
        $user = User::factory()->create([
            'username' => 'pending-linked-' . uniqid() . '@test.com',
            'role' => 'requestor',
            'requestor_type' => 'student',
            'password' => null,
            'google_id' => 'already-linked-google-user',
            'is_active' => true,
        ]);

        $googleUser = \Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $googleUser->shouldReceive('getId')->andReturn('already-linked-google-user');
        $googleUser->shouldReceive('getEmail')->andReturn($user->username);
        $googleUser->shouldReceive('getName')->andReturn('Pending Linked');
        $googleUser->user = ['email_verified' => true, 'given_name' => 'Pending', 'family_name' => 'Linked'];

        $provider = \Mockery::mock();
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertSame('already-linked-google-user', $user->fresh()->google_id);
    }

    public function test_google_callback_rejects_google_id_identity_mismatch_before_fallthrough(): void
    {
        $accountA = User::factory()->create([
            'username' => 'account-a@test.com',
            'role' => 'requestor',
            'requestor_type' => 'student',
            'google_id' => 'google-user-123',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $accountB = User::factory()->create([
            'username' => 'account-b@test.com',
            'role' => 'requestor',
            'requestor_type' => 'outsider',
            'google_id' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $googleUser = \Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $googleUser->shouldReceive('getId')->andReturn('google-user-123');
        $googleUser->shouldReceive('getEmail')->andReturn($accountB->username);
        $googleUser->shouldReceive('getName')->andReturn('Account B');
        $googleUser->user = ['email_verified' => true, 'given_name' => 'Account', 'family_name' => 'B'];

        $provider = \Mockery::mock();
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'username' => 'Google authentication could not be completed because the account information does not match.',
            ]);

        $accountA->refresh();
        $accountB->refresh();

        $this->assertSame('google-user-123', $accountA->google_id);
        $this->assertNull($accountB->google_id);
        $this->assertGuest();
    }

    public function test_unknown_google_account_still_routes_to_outsider_registration(): void
    {
        $googleUser = \Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $googleUser->shouldReceive('getId')->andReturn('new-google-user-456');
        $googleUser->shouldReceive('getEmail')->andReturn('new-outsider@example.com');
        $googleUser->shouldReceive('getName')->andReturn('New Outsider');
        $googleUser->user = ['email_verified' => true, 'given_name' => 'New', 'family_name' => 'Outsider'];

        $provider = \Mockery::mock();
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->from(route('login'))
            ->get(route('google.callback'))
            ->assertRedirect(route('register'));

        $this->assertSame('new-outsider@example.com', session('google_registration_profile.email'));
        $this->assertGuest();

        $this->post(route('register.post'), [
            'first_name' => 'New',
            'surname' => 'Outsider',
            'username' => 'forged@example.com',
            'password' => '',
            'password_confirmation' => '',
            'requestor_type' => 'student',
            'role' => 'admin',
            'google_id' => 'forged-google-id',
            'is_active' => false,
            'email_verified_at' => null,
            'office_or_organization' => 'External Company',
            'organization_type' => 'Company',
        ])->assertRedirect(route('requestor.index'));

        $registered = User::where('username', 'new-outsider@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($registered);
        $this->assertSame('requestor', $registered->role);
        $this->assertSame('outsider', $registered->requestor_type);
        $this->assertSame('new-google-user-456', $registered->google_id);
        $this->assertTrue($registered->is_active);
        $this->assertNotNull($registered->email_verified_at);
        $this->assertDatabaseMissing('users', ['username' => 'forged@example.com']);
    }

    public function test_existing_outsider_account_keeps_outsider_classification_when_google_email_matches(): void
    {
        $outsider = User::factory()->create([
            'username' => 'outsider-google@example.com',
            'role' => 'requestor',
            'requestor_type' => 'outsider',
            'google_id' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $googleUser = \Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $googleUser->shouldReceive('getId')->andReturn('google-outsider-789');
        $googleUser->shouldReceive('getEmail')->andReturn($outsider->username);
        $googleUser->shouldReceive('getName')->andReturn('Outsider Google');
        $googleUser->user = ['email_verified' => true, 'given_name' => 'Outsider', 'family_name' => 'Google'];

        $provider = \Mockery::mock();
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('google.callback'))
            ->assertRedirect(route('requestor.index'));

        $outsider->refresh();
        $this->assertAuthenticatedAs($outsider);
        $this->assertSame('requestor', $outsider->role);
        $this->assertSame('outsider', $outsider->requestor_type);
        $this->assertSame('google-outsider-789', $outsider->google_id);
    }
}