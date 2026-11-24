<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\RequestVenue;
use App\Models\User;
use App\Models\Venue;
use App\Services\DocumentUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * REQ-14: a form sent twice makes one request; control numbers never clash; the
 * request, its schedule, items and history are saved together or not at all; and
 * each proposal file is checked and recorded on its own, with no files left behind.
 */
class DuplicateSubmitGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $requestor;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create(['name' => 'Gymnasium', 'capacity' => 500, 'custodian_id' => $custodian->id, 'is_active' => true]);
        $this->requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
    }

    private function submit(?string $token, array $extra = [])
    {
        $date = now()->addDays(5)->toDateString();

        return $this->actingAs($this->requestor)->post(route('requestor.store'), array_filter([
            'submission_token' => $token,
            'department' => 'Test Department',
            'request_context' => 'personal',
            'name_of_activity' => 'Duplicate guard',
            'expected_participants' => 20,
            'venue' => ['Gymnasium'],
            'venue_schedules' => ['Gymnasium' => [
                'start_date' => $date, 'end_date' => $date,
                'start_time' => '08:00', 'end_time' => '10:00',
                'reservation_duration' => 'specific_time',
            ]],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
            'proposal_submission_type' => 'final',
        ] + $extra, fn ($value) => $value !== null));
    }

    private function storedFiles(): array
    {
        return Storage::disk('local')->allFiles('documents');
    }

    public function test_control_numbers_are_unique_in_the_database(): void
    {
        $this->assertTrue(collect(\Illuminate\Support\Facades\Schema::getIndexes('facility_requests'))
            ->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === ['control_number']));
    }

    public function test_the_request_form_carries_a_one_time_token(): void
    {
        $this->actingAs($this->requestor)->get(route('requestor.index', ['tab' => 'create']))
            ->assertOk()
            ->assertSee('name="submission_token"', false);
    }

    public function test_the_same_form_sent_twice_makes_one_request(): void
    {
        $token = (string) Str::uuid();

        $this->submit($token)->assertSessionHasNoErrors()->assertRedirect(route('requestor.index', ['tab' => 'requests']));
        $request = FacilityRequest::query()->sole();

        $this->submit($token)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('requestor.index', ['tab' => 'requests']))
            ->assertSessionHas('success', 'This request was already submitted as ' . $request->control_number . '. It was not submitted again.');

        $this->assertSame(1, FacilityRequest::count());
        $this->assertCount(1, $this->storedFiles());
    }

    public function test_going_back_to_the_form_after_submitting_shows_the_submitted_request(): void
    {
        $this->submit((string) Str::uuid())->assertSessionHasNoErrors();
        $request = FacilityRequest::query()->sole();

        // Back (or reopening the form) does not offer a fresh form ready to send again.
        $this->actingAs($this->requestor)->get(route('requestor.index', ['tab' => 'create']))
            ->assertOk()
            ->assertSee('Your request was submitted as ' . $request->control_number . '.')
            ->assertSee('View ' . $request->control_number)
            ->assertDontSee('name="submission_token"', false);

        // Starting a new request on purpose gives an empty form.
        $this->actingAs($this->requestor)->get(route('requestor.index', ['tab' => 'create', 'new_request' => 1]))
            ->assertOk()
            ->assertSee('name="submission_token"', false)
            ->assertDontSee('Your request was submitted as', false);
        $this->actingAs($this->requestor)->get(route('requestor.index', ['tab' => 'create']))
            ->assertSee('name="submission_token"', false);
    }

    public function test_the_form_does_not_show_a_control_number_before_submitting(): void
    {
        $this->actingAs($this->requestor)->get(route('requestor.index', ['tab' => 'create']))
            ->assertOk()
            ->assertSee('value="Assigned when you submit"', false)
            ->assertDontSee('value="FER-', false);
    }

    public function test_separate_forms_and_old_forms_without_a_token_still_submit(): void
    {
        $this->submit((string) Str::uuid())->assertSessionHasNoErrors();
        $this->submit(null)->assertSessionHasNoErrors();
        $this->submit('not a valid token!')->assertSessionHasNoErrors();

        $this->assertSame(3, FacilityRequest::count());
    }

    public function test_request_schedule_items_and_history_are_saved_together(): void
    {
        $this->submit((string) Str::uuid())->assertSessionHasNoErrors();

        $request = FacilityRequest::query()->sole();
        $this->assertSame(1, $request->requestVenues()->count());
        $this->assertTrue($request->histories()->where('action', 'submitted')->exists());
    }

    public function test_a_failure_while_saving_leaves_no_request_and_no_files(): void
    {
        RequestVenue::creating(function (): void {
            throw new \RuntimeException('Simulated failure while saving the schedule.');
        });

        $this->submit((string) Str::uuid())
            ->assertSessionHasErrors(['request' => 'Your request could not be saved, so nothing was submitted. Please try again. If this keeps happening, contact the Supply Office.']);

        $this->assertSame(0, FacilityRequest::withTrashed()->count());
        $this->assertSame([], $this->storedFiles());
    }

    public function test_a_taken_control_number_is_replaced_instead_of_failing(): void
    {
        $taken = null;
        FacilityRequest::creating(function (FacilityRequest $request) use (&$taken): void {
            if ($taken !== null) {
                return;
            }
            // Another request takes this number just before ours is saved.
            $taken = $request->control_number;
            FacilityRequest::withoutEvents(fn () => FacilityRequest::create([
                'control_number' => $taken,
                'date_requested' => now()->toDateString(),
                'department' => 'IT',
                'name_of_activity' => 'Other request',
                'expected_participants' => 5,
                'start_date' => now()->addDays(20)->toDateString(),
                'end_date' => now()->addDays(20)->toDateString(),
                'start_time' => '09:00',
                'end_time' => '10:00',
                'venue' => ['Gymnasium'],
                'requested_by_id' => $this->requestor->id,
                'status' => 'pending',
                'venue_status' => 'pending',
                'equipment_status' => 'approved',
            ]));
        });

        $this->submit((string) Str::uuid())->assertSessionHasNoErrors();

        $mine = FacilityRequest::query()->where('name_of_activity', 'Duplicate guard')->sole();
        $this->assertNotSame($taken, $mine->control_number);
        $this->assertMatchesRegularExpression('/^FER-\d{4}-\d{3,5}$/', $mine->control_number);
    }

    public function test_each_proposal_file_is_recorded_and_a_failed_one_removes_the_other(): void
    {
        $this->submit((string) Str::uuid(), [
            'proposal_file' => UploadedFile::fake()->create('memo.pdf', 50, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $request = FacilityRequest::query()->sole();
        $this->assertNotNull($request->proposal_file);
        $this->assertNotNull($request->activity_proposal_file);
        $this->assertSame('memo.pdf', $request->document_metadata['proposal_file']['original_name'] ?? null);
        $this->assertSame('proposal.pdf', $request->document_metadata['activity_proposal']['original_name'] ?? null);
        $this->assertCount(2, $this->storedFiles());

        Storage::fake('local');
        $this->app->instance(DocumentUploadService::class, new class extends DocumentUploadService {
            public function uploadDocument(UploadedFile $file, string $documentType, string $controlNumber): array
            {
                return $documentType === 'activity_proposal'
                    ? ['success' => false, 'error' => 'The activity proposal could not be saved.']
                    : parent::uploadDocument($file, $documentType, $controlNumber);
            }
        });

        $this->submit((string) Str::uuid(), [
            'proposal_file' => UploadedFile::fake()->create('memo.pdf', 50, 'application/pdf'),
        ])->assertSessionHasErrors(['activity_proposal_file' => 'The activity proposal could not be saved.']);

        $this->assertSame(1, FacilityRequest::count());
        $this->assertSame([], $this->storedFiles());
    }

    public function test_api_retries_with_the_same_idempotency_key_make_one_request(): void
    {
        Sanctum::actingAs($this->requestor);
        $payload = [
            'name_of_activity' => 'API retry',
            'expected_participants' => 10,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'department' => 'IT',
            'venue' => ['Gymnasium'],
        ];
        $key = (string) Str::uuid();

        $first = $this->postJson('/api/facility-requests', $payload, ['Idempotency-Key' => $key])->assertCreated();
        $this->postJson('/api/facility-requests', $payload, ['Idempotency-Key' => $key])
            ->assertOk()
            ->assertJsonPath('request.control_number', $first->json('request.control_number'));

        $this->assertSame(1, FacilityRequest::count());
    }
}
