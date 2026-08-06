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

    /**
     * Create a new event instance.
     */
    public function __construct($requestId, $controlNumber, $approvalType, $userName)
    {
        $this->requestId = $requestId;
        $this->controlNumber = $controlNumber;
        $this->approvalType = $approvalType;
        $this->userName = $userName;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('facility-requests'),
        ];
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
        return [
            'type' => 'request_approved',
            'request_id' => $this->requestId,
            'control_number' => $this->controlNumber,
            'approval_type' => $this->approvalType,
            'user_name' => $this->userName,
            'timestamp' => now()->toISOString(),
        ];
    }
}
