<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\RequestVenue;
use App\Models\ReservationSchedule;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\ReservationReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_reminders_to_requestor_custodian_and_supply_office_two_hours_before_start(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));

        $requestor = User::factory()->create([
            'name' => 'Requestor User',
            'role' => 'requestor',
        ]);

        $custodian = User::factory()->create([
            'name' => 'Custodian User',
            'role' => 'custodian',
        ]);

        $supplyOffice = User::factory()->create([
            'name' => 'Supply Office',
            'role' => 'supply_office',
        ]);

        $venue = Venue::create([
            'name' => 'CHIC Conference Hall',
            'custodian_id' => $custodian->id,
            'capacity' => 50,
        ]);

        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-001',
            'date_requested' => now()->toDateString(),
            'department' => 'BSIT',
            'name_of_activity' => 'Board Meeting',
            'expected_participants' => 20,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'requested_by_id' => $requestor->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'priority' => 'normal',
        ]);

        RequestVenue::create([
            'facility_request_id' => $request->id,
            'venue_id' => $venue->id,
            'name' => $venue->name,
        ]);

        ReservationSchedule::create([
            'facility_request_id' => $request->id,
            'start_datetime' => now()->copy()->addHours(2)->minute(0)->second(0),
            'end_datetime' => now()->copy()->addHours(3)->minute(0)->second(0),
        ]);

        $this->artisan('facility-requests:send-reminders')->assertSuccessful();

        Notification::assertSentTo($requestor, ReservationReminderNotification::class, function ($notification) use ($request): bool {
            return $notification->facilityRequest->is($request) && $notification->reminderType === 'two_hours_before';
        });

        Notification::assertSentTo($custodian, ReservationReminderNotification::class, function ($notification) use ($request): bool {
            return $notification->facilityRequest->is($request) && $notification->reminderType === 'two_hours_before';
        });

        Notification::assertSentTo($supplyOffice, ReservationReminderNotification::class, function ($notification) use ($request): bool {
            return $notification->facilityRequest->is($request) && $notification->reminderType === 'two_hours_before';
        });
    }

    public function test_it_sends_one_day_before_reminder_and_records_it_once(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));
        $request = $this->createApprovedReservation(now()->copy()->addDay());

        $this->artisan('facility-requests:send-reminders')->assertSuccessful();
        $this->artisan('facility-requests:send-reminders')->assertSuccessful();

        Notification::assertSentTo($request->requester, ReservationReminderNotification::class, function ($notification) use ($request): bool {
            return $notification->facilityRequest->is($request) && $notification->reminderType === 'one_day_before';
        });
        Notification::assertSentToTimes($request->requester, ReservationReminderNotification::class, 1);
        $this->assertSame(1, DB::table('reservation_reminder_logs')->where('facility_request_id', $request->id)->count());
    }

    public function test_it_sends_start_time_reminder_when_the_command_runs_after_start(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-01-01 10:05:00'));
        $request = $this->createApprovedReservation(Carbon::parse('2026-01-01 10:00:00'));

        $this->artisan('facility-requests:send-reminders')->assertSuccessful();

        Notification::assertSentTo($request->requester, ReservationReminderNotification::class, function ($notification) use ($request): bool {
            return $notification->facilityRequest->is($request) && $notification->reminderType === 'start_time';
        });
    }

    public function test_it_does_not_send_reminders_for_non_approved_requests(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));
        $requestor = User::factory()->create(['role' => 'requestor']);

        foreach (['pending', 'rejected'] as $status) {
            $request = $this->createApprovedReservation(now()->copy()->addHours(2), $requestor, $status);
            $this->assertSame($status, $request->status);
        }

        $this->artisan('facility-requests:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('reservation_reminder_logs')->count());
    }

    public function test_it_detects_a_reminder_after_a_small_scheduler_delay(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-01-01 10:14:00'));
        $request = $this->createApprovedReservation(Carbon::parse('2026-01-01 12:00:00'));

        $this->artisan('facility-requests:send-reminders')->assertSuccessful();

        Notification::assertSentTo($request->requester, ReservationReminderNotification::class, function ($notification) use ($request): bool {
            return $notification->facilityRequest->is($request) && $notification->reminderType === 'two_hours_before';
        });
    }

    public function test_reminders_for_multiple_reservations_are_independent(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));
        $firstRequest = $this->createApprovedReservation(now()->copy()->addHours(2));
        $secondRequest = $this->createApprovedReservation(now()->copy()->addHours(2));

        $this->artisan('facility-requests:send-reminders')->assertSuccessful();

        Notification::assertSentTo($firstRequest->requester, ReservationReminderNotification::class);
        Notification::assertSentTo($secondRequest->requester, ReservationReminderNotification::class);
        $this->assertSame(2, DB::table('reservation_reminder_logs')->where('reminder_type', 'two_hours_before')->count());
    }

    public function test_a_changed_schedule_uses_a_new_reminder_key(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));
        $request = $this->createApprovedReservation(now()->copy()->addHours(2));

        $this->artisan('facility-requests:send-reminders')->assertSuccessful();
        $request->reservationSchedule->update([
            'start_datetime' => now()->copy()->addHours(3),
        ]);

        Carbon::setTestNow(Carbon::parse('2026-01-01 11:00:00'));
        $this->artisan('facility-requests:send-reminders')->assertSuccessful();

        Notification::assertSentToTimes($request->requester, ReservationReminderNotification::class, 2);
        $this->assertSame(2, DB::table('reservation_reminder_logs')->where('facility_request_id', $request->id)->count());
    }

    private function createApprovedReservation(Carbon $start, ?User $requestor = null, string $status = 'approved'): FacilityRequest
    {
        $requestor ??= User::factory()->create(['role' => 'requestor']);

        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-' . fake()->unique()->numerify('###'),
            'date_requested' => now()->toDateString(),
            'department' => 'BSIT',
            'name_of_activity' => 'Reminder Test Activity',
            'expected_participants' => 10,
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
            'start_time' => $start->format('H:i'),
            'end_time' => $start->copy()->addHour()->format('H:i'),
            'requested_by_id' => $requestor->id,
            'status' => $status,
            'venue_status' => $status,
            'equipment_status' => $status,
            'priority' => 'regular',
        ]);

        ReservationSchedule::create([
            'facility_request_id' => $request->id,
            'start_datetime' => $start,
            'end_datetime' => $start->copy()->addHour(),
        ]);

        return $request->fresh(['requester', 'reservationSchedule']);
    }
}
