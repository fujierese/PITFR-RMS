<?php

namespace Tests\Feature;

use Database\Seeders\CollegeDepartmentSeeder;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use App\Models\AuditLog;
use RuntimeException;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\PasswordChangedNotification;
use App\Mail\RegistrationOtp;
use Carbon\Carbon;
use Illuminate\Support\Facades\Password;

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
            ->assertSee('Surname')
            ->assertSee('Middle name')
            ->assertDontSee('Full name');
    }

    /**
     * Test registration page displays College and Department dropdowns
     */
    public function test_registration_page_has_organization_field(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk()
            ->assertSee('Organization name / affiliation')
            ->assertSee('Your organization, office, company, or Individual / Personal')
            ->assertDontSee('College of Technology and Engineering');
    }

    /**
     * Test student registration with valid data
     */
    public function test_public_registration_ignores_submitted_student_classification(): void
    {
        $username = 'johndoe' . uniqid() . '@test.com';
        $response = $this->post(route('register.post'), [
            'first_name' => 'John',
            'middle_name' => 'Michael',
            'surname' => 'Doe',
            'username' => $username,
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
            'requestor_type' => 'student',
            'college_id' => 1,
            'department_id' => 1,
            'school_id_number' => '23-0098-635',
            'office_or_organization' => 'External Company',
            'organization_type' => 'Company',
            'contact_number' => '09171234567',
        ]);

        $response->assertRedirect(route('register.verify'));
        $this->assertDatabaseHas('users', [
            'username' => $username,
            'role' => 'requestor',
            'requestor_type' => 'outsider',
            'school_id_number' => null,
            'email_verified_at' => null,
            'is_active' => true,
        ]);
    }

    /**
     * Test student registration fails with invalid student ID format
     */
    public function test_public_student_registration_does_not_use_student_specific_fields_for_classification(): void
    {
        $email = 'johndoe' . uniqid() . '@test.com';
        $response = $this->post(route('register.post'), [
            'first_name' => 'John',
            'middle_name' => '',
            'surname' => 'Doe',
            'username' => $email,
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
            'requestor_type' => 'student',
            'college_id' => 1,
            'department_id' => 1,
            'school_id_number' => 'invalid-id',
            'office_or_organization' => 'External Company',
            'organization_type' => 'Company',
            'contact_number' => '09171234567',
        ]);

        $response->assertRedirect(route('register.verify'));
        $this->assertDatabaseHas('users', ['username' => $email, 'role' => 'requestor', 'requestor_type' => 'outsider']);
    }

    /**
     * Test external user registration without Student ID
     */
    public function test_external_user_can_register_without_student_id(): void
    {
        $email = 'janeext' . uniqid() . '@test.com';
        $response = $this->post(route('register.post'), [
            'first_name' => 'Jane',
            'middle_name' => '',
            'surname' => 'External',
            'username' => $email,
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
            'office_or_organization' => 'External Company',
            'organization_type' => 'External Company',
            'contact_number' => '09171234567',
        ]);

        $response->assertRedirect(route('register.verify'));
        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'username' => $email,
            'role' => 'requestor',
            'requestor_type' => 'outsider',
        ]);
    }

    /**
     * Test student registration requires college and department
     */
    public function test_public_registration_ignores_submitted_student_fields_and_creates_outsider(): void
    {
        $email = 'johndoe' . uniqid() . '@test.com';
        $response = $this->post(route('register.post'), [
            'first_name' => 'John',
            'middle_name' => '',
            'surname' => 'Doe',
            'username' => $email,
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
            'requestor_type' => 'student',
            'school_id_number' => '23-0098-635',
            'contact_number' => '09171234567',
            'office_or_organization' => 'External Company',
            'organization_type' => 'Company',
        ]);

        $response->assertRedirect(route('register.verify'));
        $this->assertDatabaseHas('users', ['username' => $email, 'role' => 'requestor', 'requestor_type' => 'outsider']);
    }

    public function test_public_registration_ignores_submitted_faculty_classification(): void
    {
        $username = 'faculty' . uniqid() . '@test.com';
        $facultyId = 'FAC-' . uniqid();
        $response = $this->post(route('register.post'), [
            'first_name' => 'Faculty',
            'middle_name' => '',
            'surname' => 'Member',
            'username' => $username,
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
            'requestor_type' => 'faculty',
            'college_id' => 1,
            'department_id' => 1,
            'faculty_id' => $facultyId,
            'position' => 'Department Chair',
            'office_or_organization' => 'External Company',
            'organization_type' => 'Company',
            'contact_number' => '09171234567',
        ]);

        $response->assertRedirect(route('register.verify'));
        $this->assertDatabaseHas('users', ['username' => $username, 'role' => 'requestor', 'requestor_type' => 'outsider']);
    }

    public function test_public_registration_ignores_other_requestor_type_values(): void
    {
        foreach (['staff', 'admin', 'custodian-venue', 'custodian-equipment'] as $index => $requestorType) {
            $email = 'forged-type-' . $index . '-' . uniqid() . '@test.com';
            $response = $this->post(route('register.post'), $this->registrationData([
                'username' => $email,
                'requestor_type' => $requestorType,
            ]));

            $response->assertRedirect(route('register.verify'));
            $this->assertDatabaseHas('users', [
                'username' => $email,
                'role' => 'requestor',
                'requestor_type' => 'outsider',
            ]);
        }
    }

    public function test_public_registration_ignores_student_and_faculty_fields(): void
    {
        $email = 'faculty' . uniqid() . '@test.com';
        $this->post(route('register.post'), [
            'first_name' => 'Faculty',
            'surname' => 'Member',
            'username' => $email,
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
            'requestor_type' => 'faculty',
            'college_id' => 1,
            'department_id' => 1,
            'office_or_organization' => 'External Company',
            'organization_type' => 'Company',
        ])->assertRedirect(route('register.verify'));
        $this->assertDatabaseHas('users', ['username' => $email, 'role' => 'requestor', 'requestor_type' => 'outsider']);
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

    public function test_otp_verification_endpoint_throttles_repeated_attempts(): void
    {
        $user = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'outsider',
            'email_verified_at' => null,
            'otp_hash' => Hash::make('123456'),
            'otp_expires_at' => now()->addMinutes(10),
            'otp_attempts' => 0,
        ]);

        $this->withSession(['registration_user_id' => $user->id]);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('register.verify.post'), ['otp' => '000000'])
                ->assertSessionHasErrors('otp');
        }

        $this->post(route('register.verify.post'), ['otp' => '000000'])
            ->assertStatus(429);
    }

    public function test_registration_endpoint_throttles_excessive_account_creation(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('register.post'), $this->registrationData([
                'username' => 'throttle-' . $attempt . '-' . uniqid() . '@test.com',
            ]))->assertRedirect(route('register.verify'));
        }

        $this->post(route('register.post'), $this->registrationData([
            'username' => 'throttle-sixth-' . uniqid() . '@test.com',
        ]))->assertStatus(429);

        $this->assertSame(5, User::where('username', 'like', 'throttle-%@test.com')->count());
    }

    public function test_authenticated_user_cannot_post_public_registration(): void
    {
        $authenticatedUser = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'outsider']);
        $email = 'authenticated-register-' . uniqid() . '@test.com';

        $this->actingAs($authenticatedUser)
            ->post(route('register.post'), $this->registrationData(['username' => $email]))
            ->assertRedirect(route('home'));

        $this->assertDatabaseMissing('users', ['username' => $email]);
    }

    public function test_public_registration_ignores_forged_security_fields_and_audits_creation(): void
    {
        $email = 'spoof-registration-' . uniqid() . '@test.com';
        $response = $this->post(route('register.post'), $this->registrationData([
            'username' => $email,
            'role' => 'admin',
            'requestor_type' => 'faculty',
            'is_active' => false,
            'email_verified_at' => now()->toDateTimeString(),
            'google_id' => 'forged-google-identity',
        ]));

        $response->assertRedirect(route('register.verify'));
        $user = User::where('username', $email)->firstOrFail();
        $this->assertSame('requestor', $user->role);
        $this->assertSame('outsider', $user->requestor_type);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->google_id);
        $this->assertTrue(Hash::check('password12345', $user->password));
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => null,
            'target_user_id' => $user->id,
            'action' => 'user_created',
        ]);

        $audit = AuditLog::where('target_user_id', $user->id)->where('action', 'user_created')->firstOrFail();
        $serializedAudit = json_encode([$audit->details, $audit->old_values, $audit->new_values]);
        $this->assertStringNotContainsString('password', strtolower((string) $serializedAudit));
        $this->assertStringNotContainsString('otp', strtolower((string) $serializedAudit));
        $this->assertStringNotContainsString('google_id', strtolower((string) $serializedAudit));
        $this->assertStringNotContainsString('forged-google-identity', (string) $serializedAudit);
    }

    public function test_public_registration_ignores_forged_custodian_roles(): void
    {
        foreach (['custodian-venue', 'custodian-equipment'] as $index => $role) {
            $email = 'forged-role-' . $index . '-' . uniqid() . '@test.com';
            $this->post(route('register.post'), $this->registrationData([
                'username' => $email,
                'role' => $role,
            ]))->assertRedirect(route('register.verify'));

            $this->assertDatabaseHas('users', [
                'username' => $email,
                'role' => 'requestor',
                'requestor_type' => 'outsider',
            ]);
        }
    }

    public function test_registration_audit_failure_rolls_back_outsider_creation(): void
    {
        $email = 'audit-failure-' . uniqid() . '@test.com';
        AuditLog::creating(function (): void {
            throw new RuntimeException('Simulated registration audit failure.');
        });
        $this->withoutExceptionHandling();

        try {
            $this->post(route('register.post'), $this->registrationData(['username' => $email]));
            $this->fail('Expected audit creation failure to be propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated registration audit failure.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('users', ['username' => $email]);
    }

    public function test_duplicate_registration_returns_generic_error_without_modifying_existing_user(): void
    {
        $existing = User::factory()->create([
            'username' => 'duplicate-outsider-' . uniqid() . '@test.com',
            'role' => 'requestor',
            'requestor_type' => 'outsider',
            'password' => Hash::make('ExistingPassword123!'),
            'is_active' => false,
            'google_id' => null,
        ]);

        $this->from(route('register'))
            ->post(route('register.post'), $this->registrationData([
                'username' => $existing->username,
                'role' => 'admin',
                'requestor_type' => 'student',
                'is_active' => true,
            ]))
            ->assertSessionHasErrors([
                'username' => 'Unable to complete registration with the provided details.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $existing->id,
            'role' => 'requestor',
            'requestor_type' => 'outsider',
            'is_active' => false,
            'google_id' => null,
        ]);
        $this->assertTrue(Hash::check('ExistingPassword123!', $existing->fresh()->password));
    }

    public function test_existing_insider_cannot_be_converted_by_public_registration(): void
    {
        $existing = User::factory()->create([
            'username' => 'duplicate-insider-' . uniqid() . '@test.com',
            'role' => 'requestor',
            'requestor_type' => 'faculty',
            'password' => Hash::make('InsiderPassword123!'),
            'is_active' => true,
            'google_id' => 'existing-insider-google',
        ]);

        $this->post(route('register.post'), $this->registrationData([
            'username' => $existing->username,
            'requestor_type' => 'outsider',
            'role' => 'admin',
            'is_active' => false,
            'google_id' => 'forged-google',
        ]))->assertSessionHasErrors([
            'username' => 'Unable to complete registration with the provided details.',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $existing->id,
            'role' => 'requestor',
            'requestor_type' => 'faculty',
            'is_active' => true,
            'google_id' => 'existing-insider-google',
        ]);
        $this->assertTrue(Hash::check('InsiderPassword123!', $existing->fresh()->password));
    }

    public function test_registration_rejects_email_longer_than_existing_username_column(): void
    {
        $email = str_repeat('a', 48) . '@test.com';
        $this->post(route('register.post'), $this->registrationData(['username' => $email]))
            ->assertSessionHasErrors('username');

        $this->assertDatabaseMissing('users', ['username' => $email]);
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
            ->assertSessionHas('status', $this->genericResetResponse());

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_password_recovery_request_is_generic_for_existing_inactive_and_unknown_accounts(): void
    {
        Notification::fake();
        $active = User::factory()->create(['username' => 'active-recovery-' . uniqid() . '@test.com']);
        $inactive = User::factory()->create([
            'username' => 'inactive-recovery-request-' . uniqid() . '@test.com',
            'is_active' => false,
        ]);

        foreach ([$active->username, $inactive->username, 'unknown-' . uniqid() . '@test.com'] as $email) {
            $this->post(route('password.email'), ['email' => $email])
                ->assertSessionHas('status', $this->genericResetResponse())
                ->assertSessionDoesntHaveErrors('email');
        }

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $active->username]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $inactive->username]);
        Notification::assertSentTo($active, ResetPasswordNotification::class);
        Notification::assertNotSentTo($inactive, ResetPasswordNotification::class);
    }

    public function test_password_reset_link_uses_configured_host_instead_of_request_host(): void
    {
        Notification::fake();
        config(['app.url' => 'https://pitfr.example.edu']);
        $user = User::factory()->create([
            'username' => 'canonical-reset-' . uniqid() . '@test.com',
        ]);

        $this->withServerVariables(['HTTP_HOST' => 'attacker.example'])
            ->post('/forgot-password', ['email' => $user->username])
            ->assertSessionHas('status', $this->genericResetResponse());

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            return parse_url($mail->actionUrl, PHP_URL_HOST) === 'pitfr.example.edu'
                && parse_url($mail->actionUrl, PHP_URL_SCHEME) === 'https';
        });
    }

    public function test_password_recovery_malformed_email_keeps_validation_feedback(): void
    {
        $this->post(route('password.email'), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('status');
    }

    public function test_password_reset_link_updates_password(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'username' => 'reset' . uniqid() . '@test.com',
            'password' => Hash::make('OriginalPassword123!'),
            'remember_token' => 'old-remember-token',
            'email_verified_at' => now(),
        ]);
        $apiToken = $user->createToken('password-recovery-test');
        $this->post(route('password.email'), ['email' => $user->username]);
        $token = null;
        config(['session.driver' => 'database']);
        $sessionId = 'recovery-session-' . uniqid();
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'password-recovery-test',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token): bool {
            $token = $notification->token;
            return true;
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->username,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertNotSame('new-password', $user->password);
        $this->assertFalse(Hash::check('OriginalPassword123!', $user->password));
        $this->assertNotSame('old-remember-token', $user->remember_token);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $apiToken->accessToken->id]);
        $this->assertDatabaseMissing('sessions', ['id' => $sessionId, 'user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', [
            'target_user_id' => $user->id,
            'action' => 'password_recovered',
        ]);
        $audit = AuditLog::where('target_user_id', $user->id)->where('action', 'password_recovered')->firstOrFail();
        $auditContents = json_encode($audit->getAttributes());
        $this->assertStringNotContainsString('new-password', $auditContents);
        $this->assertStringNotContainsString($token, $auditContents);
        $this->assertStringNotContainsString($user->password, $auditContents);
        Notification::assertSentTo($user, PasswordChangedNotification::class);

        $this->from(route('login'))->post(route('login'), [
            'email' => $user->username,
            'password' => 'OriginalPassword123!',
        ])->assertSessionHasErrors('email');

        $this->post(route('login'), [
            'email' => $user->username,
            'password' => 'new-password',
        ])->assertRedirect(route('requestor.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_reset_deletes_only_the_target_users_database_sessions(): void
    {
        Notification::fake();
        config(['session.driver' => 'database']);
        $user = User::factory()->create([
            'username' => 'session-revocation-' . uniqid() . '@test.com',
        ]);
        $otherUser = User::factory()->create([
            'username' => 'unaffected-session-' . uniqid() . '@test.com',
        ]);

        $this->post(route('password.email'), ['email' => $user->username]);
        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token): bool {
            $token = $notification->token;
            return true;
        });

        $targetSessionId = 'target-session-' . uniqid();
        $otherSessionId = 'other-session-' . uniqid();
        foreach ([
            [$targetSessionId, $user->id],
            [$otherSessionId, $otherUser->id],
        ] as [$sessionId, $userId]) {
            DB::table('sessions')->insert([
                'id' => $sessionId,
                'user_id' => $userId,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'password-recovery-test',
                'payload' => '',
                'last_activity' => now()->getTimestamp(),
            ]);
        }

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->username,
            'password' => 'RecoveredPassword123!',
            'password_confirmation' => 'RecoveredPassword123!',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('sessions', ['id' => $targetSessionId]);
        $this->assertDatabaseHas('sessions', ['id' => $otherSessionId, 'user_id' => $otherUser->id]);
    }

    public function test_reset_token_is_hashed_and_cannot_be_reused(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'username' => 'single-use-' . uniqid() . '@test.com',
            'password' => null,
        ]);
        $this->post(route('password.email'), ['email' => $user->username])->assertSessionHas('status');
        $token = null;

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token): bool {
            $token = $notification->token;
            return true;
        });

        $storedToken = DB::table('password_reset_tokens')->where('email', $user->username)->value('token');
        $this->assertNotSame($token, $storedToken);

        $credentials = [
            'token' => $token,
            'email' => $user->username,
            'password' => 'FirstSecurePassword123!',
            'password_confirmation' => 'FirstSecurePassword123!',
        ];
        $this->post(route('password.update'), $credentials)->assertRedirect(route('login'));

        $this->from(route('password.reset', ['token' => $token, 'email' => $user->username]))
            ->post(route('password.update'), [
                ...$credentials,
                'password' => 'SecondSecurePassword123!',
                'password_confirmation' => 'SecondSecurePassword123!',
            ])
            ->assertRedirect(route('password.reset', ['token' => $token, 'email' => $user->username]))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('FirstSecurePassword123!', $user->fresh()->password));
    }

    public function test_expired_password_reset_token_is_rejected(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'username' => 'expired-reset-' . uniqid() . '@test.com',
            'password' => null,
        ]);
        $this->post(route('password.email'), ['email' => $user->username]);
        $token = null;

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token): bool {
            $token = $notification->token;
            return true;
        });

        $this->travel(61)->minutes();
        $this->from(route('password.reset', ['token' => $token, 'email' => $user->username]))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->username,
                'password' => 'ExpiredSecurePassword123!',
                'password_confirmation' => 'ExpiredSecurePassword123!',
            ])
            ->assertSessionHasErrors('email');

        $this->assertNull($user->fresh()->password);
    }

    public function test_invalid_password_reset_token_cannot_establish_password(): void
    {
        $user = User::factory()->create([
            'username' => 'invalid-reset-' . uniqid() . '@test.com',
            'password' => null,
        ]);

        $this->from(route('password.request'))
            ->post(route('password.update'), [
                'token' => 'not-a-valid-reset-token',
                'email' => $user->username,
                'password' => 'InvalidTokenPassword123!',
                'password_confirmation' => 'InvalidTokenPassword123!',
            ])
            ->assertSessionHasErrors('email');

        $this->assertNull($user->fresh()->password);
    }

    public function test_reset_link_issuance_throttle_and_new_token_replacement_are_preserved(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'username' => 'throttled-reset-' . uniqid() . '@test.com',
            'password' => null,
        ]);
        $this->post(route('password.email'), ['email' => $user->username])->assertSessionHas('status');
        $firstToken = null;

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$firstToken): bool {
            $firstToken = $notification->token;
            return true;
        });

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->username])
            ->assertSessionHas('status', $this->genericResetResponse())
            ->assertSessionDoesntHaveErrors('email');
        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);

        $this->travel(61)->seconds();
        $this->post(route('password.email'), ['email' => $user->username])->assertSessionHas('status');
        $tokens = [];
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$tokens): bool {
            $tokens[] = $notification->token;
            return true;
        });
        $this->assertCount(2, $tokens);
        $this->assertNotSame($firstToken, $tokens[1]);

        $this->from(route('password.reset', ['token' => $firstToken, 'email' => $user->username]))
            ->post(route('password.update'), [
                'token' => $firstToken,
                'email' => $user->username,
                'password' => 'ReplacedTokenPassword123!',
                'password_confirmation' => 'ReplacedTokenPassword123!',
            ])
            ->assertSessionHasErrors('email');
        $this->assertNull($user->fresh()->password);

        $this->post(route('password.update'), [
            'token' => $tokens[1],
            'email' => $user->username,
            'password' => 'NewestTokenPassword123!',
            'password_confirmation' => 'NewestTokenPassword123!',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewestTokenPassword123!', $user->fresh()->password));
    }

    public function test_deactivated_passwordless_account_cannot_complete_setup_and_token_is_consumed(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'username' => 'inactive-setup-' . uniqid() . '@test.com',
            'password' => null,
            'is_active' => true,
        ]);
        $this->post(route('password.email'), ['email' => $user->username]);
        $token = null;

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token): bool {
            $token = $notification->token;
            return true;
        });
        $user->update(['is_active' => false]);

        $this->from(route('password.reset', ['token' => $token, 'email' => $user->username]))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->username,
                'password' => 'InactiveSetupPassword123!',
                'password_confirmation' => 'InactiveSetupPassword123!',
            ])
            ->assertSessionHasErrors('email');

        $this->assertNull($user->fresh()->password);
        $this->assertFalse($user->fresh()->is_active);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->username]);
    }

    public function test_deactivated_existing_account_cannot_reset_password(): void
    {
        Notification::fake();
        $originalPassword = 'OriginalSecurePassword123!';
        $user = User::factory()->create([
            'username' => 'inactive-recovery-' . uniqid() . '@test.com',
            'password' => Hash::make($originalPassword),
            'is_active' => true,
        ]);
        $this->post(route('password.email'), ['email' => $user->username]);
        $token = null;

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token): bool {
            $token = $notification->token;
            return true;
        });
        $user->update(['is_active' => false]);

        $this->from(route('password.reset', ['token' => $token, 'email' => $user->username]))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->username,
                'password' => 'InactiveRecoveryPassword123!',
                'password_confirmation' => 'InactiveRecoveryPassword123!',
            ])
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check($originalPassword, $user->fresh()->password));
        $this->assertFalse($user->fresh()->is_active);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->username]);
    }

    public function test_inactive_account_does_not_receive_a_password_reset_token_and_gets_generic_response(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'username' => 'inactive-request-' . uniqid() . '@test.com',
            'is_active' => false,
        ]);

        $this->post(route('password.email'), ['email' => $user->username])
            ->assertSessionHas('status', $this->genericResetResponse())
            ->assertSessionDoesntHaveErrors('email');

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->username]);
        Notification::assertNotSentTo($user, ResetPasswordNotification::class);
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_password_reset_preserves_account_classification_and_ignores_forged_fields(): void
    {
        $accountTypes = [
            ['role' => 'requestor', 'requestor_type' => 'student'],
            ['role' => 'requestor', 'requestor_type' => 'faculty'],
            ['role' => 'requestor', 'requestor_type' => 'staff'],
            ['role' => 'requestor', 'requestor_type' => 'outsider'],
            ['role' => 'custodian-venue', 'requestor_type' => null],
            ['role' => 'custodian-equipment', 'requestor_type' => null],
            ['role' => 'admin', 'requestor_type' => null],
        ];

        foreach ($accountTypes as $index => $classification) {
            $this->travel($index * 61)->seconds();
            $username = 'classification-reset-' . $index . '-' . uniqid() . '@test.com';
            $verifiedAt = now()->subDay()->startOfSecond();
            $googleId = 'google-reset-' . $index . '-' . uniqid();
            $user = User::factory()->create([
                ...$classification,
                'username' => $username,
                'password' => Hash::make('OriginalPassword123!'),
                'google_id' => $googleId,
                'email_verified_at' => $verifiedAt,
                'is_active' => true,
            ]);
            $token = Password::broker()->createToken($user);

            $this->post(route('password.update'), [
                'token' => $token,
                'email' => $username,
                'password' => 'UpdatedPassword123!',
                'password_confirmation' => 'UpdatedPassword123!',
                'user_id' => 999999,
                'role' => 'admin',
                'requestor_type' => 'outsider',
                'is_active' => false,
                'google_id' => 'forged-google-id',
                'email_verified_at' => null,
            ])->assertRedirect(route('login'));

            $user->refresh();
            $this->assertSame($classification['role'], $user->role);
            $this->assertSame($classification['requestor_type'], $user->requestor_type);
            $this->assertTrue($user->is_active);
            $this->assertSame($googleId, $user->google_id);
            $this->assertTrue($user->email_verified_at->equalTo($verifiedAt));
        }
    }

    public function test_reset_token_and_identity_bind_password_change_to_its_account(): void
    {
        $tokenOwner = User::factory()->create([
            'username' => 'token-owner-' . uniqid() . '@test.com',
            'password' => Hash::make('OwnerPassword123!'),
        ]);
        $otherUser = User::factory()->create([
            'username' => 'other-user-' . uniqid() . '@test.com',
            'password' => Hash::make('OtherPassword123!'),
        ]);
        $token = Password::broker()->createToken($tokenOwner);

        $this->from(route('password.request'))->post(route('password.update'), [
            'token' => $token,
            'email' => $otherUser->username,
            'user_id' => $otherUser->id,
            'password' => 'AttackerPassword123!',
            'password_confirmation' => 'AttackerPassword123!',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('OwnerPassword123!', $tokenOwner->fresh()->password));
        $this->assertTrue(Hash::check('OtherPassword123!', $otherUser->fresh()->password));
    }

    public function test_password_reset_audit_failure_rolls_back_password_and_access_revocation(): void
    {
        $user = User::factory()->create([
            'username' => 'audit-rollback-' . uniqid() . '@test.com',
            'password' => Hash::make('OriginalPassword123!'),
        ]);
        $apiToken = $user->createToken('audit-rollback-test');
        $token = Password::broker()->createToken($user);
        AuditLog::creating(static function (): never {
            throw new RuntimeException('Audit insert failed.');
        });

        $this->withoutExceptionHandling();
        try {
            $this->post(route('password.update'), [
                'token' => $token,
                'email' => $user->username,
                'password' => 'UpdatedPassword123!',
                'password_confirmation' => 'UpdatedPassword123!',
            ]);
            $this->fail('The audit failure should abort password reset.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit insert failed.', $exception->getMessage());
        }

        $this->assertTrue(Hash::check('OriginalPassword123!', $user->fresh()->password));
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $apiToken->accessToken->id]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->username]);
    }

    public function test_forgot_password_and_reset_post_routes_are_throttled(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.email'), ['email' => 'throttle-' . $attempt . '@test.com'])
                ->assertSessionHas('status', $this->genericResetResponse());
        }
        $this->post(route('password.email'), ['email' => 'throttle-over-limit@test.com'])
            ->assertStatus(429);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.update'), [
                'token' => 'invalid-token-' . $attempt,
                'email' => 'reset-throttle@test.com',
                'password' => 'UpdatedPassword123!',
                'password_confirmation' => 'UpdatedPassword123!',
            ])->assertSessionHasErrors('email');
        }
        $this->post(route('password.update'), [
            'token' => 'invalid-token-over-limit',
            'email' => 'reset-throttle@test.com',
            'password' => 'UpdatedPassword123!',
            'password_confirmation' => 'UpdatedPassword123!',
        ])->assertStatus(429);
    }

    private function genericResetResponse(): string
    {
        return 'If an active account matches that email address, a password reset link will be sent.';
    }

    private function studentData(): array
    {
        return [
            'first_name' => 'Test',
            'surname' => 'Outsider',
            'office_or_organization' => 'Test Organization',
            'organization_type' => 'External Organization',
            'username' => 'otp' . uniqid() . '@test.com',
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
            'requestor_type' => 'outsider',
        ];
    }

    private function registrationData(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Public',
            'middle_name' => '',
            'surname' => 'Outsider',
            'office_or_organization' => 'External Company',
            'organization_type' => 'Company',
            'username' => 'public-registration-' . uniqid() . '@test.com',
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
        ], $overrides);
    }
}
