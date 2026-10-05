<?php
namespace App\Notifications;

use App\Models\FacilityRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Notifications\Concerns\UsesNotificationPreferences;

class NewFacilityRequestNotification extends Notification
{
    use Queueable;
    use UsesNotificationPreferences;

    public function __construct(public FacilityRequest $facilityRequest, public ?string $actor = null)
    {
    }

    public function via($notifiable): array
    {
        return $this->channelsWithPreference($notifiable, 'request_updates', ['database']);
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
