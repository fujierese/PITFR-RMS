<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\Holiday;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * REQ-17: everything runs on Philippine time. Dates sent as JSON keep the booked date,
 * "today" switches at midnight in Manila (not at 8 AM, when UTC changes date), and the
 * calendar's "now" comes from Philippine time instead of the device clock.
 */
class TimezoneHandlingTest extends TestCase
{
    use RefreshDatabase;

    private User $requestor;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        Holiday::query()->delete();
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create(['name' => 'Gymnasium', 'capacity' => 500, 'custodian_id' => $custodian->id, 'is_active' => true]);
        $this->requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
    }

    private function booking(string $date): FacilityRequest
    {
        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-700',
            'date_requested' => $date,
            'department' => 'IT',
            'name_of_activity' => 'Timezone check',
            'expected_participants' => 10,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'venue' => ['Gymnasium'],
            'requested_by_id' => $this->requestor->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'approved_date' => Carbon::parse($date . ' 07:30', 'Asia/Manila'),
        ]);
        $request->syncRelationalItems();

        return $request;
    }

    public function test_the_application_runs_on_philippine_time(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));
        $this->assertSame('Asia/Manila', date_default_timezone_get());
        $this->assertSame('+08:00', now()->format('P'));
    }

    public function test_json_dates_keep_the_booked_date(): void
    {
        $request = $this->booking('2026-12-15');

        $json = $request->fresh()->toArray();
        $this->assertSame('2026-12-15T00:00:00+08:00', $json['start_date']);
        $this->assertSame('2026-12-15T07:30:00+08:00', $json['approved_date']);

        $supply = User::factory()->create(['role' => 'supply_office']);
        $this->actingAs($supply)->getJson('/api-test/facility-requests/' . $request->id)
            ->assertOk()
            ->assertJsonPath('start_date', '2026-12-15T00:00:00+08:00')
            ->assertJsonPath('end_date', '2026-12-15T00:00:00+08:00');
    }

    public function test_today_changes_at_midnight_in_manila(): void
    {
        // 12:30 AM on Dec 15 in Manila is still Dec 14 in UTC.
        $this->travelTo(Carbon::parse('2026-12-15 00:30', 'Asia/Manila'));

        $this->actingAs($this->requestor)->get(route('requestor.index', ['tab' => 'create']))
            ->assertOk()
            ->assertSee('min="2026-12-15"', false);

        $this->actingAs($this->requestor)->post(route('requestor.store'), [
            'department' => 'Test Department',
            'request_context' => 'personal',
            'name_of_activity' => 'Same-day booking',
            'purpose' => 'Same-day booking',
            'expected_participants' => 20,
            'venue' => ['Gymnasium'],
            'venue_schedules' => ['Gymnasium' => [
                'start_date' => '2026-12-15', 'end_date' => '2026-12-15',
                'start_time' => '08:00', 'end_time' => '10:00',
                'reservation_duration' => 'specific_time',
            ]],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
            'proposal_submission_type' => 'final',
        ])->assertSessionDoesntHaveErrors(['venue_schedules.Gymnasium.start_date']);

        $this->assertSame('2026-12-15', FacilityRequest::query()->sole()->start_date->toDateString());
    }

    public function test_the_calendar_takes_now_from_philippine_time(): void
    {
        $this->get(route('calendar.index'))
            ->assertOk()
            ->assertSee("timeZone: 'Asia/Manila'", false)
            ->assertSee('now: function()', false);
    }
}
