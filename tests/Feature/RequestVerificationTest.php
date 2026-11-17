<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue APR-15: the printed form carries a QR code that opens a public page
 * confirming the form against the system record, without personal names.
 */
class RequestVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function approvedRequest(): array
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student', 'name' => 'Requester Person Name']);
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue', 'name' => 'Venue Person Name']);
        $request = FacilityRequest::factory()->create([
            'requested_by_id' => $requester->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'venue' => ['Gymnasium'],
            'equipment' => [],
        ]);
        $request->recordApprovalSignature('venue', $venueCustodian);

        return [$request->fresh(), $requester];
    }

    public function test_valid_code_shows_status_and_approval_references_without_names(): void
    {
        [$request] = $this->approvedRequest();
        $reference = FacilityRequest::approvalReference($request->getStageApprovalSnapshots('venue')[0]);

        $this->get($request->verificationUrl())
            ->assertOk()
            ->assertSee('This form matches a request')
            ->assertSee($request->control_number)
            ->assertSee('Approved')
            ->assertSee($reference)
            ->assertDontSee('Venue Person Name')
            ->assertDontSee('Requester Person Name')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_wrong_code_or_unknown_request_is_rejected(): void
    {
        [$request] = $this->approvedRequest();

        $this->get(route('request.verify', ['facilityRequest' => $request->id, 'code' => str_repeat('0', 20)]))
            ->assertNotFound()
            ->assertSee('This verification link is not valid')
            ->assertDontSee($request->control_number);

        $this->get(route('request.verify', ['facilityRequest' => 999999, 'code' => $request->verificationCode()]))
            ->assertNotFound();
    }

    public function test_printed_form_includes_the_verification_qr_code(): void
    {
        [$request, $requester] = $this->approvedRequest();

        $this->actingAs($requester)->get(route('request.print', $request->id))
            ->assertOk()
            ->assertSee('verification-qr', false)
            ->assertSee('<svg', false)
            ->assertSee('Scan the QR code');
    }
}
