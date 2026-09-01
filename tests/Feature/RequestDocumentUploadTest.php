<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Equipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RequestDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected User $studentUser;
    protected User $facultyUser;
    protected User $externalUser;
    protected UploadedFile $pdfFile;
    protected UploadedFile $jpgFile;
    protected UploadedFile $pngFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a custodian user for equipment (id will be 1 or auto-increment)
        $custodian = User::factory()->create([
            'role' => 'admin',
        ]);

        // Create test equipment with custodian
        Equipment::factory()->create([
            'name' => 'Sound System',
            'custodian_id' => $custodian->id,
        ]);
        Equipment::factory()->create([
            'name' => 'Microphones',
            'custodian_id' => $custodian->id,
            'quantity' => 10,
            'quantity_available' => 10,
        ]);
        Equipment::factory()->create([
            'name' => 'Canopies',
            'custodian_id' => $custodian->id,
        ]);
        Equipment::factory()->create([
            'name' => 'Industrial Fans',
            'custodian_id' => $custodian->id,
        ]);
        Equipment::factory()->create([
            'name' => 'Iwata Cooler Fans',
            'custodian_id' => $custodian->id,
        ]);
        Equipment::factory()->create([
            'name' => 'Tables',
            'custodian_id' => $custodian->id,
            'quantity' => 100,
            'quantity_available' => 100,
        ]);
        Equipment::factory()->create([
            'name' => 'Monobloc chairs',
            'custodian_id' => $custodian->id,
        ]);

        // Create test users
        $this->studentUser = User::factory()->create([
            'requestor_type' => 'student',
            'role' => 'requestor',
        ]);

        $this->facultyUser = User::factory()->create([
            'requestor_type' => 'faculty',
            'role' => 'faculty',
            'position' => 'Department Chair',
        ]);

        $this->externalUser = User::factory()->create([
            'requestor_type' => 'outsider',
            'role' => 'requestor',  // Must be 'requestor' role to be able to submit requests
        ]);

        $this->studentUser->update([
            'office_or_organization' => null,
        ]);
    }

    public function test_student_requires_activity_proposal(): void
    {
        $this->actingAs($this->studentUser);

        $response = $this->post(route('requestor.store'), [
            'department' => 'Computer Science',
            'name_of_activity' => 'Tech Workshop',
            'expected_participants' => 50,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => 'Conference Hall & Interaction Center (CHIC)',
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 1],
            'e_signature_file' => UploadedFile::fake()->create('signature.pdf', 100),
            // Missing activity_proposal_file
        ]);

        $response->assertSessionHasErrors('activity_proposal_file');
    }

    public function test_external_requires_igp_receipt(): void
    {
        $this->actingAs($this->externalUser);

        $response = $this->post(route('requestor.store'), [
            'department' => 'External Org',
            'name_of_activity' => 'Partnership Event',
            'expected_participants' => 30,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '14:00',
            'end_time' => '16:00',
            'venue' => 'Gymnasium',
            'equipment' => ['Tables'],
            'equipment_quantities' => ['Tables' => 5],
            'e_signature_file' => UploadedFile::fake()->create('signature.jpg', 100),
            // Missing igp_receipt_file
        ]);

        $response->assertSessionHasErrors('igp_receipt_file');
        $this->assertDatabaseCount('facility_requests', 0);
    }

    public function test_external_requires_both_documents(): void
    {
        $this->actingAs($this->externalUser);

        $response = $this->post(route('requestor.store'), [
            'department' => 'External Org',
            'name_of_activity' => 'Partnership Event',
            'expected_participants' => 30,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '14:00',
            'end_time' => '16:00',
            'venue' => 'Gymnasium',
            'equipment' => ['Tables'],
            'equipment_quantities' => ['Tables' => 5],
        ]);

        $response->assertSessionHasErrors(['igp_receipt_file', 'e_signature_file']);
        $this->assertDatabaseCount('facility_requests', 0);
    }

    public function test_all_requestors_require_e_signature(): void
    {
        $this->actingAs($this->studentUser);

        $response = $this->post(route('requestor.store'), [
            'department' => 'Computer Science',
            'name_of_activity' => 'Tech Workshop',
            'expected_participants' => 50,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => 'Conference Hall & Interaction Center (CHIC)',
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 1],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100),
            // Missing e_signature_file
        ]);

        $response->assertSessionHasErrors('e_signature_file');
        $this->assertDatabaseCount('facility_requests', 0);
    }

    public function test_student_requires_both_documents(): void
    {
        $this->actingAs($this->studentUser);

        $response = $this->post(route('requestor.store'), [
            'department' => 'Computer Science',
            'name_of_activity' => 'Tech Workshop',
            'expected_participants' => 50,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => 'Conference Hall & Interaction Center (CHIC)',
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 1],
        ]);

        $response->assertSessionHasErrors(['activity_proposal_file', 'e_signature_file']);
        $this->assertDatabaseCount('facility_requests', 0);
    }

    public function test_student_can_submit_with_activity_proposal_and_e_signature(): void
    {
        $this->actingAs($this->studentUser);

        $response = $this->post(route('requestor.store'), [
            'requested_by_position' => 'Student',
            'department' => 'Computer Science',
            'name_of_activity' => 'Tech Workshop',
            'expected_participants' => 50,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => 'Conference Hall & Interaction Center (CHIC)',
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 1],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100),
            'e_signature_file' => UploadedFile::fake()->create('signature.png', 100),
        ]);

        $response->assertRedirect();

        $request = FacilityRequest::where('requested_by_id', $this->studentUser->id)->first();
        $this->assertNotNull($request);
        $this->assertNotNull($request->activity_proposal_file);
        $this->assertNotNull($request->e_signature_file);
        $this->assertNull($request->igp_receipt_file);
    }

    public function test_requestor_submitted_priority_is_ignored_and_final_classification_stays_regular(): void
    {
        $this->actingAs($this->studentUser);

        $this->post(route('requestor.store'), [
            'priority' => 'institutional',
            'requested_priority' => 'institutional',
            'department' => 'Computer Science',
            'name_of_activity' => 'Classification Security Check',
            'expected_participants' => 10,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => 'Conference Hall & Interaction Center (CHIC)',
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 1],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100),
            'e_signature_file' => UploadedFile::fake()->create('signature.png', 100),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $request = FacilityRequest::where('requested_by_id', $this->studentUser->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('regular', $request->priority);
        $this->assertNull($request->requested_priority);
    }

    public function test_external_can_submit_with_igp_receipt_and_e_signature(): void
    {
        $this->actingAs($this->externalUser);

        $response = $this->post(route('requestor.store'), [
            'requested_by_position' => 'External Partner',
            'department' => 'External Org',
            'name_of_activity' => 'Partnership Event',
            'expected_participants' => 30,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '14:00',
            'end_time' => '16:00',
            'venue' => 'Gymnasium',
            'equipment' => ['Tables'],
            'equipment_quantities' => ['Tables' => 5],
            'igp_receipt_file' => UploadedFile::fake()->create('igp.pdf', 100),
            'e_signature_file' => UploadedFile::fake()->create('signature.jpg', 100),
        ]);

        $response->assertRedirect();

        $request = FacilityRequest::where('requested_by_id', $this->externalUser->id)->first();
        $this->assertNotNull($request);
        $this->assertNotNull($request->igp_receipt_file);
        $this->assertNotNull($request->e_signature_file);
        $this->assertNull($request->activity_proposal_file);
    }

    public function test_student_can_request_for_an_organization(): void
    {
        $this->actingAs($this->studentUser);

        $response = $this->post(route('requestor.store'), [
            'organization_name' => 'PIT Student Council',
            'department' => 'Computer Science',
            'name_of_activity' => 'Organization Assembly',
            'expected_participants' => 30,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '14:00',
            'end_time' => '16:00',
            'venue' => 'Gymnasium',
            'equipment' => ['Tables'],
            'equipment_quantities' => ['Tables' => 5],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100),
            'e_signature_file' => UploadedFile::fake()->create('signature.jpg', 100),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $request = FacilityRequest::where('requested_by_id', $this->studentUser->id)->first();
        $this->assertNotNull($request);
        $this->assertSame('PIT Student Council', $request->organization_name);
        $this->assertNotNull($request->activity_proposal_file);
        $this->assertNull($request->igp_receipt_file);
    }

    public function test_faculty_requires_activity_proposal(): void
    {
        $this->actingAs($this->facultyUser);

        $response = $this->post(route('requestor.store'), [
            'department' => 'Engineering',
            'name_of_activity' => 'Faculty Seminar',
            'expected_participants' => 20,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:30',
            'venue' => 'Conference Hall & Interaction Center (CHIC)',
            'equipment' => ['Microphones'],
            'equipment_quantities' => ['Microphones' => 2],
            'e_signature_file' => UploadedFile::fake()->create('signature.png', 100),
            // Missing activity_proposal_file
        ]);

        $response->assertSessionHasErrors('activity_proposal_file');
    }

    public function test_request_uses_persisted_faculty_position_when_not_resubmitted(): void
    {
        $this->actingAs($this->facultyUser);

        $this->post(route('requestor.store'), [
            'department' => 'Engineering',
            'name_of_activity' => 'Faculty Seminar',
            'expected_participants' => 20,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:30',
            'venue' => 'Conference Hall & Interaction Center (CHIC)',
            'equipment' => ['Microphones'],
            'equipment_quantities' => ['Microphones' => 2],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100),
            'e_signature_file' => UploadedFile::fake()->create('signature.png', 100),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $request = FacilityRequest::where('requested_by_id', $this->facultyUser->id)->firstOrFail();
        $this->assertSame('Department Chair', $request->requested_by_position);
    }

    public function test_emergency_request_still_requires_activity_proposal(): void
    {
        $this->actingAs($this->studentUser);

        $response = $this->post(route('requestor.store'), [
            'department' => 'Computer Science',
            'name_of_activity' => 'Emergency Workshop',
            'expected_participants' => 30,
            'start_date' => now()->addHours(24)->toDateString(),
            'end_date' => now()->addHours(24)->toDateString(),
            'start_time' => '13:00',
            'end_time' => '15:00',
            'venue' => 'Covered Court',
            'equipment' => ['Tables'],
            'equipment_quantities' => ['Tables' => 3],
            'is_emergency' => true,
            'emergency_justification' => 'This is an urgent matter.',
            'e_signature_file' => UploadedFile::fake()->create('signature.pdf', 100),
            // activity_proposal_file is still required for emergency requests
        ]);

        $response->assertSessionHasErrors('activity_proposal_file');
    }

    public function test_file_type_validation(): void
    {
        $this->actingAs($this->studentUser);

        // Test with invalid executable file
        $response = $this->post(route('requestor.store'), [
            'department' => 'Computer Science',
            'name_of_activity' => 'Tech Workshop',
            'expected_participants' => 50,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => 'Conference Hall & Interaction Center (CHIC)',
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 1],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.exe', 100),
            'e_signature_file' => UploadedFile::fake()->create('signature.png', 100),
        ]);

        $response->assertSessionHasErrors('activity_proposal_file');
    }

    public function test_file_content_must_match_allowed_mime_type(): void
    {
        $this->actingAs($this->studentUser);

        $response = $this->post(route('requestor.store'), [
            'department' => 'Computer Science',
            'name_of_activity' => 'Tech Workshop',
            'expected_participants' => 50,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => 'Conference Hall & Interaction Center (CHIC)',
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 1],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'text/plain'),
            'e_signature_file' => UploadedFile::fake()->create('signature.png', 100, 'image/png'),
        ]);

        $response->assertSessionHasErrors('activity_proposal_file');
        $this->assertDatabaseCount('facility_requests', 0);
    }

    public function test_document_metadata_stored(): void
    {
        $this->actingAs($this->studentUser);

        $this->post(route('requestor.store'), [
            'requested_by_position' => 'Student',
            'department' => 'Computer Science',
            'name_of_activity' => 'Tech Workshop',
            'expected_participants' => 50,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => 'Conference Hall & Interaction Center (CHIC)',
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 1],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100),
            'e_signature_file' => UploadedFile::fake()->create('signature.png', 100),
        ]);

        $request = FacilityRequest::where('requested_by_id', $this->studentUser->id)->first();
        $this->assertNotNull($request->document_metadata);
        $this->assertArrayHasKey('activity_proposal', $request->document_metadata);
        $this->assertArrayHasKey('e_signature', $request->document_metadata);
        $this->assertArrayHasKey('uploaded_at', $request->document_metadata['activity_proposal']);
        $this->assertArrayHasKey('original_name', $request->document_metadata['activity_proposal']);
    }
}
