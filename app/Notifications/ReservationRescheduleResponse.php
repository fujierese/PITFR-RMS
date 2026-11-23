<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use App\Notifications\Concerns\QueuesMailAfterCommit;
use Illuminate\Bus\Queueable;
use App\Models\RevisionHistory;
use App\Notifications\Concerns\UsesWorkflowNotificationChannels;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationRescheduleResponse extends Notification implements ShouldQueueAfterCommit
{
    use UsesWorkflowNotificationChannels, Queueable, QueuesMailAfterCommit;

    public function __construct(public RevisionHistory $revision)
    {
    }

    public function via(object $notifiable): array
    {
        return $this->workflowChannels($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->revision->facilityRequest;
        $accepted = $this->revision->status === 'accepted';

        return (new MailMessage)
            ->subject('Suggested schedule ' . ($accepted ? 'accepted: ' : 'declined: ') . $request->control_number)
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . '!')
            ->line('The requestor ' . ($accepted ? 'accepted' : 'declined') . ' the suggested new schedule for reservation ' . $request->control_number . '.')
            ->action('View Reservation', route('request.show', $request));
    }

    public function toArray(object $notifiable): array
    {
        $accepted = $this->revision->status === 'accepted';
        $request = $this->revision->facilityRequest;
        $message = 'The requestor ' . ($accepted ? 'accepted' : 'declined') . ' the suggested new schedule.';

        return [
            'facility_request_id' => $request->id,
            'revision_id' => $this->revision->id,
            'control_number' => $request->control_number,
            'title' => $accepted ? 'Suggested schedule accepted' : 'Suggested schedule declined',
            'message' => $message,
            'status' => 'reschedule_' . $this->revision->status,
            'route' => route('request.show', $request),
        ];
    }
}
