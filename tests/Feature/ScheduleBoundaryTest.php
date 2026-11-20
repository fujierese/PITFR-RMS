<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\Holiday;
use App\Models\RevisionHistory;
use App\Models\User;
use App\Models\Venue;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * REQ-16: schedules over several dates book the same hours each day; an end time at or
 * before the start time means overnight, each night; no booking starts or ends from
 * 12:00 AM through 5:00 AM; and every route agrees with the calendar.
 */
class ScheduleBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private User $requestor;
    private AvailabilityService $availability;
    private Carbon $monday;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        Holiday::query()->delete();

        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $equipmentCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        Venue::create(['name' => 'Gymnasium', 'capacity' => 500, 'custodian_id' => $venueCustodian->id, 'is_active' => true]);
        Equipment::create(['name' => 'Projector', 'custodian_id' => $equipmentCustodian->id, 'quantity' => 1, 'quantity_available' => 1, 'is_active' => true]);

        $this->requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $this->availability = app(AvailabilityService::class);
        $this->monday = now()->addDays(14)->startOfWeek();
    }

    private function day(int $offset): string
    {
        return $this->monday->copy()->addDays($offset)->toDateString();
    }

    private function at(int $offset, string $time): Carbon
    {
        return Carbon::parse($this->day($offset) . ' ' . $time, config('app.timezone'));
    }

    private function approvedBooking(int $startDay, string $startTime, int $endDay, string $endTime, array $equipment = []): FacilityRequest
    {
        static $number = 500;
        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-' . (++$number),
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Existing booking',
            'expected_participants' => 10,
            'start_date' => $this->day($startDay),
            'end_date' => $this->day($endDay),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'venue' => ['Gymnasium'],
            'equipment' => array_keys($equipment),
            'equipment_quantities' => $equipment,
            'requested_by_id' => $this->requestor->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);
        $request->syncRelationalItems();

        return $request;
    }

    private function venueFree(int $startDay, string $startTime, int $endDay, string $endTime): bool
    {
        return $this->availability->checkVenueAvailability('Gymnasium', $this->at($startDay, $startTime), $this->at($endDay, $endTime))['available'];
    }

    private function submit(int $startDay, string $startTime, int $endDay, string $endTime)
    {
        return $this->actingAs($this->requestor)->post(route('requestor.store'), [
            'department' => 'Test Department',
            'request_context' => 'personal',
            'name_of_activity' => 'Boundary test',
            'purpose' => 'Boundary test',
            'expected_participants' => 20,
            'venue' => ['Gymnasium'],
            'venue_schedules' => ['Gymnasium' => [
                'start_date' => $this->day($startDay), 'end_date' => $this->day($endDay),
                'start_time' => $startTime, 'end_time' => $endTime,
                'reservation_duration' => 'specific_time',
            ]],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
        ]);
    }

    public function test_schedules_split_into_days_and_nights_like_the_calendar(): void
    {
        $times = fn (array $ranges): array => array_map(fn (array $r): string => $r['start']->format('D H:i') . '-' . $r['end']->format('D H:i'), $ranges);

        $this->assertSame(['Mon 08:00-Mon 17:00', 'Tue 08:00-Tue 17:00', 'Wed 08:00-Wed 17:00'],
            $times(FacilityRequest::dailyRanges($this->at(0, '08:00'), $this->at(2, '17:00'))));
        $this->assertSame(['Mon 20:00-Tue 06:00', 'Tue 20:00-Wed 06:00'],
            $times(FacilityRequest::dailyRanges($this->at(0, '20:00'), $this->at(2, '06:00'))));
        $this->assertSame(['Mon 20:00-Tue 06:00'],
            $times(FacilityRequest::dailyRanges($this->at(0, '20:00'), $this->at(1, '06:00'))));
        $this->assertSame(['Mon 09:00-Mon 11:00'],
            $times(FacilityRequest::dailyRanges($this->at(0, '09:00'), $this->at(0, '11:00'))));
    }

    public function test_a_multi_day_booking_leaves_the_evenings_free(): void
    {
        $this->approvedBooking(0, '08:00', 2, '17:00');

        $this->assertTrue($this->venueFree(1, '18:00', 1, '20:00'));
        $this->assertTrue($this->venueFree(1, '17:00', 1, '19:00'), 'A booking may start when the previous day block ends.');
        $this->assertFalse($this->venueFree(1, '16:00', 1, '18:00'));
        $this->assertFalse($this->venueFree(2, '07:00', 2, '09:00'));
        $this->assertTrue($this->venueFree(3, '08:00', 3, '17:00'));
    }

    public function test_an_overnight_booking_blocks_the_night_across_midnight(): void
    {
        $this->approvedBooking(0, '20:00', 1, '06:00');

        $this->assertFalse($this->venueFree(0, '21:00', 0, '23:00'));
        $this->assertFalse($this->venueFree(0, '22:00', 1, '07:00'));
        $this->assertTrue($this->venueFree(1, '06:00', 1, '08:00'), 'The next morning is free once the night ends.');
        $this->assertTrue($this->venueFree(0, '08:00', 0, '20:00'), 'The same day is free until the night starts.');
    }

    public function test_a_several_night_booking_leaves_the_daytime_free(): void
    {
        $this->approvedBooking(0, '20:00', 2, '06:00');

        $this->assertTrue($this->venueFree(1, '09:00', 1, '17:00'));
        $this->assertFalse($this->venueFree(1, '22:00', 1, '23:00'));
        $this->assertTrue($this->venueFree(2, '20:00', 3, '06:00'));
    }

    public function test_equipment_is_counted_per_day_too(): void
    {
        $this->approvedBooking(0, '08:00', 2, '17:00', ['Projector' => 1]);

        $this->assertTrue($this->availability->checkEquipmentAvailability('Projector', 1, $this->at(1, '18:00'), $this->at(1, '20:00'))['available']);
        $this->assertFalse($this->availability->checkEquipmentAvailability('Projector', 1, $this->at(1, '10:00'), $this->at(1, '11:00'))['available']);
        $this->assertFalse($this->availability->checkEquipmentAvailability('Projector', 1, $this->at(1, '18:00'), $this->at(3, '10:00'))['available'],
            'A new multi-day booking is checked on each of its days.');
    }

    public function test_a_new_multi_day_request_is_checked_day_by_day(): void
    {
        $this->approvedBooking(1, '18:00', 1, '20:00');

        $this->submit(0, '08:00', 2, '17:00')->assertSessionHasNoErrors();
        $this->assertSame(1, FacilityRequest::where('name_of_activity', 'Boundary test')->count());

        $this->approvedBooking(4, '10:00', 4, '11:00');
        $this->submit(3, '08:00', 5, '17:00')->assertSessionHasErrors();
        $this->assertSame(1, FacilityRequest::where('name_of_activity', 'Boundary test')->count());
    }

    public function test_midnight_and_early_morning_limits(): void
    {
        $this->submit(0, '20:00', 1, '00:00')->assertSessionHasErrors('venue_schedules.Gymnasium.end_time');
        $this->submit(0, '20:00', 1, '05:00')->assertSessionHasErrors('venue_schedules.Gymnasium.end_time');
        $this->submit(0, '00:00', 0, '10:00')->assertSessionHasErrors('venue_schedules.Gymnasium.start_time');
        $this->submit(0, '20:00', 0, '10:00')->assertSessionHasErrors('venue_schedules.Gymnasium.end_date');
        $this->assertSame(0, FacilityRequest::count());

        $this->submit(0, '20:00', 1, '05:30')->assertSessionHasNoErrors();
        $request = FacilityRequest::query()->sole();
        $this->assertSame($this->day(1), $request->end_date->toDateString());
        $this->assertSame('05:30', substr((string) $request->end_time, 0, 5));
    }

    public function test_supply_office_cannot_propose_a_schedule_that_ends_before_it_starts(): void
    {
        $booking = $this->approvedBooking(0, '09:00', 0, '12:00');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('supply-office.requests.revise'), [
            'facility_request_id' => $booking->id,
            'start_date' => $this->day(3),
            'end_date' => $this->day(3),
            'start_time' => '18:00',
            'end_time' => '09:00',
            'venue' => ['Gymnasium'],
            'revision_reason' => 'Moving this booking to another day.',
        ])->assertSessionHasErrors(['end_time' => 'The end time must be after the start time. For an overnight booking, set the end date to the next day.']);

        $this->assertSame(0, RevisionHistory::count());
    }
}
