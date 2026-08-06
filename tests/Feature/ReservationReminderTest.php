<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\RequestVenue;
use App\Models\ReservationSchedule;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\ReservationReminderNotification;
use Carbon\Carbon;
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
}
