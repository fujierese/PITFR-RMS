<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EquipmentIssueReported extends Notification
{
    use Queueable;

    public function __construct(
        protected $equipment,
        protected string $issueType,
        protected int $quantityAffected,
        protected string $description,
        protected string $custodianName
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'equipment_id' => $this->equipment->id,
            'equipment_name' => $this->equipment->name,
            'issue_type' => $this->issueType,
            'quantity_affected' => $this->quantityAffected,
            'description' => $this->description,
            'custodian_name' => $this->custodianName,
            'message' => "Equipment '{$this->equipment->name}' issue reported: {$this->issueType} (Qty: {$this->quantityAffected})",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $issueLabel = match($this->issueType) {
            'damaged' => 'Damaged',
            'lost' => 'Lost/Missing',
            'non_functional' => 'Non-functional',
            default => 'Other Issue',
        };

        return (new MailMessage)
            ->subject("Equipment Issue Report: {$this->equipment->name}")
            ->greeting("Equipment issue has been reported")
            ->line("**Equipment:** {$this->equipment->name}")
            ->line("**Issue Type:** {$issueLabel}")
            ->line("**Quantity Affected:** {$this->quantityAffected}")
            ->line("**Reported By:** {$this->custodianName}")
            ->when($this->description, function ($mail) {
                return $mail->line("**Description:** {$this->description}");
            })
            ->action('Review in Dashboard', url('/admin'))
            ->line('Please review and take appropriate action.');
    }
}
