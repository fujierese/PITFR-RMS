<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Department;
use App\Models\StudentOrganization;
use App\Models\User;
use Database\Seeders\CollegeDepartmentSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class RequestFormFeedbackTest extends TestCase
{
    public function test_request_form_shows_outside_organization_guidance_and_summary(): void
    {
        $user = new User([
            'id' => 1,
            'role' => 'requestor',
            'requestor_type' => 'outsider',
            'name' => 'Juan Dela Cruz',
            'department' => 'Private Citizen',
        ]);

        $html = view('requestor.partials.request_form', [
            'controlNumber' => 'FER-2026-001',
            'venueOptions' => ['Gymnasium'],
            'equipment' => collect(),
            'errors' => new \Illuminate\Support\ViewErrorBag(),
            'requestorType' => 'outsider',
        ])->render();

        $this->assertStringContainsString('Outsider Guidance', $html);
        $this->assertStringContainsString('Applicable rental fees and payment procedures', $html);
        $this->assertStringContainsString('Selected Items Summary', $html);
        $this->assertStringContainsString('Request Progress', $html);
        $this->assertStringContainsString('Draft autosave', $html);
        $this->assertStringContainsString('Official Request Form', $html);
        $this->assertStringContainsString('Submission checklist', $html);
        $this->assertStringNotContainsString('requested-priority', $html);
        $this->assertStringNotContainsString('name="priority"', $html);
        $this->assertStringNotContainsString('Requested Importance', $html);
    }

    public function test_request_form_shows_default_equipment_options_when_database_is_empty(): void
    {
        $html = view('requestor.partials.request_form', [
            'controlNumber' => 'FER-2026-001',
            'venueOptions' => ['Gymnasium'],
            'equipment' => collect(),
            'errors' => new \Illuminate\Support\ViewErrorBag(),
            'requestorType' => 'outsider',
        ])->render();

        $this->assertStringContainsString('Sound System', $html);
        $this->assertStringContainsString('Wireless Microphones', $html);
        $this->assertStringContainsString('Non-Wireless Microphones', $html);
        $this->assertStringContainsString('Monobloc Chairs', $html);
        $this->assertStringNotContainsString('value="Wireless Microphone"', $html);
        $this->assertStringNotContainsString('value="Non-wireless Microphone"', $html);
        $this->assertStringNotContainsString('value="Monobloc chairs"', $html);
        $this->assertStringNotContainsString('value="Chairs"', $html);
    }

    public function test_student_form_displays_trusted_student_organization_as_the_source_of_truth(): void
    {
        $this->seed(CollegeDepartmentSeeder::class);

        $collegeId = \App\Models\College::query()->value('id') ?? 1;
        $departmentId = \App\Models\Department::query()->value('id') ?? 1;

        $student = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
            'name' => 'Student Leader',
            'position' => 'President',
            'department' => 'Computer Science',
            'department_id' => $departmentId,
            'college_id' => $collegeId,
        ]);

        $org = StudentOrganization::create([
            'name' => 'BITS Student Council',
            'is_active' => true,
        ]);

        $student->organizationMemberships()->create([
            'student_organization_id' => $org->id,
            'membership_role' => 'President',
            'can_submit_requests' => true,
            'is_active' => true,
        ]);

        Auth::login($student);

        $html = view('requestor.partials.request_form', [
            'controlNumber' => 'FER-2026-001',
            'venueOptions' => ['Gymnasium'],
            'equipment' => collect(),
            'errors' => new \Illuminate\Support\ViewErrorBag(),
            'requestorType' => 'student',
        ])->render();

        $this->assertStringContainsString('Student Organization', $html);
        $this->assertStringContainsString('BITS Student Council', $html);
        $this->assertStringNotContainsString('Department / Requisitioning Office', $html);
    }
}
