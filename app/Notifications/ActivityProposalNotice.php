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
 * Additional Part 2: short, clickable Activity Proposal notices. Remarks and full
 * details are shown on the request page (Activity Proposal section).
 */
class ActivityProposalNotice extends Notification implements ShouldQueueAfterCommit
{
    use Queueable, QueuesMailAfterCommit;
    use UsesWorkflowNotificationChannels;

    public const MESSAGES = [
        'final_submitted' => ['Final Proposal Submitted', 'A final activity proposal is ready for verification.'],
        'corrected_submitted' => ['Corrected Proposal Submitted', 'A corrected activity proposal is ready for verification.'],
        'requires_correction' => ['Proposal Requires Correction', 'Your activity proposal needs correction. View the remarks.'],
        'verified' => ['Proposal Verified', 'Your activity proposal has been verified.'],
        'deadline_assigned' => ['Proposal Deadline Set', 'A submission deadline was set for your activity proposal.'],
        'deadline_modified' => ['Proposal Deadline Changed', 'The submission deadline for your activity proposal changed.'],
        'deadline_extended' => ['Proposal Deadline Extended', 'The submission deadline for your activity proposal was extended.'],
        'upcoming_reminder' => ['Proposal Due Soon', 'Your final activity proposal is due soon.'],
        'final_reminder' => ['Proposal Due Tomorrow', 'Final reminder: your activity proposal is due on the next working day.'],
        'deadline_today' => ['Proposal Due Today', 'Your final activity proposal is due today.'],
        'overdue' => ['Proposal Overdue', 'The final activity proposal is overdue.'],
        'outstanding' => ['Proposal Still Outstanding', 'A final activity proposal is still outstanding.'],
    ];

    public function __construct(
        public FacilityRequest $facilityRequest,
        public string $kind,
        public string $noticeKey,
        public string $audience = 'requestor',
    ) {
    }

    public function via($notifiable): array
    {
        return $this->workflowChannels($notifiable, ['database', 'mail']);
    }

    private function parts(): array
    {
        return self::MESSAGES[$this->kind] ?? ['Activity Proposal Update', 'There is an update on an activity proposal.'];
    }

    public function toArray($notifiable): array
    {
        [$title, $body] = $this->parts();

        return [
            'request_id' => $this->facilityRequest->id,
            'control_number' => $this->facilityRequest->control_number,
            'activity' => $this->facilityRequest->name_of_activity,
            'status' => 'proposal_' . $this->kind,
            'title' => $title,
            'body' => $body,
            'decision' => true,
            'notice_key' => $this->noticeKey,
            'anchor' => 'activity-proposal',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        [$title, $body] = $this->parts();

        return (new MailMessage)
            ->subject("{$title} — {$this->facilityRequest->control_number}")
            ->greeting("Hello {$notifiable->name}!")
            ->line($body)
            ->action('View request details', route('request.show', ['id' => $this->facilityRequest->id]) . '#activity-proposal');
    }
}
