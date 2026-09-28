<?php
namespace App\Notifications;

use App\Models\FacilityRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewFacilityRequestNotification extends Notification
{
    use Queueable;

    public function __construct(public FacilityRequest $facilityRequest, public ?string $actor = null)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $resource = method_exists($notifiable, 'assignedCustodianResourceLabel')
            ? $notifiable->assignedCustodianResourceLabel()
            : '';

        $body = $this->actor
            ? "A new request was submitted by {$this->actor}."
            : 'A new request is waiting for your verification.';

        return [
            'request_id' => $this->facilityRequest->id,
            'control_number' => $this->facilityRequest->control_number,
            'activity' => $this->facilityRequest->name_of_activity,
            'status' => 'new_request',
            'title' => 'New Request Submitted',
            'body' => $body,
            'resource' => $resource ?: 'Assigned resource',
            'message' => $body,
        ];
    }
}
