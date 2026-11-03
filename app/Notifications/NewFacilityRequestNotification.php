<?php
namespace App\Notifications;

use App\Models\FacilityRequest;
use App\Notifications\Concerns\UsesWorkflowNotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewFacilityRequestNotification extends Notification
{
    use Queueable;
    use UsesWorkflowNotificationChannels;

    public function __construct(public FacilityRequest $facilityRequest, public ?string $actor = null)
    {
    }

    public function via($notifiable): array
    {
        return $this->workflowChannels($notifiable);
    }

    public function toMail($notifiable): MailMessage
    {
        $requestorName = $this->actor ?? $this->facilityRequest->requester?->name ?? 'Unknown requestor';

        return (new MailMessage)
            ->subject('New facility request: ' . $this->facilityRequest->control_number)
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . '!')
            ->line($requestorName . ' submitted a facility request for "' . ($this->facilityRequest->name_of_activity ?: 'Untitled activity') . '".')
            ->line('Control number: ' . $this->facilityRequest->control_number)
            ->action('Review request', route('request.show', $this->facilityRequest));
    }

    public function toArray($notifiable): array
    {
        $requestorName = $this->actor ?? $this->facilityRequest->requester?->name ?? 'Unknown requestor';
        $activityName = $this->facilityRequest->name_of_activity ?: 'Untitled activity';
        $body = "A new request from {$requestorName} for \"{$activityName}\" is waiting for your verification.";

        return [
            'request_id' => $this->facilityRequest->id,
            'control_number' => $this->facilityRequest->control_number,
            'activity' => $activityName,
            'requestor_name' => $requestorName,
            'status' => 'new_request',
            'title' => 'New Request Submitted',
            'body' => $body,
            'message' => $body,
        ];
    }
}
