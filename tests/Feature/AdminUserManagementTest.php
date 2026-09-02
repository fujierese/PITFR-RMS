<?php

namespace Tests\Feature;

use App\Models\StudentOrganization;
use App\Models\User;
use Database\Seeders\CollegeDepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CollegeDepartmentSeeder::class);
    }

    public function test_combined_admin_role_can_access_user_management(): void
    {
        $roles = [
            'admin' => ['role' => 'admin'],
            'supply_office' => ['role' => 'supply_office'],
            'student' => ['role' => 'requestor', 'requestor_type' => 'student'],
            'external' => ['role' => 'requestor', 'requestor_type' => 'outsider'],
            'faculty' => ['role' => 'faculty', 'requestor_type' => 'faculty'],
        ];

        foreach ($roles as $name => $attributes) {
            /** @var User $user */
            $user = User::factory()->createOne($attributes);

            $response = $this->actingAs($user)->get(route('admin.users'));

            if (in_array($name, ['admin', 'supply_office'], true)) {
                $response->assertOk();
                $this->assertSame(1, substr_count($response->getContent(), '+ Add User'));
            } else {
                $response->assertForbidden();
            }
        }
    }

    public function test_admin_can_update_and_delete_a_user(): void
    {
        $admin = User::create([
            'username' => 'admin-test-' . uniqid(),
            'password' => Hash::make('password123'),
            'name' => 'Admin User',
            'role' => 'admin',
        ]);

        $user = User::create([
            'username' => 'requestor-test-' . uniqid(),
            'password' => Hash::make('password123'),
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
        ]);

        $updateResponse->assertRedirect(route('admin.users'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated User',
            'role' => 'custodian-equipment',
        ]);

        $deleteResponse = $this->delete(route('admin.users.destroy', $user), [
            '_token' => 'test-token',
        ]);

        $deleteResponse->assertRedirect(route('admin.users'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
    }

    public function test_admin_can_create_all_supported_account_types(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $this->actingAs($admin);

        $accounts = [
            ['account_type' => 'student', 'name' => 'Admin Student', 'school_id_number' => '23-0098-635', 'college_id' => 1, 'department_id' => 1],
            ['account_type' => 'outsider', 'name' => 'Admin Outsider', 'office_or_organization' => 'External Company'],
            ['account_type' => 'faculty', 'name' => 'Admin Faculty', 'faculty_id' => 'FAC-' . uniqid(), 'position' => 'Professor', 'college_id' => 1, 'department_id' => 1],
        ];

        foreach ($accounts as $index => $account) {
            $email = "admin-created-{$index}-" . uniqid() . '@test.com';
            $response = $this->post(route('admin.users.store'), $account + [
                'username' => $email,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

            $response->assertRedirect(route('admin.users'));
            $created = User::where('username', $email)->firstOrFail();
            $this->assertSame('requestor', $created->role);
            $this->assertTrue(Hash::check('password123', $created->password));
            $this->assertNotSame('password123', $created->password);
            $this->assertNotNull($created->email_verified_at);
        }

        $this->assertDatabaseHas('users', ['requestor_type' => 'student']);
        $this->assertDatabaseHas('users', ['requestor_type' => 'faculty']);
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
                'password' => 'password123',
                'password_confirmation' => 'password123',
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
                'password' => 'password123',
                'password_confirmation' => 'password123',
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
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'college_id' => 1,
                'department_id' => 1,
                'school_id_number' => '23-0098-635',
                'contact_number' => '09123456789',
            ]);

        $response->assertRedirect(route('admin.users'));

        $created = User::where('username', $username)->firstOrFail();
        $this->assertSame('Maria Santos Dela Cruz Jr.', $created->name);
        $this->assertSame('Dela Cruz', $created->surname);
        $this->assertSame('Maria', $created->first_name);
        $this->assertSame('Santos', $created->middle_name);
        $this->assertSame('Jr.', $created->suffix);
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
                'password' => 'password123',
                'password_confirmation' => 'password123',
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
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
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
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])->assertSessionHasErrors('username');
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
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])->assertSessionHasErrors('faculty_id');
    }

    public function test_admin_cannot_provision_student_organization_account_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'account_type' => 'student_organization',
                'name' => 'Organization Account',
                'username' => 'organization-' . uniqid() . '@test.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'office_or_organization' => 'PIT Student Council',
            ])
            ->assertSessionHasErrors('account_type');
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
        ])->assertRedirect(route('admin.users'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);

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
