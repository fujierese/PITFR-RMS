<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Approvals are account-based (issue APR-01, decisions D1 and D3): no signature
 * image is copied, and the printed form lists role, action, time and a reference.
 */
class DigitalApprovalRecordTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_approval_records_the_account_and_role_without_copying_a_signature_image(): void
    {
        // Even an approver with a legacy signature file on their account gets no image copied.
        $user = User::factory()->create([
            'role' => 'custodian-venue',
            'name' => 'Arlene Sala',
            'e_signature_file' => 'legacy-signature.png',
        ]);
        Storage::disk('local')->put('documents/e_signature/users/legacy-signature.png', base64_decode(self::PNG));

        $request = FacilityRequest::factory()->create([
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
            'venue' => ['Gymnasium'],
            'equipment' => [],
        ]);

        $request->recordApprovalSignature('venue', $user);
        $request->refresh();

        $snapshot = $request->getStageApprovalSnapshots('venue')[0] ?? [];
        $this->assertSame($user->id, $snapshot['approver_id'] ?? null);
        $this->assertSame('custodian-venue', $snapshot['approver_role'] ?? null);
        $this->assertNull($snapshot['signature_file'] ?? null);
        $this->assertNull($request->venue_approval_signature_file);
        $this->assertNotEmpty($request->venue_approval_signature);
        $this->assertSame([], Storage::disk('local')->files('documents/e_signature/approvals'));
        $this->assertMatchesRegularExpression('/^APR-[0-9A-F]{10}$/', FacilityRequest::approvalReference($snapshot));
    }

    public function test_printed_form_shows_digital_approval_records_without_names_or_images(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue', 'name' => 'Venue Person Name']);
        $finalApprover = User::factory()->create(['role' => 'admin', 'name' => 'Final Person Name']);

        $request = FacilityRequest::factory()->create([
            'requested_by_id' => $requester->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'venue' => ['Gymnasium'],
            'equipment' => [],
        ]);
        $request->recordApprovalSignature('venue', $venueCustodian);
        $request->recordApprovalSignature('final', $finalApprover);
        $request->refresh();

        $venueReference = FacilityRequest::approvalReference($request->getStageApprovalSnapshots('venue')[0]);
        $finalReference = FacilityRequest::approvalReference($request->getStageApprovalSnapshots('final')[0]);

        $html = $this->actingAs($requester)->get(route('request.print', $request->id))->assertOk()->getContent();

        $this->assertStringContainsString('Digital Approval Records', $html);
        $this->assertStringContainsString('by Venue Custodian', $html);
        $this->assertStringContainsString('by Supply Office', $html);
        $this->assertStringContainsString('Ref: ' . $venueReference, $html);
        $this->assertStringContainsString('Ref: ' . $finalReference, $html);
        $this->assertStringNotContainsString('Venue Person Name', $html);
        $this->assertStringNotContainsString('Final Person Name', $html);
        $this->assertStringNotContainsString('data:image/', $html);
        $this->assertStringContainsString('Signature over printed name', $html);
    }
}
