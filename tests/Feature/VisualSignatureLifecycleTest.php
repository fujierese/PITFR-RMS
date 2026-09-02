<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Equipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VisualSignatureLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $custodian = User::factory()->create(['role' => 'admin']);
        Equipment::factory()->create([
            'name' => 'Sound System',
            'custodian_id' => $custodian->id,
        ]);
    }

    /** @test */
    public function requestor_account_signature_is_used_for_future_requests_without_reupload(): void
    {
        $college = \App\Models\College::create(['name' => 'College of Engineering']);
        $department = \App\Models\Department::create(['college_id' => $college->id, 'name' => 'Computer Studies']);

        $user = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
            'college_id' => $college->id,
            'department_id' => $department->id,
            'department' => $department->name,
            'e_signature_file' => 'saved-signature.png',
        ]);

        Storage::disk('local')->put('documents/e_signature/users/saved-signature.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));

        $this->actingAs($user);

        $response = $this->post(route('requestor.store'), [
            'request_context' => 'personal',
            'college_id' => $college->id,
            'department_id' => $department->id,
            'name_of_activity' => 'Campus Event',
            'purpose' => 'Student activity',
            'expected_participants' => 20,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'venue' => 'Gymnasium',
            'equipment' => [],
            'equipment_quantities' => [],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect(route('requestor.index', ['tab' => 'requests']));

        $request = FacilityRequest::query()->where('requested_by_id', $user->id)->latest()->first();
        $this->assertNotNull($request);
        $this->assertNotNull($request->e_signature_file);
        $this->assertTrue(Storage::disk('local')->exists('documents/e_signature/' . $request->e_signature_file));
        $this->assertNotSame('saved-signature.png', $request->e_signature_file);
    }

    /** @test */
    public function submitted_request_preserves_original_signature_when_account_signature_changes(): void
    {
        $college = \App\Models\College::create(['name' => 'College of Engineering']);
        $department = \App\Models\Department::create(['college_id' => $college->id, 'name' => 'Computer Studies']);

        $user = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
            'college_id' => $college->id,
            'department_id' => $department->id,
            'department' => $department->name,
            'e_signature_file' => 'first-signature.png',
        ]);

        Storage::disk('local')->put('documents/e_signature/users/first-signature.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));

        $this->actingAs($user);

        $this->post(route('requestor.store'), [
            'request_context' => 'personal',
            'college_id' => $college->id,
            'department_id' => $department->id,
            'name_of_activity' => 'Original Signature Event',
            'purpose' => 'Student activity',
            'expected_participants' => 15,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => 'Gymnasium',
            'equipment' => [],
            'equipment_quantities' => [],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
        ]);

        $request = FacilityRequest::query()->where('requested_by_id', $user->id)->latest()->first();
        $originalFilename = $request->e_signature_file;

        Storage::disk('local')->put('documents/e_signature/users/updated-signature.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
        $user->update(['e_signature_file' => 'updated-signature.png']);

        $request->refresh();

        $this->assertSame($originalFilename, $request->e_signature_file);
        $this->assertTrue(Storage::disk('local')->exists('documents/e_signature/' . $originalFilename));
        $this->assertNotSame('updated-signature.png', $request->e_signature_file);
    }

    /** @test */
    public function approval_signature_file_is_preserved_when_approver_updates_their_account_signature(): void
    {
        $user = User::factory()->create([
            'role' => 'custodian-venue',
            'e_signature_file' => 'approver-first.png',
        ]);

        Storage::disk('local')->put('documents/e_signature/users/approver-first.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));

        $request = FacilityRequest::factory()->create([
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
            'venue' => ['Gymnasium'],
            'equipment' => [],
        ]);

        $request->recordApprovalSignature('venue', $user);
        $originalSignatureFile = $request->venue_approval_signature_file;

        Storage::disk('local')->put('documents/e_signature/users/approver-updated.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
        $user->update(['e_signature_file' => 'approver-updated.png']);

        $request->refresh();

        $this->assertSame($originalSignatureFile, $request->venue_approval_signature_file);
        $this->assertTrue(Storage::disk('local')->exists('documents/e_signature/approvals/' . $originalSignatureFile));
        $this->assertNotSame('approver-updated.png', $request->venue_approval_signature_file);
    }
}
