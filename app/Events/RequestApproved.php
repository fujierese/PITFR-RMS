<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RequestApproved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $requestId;
    public $controlNumber;
    public $approvalType;
    public $userName;
    public $ownerId;
    public $custodianIds = [];

    /**
     * Create a new event instance.
     */
    public function __construct($requestId, $controlNumber, $approvalType, $userName, $ownerId, array $custodianIds = [])
    {
        $this->requestId = $requestId;
        $this->controlNumber = $controlNumber;
        $this->approvalType = $approvalType;
        $this->userName = $userName;
        $this->ownerId = $ownerId;
        $this->custodianIds = array_values(array_map('intval', $custodianIds));
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [new Channel('facility-requests.admin'), new PrivateChannel('App.Models.User.' . $this->ownerId)];
        foreach ($this->custodianIds as $cid) {
            $channels[] = new PrivateChannel('facility-requests.custodian.' . (int) $cid);
        }
        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'request.approved';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $ts = now()->toISOString();
        return [
            'type' => 'request_approved',
            'request_id' => $this->requestId,
            'control_number' => $this->controlNumber,
            'approval_type' => $this->approvalType,
            'user_name' => $this->userName,
            'timestamp' => $ts,
            'event_uid' => sha1($this->broadcastAs() . ':' . $this->requestId . ':' . $ts),
        ];
    }
}
