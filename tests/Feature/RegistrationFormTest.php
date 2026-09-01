<?php

namespace Tests\Feature;

use Database\Seeders\CollegeDepartmentSeeder;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use App\Mail\RegistrationOtp;
use Carbon\Carbon;

class RegistrationFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CollegeDepartmentSeeder::class);
        Mail::fake();
    }

    public function test_registration_page_is_for_outsiders_only(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk()
            ->assertSee('Outsider registration')
            ->assertSee('Students and Faculty should obtain accounts through the authorized PIT administrator')
            ->assertDontSee('data-type="student"')
            ->assertDontSee('data-type="faculty"')
            ->assertDontSee('data-type="student_organization"');
    }

    /**
     * Test registration page displays new name fields
     */
    public function test_registration_page_has_separate_name_fields(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk()
            ->assertSee('First name')
            ->assertSee('Last name')
            ->assertSee('Middle name')
            ->assertDontSee('Full name');
    }

    /**
     * Test registration page displays College and Department dropdowns
     */
    public function test_registration_page_has_college_and_department_dropdowns(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk()
            ->assertSee('College')
            ->assertSee('Department')
            ->assertSee('College of Technology and Engineering');
    }

    /**
     * Test student registration with valid data
     */
    public function test_public_student_registration_is_rejected(): void
    {
        $username = 'johndoe' . uniqid() . '@test.com';
        $response = $this->post(route('register.post'), [
            'first_name' => 'John',
            'middle_name' => 'Michael',
            'last_name' => 'Doe',
            'username' => $username,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'requestor_type' => 'student',
            'college_id' => 1,
            'department_id' => 1,
            'school_id_number' => '23-0098-635',
            'contact_number' => '09171234567',
        ]);

        $response->assertSessionHasErrors('requestor_type');
        $this->assertDatabaseMissing('users', ['username' => $username]);
    }

    /**
     * Test student registration fails with invalid student ID format
     */
    public function test_public_student_registration_is_rejected_before_student_field_validation(): void
    {
        $response = $this->post(route('register.post'), [
            'first_name' => 'John',
            'middle_name' => '',
            'last_name' => 'Doe',
            'username' => 'johndoe' . uniqid() . '@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'requestor_type' => 'student',
            'college_id' => 1,
            'department_id' => 1,
            'school_id_number' => 'invalid-id',
            'contact_number' => '09171234567',
        ]);

        $response->assertSessionHasErrors('requestor_type');
    }

    /**
     * Test external user registration without Student ID
     */
    public function test_external_user_can_register_without_student_id(): void
    {
        $response = $this->post(route('register.post'), [
            'first_name' => 'Jane',
            'middle_name' => '',
            'last_name' => 'External',
            'username' => 'janeext' . uniqid() . '@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'requestor_type' => 'outsider',
            'contact_person' => 'Jane External',
            'office_or_organization' => 'External Company',
            'contact_number' => '09171234567',
        ]);

        $response->assertRedirect(route('register.verify'));
        $this->assertGuest();
    }

    /**
     * Test student registration requires college and department
     */
    public function test_public_student_registration_cannot_probe_student_requirements(): void
    {
        $response = $this->post(route('register.post'), [
            'first_name' => 'John',
            'middle_name' => '',
            'last_name' => 'Doe',
            'username' => 'johndoe' . uniqid() . '@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'requestor_type' => 'student',
            'school_id_number' => '23-0098-635',
            'contact_number' => '09171234567',
        ]);

        $response->assertSessionHasErrors('requestor_type');
    }

    public function test_public_faculty_registration_is_rejected(): void
    {
        $username = 'faculty' . uniqid() . '@test.com';
        $facultyId = 'FAC-' . uniqid();
        $response = $this->post(route('register.post'), [
            'first_name' => 'Faculty',
            'middle_name' => '',
            'last_name' => 'Member',
            'username' => $username,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'requestor_type' => 'faculty',
            'college_id' => 1,
            'department_id' => 1,
            'faculty_id' => $facultyId,
            'position' => 'Department Chair',
            'contact_number' => '09171234567',
        ]);

        $response->assertSessionHasErrors('requestor_type');
        $this->assertDatabaseMissing('users', ['username' => $username]);
    }

    public function test_public_student_organization_registration_is_rejected(): void
    {
        $username = 'organization' . uniqid() . '@test.com';
        $response = $this->post(route('register.post'), [
            'username' => $username,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'requestor_type' => 'student_organization',
            'contact_person' => 'Organization Account',
            'office_or_organization' => 'PIT Student Council',
        ]);

        $response->assertSessionHasErrors('requestor_type');
        $this->assertDatabaseMissing('users', ['username' => $username]);
    }

    public function test_forged_faculty_registration_is_rejected_before_field_validation(): void
    {
        $this->post(route('register.post'), [
            'first_name' => 'Faculty',
            'last_name' => 'Member',
            'username' => 'faculty' . uniqid() . '@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'requestor_type' => 'faculty',
            'college_id' => 1,
            'department_id' => 1,
        ])->assertSessionHasErrors('requestor_type');
    }

    public function test_google_redirect_accepts_clinic_type(): void
    {
        $response = $this->get(route('google.redirect', ['type' => 'clinic']));

        $response->assertRedirect();
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth', $response->getTargetUrl());
    }

    public function test_valid_otp_verifies_account_and_logs_user_in(): void
    {
        $data = $this->studentData();
        $this->post(route('register.post'), $data);
        $mail = Mail::sent(RegistrationOtp::class)->first();
        $user = User::where('username', $data['username'])->firstOrFail();

        $response = $this->post(route('register.verify.post'), ['otp' => $mail->otp]);

        $response->assertRedirect(route('requestor.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNull($user->fresh()->otp_hash);
    }

    public function test_invalid_and_expired_otp_are_rejected(): void
    {
        $data = $this->studentData();
        $this->post(route('register.post'), $data);
        $user = User::where('username', $data['username'])->firstOrFail();

        $this->post(route('register.verify.post'), ['otp' => '000000'])
            ->assertSessionHasErrors('otp');
        $user->update(['otp_expires_at' => Carbon::now()->subMinute()]);

        $this->post(route('register.verify.post'), ['otp' => '000000'])
            ->assertSessionHasErrors('otp');
        $this->assertGuest();
    }

    public function test_resend_otp_sends_a_new_code_and_is_rate_limited(): void
    {
        $this->post(route('register.post'), $this->studentData());

        $this->post(route('register.verify.resend'))->assertSessionHas('status');
        $this->post(route('register.verify.resend'))->assertSessionHas('status');
        $this->post(route('register.verify.resend'))->assertSessionHas('status');
        $this->post(route('register.verify.resend'))->assertSessionHasErrors('otp');
        Mail::assertSent(RegistrationOtp::class, 4);
    }

    public function test_departments_endpoint_returns_only_selected_college_departments(): void
    {
        $response = $this->getJson(route('register.departments', ['college' => 1]));

        $response->assertOk()->assertJsonStructure([['id', 'name']]);
        $this->assertTrue(collect($response->json())->every(fn (array $department) => $department['id'] !== 6));
    }

    public function test_forgot_password_sends_reset_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['username' => 'recover' . uniqid() . '@test.com']);

        $this->get(route('password.request'))->assertOk();
        $this->post(route('password.email'), ['email' => $user->username])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_link_updates_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['username' => 'reset' . uniqid() . '@test.com']);
        $this->post(route('password.email'), ['email' => $user->username]);
        $token = null;

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;
            return true;
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->username,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    private function studentData(): array
    {
        return [
            'contact_person' => 'Test Outsider',
            'office_or_organization' => 'Test Organization',
            'username' => 'otp' . uniqid() . '@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'requestor_type' => 'outsider',
        ];
    }
}
