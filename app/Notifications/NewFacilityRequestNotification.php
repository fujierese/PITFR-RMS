<?php
namespace App\Notifications;

use App\Models\FacilityRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewFacilityRequestNotification extends Notification
{
    use Queueable;

    public function __construct(public FacilityRequest $facilityRequest)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'request_id' => $this->facilityRequest->id,
            'control_number' => $this->facilityRequest->control_number,
            'activity' => $this->facilityRequest->name_of_activity,
            'status' => 'new_request',
            'message' => 'A new facility request has been submitted and needs your review.',
        ];
    }
}
