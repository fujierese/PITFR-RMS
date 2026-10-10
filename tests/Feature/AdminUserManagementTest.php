<?php

namespace Tests\Feature;

use App\Models\StudentOrganization;
use App\Models\User;
use Database\Seeders\CollegeDepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Notifications\ResetPasswordNotification;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CollegeDepartmentSeeder::class);
    }

    public function test_only_literal_admin_role_can_access_user_management(): void
    {
        $roles = [
            'admin' => ['role' => 'admin'],
            'supply_office' => ['role' => 'supply_office'],
            'facility_admin' => ['role' => 'facility_admin'],
            'student' => ['role' => 'requestor', 'requestor_type' => 'student'],
            'external' => ['role' => 'requestor', 'requestor_type' => 'outsider'],
            'faculty' => ['role' => 'faculty', 'requestor_type' => 'faculty'],
        ];

        foreach ($roles as $name => $attributes) {
            /** @var User $user */
            $user = User::factory()->createOne($attributes);

            $response = $this->actingAs($user)->get(route('admin.users'));

            if ($name === 'admin') {
                $response->assertOk();
                $this->assertSame(1, substr_count($response->getContent(), '+ Add User'));
            } else {
                $response->assertForbidden();
            }
        }
    }

    public function test_add_user_form_filters_departments_and_orders_faculty_position_before_adviser(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.users', ['add_user' => 1]));

        $response->assertOk();
        $response->assertSee('data-college="1"', false);
        $response->assertSee('data-college="2"', false);
        $response->assertSee('Student Organization Representative');
        $response->assertSee('+ Add new student organization');
        $response->assertSee('role="dialog"', false);
        $response->assertSee('aria-modal="true"', false);
        $response->assertSee('max-h-[calc(100vh-1.5rem)]', false);
        $response->assertSee('Close create user dialog');

        $html = $response->getContent();
        $this->assertLessThan(
            strpos($html, '>Faculty adviser</label>'),
            strpos($html, '>Position</label>')
        );
    }

    public function test_edit_user_dialog_uses_role_specific_fields_and_includes_custodian_roles(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $user = User::factory()->createOne([
            'role' => 'custodian-equipment',
            'first_name' => 'Equipment',
            'surname' => 'Custodian',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users', ['edit_user' => $user->id]))
            ->assertOk()
            ->assertSee('Name')
            ->assertSee('Sign-in and role')
            ->assertSee('Password and sign-in access')
            ->assertSee('Venue Custodian')
            ->assertSee('Equipment Custodian')
            ->assertSee('Requestor category')
            ->assertSee('data-edit-field="student"', false)
            ->assertSee('data-edit-field="faculty-adviser"', false)
            ->assertSee('sm:grid-cols-2 lg:grid-cols-3', false)
            ->assertSee('max-h-[calc(100vh-1.5rem)]', false)
            ->assertSee('Save changes');
    }

    public function test_edit_role_menu_uses_current_roles_and_keeps_legacy_role_as_existing_only(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $user = User::factory()->createOne(['role' => 'custodian']);

        $response = $this->actingAs($admin)
            ->get(route('admin.users', ['edit_user' => $user->id]))
            ->assertOk();

        preg_match('/<select name="role"[^>]*>(.*?)<\/select>/s', $response->getContent(), $matches);
        $this->assertCount(2, $matches);
        $roleOptions = $matches[1];
        $this->assertStringContainsString('value="requestor"', $roleOptions);
        $this->assertStringContainsString('value="custodian-venue"', $roleOptions);
        $this->assertStringContainsString('value="custodian-equipment"', $roleOptions);
        $this->assertStringContainsString('Custodian (existing legacy role)', $roleOptions);
        $this->assertStringNotContainsString('value="student"', $roleOptions);
        $this->assertStringNotContainsString('value="facility_admin"', $roleOptions);
        $this->assertStringNotContainsString('value="admin"', $roleOptions);
        $this->assertStringNotContainsString('value="supply_office"', $roleOptions);
    }

    public function test_privileged_account_edit_shows_no_admin_role_choices(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $facilityAdmin = User::factory()->createOne(['role' => 'facility_admin']);

        $response = $this->actingAs($admin)
            ->get(route('admin.users', ['edit_user' => $facilityAdmin->id]))
            ->assertOk()
            ->assertSee('Protected administrator account')
            ->assertSee('The system role and access for this account cannot be changed here.')
            ->assertSee('name="role" value="facility_admin"', false)
            ->assertDontSee('Facility administrator (existing account)');

        preg_match('/<select name="role"[^>]*>/', $response->getContent(), $matches);
        $this->assertSame([], $matches);
    }

    public function test_legacy_roles_cannot_be_assigned_to_another_account(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $user = User::factory()->createOne([
            'role' => 'requestor',
            'requestor_type' => 'student',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'username' => $user->username,
                'role' => 'student',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'requestor',
            'requestor_type' => 'student',
        ]);
    }

    public function test_admin_can_update_and_delete_a_user(): void
    {
        $admin = User::create([
            'username' => 'admin-test-' . uniqid(),
            'password' => Hash::make('password12345'),
            'name' => 'Admin User',
            'role' => 'admin',
        ]);

        $user = User::create([
            'username' => 'requestor-test-' . uniqid(),
            'password' => Hash::make('password12345'),
            'name' => 'Original User',
            'role' => 'requestor',
            'requestor_type' => 'student',
            'department' => 'IT',
        ]);

        $this->actingAs($admin);
        $this->withSession(['_token' => 'test-token']);

        $updateResponse = $this->put(route('admin.users.update', $user), [
            '_token' => 'test-token',
            'name' => 'Updated User',
            'username' => 'updated-user-' . uniqid(),
            'role' => 'custodian-equipment',
            'department' => 'CS',
            'requestor_type' => 'faculty',
            'school_id_number' => '20240001',
            'office_or_organization' => '',
            'contact_number' => '09123456789',
            'password' => 'new-password12345',
            'password_confirmation' => 'new-password12345',
        ]);

        $updateResponse->assertRedirect(route('admin.users'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated User',
            'role' => 'custodian-equipment',
            'requestor_type' => null,
            'school_id_number' => null,
        ]);
        $this->assertTrue(Hash::check('new-password12345', $user->fresh()->password));

        $deleteResponse = $this->delete(route('admin.users.destroy', $user), [
            '_token' => 'test-token',
        ]);

        $deleteResponse->assertRedirect(route('admin.users'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'target_user_id' => $user->id,
            'action' => 'user_deactivated',
        ]);
    }

    public function test_editing_requestor_category_to_faculty_saves_faculty_fields(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $user = User::factory()->createOne([
            'role' => 'requestor',
            'requestor_type' => 'student',
            'school_id_number' => 'OLD-STUDENT-ID',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'username' => $user->username,
                'role' => 'requestor',
                'requestor_type' => 'faculty',
                'faculty_id' => 'FAC-EDIT-123',
                'position' => 'Professor',
                'college_id' => 1,
                'department_id' => 1,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.users'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'requestor',
            'requestor_type' => 'faculty',
            'faculty_id' => 'FAC-EDIT-123',
            'school_id_number' => null,
            'college_id' => 1,
            'department_id' => 1,
        ]);
    }

    public function test_admin_can_create_all_supported_insider_account_types_without_credentials(): void
    {
        Notification::fake();
        $admin = User::factory()->createOne(['role' => 'admin']);
        $this->actingAs($admin);
        $organization = StudentOrganization::create([
            'name' => 'Supported Test Student Organization',
            'is_active' => true,
        ]);

        $accounts = [
            ['account_type' => 'student', 'surname' => 'Student', 'first_name' => 'Admin', 'school_id_number' => '23-0098-635', 'college_id' => 1, 'department_id' => 1, 'student_organization_id' => $organization->id, 'role' => 'admin', 'requestor_type' => 'outsider'],
            ['account_type' => 'faculty', 'name' => 'Admin Faculty', 'faculty_id' => 'FAC-' . uniqid(), 'position' => 'Professor', 'college_id' => 1, 'department_id' => 1, 'role' => 'custodian', 'requestor_type' => 'outsider'],
            ['account_type' => 'staff', 'surname' => 'Staff', 'first_name' => 'Admin', 'position' => 'Administrative Staff', 'role' => 'admin', 'requestor_type' => 'faculty'],
            ['account_type' => 'custodian_venue', 'surname' => 'Venue', 'first_name' => 'Custodian', 'role' => 'admin', 'requestor_type' => 'student'],
            ['account_type' => 'custodian_equipment', 'surname' => 'Equipment', 'first_name' => 'Custodian', 'role' => 'requestor', 'requestor_type' => 'staff'],
        ];

        $expectedMappings = [
            ['role' => 'requestor', 'requestor_type' => 'student'],
            ['role' => 'requestor', 'requestor_type' => 'faculty'],
            ['role' => 'requestor', 'requestor_type' => 'staff'],
            ['role' => 'custodian-venue', 'requestor_type' => null],
            ['role' => 'custodian-equipment', 'requestor_type' => null],
        ];

        foreach ($accounts as $index => $account) {
            $email = "admin-created-{$index}-" . uniqid() . '@test.com';
            $response = $this->post(route('admin.users.store'), $account + [
                'username' => $email,
                'password' => 'BrowserSuppliedPassword123!',
                'google_id' => 'forged-google-id-' . $index,
            ]);

            $response->assertRedirect(route('admin.users'));
            $created = User::where('username', $email)->firstOrFail();
            $this->assertSame($expectedMappings[$index]['role'], $created->role);
            $this->assertSame($expectedMappings[$index]['requestor_type'], $created->requestor_type);
            $this->assertNull($created->password);
            $this->assertNull($created->google_id);
            $this->assertTrue($created->is_active);
            $this->assertNotNull($created->email_verified_at);
            if ($account['account_type'] === 'staff') {
                $this->assertSame('Administrative Staff', $created->position);
                $this->assertNull($created->office_or_organization);
            }
            if (str_starts_with($account['account_type'], 'custodian_')) {
                $this->assertSame($account['first_name'] . ' ' . $account['surname'], $created->name);
                $this->assertSame(
                    $account['account_type'] === 'custodian_venue' ? 'Venue Custodian' : 'Equipment Custodian',
                    $created->position,
                );
            }
            $this->assertDatabaseHas('audit_logs', [
                'actor_id' => $admin->id,
                'target_user_id' => $created->id,
                'action' => 'user_created',
            ]);
            Notification::assertSentTo($created, ResetPasswordNotification::class);
        }
    }

    public function test_admin_created_passwordless_insider_can_set_initial_password_with_existing_reset_link(): void
    {
        Notification::fake();
        $admin = User::factory()->createOne(['role' => 'admin']);
        $email = 'initial-setup-' . uniqid() . '@test.com';

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'account_type' => 'staff',
                'surname' => 'Setup',
                'first_name' => 'Initial',
                'username' => $email,
            ])
            ->assertRedirect(route('admin.users'));

        $user = User::where('username', $email)->firstOrFail();
        $this->assertNull($user->password);
        $this->assertTrue($user->is_active);
        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token): bool {
            $token = $notification->token;
            return true;
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $email,
            'password' => 'InitialSecurePassword123!',
            'password_confirmation' => 'InitialSecurePassword123!',
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('InitialSecurePassword123!', $user->password));
        $this->assertTrue($user->is_active);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $email]);
        $this->assertDatabaseHas('audit_logs', [
            'target_user_id' => $user->id,
            'action' => 'password_setup_completed',
        ]);
        Notification::assertSentTo($user, \App\Notifications\PasswordChangedNotification::class);
    }

    public function test_setup_link_delivery_failure_removes_broker_token(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $email = 'failed-setup-' . uniqid() . '@test.com';
        $resetNotification = null;
        Log::spy();
        Notification::shouldReceive('send')
            ->once()
            ->andReturnUsing(function ($notifiables, $notification) use (&$resetNotification): void {
                $resetNotification = $notification;
                throw new \RuntimeException('Mail transport failed.');
            });

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'account_type' => 'staff',
                'surname' => 'Setup',
                'first_name' => 'Failed',
                'username' => $email,
            ])
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('warning', 'User account created, but the password setup link could not be sent.');

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $email]);
        $user = User::where('username', $email)->firstOrFail();
        $this->assertInstanceOf(ResetPasswordNotification::class, $resetNotification);
        $resetToken = $resetNotification->token;
        $resetUrl = $resetNotification->toMail($user)->actionUrl;
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($resetToken, $resetUrl): bool {
                $loggedData = json_encode([$message, $context], JSON_THROW_ON_ERROR);

                return ! str_contains($loggedData, $resetToken)
                    && ! str_contains($loggedData, $resetUrl);
            });
    }

    public function test_admin_accounts_are_hidden_from_management_views_and_updates(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $this->actingAs($admin);

        $visibleUser = User::factory()->createOne([
            'role' => 'requestor',
            'requestor_type' => 'student',
            'username' => 'visible-user-' . uniqid() . '@test.com',
        ]);
        $hiddenAdmin = User::factory()->createOne([
            'role' => 'admin',
            'username' => 'hidden-admin-' . uniqid() . '@test.com',
        ]);

        $response = $this->get(route('admin.users'));
        $response->assertOk();
        $response->assertSee($visibleUser->username);
        $response->assertDontSee($hiddenAdmin->username);

        $this->withSession(['_token' => 'test-token'])
            ->put(route('admin.users.update', $hiddenAdmin), [
                '_token' => 'test-token',
                'username' => 'blocked-admin-' . uniqid() . '@test.com',
                'role' => 'admin',
            ])->assertRedirect(route('admin.users'));

        $this->assertDatabaseHas('users', ['id' => $hiddenAdmin->id, 'role' => 'admin']);
    }

    public function test_faculty_adviser_requires_a_student_organization(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $this->actingAs($admin);

        $this->withSession(['_token' => 'test-token'])
            ->post(route('admin.users.store'), [
                '_token' => 'test-token',
                'account_type' => 'faculty',
                'surname' => 'Adviser',
                'first_name' => 'Faculty',
                'username' => 'faculty-adviser-' . uniqid() . '@test.com',
                'password' => 'password12345',
                'password_confirmation' => 'password12345',
                'college_id' => 1,
                'department_id' => 1,
                'faculty_id' => 'FAC-' . uniqid(),
                'faculty_adviser' => 'yes',
                'position' => 'Professor',
            ])->assertSessionHasErrors('student_organization_id');

        $organization = StudentOrganization::create([
            'name' => 'Computer Science Society',
            'department_id' => 1,
            'college_id' => 1,
            'is_active' => true,
        ]);

        $this->withSession(['_token' => 'test-token'])
            ->post(route('admin.users.store'), [
                '_token' => 'test-token',
                'account_type' => 'faculty',
                'surname' => 'Adviser',
                'first_name' => 'Faculty',
                'username' => 'faculty-adviser-2-' . uniqid() . '@test.com',
                'password' => 'password12345',
                'password_confirmation' => 'password12345',
                'college_id' => 1,
                'department_id' => 1,
                'faculty_id' => 'FAC-' . uniqid(),
                'faculty_adviser' => 'yes',
                'student_organization_id' => $organization->id,
                'position' => 'Professor',
            ])->assertRedirect(route('admin.users'));
    }

    public function test_admin_can_create_user_with_split_name_components(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $this->actingAs($admin);

        $username = 'maria-' . uniqid() . '@test.com';
        $response = $this->withSession(['_token' => 'test-token'])
            ->post(route('admin.users.store'), [
                '_token' => 'test-token',
                'account_type' => 'student',
                'surname' => 'Dela Cruz',
                'first_name' => 'Maria',
                'middle_name' => 'Santos',
                'suffix' => 'Jr.',
                'username' => $username,
                'password' => 'password12345',
                'password_confirmation' => 'password12345',
                'college_id' => 1,
                'department_id' => 1,
                'school_id_number' => '23-0098-635',
                'contact_number' => '09123456789',
                'student_organization_id' => '__new__',
                'new_student_organization_name' => 'Maria Dela Cruz Student Council',
                'new_student_organization_acronym' => 'MDSC',
            ]);

        $response->assertRedirect(route('admin.users'));

        $created = User::where('username', $username)->firstOrFail();
        $this->assertSame('Maria Santos Dela Cruz Jr.', $created->name);
        $this->assertSame('Dela Cruz', $created->surname);
        $this->assertSame('Maria', $created->first_name);
        $this->assertSame('Santos', $created->middle_name);
        $this->assertSame('Jr.', $created->suffix);
        $organization = StudentOrganization::where('name', 'Maria Dela Cruz Student Council')->firstOrFail();
        $this->assertSame('MDSC', $organization->acronym);
        $this->assertSame(1, $organization->college_id);
        $this->assertSame(1, $organization->department_id);
        $this->assertDatabaseHas('student_organization_members', [
            'user_id' => $created->id,
            'student_organization_id' => $organization->id,
            'membership_role' => 'Member',
        ]);
    }

    public function test_staff_account_requires_person_name_and_stores_optional_office_and_position(): void
    {
        Notification::fake();
        $admin = User::factory()->createOne(['role' => 'admin']);
        $this->actingAs($admin);

        $this->from(route('admin.users', ['add_user' => 1]))
            ->post(route('admin.users.store'), [
                'account_type' => 'staff',
                'username' => 'missing-staff-name-' . uniqid() . '@test.com',
            ])
            ->assertRedirect(route('admin.users', ['add_user' => 1]))
            ->assertSessionHasErrors(['surname', 'first_name']);

        $email = 'staff-profile-' . uniqid() . '@test.com';
        $this->post(route('admin.users.store'), [
            'account_type' => 'staff',
            'surname' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'username' => $email,
            'position' => 'Administrative Assistant',
            'office_or_organization' => 'Registrar Office',
            'contact_number' => '09123456789',
        ])->assertRedirect(route('admin.users'));

        $staff = User::where('username', $email)->firstOrFail();
        $this->assertSame('Juan Santos Dela Cruz', $staff->name);
        $this->assertSame('staff', $staff->requestor_type);
        $this->assertSame('Administrative Assistant', $staff->position);
        $this->assertSame('Registrar Office', $staff->office_or_organization);
        $this->assertSame('09123456789', $staff->contact_number);

        $this->view('components.dashboard-sidebar', ['user' => $staff])
            ->assertSee('Juan Santos Dela Cruz')
            ->assertSee('Staff · Registrar Office — Administrative Assistant');
    }

    public function test_custodian_accounts_require_person_name_and_use_role_based_positions(): void
    {
        Notification::fake();
        $admin = User::factory()->createOne(['role' => 'admin']);
        $this->actingAs($admin);

        foreach ([
            'custodian_venue' => ['surname' => 'Venue', 'first_name' => 'Custodian', 'position' => 'Forged Position', 'expected_position' => 'Venue Custodian'],
            'custodian_equipment' => ['surname' => 'Equipment', 'first_name' => 'Custodian', 'position' => 'Forged Position', 'expected_position' => 'Equipment Custodian'],
        ] as $accountType => $details) {
            $this->from(route('admin.users', ['add_user' => 1]))
                ->post(route('admin.users.store'), [
                    'account_type' => $accountType,
                    'username' => "missing-name-{$accountType}-" . uniqid() . '@test.com',
                ])
                ->assertRedirect(route('admin.users', ['add_user' => 1]))
                ->assertSessionHasErrors(['surname', 'first_name']);

            $email = "custodian-{$accountType}-" . uniqid() . '@test.com';
            $this->post(route('admin.users.store'), [
                'account_type' => $accountType,
                'surname' => $details['surname'],
                'first_name' => $details['first_name'],
                'username' => $email,
                'position' => $details['position'],
            ])->assertRedirect(route('admin.users'));

            $custodian = User::where('username', $email)->firstOrFail();
            $this->assertSame($details['first_name'] . ' ' . $details['surname'], $custodian->name);
            $this->assertSame($details['surname'], $custodian->surname);
            $this->assertSame($details['first_name'], $custodian->first_name);
            $this->assertSame($details['expected_position'], $custodian->position);
            $this->assertNull($custodian->contact_number);
        }
    }

    public function test_student_representative_requires_person_name_and_explicit_organization(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $this->actingAs($admin)
            ->from(route('admin.users', ['add_user' => 1]))
            ->post(route('admin.users.store'), [
                'account_type' => 'student',
                'username' => 'missing-student-profile-' . uniqid() . '@test.com',
                'college_id' => 1,
                'department_id' => 1,
                'school_id_number' => '23-0098-635',
            ])
            ->assertRedirect(route('admin.users', ['add_user' => 1]))
            ->assertSessionHasErrors(['surname', 'first_name', 'student_organization_id']);
    }

    public function test_admin_can_create_student_requestor_with_position_and_organization_membership(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $this->actingAs($admin);

        $organization = StudentOrganization::create([
            'name' => 'Computer Science Society',
            'is_active' => true,
        ]);

        $response = $this->withSession(['_token' => 'test-token'])
            ->post(route('admin.users.store'), [
                '_token' => 'test-token',
                'account_type' => 'student',
                'surname' => 'Officer',
                'first_name' => 'Student',
                'username' => 'student-officer-' . uniqid() . '@test.com',
                'password' => 'password12345',
                'password_confirmation' => 'password12345',
                'college_id' => 1,
                'department_id' => 1,
                'school_id_number' => '23-0098-635',
                'position' => 'Student Council Officer',
                'student_organization_id' => $organization->id,
                'contact_number' => '09123456789',
            ]);

        $response->assertRedirect(route('admin.users'));

        $created = User::where('username', 'like', 'student-officer-%@test.com')->firstOrFail();
        $this->assertSame('Student Council Officer', $created->position);
        $this->assertDatabaseHas('student_organization_members', [
            'user_id' => $created->id,
            'student_organization_id' => $organization->id,
            'is_active' => true,
        ]);
    }

    public function test_student_requestor_source_of_truth_uses_registered_organization_in_request_flow(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $student = User::factory()->createOne([
            'role' => 'requestor',
            'requestor_type' => 'student',
            'name' => 'Student Org Member',
            'position' => 'Student Council Officer',
            'department_id' => 1,
            'college_id' => 1,
        ]);
        $organization = StudentOrganization::create([
            'name' => 'BITS Student Council',
            'is_active' => true,
        ]);

        $student->organizationMemberships()->create([
            'student_organization_id' => $organization->id,
            'membership_role' => 'President',
            'can_submit_requests' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($student)
            ->get(route('requestor.index', ['tab' => 'create']));

        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('BITS Student Council', $html);
        $this->assertStringNotContainsString('Department not provided', $html);
    }

    public function test_non_admin_roles_cannot_create_accounts(): void
    {
        foreach ([
            ['role' => 'requestor', 'requestor_type' => 'student'],
            ['role' => 'requestor', 'requestor_type' => 'outsider'],
            ['role' => 'requestor', 'requestor_type' => 'faculty'],
            ['role' => 'requestor', 'requestor_type' => 'student_organization'],
        ] as $attributes) {
            $this->actingAs(User::factory()->createOne($attributes))
                ->post(route('admin.users.store'), [
                    'account_type' => 'faculty',
                    'name' => 'Unauthorized User',
                    'username' => uniqid() . '@test.com',
                    'password' => 'password12345',
                    'password_confirmation' => 'password12345',
                ])->assertForbidden();
        }
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin', 'username' => 'existing@example.com']);

        $this->actingAs($admin)
            ->from(route('admin.users', ['add_user' => 1]))
            ->post(route('admin.users.store'), [
                'account_type' => 'faculty',
                'name' => 'Duplicate Email',
                'username' => 'existing@example.com',
                'password' => 'password12345',
                'password_confirmation' => 'password12345',
            ])->assertSessionHasErrors('username');
    }

    public function test_duplicate_username_attempt_does_not_mutate_existing_account(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $existing = User::factory()->createOne([
            'username' => 'protected-existing-' . uniqid() . '@test.com',
            'name' => 'Existing Profile',
            'role' => 'requestor',
            'requestor_type' => 'outsider',
            'password' => Hash::make('ExistingPassword123!'),
            'google_id' => 'existing-google-id',
            'is_active' => false,
            'office_or_organization' => 'Existing Organization',
        ]);
        $original = $existing->only([
            'name',
            'role',
            'requestor_type',
            'password',
            'google_id',
            'is_active',
            'office_or_organization',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'account_type' => 'staff',
                'surname' => 'Replacement',
                'first_name' => 'Profile',
                'username' => $existing->username,
                'role' => 'admin',
                'requestor_type' => 'student',
                'password' => 'AttackerChosenPassword123!',
                'google_id' => 'attacker-google-id',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('username');

        $this->assertSame($original, $existing->fresh()->only(array_keys($original)));
    }

    public function test_faculty_creation_requires_faculty_id(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'account_type' => 'faculty',
                'name' => 'Faculty Without ID',
                'username' => 'faculty-no-id-' . uniqid() . '@test.com',
                'college_id' => 1,
                'department_id' => 1,
                'password' => 'password12345',
                'password_confirmation' => 'password12345',
            ])->assertSessionHasErrors('faculty_id');
    }

    public function test_admin_cannot_provision_outsider_or_other_unsupported_account_types(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['outsider', 'admin', 'student_organization', 'invalid'] as $accountType) {
            $this->actingAs($admin)
                ->post(route('admin.users.store'), [
                    'account_type' => $accountType,
                    'name' => 'Unsupported Account',
                    'username' => 'unsupported-' . uniqid() . '@test.com',
                ])
                ->assertSessionHasErrors('account_type');
        }
    }

    public function test_admin_cannot_deactivate_or_demote_themselves(): void
    {
        $admin = User::factory()->createOne([
            'role' => 'admin',
            'name' => 'Self Protect Admin',
            'username' => 'self-protect-admin-' . uniqid() . '@test.com',
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        $this->put(route('admin.users.update', $admin), [
            'name' => 'Self Protect Admin',
            'username' => $admin->username,
            'role' => 'requestor',
            'department' => 'IT',
            'requestor_type' => 'student',
            'contact_number' => '09123456789',
            'is_active' => '0',
        ])->assertRedirect(route('admin.users'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin', 'is_active' => true]);

        $this->delete(route('admin.users.destroy', $admin))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_active' => true]);
    }

    public function test_admin_cannot_promote_anyone_to_admin_role(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $user = User::factory()->createOne([
            'name' => 'Normal User',
            'username' => 'normal-user-' . uniqid() . '@test.com',
            'role' => 'requestor',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => 'Normal User',
                'username' => $user->username,
                'role' => 'admin',
                'requestor_type' => 'student',
                'department' => 'IT',
            ])
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'requestor']);
    }

    public function test_user_management_actions_are_logged_for_admin_audit(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $user = User::factory()->createOne([
            'name' => 'Audit Target',
            'username' => 'audit-target-' . uniqid() . '@test.com',
            'role' => 'requestor',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => 'Audit Target Updated',
                'username' => $user->username,
                'role' => 'requestor',
                'requestor_type' => 'student',
                'department' => 'IT',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'target_user_id' => $user->id,
            'action' => 'user_updated',
        ]);
    }

    public function test_admin_can_reactivate_deactivated_user(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $user = User::factory()->createOne([
            'name' => 'Old Employee',
            'username' => 'reactivate-user-' . uniqid() . '@test.com',
            'is_active' => false,
            'role' => 'requestor',
        ]);

        $this->actingAs($admin)
            ->post(route('supply-office.users.reactivate', $user))
            ->assertRedirect(route('admin.users'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => true]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'target_user_id' => $user->id,
            'action' => 'user_reactivated',
        ]);
    }

    public function test_admin_cannot_change_privileged_targets_role_or_status_using_direct_requests(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $targets = [];
        foreach (['admin', 'facility_admin', 'supply_office'] as $role) {
            $targets[] = User::factory()->createOne([
                'role' => $role,
                'is_active' => true,
                'username' => $role . '-protected-' . uniqid() . '@test.com',
            ]);
        }

        $this->actingAs($admin);
        config(['session.driver' => 'database']);
        $tokens = [];
        $sessionIds = [];

        foreach ($targets as $target) {
            $auditCount = \App\Models\AuditLog::count();
            $tokens[$target->id] = $target->createToken('protected-account')->accessToken;
            $sessionIds[$target->id] = 'protected-session-' . $target->id;
            \Illuminate\Support\Facades\DB::table('sessions')->insert([
                'id' => $sessionIds[$target->id],
                'user_id' => $target->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test',
                'payload' => base64_encode(serialize([])),
                'last_activity' => time(),
            ]);

            $this->delete(route('admin.users.destroy', $target))
                ->assertRedirect(route('admin.users'))
                ->assertSessionHas('error');

            $this->put(route('admin.users.update', $target), [
                'name' => $target->name,
                'username' => $target->username,
                'role' => 'requestor',
                'requestor_type' => 'outsider',
                'is_active' => '0',
            ])->assertRedirect(route('admin.users'))
                ->assertSessionHas('error');

            $this->assertDatabaseHas('users', [
                'id' => $target->id,
                'role' => $target->role,
                'is_active' => true,
            ]);
            $this->assertSame($auditCount, \App\Models\AuditLog::count());
            $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokens[$target->id]->id]);
            $this->assertDatabaseHas('sessions', ['id' => $sessionIds[$target->id], 'user_id' => $target->id]);
        }
    }

    public function test_normal_insider_and_outsider_accounts_remain_managed_through_existing_routes(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $accounts = [
            ['role' => 'requestor', 'requestor_type' => 'student'],
            ['role' => 'requestor', 'requestor_type' => 'faculty'],
            ['role' => 'requestor', 'requestor_type' => 'staff'],
            ['role' => 'requestor', 'requestor_type' => 'outsider'],
            ['role' => 'custodian-venue', 'requestor_type' => null],
            ['role' => 'custodian-equipment', 'requestor_type' => null],
        ];

        $this->actingAs($admin);
        foreach ($accounts as $index => $attributes) {
            $user = User::factory()->createOne($attributes + [
                'username' => 'phase-four-account-' . $index . '-' . uniqid() . '@test.com',
                'is_active' => true,
            ]);

            $this->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'requestor_type' => $user->requestor_type,
                'is_active' => '1',
            ])->assertRedirect(route('admin.users'));

            $this->delete(route('admin.users.destroy', $user))
                ->assertRedirect(route('admin.users'));
            $this->assertFalse($user->fresh()->is_active);

            $this->post(route('admin.users.reactivate', $user))
                ->assertRedirect(route('admin.users'));
            $this->assertTrue($user->fresh()->is_active);
        }
    }

    public function test_admin_cannot_reactivate_a_privileged_target(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $target = User::factory()->createOne([
            'role' => 'supply_office',
            'is_active' => false,
            'username' => 'inactive-privileged-' . uniqid() . '@test.com',
        ]);
        $auditCount = \App\Models\AuditLog::count();

        $this->actingAs($admin)
            ->post(route('supply-office.users.reactivate', $target))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'supply_office', 'is_active' => false]);
        $this->assertSame($auditCount, \App\Models\AuditLog::count());
    }

    public function test_admin_user_search_filters_by_name_only(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        User::factory()->createOne([
            'name' => 'Alice Example',
            'first_name' => 'Alice',
            'surname' => 'Example',
            'username' => 'alice@example.com',
            'role' => 'requestor',
        ]);
        User::factory()->createOne([
            'name' => 'Bob Example',
            'first_name' => 'Bob',
            'surname' => 'Example',
            'username' => 'bob@example.com',
            'role' => 'requestor',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.users', ['search' => 'Alice']));

        $response->assertOk();
        $response->assertSee('Alice Example');
        $response->assertDontSee('Bob Example');
    }
}
