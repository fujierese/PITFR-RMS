<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

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
}
