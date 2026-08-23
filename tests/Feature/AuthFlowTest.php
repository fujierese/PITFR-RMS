<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;
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
}