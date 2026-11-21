<?php

namespace App\Notifications;

use App\Models\Venue;
use App\Notifications\Concerns\QueuesMailAfterCommit;
use App\Notifications\Concerns\UsesWorkflowNotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * REQ-20: tells a custodian they now look after a venue that already has pending or
 * upcoming bookings.
 */
class VenueCustodianAssigned extends Notification implements ShouldQueueAfterCommit
{
    use Queueable, QueuesMailAfterCommit;
    use UsesWorkflowNotificationChannels;

    public function __construct(
        public Venue $venue,
        public int $bookingCount,
        public string $controlNumbers,
    ) {}

    public function via($notifiable): array
    {
        return $this->workflowChannels($notifiable);
    }

    private function message(): string
    {
        return "You are now the custodian of {$this->venue->name}. It has {$this->bookingCount} pending or upcoming booking(s)"
            . ($this->controlNumbers !== '' ? " ({$this->controlNumbers})" : '')
            . '. Requests still waiting for a venue confirmation are in your queue.';
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You are now the custodian of {$this->venue->name}")
            ->line($this->message())
            ->action('Open your dashboard', url('/'));
    }

    public function toDatabase($notifiable): array
    {
        return [
            'resource_type' => 'venue',
            'resource_name' => $this->venue->name,
            'resource_id' => $this->venue->id,
            'status' => 'assigned',
            'message' => $this->message(),
        ];
    }
}
