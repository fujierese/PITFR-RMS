<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * REQ-03: urgent requests can only be held today in the institutional timezone,
 * on the form (date pickers and calendar) and on the server.
 */
class UrgentRequestDateTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-27 08:00:00', 'Asia/Manila'));
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create(['name' => 'Gymnasium', 'custodian_id' => $custodian->id, 'capacity' => 500]);
        $this->requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'faculty']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function urgentPayload(string $date): array
    {
        return [
            'department' => 'College of Engineering',
            'name_of_activity' => 'CHED Visit',
            'expected_participants' => 40,
            'venue' => ['Gymnasium'],
            'venue_schedules' => ['Gymnasium' => [
                'start_date' => $date, 'end_date' => $date,
                'start_time' => '13:00', 'end_time' => '15:00',
                'reservation_duration' => 'specific_time',
            ]],
            'is_emergency' => '1',
            'emergency_office' => 'CHED',
            'emergency_justification' => 'CHED evaluation moved to today.',
            'proposal_file' => UploadedFile::fake()->create('memo.pdf', 50, 'application/pdf'),
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 50, 'application/pdf'),
        ];
    }

    public function test_server_rejects_an_urgent_request_for_another_day(): void
    {
        $this->actingAs($this->requester)
            ->post(route('requestor.store'), $this->urgentPayload('2026-11-28'))
            ->assertSessionHasErrors(['is_emergency' => 'Urgent requests must be submitted and held entirely on the same local calendar day.']);

        $this->assertDatabaseCount('facility_requests', 0);
    }

    public function test_form_limits_urgent_dates_to_today_in_manila_time(): void
    {
        $this->actingAs($this->requester)
            ->get(route('requestor.index', ['tab' => 'create']))
            ->assertOk()
            ->assertSee('window.pitfrUrgentToday = "2026-11-27"', false)
            ->assertSee('Urgent requests can only be held today', false);
    }

    public function test_urgent_request_for_today_is_accepted(): void
    {
        $this->actingAs($this->requester)
            ->post(route('requestor.store'), $this->urgentPayload('2026-11-27'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('facility_requests', ['name_of_activity' => 'CHED Visit', 'is_emergency' => true]);
    }

    public function test_urgent_toggle_shows_office_choice_and_notice_immediately(): void
    {
        // REQ-04: the form script enables the issuing office and shows the notice on toggle,
        // not only after a failed submission.
        $script = file_get_contents(resource_path('js/request_form.js'));
        $this->assertStringContainsString("emergencyOfficeSelect?.removeAttribute('disabled')", $script);
        $this->assertStringContainsString("urgentIndicator?.classList.remove('hidden')", $script);

        $this->actingAs($this->requester)
            ->get(route('requestor.index', ['tab' => 'create']))
            ->assertOk()
            ->assertSee('id="emergency-office"', false)
            ->assertSee('id="urgent-request-indicator"', false);
    }

    public function test_server_still_requires_the_issuing_office(): void
    {
        $payload = $this->urgentPayload('2026-11-27');
        unset($payload['emergency_office']);

        $this->actingAs($this->requester)
            ->post(route('requestor.store'), $payload)
            ->assertSessionHasErrors('emergency_office');
    }
}
