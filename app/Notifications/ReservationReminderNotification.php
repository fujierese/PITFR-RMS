<?php

namespace App\Notifications;

use App\Models\FacilityRequest;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public FacilityRequest $facilityRequest,
        public string $reminderType,
        public Carbon $scheduledFor
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $label = match ($this->reminderType) {
            'one_day_before' => '1 day before the reservation',
            'two_hours_before' => '2 hours before the reservation',
            'start_time' => 'now that the reservation has started',
            default => 'for your reservation',
        };

        return (new MailMessage)
            ->subject('Reservation reminder: ' . $this->facilityRequest->control_number)
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . '!')
            ->line('This is a reminder for reservation ' . $this->facilityRequest->control_number . ' ' . $label . '.')
            ->line('Activity: ' . $this->facilityRequest->name_of_activity)
            ->line('Venue: ' . implode(', ', $this->facilityRequest->getVenueNames()))
            ->line('Scheduled for: ' . $this->scheduledFor->format('F j, Y g:i A'))
            ->action('View Reservation', url('/request/' . $this->facilityRequest->id));
    }

    public function toArray($notifiable): array
    {
        $title = match ($this->reminderType) {
            'one_day_before' => 'Reservation Reminder',
            'two_hours_before' => 'Reservation Reminder',
            'start_time' => 'Reservation Started',
            default => 'Reservation Update',
        };

        $body = match ($this->reminderType) {
            'one_day_before' => 'Your reservation is scheduled for tomorrow.',
            'two_hours_before' => 'Your reservation begins in 2 hours.',
            'start_time' => 'Your reservation has started.',
            default => 'Your reservation status has been updated.',
        };

        return [
            'request_id' => $this->facilityRequest->id,
            'control_number' => $this->facilityRequest->control_number,
            'activity' => $this->facilityRequest->name_of_activity,
            'status' => 'reservation_reminder',
            'title' => $title,
            'body' => $body,
            'message' => $body,
            'reminder_type' => $this->reminderType,
            'scheduled_for' => $this->scheduledFor->toDateTimeString(),
        ];
    }
}
