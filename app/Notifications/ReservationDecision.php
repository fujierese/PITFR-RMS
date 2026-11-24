<?php

namespace App\Notifications;

use App\Models\FacilityRequest;
use App\Notifications\Concerns\QueuesMailAfterCommit;
use App\Notifications\Concerns\UsesWorkflowNotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Additional Part 2: a short, clickable notice about a Supply Office decision. The
 * full explanation is shown on the request page (Administrative decisions), not in
 * the notification, and nothing about another requestor is included.
 */
class ReservationDecision extends Notification implements ShouldQueueAfterCommit
{
    use Queueable, QueuesMailAfterCommit;
    use UsesWorkflowNotificationChannels;

    /** Short preview per decision: [title, requestor text, custodian text]. */
    public const MESSAGES = [
        'reservation_affected' => ['Reservation Affected', 'Your reservation requires administrative adjustment.', 'A reservation you handle requires administrative adjustment.'],
        'alternative_venue_assigned' => ['Alternative Venue Assigned', 'An alternative venue has been assigned.', 'A reservation\'s venue changed. Check your assignments.'],
        'reservation_rescheduled' => ['Reservation Rescheduled', 'Your reservation schedule has been updated.', 'A reservation you handle was rescheduled.'],
        'reservation_declined' => ['Reservation Declined', 'Your reservation has been declined. View the reason for details.', 'A reservation you handle was declined.'],
        'revision_requested' => ['Revision Requested', 'Please correct your reservation request. View the details.', 'A reservation you handle was returned for revision.'],
    ];

    public function __construct(
        public FacilityRequest $facilityRequest,
        public string $kind,
        public string $decisionKey,
        public string $audience = 'requestor',
    ) {
    }

    public function via($notifiable): array
    {
        return $this->workflowChannels($notifiable, ['database', 'mail']);
    }

    private function parts(): array
    {
        return self::MESSAGES[$this->kind] ?? ['Reservation Update', 'Your reservation was updated.', 'A reservation you handle was updated.'];
    }

    public function toArray($notifiable): array
    {
        [$title, $forRequestor, $forCustodian] = $this->parts();

        return [
            'request_id' => $this->facilityRequest->id,
            'control_number' => $this->facilityRequest->control_number,
            'activity' => $this->facilityRequest->name_of_activity,
            'status' => $this->kind,
            'title' => $title,
            'body' => $this->audience === 'custodian' ? $forCustodian : $forRequestor,
            'decision' => true,
            'decision_key' => $this->decisionKey,
            'anchor' => 'administrative-decisions',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        [$title, $forRequestor, $forCustodian] = $this->parts();

        return (new MailMessage)
            ->subject("{$title} — {$this->facilityRequest->control_number}")
            ->greeting("Hello {$notifiable->name}!")
            ->line($this->audience === 'custodian' ? $forCustodian : $forRequestor)
            ->action('View request details', route('request.show', ['id' => $this->facilityRequest->id]) . '#administrative-decisions');
    }
}
