<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RequestRejected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $requestId;
    public $controlNumber;
    public $rejectionType;
    public $reason;
    public $userName;

    /**
     * Create a new event instance.
     */
    public function __construct($requestId, $controlNumber, $rejectionType, $reason, $userName)
    {
        $this->requestId = $requestId;
        $this->controlNumber = $controlNumber;
        $this->rejectionType = $rejectionType;
        $this->reason = $reason;
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
        return 'request.rejected';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'request_rejected',
            'request_id' => $this->requestId,
            'control_number' => $this->controlNumber,
            'rejection_type' => $this->rejectionType,
            'reason' => $this->reason,
            'user_name' => $this->userName,
            'timestamp' => now()->toISOString(),
        ];
    }
}
