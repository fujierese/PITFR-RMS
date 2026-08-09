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

    private const LATE_TOLERANCE_MINUTES = 15;

    public function handle(): int
    {
        $now = Carbon::now();
        $comparisonNow = $now->copy()->startOfMinute();
        $reminders = [
            'one_day_before' => $comparisonNow->copy()->addDay(),
            'two_hours_before' => $comparisonNow->copy()->addHours(2),
            'start_time' => $comparisonNow,
        ];

        foreach ($reminders as $reminderType => $targetTime) {
            $this->sendRemindersForWindow($reminderType, $targetTime, $now);
        }

        return self::SUCCESS;
    }

    private function sendRemindersForWindow(string $reminderType, Carbon $targetTime, Carbon $now): void
    {
        $dueSince = $targetTime->copy()->subMinutes(self::LATE_TOLERANCE_MINUTES);

        $requests = FacilityRequest::query()
            ->where('status', 'approved')
            ->whereHas('reservationSchedule', function ($query) use ($dueSince, $targetTime): void {
                $query->where('start_datetime', '>=', $dueSince->toDateTimeString())
                    ->where('start_datetime', '<=', $targetTime->toDateTimeString());
            })
            ->with(['requester', 'reservationSchedule'])
            ->get();

        foreach ($requests as $request) {
            $schedule = $request->reservationSchedule;
            if (!$schedule || !$schedule->start_datetime) {
                continue;
            }

            if (!$this->claimReminder($request, $reminderType, $schedule->start_datetime, $now)) {
                continue;
            }

            try {
                $recipients = $this->getRecipients($request);
                if ($recipients->isEmpty()) {
                    $this->removeReminderClaim($request, $reminderType, $schedule->start_datetime);
                    continue;
                }

                Notification::send($recipients, new ReservationReminderNotification($request, $reminderType, $schedule->start_datetime));
            } catch (\Throwable $exception) {
                $this->removeReminderClaim($request, $reminderType, $schedule->start_datetime);
                throw $exception;
            }
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

    private function claimReminder(FacilityRequest $request, string $reminderType, Carbon $startTime, Carbon $sentAt): bool
    {
        return DB::table('reservation_reminder_logs')->insertOrIgnore([
            'facility_request_id' => $request->id,
            'reminder_type' => $reminderType,
            'scheduled_for' => $startTime->toDateTimeString(),
            'sent_at' => $sentAt->toDateTimeString(),
            'created_at' => $sentAt->toDateTimeString(),
            'updated_at' => $sentAt->toDateTimeString(),
        ]) === 1;
    }

    private function removeReminderClaim(FacilityRequest $request, string $reminderType, Carbon $startTime): void
    {
        DB::table('reservation_reminder_logs')->where([
            'facility_request_id' => $request->id,
            'reminder_type' => $reminderType,
            'scheduled_for' => $startTime->toDateTimeString(),
        ])->delete();
    }
}
