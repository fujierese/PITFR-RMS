<?php
namespace App\Notifications;

use App\Models\Venue;
use App\Models\Equipment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResourceStatusChanged extends Notification
{
    use Queueable;

    /**
     * @param Venue|Equipment $resource
     * @param string $status 'enabled' or 'disabled'
     * @param string $resourceType 'venue' or 'equipment'
     */
    public function __construct(
        public Venue|Equipment $resource,
        public string $status,
        public string $resourceType,
        public string $custodianName = '',
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $action = $this->status === 'enabled' ? 'enabled' : 'disabled';
        $resourceName = $this->resource->name;
        $custodian = $this->custodianName;

        $subject = match($this->resourceType) {
            'venue' => "Venue {$action}: {$resourceName}",
            'equipment' => "Equipment {$action}: {$resourceName}",
            default => "Resource {$action}: {$resourceName}"
        };

        $message = match($this->resourceType) {
            'venue' => "The venue \"{$resourceName}\" has been {$action} by {$custodian}.",
            'equipment' => "The equipment \"{$resourceName}\" has been {$action} by {$custodian}.",
            default => "A resource has been {$action}."
        };

        if ($this->status === 'disabled') {
            $message .= ' It is no longer available for reservations.';
        } elseif ($this->status === 'enabled') {
            $message .= ' It is now available for reservations again.';
        }

        return (new MailMessage)
            ->subject($subject)
            ->line($message)
            ->action('View Dashboard', url('/'));
    }

    public function toDatabase($notifiable): array
    {
        return [
            'resource_type' => $this->resourceType,
            'resource_name' => $this->resource->name,
            'resource_id' => $this->resource->id,
            'status' => $this->status,
            'custodian_name' => $this->custodianName,
            'message' => match($this->resourceType) {
                'venue' => "Venue \"{$this->resource->name}\" has been {$this->status}.",
                'equipment' => "Equipment \"{$this->resource->name}\" has been {$this->status}.",
                default => "A resource has been {$this->status}."
            },
        ];
    }
}
