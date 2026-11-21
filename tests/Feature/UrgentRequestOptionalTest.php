<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Urgent Request section is optional. With it unticked, empty urgent fields sent
 * by the form are ignored (an empty reason used to be refused as "must be a string").
 * Ticked, it still needs the issuing office and a reason.
 */
class UrgentRequestOptionalTest extends TestCase
{
    use RefreshDatabase;

    private User $requestor;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create(['name' => 'Covered Court', 'capacity' => 300, 'custodian_id' => $custodian->id, 'is_active' => true]);
        $this->requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
    }

    private function submit(array $urgent)
    {
        $date = now()->addDays(5)->toDateString();

        return $this->actingAs($this->requestor)->post(route('requestor.store'), $urgent + [
            'department' => 'IT',
            'request_context' => 'personal',
            'name_of_activity' => 'Regular request',
            'purpose' => 'Regular request',
            'expected_participants' => 10,
            'venue' => ['Covered Court'],
            'venue_schedules' => ['Covered Court' => ['start_date' => $date, 'end_date' => $date, 'start_time' => '09:00', 'end_time' => '10:00', 'reservation_duration' => 'specific_time']],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 50, 'application/pdf'),
        ]);
    }

    public function test_a_regular_request_ignores_empty_urgent_fields(): void
    {
        $this->submit(['is_emergency' => '0', 'emergency_justification' => '', 'emergency_office' => ''])
            ->assertSessionHasNoErrors();

        $request = FacilityRequest::query()->sole();
        $this->assertFalse((bool) $request->is_emergency);
        $this->assertSame('regular', $request->priority);
    }

    public function test_ticking_urgent_still_needs_an_office_and_a_reason(): void
    {
        $this->submit(['is_emergency' => '1', 'emergency_justification' => '', 'emergency_office' => ''])
            ->assertSessionHasErrors(['emergency_justification', 'emergency_office']);

        $this->assertSame(0, FacilityRequest::count());
    }
}
