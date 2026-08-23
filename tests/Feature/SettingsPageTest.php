<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_settings_page_displays_profile_and_password_forms(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin User',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.settings'));

        $response->assertOk();
        $response->assertSee('Profile Information');
        $response->assertSee('Change Password');
        $response->assertSee('Save Profile');
        $response->assertSee('Update Password');
        $response->assertSee('Account Security');
        $response->assertDontSee('E-signature Management');
    }

    public function test_requestor_settings_show_registered_organization_and_signature(): void
    {
        $requestor = User::factory()->create([
            'role' => 'requestor',
            'department' => 'Protected Department',
        ]);

        $response = $this->actingAs($requestor)->get(route('requestor.settings'));

        $response->assertOk();
        $response->assertSee('Registered College');
        $response->assertSee('Registered Department');
        $response->assertSee('E-signature Management');
        $response->assertSee('Email Notifications');
    }

    public function test_requestor_cannot_change_department_through_profile_settings(): void
    {
        $requestor = User::factory()->create([
            'role' => 'requestor',
            'department' => 'Protected Department',
        ]);

        $response = $this->actingAs($requestor)->post(route('requestor.settings.profile'), [
            'name' => 'Updated Name',
            'department' => 'Unauthorized Department',
            'contact_number' => '09170000000',
        ]);

        $response->assertRedirect(route('requestor.settings'));
        $this->assertDatabaseHas('users', [
            'id' => $requestor->id,
            'name' => 'Updated Name',
            'department' => 'Protected Department',
        ]);
    }

    public function test_custodian_settings_show_signature_and_security_sections(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian-venue']);

        $response = $this->actingAs($custodian)->get(route('custodian.settings'));

        $response->assertOk();
        $response->assertSee('E-signature Management');
        $response->assertSee('Account Security');
        $response->assertDontSee('Registered College');
    }

    public function test_equipment_custodian_uses_the_custodian_settings_permissions(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian-equipment']);

        $response = $this->actingAs($custodian)->get(route('custodian.settings'));

        $response->assertOk();
        $response->assertSee('E-signature Management');
        $response->assertDontSee('Registered Department');
    }

    public function test_supply_office_settings_are_available_to_the_administrative_role(): void
    {
        $supplyOffice = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($supplyOffice)->get(route('supply-office.settings'));

        $response->assertOk();
        $response->assertSee('Administrative account security');
        $response->assertSee('Email Notifications');
        $response->assertDontSee('E-signature Management');
    }
}
