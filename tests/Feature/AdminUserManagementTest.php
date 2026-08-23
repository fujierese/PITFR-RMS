<?php

namespace Tests\Feature;

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

    public function test_only_system_admin_can_access_user_management(): void
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

            if ($name === 'admin') {
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

        $deleteResponse = $this->delete(route('admin.users.destroy', $user));

        $deleteResponse->assertRedirect(route('admin.users'));
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_can_create_all_supported_account_types(): void
    {
        $admin = User::factory()->createOne(['role' => 'admin']);
        $this->actingAs($admin);

        $accounts = [
            ['account_type' => 'student', 'name' => 'Admin Student', 'school_id_number' => '23-0098-635', 'college_id' => 1, 'department_id' => 1],
            ['account_type' => 'outsider', 'name' => 'Admin Outsider', 'office_or_organization' => 'External Company'],
            ['account_type' => 'faculty', 'name' => 'Admin Faculty', 'faculty_id' => 'FAC-' . uniqid(), 'college_id' => 1, 'department_id' => 1],
            ['account_type' => 'student_organization', 'name' => 'Admin Organization', 'office_or_organization' => 'PIT Student Council'],
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
        $this->assertDatabaseHas('users', [
            'requestor_type' => 'student_organization',
            'office_or_organization' => 'PIT Student Council',
        ]);
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
}
