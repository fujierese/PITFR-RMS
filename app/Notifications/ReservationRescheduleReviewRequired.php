<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use App\Notifications\Concerns\QueuesMailAfterCommit;
use Illuminate\Bus\Queueable;
use App\Models\RevisionHistory;
use App\Notifications\Concerns\UsesWorkflowNotificationChannels;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationRescheduleReviewRequired extends Notification implements ShouldQueueAfterCommit
{
    use UsesWorkflowNotificationChannels, Queueable, QueuesMailAfterCommit;

    public function __construct(
        public RevisionHistory $revision,
        public string $acceptedByName,
    ) {
    }

    public function via(object $notifiable): array
    {
        return $this->workflowChannels($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Custodian review needed: ' . $this->revision->facilityRequest->control_number)
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . '!')
            ->line($this->acceptedByName . ' accepted the suggested new schedule.')
            ->line('Please review the suggested schedule and confirm whether your assigned venue or equipment is available.')
            ->line('The current reservation remains unchanged until all required responses are received.')
            ->action('Review proposal', route('request.show', $this->revision->facilityRequest));
    }

    public function toArray(object $notifiable): array
    {
        $request = $this->revision->facilityRequest;

        return [
            'facility_request_id' => $request->id,
            'revision_id' => $this->revision->id,
            'control_number' => $request->control_number,
            'title' => 'Custodian review needed',
            'message' => $this->acceptedByName . ' accepted the suggested schedule. Review availability and respond.',
            'proposed_schedule' => $this->formatSchedule(),
            'status' => 'reschedule_review_required',
            'route' => route('request.show', $request),
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
