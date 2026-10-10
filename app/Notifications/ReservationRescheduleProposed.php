<?php

namespace App\Notifications;

use App\Models\RevisionHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Notifications\Concerns\UsesNotificationPreferences;

class ReservationRescheduleProposed extends Notification implements ShouldQueue
{
    use Queueable;
    use UsesNotificationPreferences;

    public function __construct(public RevisionHistory $revision)
    {
    }

    public function via(object $notifiable): array
    {
        return $this->channelsWithPreference($notifiable, 'request_updates', ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->revision->facilityRequest;

        return (new MailMessage)
            ->subject('Schedule change proposal: ' . $request->control_number)
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . '!')
            ->line('The Supply Office proposed a schedule change for reservation ' . $request->control_number . '.')
            ->line('Proposed schedule: ' . $this->formatSchedule())
            ->line('Reason: ' . $this->revision->revision_reason)
            ->line('Your current reservation will remain unchanged until you respond.')
            ->action('Review proposal', route('request.show', $request));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'facility_request_id' => $this->revision->facility_request_id,
            'revision_id' => $this->revision->id,
            'control_number' => $this->revision->facilityRequest->control_number,
            'title' => 'Schedule change proposed',
            'message' => 'The Supply Office proposed a new schedule. Review it and accept or decline the change.',
            'proposed_schedule' => $this->formatSchedule(),
            'revision_reason' => $this->revision->revision_reason,
            'status' => 'reschedule_proposed',
            'route' => route('request.show', $this->revision->facilityRequest),
        ];
    }

    private function formatSchedule(): string
    {
        $date = $this->revision->new_start_date?->format('F j, Y') ?? 'TBD';
        $endDate = $this->revision->new_end_date?->format('F j, Y');
        $time = ($this->revision->new_start_time ?? 'TBD') . '–' . ($this->revision->new_end_time ?? 'TBD');
        $venues = implode(', ', $this->revision->new_venue ?? []);

        return $date . ($endDate && $endDate !== $date ? ' to ' . $endDate : '') . ', ' . $time . ' at ' . $venues;
    }
}
