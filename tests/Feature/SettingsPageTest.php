<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use App\Notifications\PasswordChangedNotification;
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
        $response->assertSee('Profile');
        $response->assertSee('Change Password');
        $response->assertSee('Save Profile');
        $response->assertSee('Update Password');
        $response->assertSee('Notifications');
        $response->assertSee('Office / Organization');
        $response->assertSee('E-signature Management');
        $response->assertDontSee('Account Security');
    }

    public function test_admin_can_update_profile_name_and_office(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Old Admin Name',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.settings.profile'), [
                'surname' => 'Santos',
                'first_name' => 'Maria',
                'middle_name' => 'Luz',
                'suffix' => '',
                'contact_number' => '09170000000',
                'office_or_organization' => 'Supply Office',
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => 'Maria Luz Santos',
            'office_or_organization' => 'Supply Office',
        ]);
    }

    public function test_admin_signature_requires_png_and_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.settings.signature'), [
                'e_signature_file' => UploadedFile::fake()->create('signature.jpg', 100, 'image/jpeg'),
                'e_signature_confirmation' => '1',
            ])
            ->assertSessionHasErrors('e_signature_file');

        $this->actingAs($admin)
            ->post(route('admin.settings.signature'), [
                'e_signature_file' => UploadedFile::fake()->create('signature.png', 100, 'image/png'),
            ])
            ->assertSessionHasErrors('e_signature_confirmation');
    }

    public function test_signature_validation_is_shown_next_to_the_upload_field(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->followingRedirects()->actingAs($admin)
            ->from(route('admin.settings'))
            ->post(route('admin.settings.signature'), [
                'e_signature_file' => UploadedFile::fake()->create('signature.jpg', 100, 'image/jpeg'),
                'e_signature_confirmation' => '1',
            ]);

        $response->assertOk()
            ->assertSee('id="signature-server-error"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('must be a file of type: png');
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
        $response->assertSee('Notifications');
        $response->assertSee('Account settings sections');
        $response->assertSee('background-color: #0f172a; background-image: linear-gradient(135deg', false);
        $response->assertSee('href="#profile"', false);
        $response->assertSee('href="#notifications"', false);
        $response->assertSee('href="#signature"', false);
        $response->assertSee('href="#security"', false);
        $response->assertSee('id="settings_contact_number"', false);
        $response->assertSee('for="settings_contact_number"', false);
        $response->assertSee('style="background-color: #047857; color: #ffffff;"', false);
        $response->assertSee('Save Preferences');
    }

    public function test_requestor_cannot_change_department_through_profile_settings(): void
    {
        $requestor = User::factory()->create([
            'role' => 'requestor',
            'department' => 'Protected Department',
        ]);

        $response = $this->actingAs($requestor)->post(route('requestor.settings.profile'), [
            'first_name' => 'Updated',
            'middle_name' => '',
            'surname' => 'Name',
            'suffix' => '',
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

    public function test_custodian_can_save_profile_fields_shown_in_account_settings(): void
    {
        $custodian = User::factory()->create([
            'role' => 'custodian-venue',
            'name' => 'Original Custodian',
        ]);

        $this->actingAs($custodian)
            ->post(route('custodian.settings.profile'), [
                'surname' => 'Santos',
                'first_name' => 'Maria',
                'middle_name' => 'Luz',
                'suffix' => '',
                'department' => 'Facilities',
                'contact_number' => '09170000000',
            ])
            ->assertRedirect(route('custodian.settings'));

        $this->assertDatabaseHas('users', [
            'id' => $custodian->id,
            'name' => 'Maria Luz Santos',
            'department' => 'Facilities',
            'contact_number' => '09170000000',
        ]);
    }

    public function test_notification_preferences_are_saved_as_delivery_controls(): void
    {
        $requestor = User::factory()->create(['role' => 'requestor']);

        $this->actingAs($requestor)
            ->post(route('requestor.settings.notifications'), [
                'request_updates' => '1',
            ])
            ->assertRedirect(route('requestor.settings'));

        $this->assertSame([
            'request_updates' => true,
            'security_alerts' => false,
        ], $requestor->fresh()->notification_preferences);
    }

    public function test_password_change_sends_security_alert_when_enabled(): void
    {
        Notification::fake();
        $requestor = User::factory()->create([
            'role' => 'requestor',
            'notification_preferences' => [
                'request_updates' => true,
                'security_alerts' => true,
            ],
        ]);

        $this->actingAs($requestor)
            ->post(route('requestor.settings.password'), [
                'current_password' => 'password',
                'password' => 'a-stronger-password-123',
                'password_confirmation' => 'a-stronger-password-123',
            ])
            ->assertRedirect(route('requestor.settings'));

        $this->assertTrue(Hash::check('a-stronger-password-123', $requestor->fresh()->password));
        Notification::assertSentTo($requestor, PasswordChangedNotification::class);
    }

    public function test_password_change_respects_disabled_security_alerts(): void
    {
        Notification::fake();
        $requestor = User::factory()->create([
            'role' => 'requestor',
            'notification_preferences' => [
                'request_updates' => true,
                'security_alerts' => false,
            ],
        ]);

        $this->actingAs($requestor)
            ->post(route('requestor.settings.password'), [
                'current_password' => 'password',
                'password' => 'a-stronger-password-123',
                'password_confirmation' => 'a-stronger-password-123',
            ])
            ->assertRedirect(route('requestor.settings'));

        $this->assertTrue(Hash::check('a-stronger-password-123', $requestor->fresh()->password));
        Notification::assertNotSentTo($requestor, PasswordChangedNotification::class);
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
        $response->assertDontSee('Administrative account security');
        $response->assertSee('Notifications');
        $response->assertSee('E-signature Management');
    }
}
