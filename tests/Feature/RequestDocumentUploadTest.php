<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Equipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            'name' => 'Wireless Microphones',
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
            'name' => 'Aircon',
            'custodian_id' => $custodian->id,
            'quantity' => 4,
            'quantity_available' => 4,
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
            'office_or_organization' => 'External Partner Organization',
        ]);

        $this->studentUser->update([
            'office_or_organization' => null,
        ]);
    }

    public function test_user_signature_route_returns_image_for_owner(): void
    {
        $user = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
        ]);

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAF' .
            'cYy5AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJ0UkG' .
            'AAAAAAgIY0xVAAAAAElFTkSuQmCC');

        Storage::disk('local')->put('documents/e_signature/users/' . $user->id . '_signature.png', $pngBytes);
        $user->update(['e_signature_file' => $user->id . '_signature.png']);

        $response = $this->actingAs($user)->get(route('user.signature', ['user' => $user->id]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
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

    public function test_external_can_submit_before_igp_records_payment(): void
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

        $response->assertRedirect(route('requestor.index', ['tab' => 'requests']));
        $this->assertDatabaseHas('facility_requests', [
            'requested_by_id' => $this->externalUser->id,
            'igp_payment_status' => 'awaiting_availability',
            'igp_receipt_number' => null,
        ]);
    }

    public function test_outsider_cannot_request_urgent_processing_and_form_explains_the_rule(): void
    {
        $this->actingAs($this->externalUser)
            ->get(route('requestor.index', ['tab' => 'create']))
            ->assertOk()
            ->assertSee('Urgent processing is available only to Institute requestors.')
            ->assertDontSee('Request Urgent Processing');

        $this->post(route('requestor.store'), ['is_emergency' => true])
            ->assertSessionHasErrors([
                'is_emergency' => 'Urgent processing is available only to Institute requestors.',
            ]);
    }

    public function test_external_signature_is_required_but_receipt_is_not_required_before_availability(): void
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

        $response->assertSessionHasErrors('e_signature_file');
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

    public function test_outsider_request_details_show_payment_receipt_and_supply_office_availability_review(): void
    {
        Storage::fake('local');
        $receiptFilename = 'external-receipt.pdf';
        Storage::disk('local')->put('documents/igp_receipt/' . $receiptFilename, 'receipt content');

        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-OUTSIDER-RECEIPT',
            'date_requested' => now()->toDateString(),
            'department' => 'External Org',
            'name_of_activity' => 'Partnership Event',
            'expected_participants' => 30,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '14:00',
            'end_time' => '16:00',
            'venue' => ['Gymnasium'],
            'equipment' => ['Tables'],
            'equipment_quantities' => ['Tables' => 5],
            'requested_by_id' => $this->externalUser->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
            'supply_office_review_status' => 'pending',
            'igp_receipt_file' => $receiptFilename,
            'priority' => 'regular',
        ]);
        $this->assertSame($receiptFilename, $request->fresh()->igp_receipt_file);
        Storage::disk('local')->assertExists('documents/igp_receipt/' . $receiptFilename);

        $this->actingAs($this->externalUser)
            ->get(route('request.show', $request))
            ->assertOk()
            ->assertSee('Request Documents')
            ->assertSee('Payment Receipt')
            ->assertSee($receiptFilename)
            ->assertDontSee('Payment Receipt:')
            ->assertSee('Pending Venue Availability Confirmation')
            ->assertSee('The venue custodian is checking whether the selected venue is available for your requested date and time.')
            ->assertDontSee('No activity proposal uploaded')
            ->assertDontSee('Pending Venue Custodian Approval')
            ->assertDontSee('Assigned reviewer(s):');

        $this->get(route('request.proposal', ['id' => $request->id, 'document' => 'receipt']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('request.proposal.download', ['id' => $request->id, 'document' => 'receipt']))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=external-receipt.pdf');

        $proposalFilename = 'internal-proposal.pdf';
        Storage::disk('local')->put('documents/activity_proposal/' . $proposalFilename, 'proposal content');
        $internalRequest = FacilityRequest::create([
            'control_number' => 'FER-2026-INTERNAL-PROPOSAL',
            'date_requested' => now()->toDateString(),
            'department' => 'Computer Science',
            'name_of_activity' => 'Student Workshop',
            'expected_participants' => 25,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => ['Gymnasium'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $this->studentUser->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
            'supply_office_review_status' => 'pending',
            'activity_proposal_file' => $proposalFilename,
            'priority' => 'regular',
        ]);

        $this->actingAs($this->studentUser)
            ->get(route('request.show', $internalRequest))
            ->assertOk()
            ->assertSee('Document Attachment')
            ->assertSee('Activity Proposal')
            ->assertSee($proposalFilename)
            ->assertDontSee('No activity proposal uploaded');
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

    public function test_legacy_proposal_upload_rejects_html_and_serves_approved_types_safely(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs($this->studentUser);

        $basePayload = [
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
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
            'e_signature_file' => UploadedFile::fake()->create('signature.png', 100, 'image/png'),
        ];

        $this->post(route('requestor.store'), $basePayload + [
            'proposal_file' => UploadedFile::fake()->create('legacy.html', 100, 'text/html'),
        ])->assertSessionHasErrors('proposal_file');
        $this->assertDatabaseCount('facility_requests', 0);

        $this->post(route('requestor.store'), $basePayload + [
            'proposal_file' => UploadedFile::fake()->create('legacy.pdf', 100, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $request = FacilityRequest::where('requested_by_id', $this->studentUser->id)->firstOrFail();
        $this->assertNotSame('legacy.pdf', $request->proposal_file);
        Storage::disk('local')->assertExists('documents/proposal_file/' . $request->proposal_file);

        $this->get(route('request.proposal', ['id' => $request->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('request.proposal.download', ['id' => $request->id]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        Storage::disk('local')->put('proposals/legacy.html', '<script>alert(1)</script>');
        $request->forceFill([
            'activity_proposal_file' => null,
            'proposal_file' => 'legacy.html',
        ])->save();

        $this->get(route('request.proposal', ['id' => $request->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'attachment; filename=legacy.html');

        Storage::disk('public')->put('proposals/legacy-public.pdf', 'legacy public PDF');
        $request->forceFill(['proposal_file' => 'legacy-public.pdf'])->save();

        $this->get(route('request.proposal', ['id' => $request->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $request->forceFill(['proposal_file' => '../legacy.html'])->save();
        $this->get(route('request.proposal', ['id' => $request->id]))->assertNotFound();
    }

    public function test_student_can_request_aircon_for_balay_alumni(): void
    {
        $this->actingAs($this->studentUser);

        $response = $this->post(route('requestor.store'), [
            'requested_by_position' => 'Student',
            'department' => 'Computer Science',
            'name_of_activity' => 'Balay Alumni Event',
            'expected_participants' => 20,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => 'Balay Alumni',
            'equipment' => ['Aircon'],
            'equipment_quantities' => ['Aircon' => 1],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100),
            'e_signature_file' => UploadedFile::fake()->create('signature.png', 100),
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('facility_requests', [
            'requested_by_id' => $this->studentUser->id,
            'name_of_activity' => 'Balay Alumni Event',
        ]);
    }

    public function test_student_can_request_iwata_cooler_fans_for_chic_and_balay_alumni(): void
    {
        Equipment::where('name', 'Iwata Cooler Fans')->update([
            'quantity' => 4,
            'quantity_available' => 4,
        ]);

        $this->actingAs($this->studentUser);

        foreach ([
            'Conference Hall & Interaction Center (CHIC)',
            'Balay Alumni',
        ] as $index => $venue) {
            $response = $this->post(route('requestor.store'), [
                'requested_by_position' => 'Student',
                'department' => 'Computer Science',
                'name_of_activity' => 'Backup Cooling Request ' . $index,
                'expected_participants' => 20,
                'start_date' => now()->addDay()->toDateString(),
                'end_date' => now()->addDay()->toDateString(),
                'start_time' => '09:00',
                'end_time' => '12:00',
                'venue' => $venue,
                'equipment' => ['Iwata Cooler Fans'],
                'equipment_quantities' => ['Iwata Cooler Fans' => 1],
                'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100),
                'e_signature_file' => UploadedFile::fake()->create('signature.png', 100),
            ]);

            $response->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->assertSame(2, FacilityRequest::where('requested_by_id', $this->studentUser->id)->count());
        $this->assertDatabaseHas('facility_requests', [
            'requested_by_id' => $this->studentUser->id,
            'name_of_activity' => 'Backup Cooling Request 0',
        ]);
        $this->assertDatabaseHas('facility_requests', [
            'requested_by_id' => $this->studentUser->id,
            'name_of_activity' => 'Backup Cooling Request 1',
        ]);
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

    public function test_student_can_submit_venue_only_request_with_venue_provided_equipment(): void
    {
        $this->actingAs($this->studentUser);
        $venue = 'Conference Hall & Interaction Center (CHIC)';
        $date = now()->addDay()->toDateString();

        $response = $this->post(route('requestor.store'), [
            'department' => 'Computer Science',
            'name_of_activity' => 'Venue Only Seminar',
            'expected_participants' => 25,
            'venue' => [$venue],
            'venue_schedules' => [
                $venue => [
                    'start_date' => $date,
                    'end_date' => $date,
                    'start_time' => '09:00',
                    'end_time' => '11:00',
                    'reservation_duration' => 'specific_time',
                ],
            ],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100),
            'e_signature_file' => UploadedFile::fake()->create('signature.png', 100),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $request = FacilityRequest::where('requested_by_id', $this->studentUser->id)->firstOrFail();
        $this->assertSame([], $request->getEquipmentItems());
        $this->assertSame('approved', $request->equipment_status);
        $this->assertSame('pending', $request->venue_status);
        $this->assertSame([], $request->getAssignedEquipmentCustodianIds());
        $this->assertCount(0, $request->requestEquipment);
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
            'equipment' => ['Wireless Microphones'],
            'equipment_quantities' => ['Wireless Microphones' => 2],
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
            'equipment' => ['Wireless Microphones'],
            'equipment_quantities' => ['Wireless Microphones' => 2],
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

    public function test_urgent_request_must_be_reserved_on_the_submission_day(): void
    {
        $this->actingAs($this->studentUser);
        $requestDate = now()->addDay()->toDateString();

        $response = $this->post(route('requestor.store'), [
            'department' => 'Computer Science',
            'name_of_activity' => 'Urgent Workshop',
            'expected_participants' => 30,
            'start_date' => $requestDate,
            'end_date' => $requestDate,
            'start_time' => '13:00',
            'end_time' => '15:00',
            'venue' => 'Covered Court',
            'equipment' => [],
            'is_emergency' => true,
            'emergency_justification' => 'This request needs urgent review.',
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100),
            'e_signature_file' => UploadedFile::fake()->create('signature.png', 100),
        ]);

        $response->assertSessionHasErrors([
            'is_emergency' => 'Urgent requests must be submitted and held entirely on the same local calendar day.',
        ]);
    }

    public function test_urgent_request_cannot_start_in_a_past_time_today(): void
    {
        $this->travelTo(now()->setTime(16, 0));
        $this->actingAs($this->studentUser);

        $this->post(route('requestor.store'), [
            'department' => 'Computer Science',
            'name_of_activity' => 'Past-time urgent workshop',
            'expected_participants' => 30,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'start_time' => '15:00',
            'end_time' => '17:00',
            'venue' => 'Covered Court',
            'equipment' => [],
            'is_emergency' => true,
            'emergency_justification' => 'This request needs urgent review.',
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100),
            'e_signature_file' => UploadedFile::fake()->create('signature.png', 100),
        ])->assertSessionHasErrors([
            'is_emergency' => 'Urgent reservations must start at the current time or later.',
        ]);
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
