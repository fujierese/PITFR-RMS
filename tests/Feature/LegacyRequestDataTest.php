<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\Holiday;
use App\Models\SystemLog;
use App\Models\User;
use App\Models\Venue;
use App\Services\AvailabilityService;
use App\Support\RequestDataReconciler;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * REQ-18: venue, item and schedule rows are the source of truth; the old columns are
 * a copy. Disagreements are found and repaired, old requests without rows still work,
 * and a multi-venue request is never collapsed by the one-venue edit form.
 */
class LegacyRequestDataTest extends TestCase
{
    use RefreshDatabase;

    private User $requestor;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Holiday::query()->delete();
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $equipmentCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        Venue::create(['name' => 'Gymnasium', 'capacity' => 500, 'custodian_id' => $venueCustodian->id, 'is_active' => true]);
        Venue::create(['name' => 'CHIC', 'capacity' => 200, 'custodian_id' => $venueCustodian->id, 'is_active' => true]);
        Equipment::create(['name' => 'Projector', 'custodian_id' => $equipmentCustodian->id, 'quantity' => 2, 'quantity_available' => 2, 'is_active' => true]);
        Equipment::create(['name' => 'Microphone', 'custodian_id' => $equipmentCustodian->id, 'quantity' => 5, 'quantity_available' => 5, 'is_active' => true]);
        $this->requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $this->date = now()->addDays(10)->toDateString();
    }

    private function request(array $overrides = []): FacilityRequest
    {
        static $number = 800;

        return FacilityRequest::create($overrides + [
            'control_number' => 'FER-2026-' . (++$number),
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Legacy data',
            'expected_participants' => 10,
            'start_date' => $this->date,
            'end_date' => $this->date,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => ['Gymnasium', 'CHIC'],
            'equipment' => ['Projector', 'Microphone'],
            'equipment_quantities' => ['Projector' => 2, 'Microphone' => 1],
            'requested_by_id' => $this->requestor->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);
    }

    private function at(string $time): Carbon
    {
        return Carbon::parse($this->date . ' ' . $time, config('app.timezone'));
    }

    private function driftCopy(FacilityRequest $request): void
    {
        DB::table('facility_requests')->where('id', $request->id)->update([
            'venue' => json_encode(['Gymnasium']),
            'equipment' => json_encode(['Projector']),
            'equipment_quantities' => json_encode(['Projector' => 2]),
        ]);
    }

    public function test_rows_win_when_the_old_columns_disagree(): void
    {
        $request = $this->request();
        $this->driftCopy($request);

        $fresh = FacilityRequest::find($request->id);
        $this->assertEqualsCanonicalizing(['Gymnasium', 'CHIC'], $fresh->getVenueNames());
        $this->assertEqualsCanonicalizing(['Projector', 'Microphone'], $fresh->getEquipmentItems());
        $this->assertEquals(['Projector' => 2, 'Microphone' => 1], $fresh->getEquipmentQuantities());

        $availability = app(AvailabilityService::class);
        $this->assertFalse($availability->checkVenueAvailability('Gymnasium', $this->at('10:00'), $this->at('12:00'))['available']);
        $this->assertFalse($availability->checkVenueAvailability('CHIC', $this->at('10:00'), $this->at('12:00'))['available']);
        $this->assertFalse($availability->checkEquipmentAvailability('Projector', 1, $this->at('10:00'), $this->at('12:00'))['available']);
    }

    public function test_the_check_reports_disagreements_and_fix_repairs_the_copy(): void
    {
        $request = $this->request();
        $this->driftCopy($request);
        DB::table('reservation_schedules')->where('facility_request_id', $request->id)->update(['end_datetime' => $this->date . ' 18:00:00']);

        $this->artisan('requests:reconcile')
            ->expectsOutputToContain($request->control_number)
            ->expectsOutputToContain('Old venue column lists Gymnasium; venue rows list Gymnasium, CHIC.')
            ->expectsOutputToContain('Run "php artisan requests:reconcile --fix" to repair them.')
            ->assertSuccessful();
        $this->assertTrue(SystemLog::where('event', 'request_data_mismatch')->exists());

        $this->artisan('requests:reconcile', ['--fix' => true])->expectsOutputToContain('Fixed.')->assertSuccessful();

        $fresh = FacilityRequest::find($request->id);
        $this->assertEqualsCanonicalizing(['Gymnasium', 'CHIC'], $fresh->venue);
        $this->assertEquals(['Projector' => 2, 'Microphone' => 1], $fresh->equipment_quantities);
        $this->assertSame($this->date . ' 11:00', $fresh->reservationSchedule->end_datetime->format('Y-m-d H:i'));
        $this->assertSame([], app(RequestDataReconciler::class)->issues($fresh));
        $this->artisan('requests:reconcile')->expectsOutputToContain('All requests agree')->assertSuccessful();
    }

    public function test_date_columns_are_rewritten_from_the_venue_schedules(): void
    {
        $request = $this->request();
        DB::table('request_venues')->where('facility_request_id', $request->id)->where('name', 'CHIC')
            ->update(['start_time' => '13:00', 'end_time' => '16:00']);

        $issues = app(RequestDataReconciler::class)->issues(FacilityRequest::find($request->id));
        $this->assertArrayHasKey('date_copy', $issues);

        app(RequestDataReconciler::class)->fix(FacilityRequest::find($request->id));
        $fresh = FacilityRequest::find($request->id);
        $this->assertSame('09:00', substr($fresh->start_time, 0, 5));
        $this->assertSame('16:00', substr($fresh->end_time, 0, 5));
        $this->assertSame('13:00', substr($fresh->getVenueSchedule('CHIC')['start_time'], 0, 5), 'The per-venue schedule is kept.');
        $this->assertSame([], app(RequestDataReconciler::class)->issues($fresh));
    }

    public function test_an_old_request_without_rows_still_works_and_fix_builds_them(): void
    {
        $request = $this->request(['venue' => ['Gymnasium'], 'equipment' => ['Projector'], 'equipment_quantities' => ['Projector' => 1]]);
        foreach (['request_equipment', 'request_venues', 'reservation_schedules'] as $table) {
            DB::table($table)->where('facility_request_id', $request->id)->delete();
        }

        $old = FacilityRequest::find($request->id);
        $this->assertSame(['Gymnasium'], $old->getVenueNames());
        $this->assertFalse(app(AvailabilityService::class)->checkVenueAvailability('Gymnasium', $this->at('10:00'), $this->at('12:00'))['available']);

        $events = $this->actingAs($this->requestor)->getJson(route('calendar.events'))->assertOk()->json();
        $this->assertTrue(collect($events)->contains(fn ($event) => str_contains(json_encode($event), $request->control_number)));

        $this->assertArrayHasKey('missing_venue_rows', app(RequestDataReconciler::class)->issues($old));
        $this->artisan('requests:reconcile', ['--fix' => true, '--id' => [$request->id]])->expectsOutputToContain('Fixed.');

        $this->assertSame(1, DB::table('request_venues')->where('facility_request_id', $request->id)->count());
        $this->assertSame(1, DB::table('request_equipment')->where('facility_request_id', $request->id)->count());
        $this->assertSame(1, DB::table('reservation_schedules')->where('facility_request_id', $request->id)->count());
    }

    public function test_requests_submitted_through_the_form_pass_the_check(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $other = now()->addDays(12)->toDateString();
        $this->actingAs($this->requestor)->post(route('requestor.store'), [
            'department' => 'Test Department',
            'request_context' => 'personal',
            'name_of_activity' => 'Two venues',
            'purpose' => 'Two venues',
            'expected_participants' => 20,
            'venue' => ['Gymnasium', 'CHIC'],
            'venue_schedules' => [
                'Gymnasium' => ['start_date' => $this->date, 'end_date' => $this->date, 'start_time' => '13:00', 'end_time' => '15:00', 'reservation_duration' => 'specific_time'],
                'CHIC' => ['start_date' => $other, 'end_date' => $other, 'start_time' => '08:00', 'end_time' => '17:00', 'reservation_duration' => 'whole_day'],
            ],
            'activity_proposal_file' => \Illuminate\Http\UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $request = FacilityRequest::where('name_of_activity', 'Two venues')->sole();
        $this->assertSame([], app(RequestDataReconciler::class)->issues($request));
        $this->artisan('requests:reconcile')->expectsOutputToContain('All requests agree')->assertSuccessful();
    }

    public function test_a_multi_venue_request_is_edited_with_every_venue_kept(): void
    {
        // QA-7: the edit page is the full request form, so a multi-venue request keeps
        // every venue and its own schedule when edited.
        $request = $this->request(['status' => 'pending', 'venue_status' => 'pending', 'equipment_status' => 'pending', 'equipment' => [], 'equipment_quantities' => []]);
        $html = $this->actingAs($this->requestor)->get(route('requestor.edit', $request))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/name="venue\[\]" value="Gymnasium"\s+checked/', $html);
        $this->assertMatchesRegularExpression('/name="venue\[\]" value="CHIC"\s+checked/', $html);

        $later = now()->addDays(11)->toDateString();
        $this->actingAs($this->requestor)->put(route('requestor.update', $request), [
            'form_version' => \App\Http\Controllers\RequestorController::editFormVersion($request->fresh()),
            'department' => 'IT', 'request_context' => 'personal', 'name_of_activity' => 'Changed', 'purpose' => 'Changed', 'expected_participants' => 10,
            'venue' => ['Gymnasium', 'CHIC'],
            'venue_schedules' => [
                'Gymnasium' => ['start_date' => $this->date, 'end_date' => $this->date, 'start_time' => '09:00', 'end_time' => '11:00', 'reservation_duration' => 'specific_time', 'activity_type' => 'seminar'],
                'CHIC' => ['start_date' => $later, 'end_date' => $later, 'start_time' => '13:00', 'end_time' => '15:00', 'reservation_duration' => 'specific_time'],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(route('request.show', $request));

        $fresh = FacilityRequest::find($request->id);
        $this->assertEqualsCanonicalizing(['Gymnasium', 'CHIC'], $fresh->getVenueNames());
        $this->assertSame('Changed', $fresh->name_of_activity);
        $this->assertSame($later, $fresh->getVenueSchedule('CHIC')['start_date']);
        $this->assertSame('13:00', substr($fresh->getVenueSchedule('CHIC')['start_time'], 0, 5));
        $this->assertSame('FER-2026-' . substr($request->control_number, 9), $fresh->control_number, 'The control number stays.');
    }
}
