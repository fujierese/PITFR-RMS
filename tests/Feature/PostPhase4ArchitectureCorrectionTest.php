<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\StudentOrganization;
use App\Models\StudentOrganizationMember;
use App\Models\User;
use App\Models\Equipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PostPhase4ArchitectureCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $custodian = User::factory()->create(['role' => 'admin']);
        Equipment::factory()->create([
            'name' => 'Sound System',
            'custodian_id' => $custodian->id,
        ]);
    }

    public function test_public_registration_rejects_all_institutional_classifications(): void
    {
        foreach (['student', 'faculty', 'student_organization'] as $type) {
            $username = $type . '-' . uniqid() . '@test.com';

            $this->post(route('register.post'), [
                'username' => $username,
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'requestor_type' => $type,
                'contact_person' => 'Forged Account',
                'office_or_organization' => 'Forged Organization',
            ])->assertSessionHasErrors('requestor_type');

            $this->assertDatabaseMissing('users', ['username' => $username]);
        }
    }

    public function test_student_organization_request_requires_active_membership(): void
    {
        $student = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $organization = StudentOrganization::create([
            'name' => 'BITS',
            'category' => 'Program-Based',
            'is_active' => true,
        ]);

        $this->actingAs($student)->post(route('requestor.store'), $this->requestPayload([
            'request_context' => 'student_organization',
            'student_organization_id' => $organization->id,
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100),
        ]))->assertSessionHasErrors('student_organization_id');

        StudentOrganizationMember::create([
            'user_id' => $student->id,
            'student_organization_id' => $organization->id,
            'membership_role' => 'Secretary',
            'can_submit_requests' => true,
            'is_active' => true,
        ]);

        $this->actingAs($student)->post(route('requestor.store'), $this->requestPayload([
            'request_context' => 'student_organization',
            'student_organization_id' => $organization->id,
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100),
        ]))->assertRedirect();

        $request = FacilityRequest::where('requested_by_id', $student->id)->firstOrFail();
        $this->assertSame('student', $student->fresh()->requestor_type);
        $this->assertSame('student_organization', $request->request_context);
        $this->assertSame($organization->id, $request->student_organization_id);
        $this->assertSame('BITS', $request->organization_name);
    }

    public function test_inactive_membership_cannot_create_new_organization_request(): void
    {
        $student = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $organization = StudentOrganization::create(['name' => 'ML Organization', 'is_active' => true]);
        StudentOrganizationMember::create([
            'user_id' => $student->id,
            'student_organization_id' => $organization->id,
            'membership_role' => 'Member',
            'can_submit_requests' => false,
            'is_active' => false,
        ]);

        $this->actingAs($student)->post(route('requestor.store'), $this->requestPayload([
            'request_context' => 'student_organization',
            'student_organization_id' => $organization->id,
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100),
        ]))->assertSessionHasErrors('student_organization_id');

        $this->assertDatabaseCount('facility_requests', 0);
    }

    public function test_active_member_without_request_authority_cannot_create_organization_request(): void
    {
        $student = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $organization = StudentOrganization::create(['name' => 'Scholars Organization', 'is_active' => true]);
        StudentOrganizationMember::create([
            'user_id' => $student->id,
            'student_organization_id' => $organization->id,
            'membership_role' => 'Member',
            'can_submit_requests' => false,
            'is_active' => true,
        ]);

        $this->actingAs($student)->post(route('requestor.store'), $this->requestPayload([
            'request_context' => 'student_organization',
            'student_organization_id' => $organization->id,
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100),
        ]))->assertSessionHasErrors('student_organization_id');
    }

    public function test_inactive_organization_cannot_be_used_even_with_authorized_membership(): void
    {
        $student = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $organization = StudentOrganization::create(['name' => 'Inactive Organization', 'is_active' => false]);
        StudentOrganizationMember::create([
            'user_id' => $student->id,
            'student_organization_id' => $organization->id,
            'membership_role' => 'Secretary',
            'can_submit_requests' => true,
            'is_active' => true,
        ]);

        $this->actingAs($student)->post(route('requestor.store'), $this->requestPayload([
            'request_context' => 'student_organization',
            'student_organization_id' => $organization->id,
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100),
        ]))->assertSessionHasErrors('student_organization_id');
    }

    public function test_student_personal_request_keeps_student_identity_and_requires_activity_proposal(): void
    {
        $student = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);

        $this->actingAs($student)->post(route('requestor.store'), $this->requestPayload([
            'request_context' => 'personal',
        ]))->assertSessionHasErrors('activity_proposal_file');

        $this->actingAs($student)->post(route('requestor.store'), $this->requestPayload([
            'request_context' => 'personal',
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100),
        ]))->assertRedirect();

        $this->assertSame('student', $student->fresh()->requestor_type);
        $this->assertDatabaseHas('facility_requests', [
            'requested_by_id' => $student->id,
            'request_context' => 'personal',
            'student_organization_id' => null,
        ]);
    }

    public function test_venue_only_personal_request_still_requires_activity_proposal(): void
    {
        $student = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);

        $this->actingAs($student)->post(route('requestor.store'), $this->requestPayload([
            'request_context' => 'personal',
            'equipment' => [],
            'equipment_quantities' => [],
        ]))->assertSessionHasErrors('activity_proposal_file');
    }

    public function test_external_partner_with_valid_documents_can_submit_request(): void
    {
        $outsider = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'outsider',
            'office_or_organization' => 'External Partner Organization',
        ]);

        $this->actingAs($outsider)->post(route('requestor.store'), $this->requestPayload([
            'request_context' => 'outside_organization',
            'igp_receipt_file' => UploadedFile::fake()->create('receipt.pdf', 100),
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('facility_requests', [
            'requested_by_id' => $outsider->id,
            'request_context' => 'outside_organization',
            'organization_name' => 'External Partner Organization',
        ]);
    }

    public function test_faculty_personal_request_keeps_faculty_identity_and_requires_activity_proposal(): void
    {
        $faculty = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'faculty']);

        $this->actingAs($faculty)->post(route('requestor.store'), $this->requestPayload([
            'request_context' => 'personal',
        ]))->assertSessionHasErrors('activity_proposal_file');

        $this->actingAs($faculty)->post(route('requestor.store'), $this->requestPayload([
            'request_context' => 'personal',
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100),
        ]))->assertRedirect();

        $this->assertSame('faculty', $faculty->fresh()->requestor_type);
        $this->assertDatabaseHas('facility_requests', [
            'requested_by_id' => $faculty->id,
            'request_context' => 'personal',
        ]);
    }

    public function test_only_admin_can_manage_organizations_and_membership_authority_is_explicit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);

        $this->actingAs($student)
            ->get(route('supply-office.organizations'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('supply-office.organizations.store'), [
                'name' => 'BITS',
                'acronym' => 'BITS',
                'organization_type' => 'Program-Based',
                'adviser' => 'PIT Adviser',
            ])
            ->assertRedirect();

        $organization = StudentOrganization::where('name', 'BITS')->firstOrFail();
        $this->actingAs($admin)
            ->post(route('supply-office.organizations.memberships.store', $organization), [
                'user_id' => $student->id,
                'membership_role' => 'Member',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('student_organization_members', [
            'user_id' => $student->id,
            'student_organization_id' => $organization->id,
            'can_submit_requests' => false,
        ]);
    }

    private function requestPayload(array $overrides = []): array
    {
        return array_merge([
            'department' => 'Computer Science',
            'name_of_activity' => 'Private or organization event',
            'purpose' => 'Architecture correction test',
            'expected_participants' => 20,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'venue' => 'Gymnasium',
            'equipment' => [],
            'equipment_quantities' => [],
            'e_signature_file' => UploadedFile::fake()->create('signature.png', 100),
        ], $overrides);
    }
}
