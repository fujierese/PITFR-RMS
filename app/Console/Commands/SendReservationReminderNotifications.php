<?php

namespace App\Console\Commands;

use App\Models\FacilityRequest;
use App\Models\ReservationSchedule;
use App\Models\User;
use App\Notifications\ReservationReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SendReservationReminderNotifications extends Command
{
    protected $signature = 'facility-requests:send-reminders';

    protected $description = 'Send reservation reminder notifications for approved requests that are upcoming.';

    public function handle(): int
    {
        $now = Carbon::now();
        $reminders = [
            'one_day_before' => $now->copy()->addDay(),
            'two_hours_before' => $now->copy()->addHours(2),
            'start_time' => $now,
        ];

        foreach ($reminders as $reminderType => $targetTime) {
            $this->sendRemindersForWindow($reminderType, $targetTime, $now);
        }

        return self::SUCCESS;
    }

    private function sendRemindersForWindow(string $reminderType, Carbon $targetTime, Carbon $now): void
    {
        $windowStart = $targetTime->copy()->startOfMinute();
        $windowEnd = $targetTime->copy()->addMinute()->startOfMinute();

        $requests = FacilityRequest::query()
            ->where('status', 'approved')
            ->whereHas('reservationSchedule', function ($query) use ($windowStart, $windowEnd): void {
                $query->whereBetween('start_datetime', [$windowStart->toDateTimeString(), $windowEnd->toDateTimeString()]);
            })
            ->with(['requester', 'reservationSchedule'])
            ->get();

        foreach ($requests as $request) {
            $schedule = $request->reservationSchedule;
            if (!$schedule || !$schedule->start_datetime) {
                continue;
            }

            if ($this->alreadyNotified($request, $reminderType, $schedule->start_datetime)) {
                continue;
            }

            $recipients = $this->getRecipients($request);
            if ($recipients->isEmpty()) {
                continue;
            }

            Notification::send($recipients, new ReservationReminderNotification($request, $reminderType, $schedule->start_datetime));

            $this->markNotified($request, $reminderType, $schedule->start_datetime, $now);
        }
    }

    private function getRecipients(FacilityRequest $request): \Illuminate\Support\Collection
    {
        $recipients = collect();

        if ($request->requester) {
            $recipients->push($request->requester);
        }

        $custodianIds = collect();
        foreach ($request->getVenueNames() as $venueName) {
            $venue = \App\Models\Venue::query()->where('name', $venueName)->first();
            if ($venue && $venue->custodian_id) {
                $custodianIds->push($venue->custodian_id);
            }
        }

        foreach ($custodianIds->unique()->all() as $custodianId) {
            $custodian = User::find($custodianId);
            if ($custodian) {
                $recipients->push($custodian);
            }
        }

        $supplyOffice = User::query()->where('role', 'supply_office')->orWhere('role', 'admin')->get();
        foreach ($supplyOffice as $user) {
            $recipients->push($user);
        }

        return $recipients->unique('id')->values();
    }

    private function alreadyNotified(FacilityRequest $request, string $reminderType, Carbon $startTime): bool
    {
        return DB::table('reservation_reminder_logs')->where([
            'facility_request_id' => $request->id,
            'reminder_type' => $reminderType,
            'scheduled_for' => $startTime->toDateTimeString(),
        ])->exists();
    }

    private function markNotified(FacilityRequest $request, string $reminderType, Carbon $startTime, Carbon $sentAt): void
    {
        DB::table('reservation_reminder_logs')->insert([
            'facility_request_id' => $request->id,
            'reminder_type' => $reminderType,
            'scheduled_for' => $startTime->toDateTimeString(),
            'sent_at' => $sentAt->toDateTimeString(),
            'created_at' => $sentAt->toDateTimeString(),
            'updated_at' => $sentAt->toDateTimeString(),
        ]);
    }
}
