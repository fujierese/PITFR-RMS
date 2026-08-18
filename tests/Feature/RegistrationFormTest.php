<?php

namespace Tests\Feature;

use Database\Seeders\CollegeDepartmentSeeder;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RegistrationFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CollegeDepartmentSeeder::class);
    }

    /**
     * Test registration page displays without Faculty option
     */
    public function test_registration_page_shows_only_student_and_external(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk()
            ->assertSee('Student')
            ->assertSee('External / Org')
            ->assertDontSee('Faculty');
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
            ->assertSee('College of Engineering');
    }

    /**
     * Test student registration with valid data
     */
    public function test_student_can_register_with_valid_student_id_format(): void
    {
        $response = $this->post(route('register.post'), [
            'first_name' => 'John',
            'middle_name' => 'Michael',
            'last_name' => 'Doe',
            'username' => 'johndoe' . uniqid() . '@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'requestor_type' => 'student',
            'college_id' => 1,
            'department_id' => 1,
            'school_id_number' => '23-0098-635',
            'contact_number' => '09171234567',
        ]);

        $response->assertRedirect(route('requestor.index'));
        $this->assertAuthenticated();
    }

    /**
     * Test student registration fails with invalid student ID format
     */
    public function test_student_registration_fails_with_invalid_student_id_format(): void
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

        $response->assertSessionHasErrors('school_id_number');
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
            'office_or_organization' => 'External Company',
            'contact_number' => '09171234567',
        ]);

        $response->assertRedirect(route('requestor.index'));
        $this->assertAuthenticated();
    }

    /**
     * Test student registration requires college and department
     */
    public function test_student_registration_requires_college_and_department(): void
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

        $response->assertSessionHasErrors(['college_id', 'department_id']);
    }

    /**
     * Test Faculty type is not allowed in registration
     */
    public function test_faculty_type_is_rejected_in_registration(): void
    {
        $response = $this->post(route('register.post'), [
            'first_name' => 'Faculty',
            'middle_name' => '',
            'last_name' => 'Member',
            'username' => 'faculty' . uniqid() . '@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'requestor_type' => 'faculty',
            'college_id' => 1,
            'department_id' => 1,
            'contact_number' => '09171234567',
        ]);

        $response->assertSessionHasErrors('requestor_type');
    }
}
