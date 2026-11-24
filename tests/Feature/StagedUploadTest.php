<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * REQ-05: an uploaded document survives a failed submission, so the requestor does
 * not have to upload it again; it is reused only with the matching token.
 */
class StagedUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create(['name' => 'Gymnasium', 'custodian_id' => $custodian->id, 'capacity' => 500]);
        $this->student = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
    }

    private function payload(array $overrides = []): array
    {
        $date = now()->addDays(5)->toDateString();

        return array_merge([
            'department' => 'College of Engineering',
            'request_context' => 'personal',
            'name_of_activity' => 'Engineering Week',
            'expected_participants' => 40,
            'venue' => ['Gymnasium'],
            'venue_schedules' => ['Gymnasium' => [
                'start_date' => $date, 'end_date' => $date,
                'start_time' => '09:00', 'end_time' => '11:00',
                'reservation_duration' => 'specific_time',
            ]],
        ], $overrides);
    }

    public function test_uploaded_proposal_is_kept_after_a_validation_error_and_reused(): void
    {
        // First attempt: proposal attached, but the activity name is missing.
        $this->actingAs($this->student)
            ->post(route('requestor.store'), $this->payload([
                'name_of_activity' => '',
                'activity_proposal_file' => $this->pdf('my-proposal.pdf'),
                'proposal_submission_type' => 'final',
            ]))
            ->assertSessionHasErrors('name_of_activity');

        $kept = session('staged_uploads.activity_proposal_file');
        $this->assertSame('my-proposal.pdf', $kept['name']);
        Storage::disk('local')->assertExists($kept['path']);

        // The form shows the kept file.
        $this->get(route('requestor.index', ['tab' => 'create']))
            ->assertSee('We kept your activity proposal')
            ->assertSee('my-proposal.pdf');

        // Second attempt: no new file, only the kept token.
        $this->actingAs($this->student)
            ->post(route('requestor.store'), $this->payload([
                'kept_activity_proposal_file' => $kept['token'],
                'proposal_submission_type' => 'final',
            ]))
            ->assertSessionHasNoErrors();

        $request = FacilityRequest::firstOrFail();
        $this->assertNotEmpty($request->activity_proposal_file);
        $this->assertNull(session('staged_uploads'));
        Storage::disk('local')->assertMissing($kept['path']);
    }

    public function test_kept_file_is_not_used_with_a_wrong_token(): void
    {
        $this->actingAs($this->student)
            ->post(route('requestor.store'), $this->payload([
                'name_of_activity' => '',
                'activity_proposal_file' => $this->pdf('my-proposal.pdf'),
                'proposal_submission_type' => 'final',
            ]));

        $this->actingAs($this->student)
            ->post(route('requestor.store'), $this->payload(['kept_activity_proposal_file' => 'wrong-token']))
            ->assertSessionHasErrors('activity_proposal_file');
        $this->assertDatabaseCount('facility_requests', 0);
    }

    private function pdf(string $name): UploadedFile
    {
        // A real (tiny) PDF so content-based type checks pass after the file is kept.
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4
1 0 obj<<>>endobj
trailer<<>>
%%EOF
");
    }
}
