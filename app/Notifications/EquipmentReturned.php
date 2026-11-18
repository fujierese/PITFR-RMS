<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use App\Notifications\Concerns\QueuesMailAfterCommit;
use App\Notifications\Concerns\UsesWorkflowNotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EquipmentReturned extends Notification implements ShouldQueueAfterCommit
{
    use Queueable, QueuesMailAfterCommit;
    use UsesWorkflowNotificationChannels;

    protected $equipment;
    protected $custodian;
    protected $quantityReturned;
    protected $condition;
    protected $remarks;

    public function __construct($equipment, $custodian, $quantityReturned, $condition, $remarks = null)
    {
        $this->equipment = $equipment;
        $this->custodian = $custodian;
        $this->quantityReturned = $quantityReturned;
        $this->condition = $condition;
        $this->remarks = $remarks;
    }

    public function via(object $notifiable): array
    {
        return $this->workflowChannels($notifiable);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'equipment_id' => $this->equipment->id,
            'equipment_name' => $this->equipment->name,
            'quantity_returned' => $this->quantityReturned,
            'condition' => ucfirst($this->condition),
            'remarks' => $this->remarks,
            'custodian_name' => $this->custodian->name,
            'message' => "Equipment '{$this->equipment->name}' returned by Custodian {$this->custodian->name} - Condition: {$this->condition}, Quantity: {$this->quantityReturned}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $conditionLabels = [
            'good' => 'Good',
            'acceptable' => 'Acceptable',
            'poor' => 'Poor',
        ];

        return (new MailMessage)
            ->subject("Equipment Returned - {$this->equipment->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Equipment has been returned by Custodian **{$this->custodian->name}**.")
            ->line("**Equipment:** {$this->equipment->name}")
            ->line("**Quantity Returned:** {$this->quantityReturned}")
            ->line("**Condition:** " . ($conditionLabels[$this->condition] ?? ucfirst($this->condition)))
            ->when($this->remarks, fn($mail) => $mail->line("**Remarks:** {$this->remarks}"))
            ->action('View Details', route('admin.dashboard'))
            ->line('Please review and process this equipment return accordingly.');
    }
}
