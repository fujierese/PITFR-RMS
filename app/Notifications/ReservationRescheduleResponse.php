<?php

namespace App\Notifications;

use App\Models\RevisionHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Notifications\Concerns\UsesNotificationPreferences;

class ReservationRescheduleResponse extends Notification implements ShouldQueue
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
        $accepted = $this->revision->status === 'accepted';

        return (new MailMessage)
            ->subject('Schedule proposal ' . ($accepted ? 'accepted: ' : 'declined: ') . $request->control_number)
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . '!')
            ->line('The requestor ' . ($accepted ? 'accepted' : 'declined') . ' the proposed schedule change for reservation ' . $request->control_number . '.')
            ->action('View Reservation', route('request.show', $request));
    }

    public function toArray(object $notifiable): array
    {
        $accepted = $this->revision->status === 'accepted';
        $request = $this->revision->facilityRequest;
        $message = 'The requestor ' . ($accepted ? 'accepted' : 'declined') . ' the proposed schedule change.';

        return [
            'facility_request_id' => $request->id,
            'revision_id' => $this->revision->id,
            'control_number' => $request->control_number,
            'title' => $accepted ? 'Schedule proposal accepted' : 'Schedule proposal declined',
            'message' => $message,
            'status' => 'reschedule_' . $this->revision->status,
            'route' => route('request.show', $request),
        ];
    }
}
