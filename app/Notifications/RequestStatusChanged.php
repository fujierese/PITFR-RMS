<?php
namespace App\Notifications;

use App\Models\FacilityRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;

class RequestStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public FacilityRequest $facilityRequest,
        public string $status,
        public string $notes = '',
        public ?string $actor = null,
        public ?string $venueCustodian = null,
        public array $equipmentCustodians = [],
        public ?string $supplyOffice = null,
        public ?string $conflictReason = null
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Build the consolidated notification message
     */
    private function buildConsolidatedMessage(): string
    {
        $message = '';
        
        if ($this->status === 'approved') {
            // Format: "{Venue}, {Equipment1}, {Equipment2}, and {Admin} approve your request"
            $actors = [];
            
            if ($this->venueCustodian) {
                $actors[] = $this->venueCustodian;
            }
            
            $actors = array_merge($actors, $this->equipmentCustodians);
            
            if ($this->supplyOffice) {
                $actors[] = $this->supplyOffice;
            }
            
            if (empty($actors)) {
                $message = "Your request has been approved";
            } elseif (count($actors) === 1) {
                $message = "{$actors[0]} approves your request";
            } else {
                $lastActor = array_pop($actors);
                $message = implode(', ', $actors) . ", and {$lastActor} approve your request";
            }
            
            $message .= ". Control No: {$this->facilityRequest->control_number}";
        } elseif ($this->status === 'rejected') {
            // Format: "{Venue Custodian} rejected your request"
            $rejecter = $this->venueCustodian ?? $this->supplyOffice ?? 'The system';
            $message = "{$rejecter} rejected your request. Control No: {$this->facilityRequest->control_number}";
        } elseif ($this->status === 'needs_reschedule') {
            // Format: "{Supply Office} rescheduling your reservation due to {reason}"
            $admin = $this->supplyOffice ?? 'Supply Office';
            $reason = $this->conflictReason ?? 'scheduling conflict';
            $message = "{$admin} rescheduling your reservation due to {$reason}. Control No: {$this->facilityRequest->control_number}";
        } else {
            $message = "Your request status has been updated to " . ucfirst($this->status) . ". Control No: {$this->facilityRequest->control_number}";
        }
        
        return $message;
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'request_id' => $this->facilityRequest->id,
            'control_number' => $this->facilityRequest->control_number,
            'activity' => $this->facilityRequest->name_of_activity,
            'status' => $this->status,
            'message' => $this->buildConsolidatedMessage(),
            'notes' => $this->notes,
            'actors' => [
                'venue_custodian' => $this->venueCustodian,
                'equipment_custodians' => $this->equipmentCustodians,
                'supply_office' => $this->supplyOffice,
            ],
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    public function toMail($notifiable): MailMessage
    {
        $statusLabel = match($this->status) {
            'approved'          => '✅ Request Approved',
            'rejected'          => '❌ Request Rejected',
            'needs_reschedule'  => '🔄 Rescheduling Required',
            'equipment_returned'=> '🔄 Equipment Returned',
            'request_cancelled' => '❌ Request Cancelled',
            default             => ucfirst($this->status),
        };

        $mail = (new MailMessage)
            ->subject("{$statusLabel} — {$this->facilityRequest->control_number}")
            ->greeting("Hello {$notifiable->name}!")
            ->line($this->buildConsolidatedMessage())
            ->line("**Activity:** {$this->facilityRequest->name_of_activity}")
            ->line("**Control No:** {$this->facilityRequest->control_number}")
            ->line("**Date:** {$this->facilityRequest->formatDateForDisplay($this->facilityRequest->start_date)}")
            ->line("**Time:** {$this->facilityRequest->formatTimeForDisplay($this->facilityRequest->start_time)}" . ($this->facilityRequest->end_time ? ' - ' . $this->facilityRequest->formatTimeForDisplay($this->facilityRequest->end_time) : ''))
            ->line("**Venue:** " . implode(', ', $this->facilityRequest->getVenueNames()))
            ->action('View My Requests', url('/requestor?tab=myRequests'));

        if ($this->notes) {
            $mail->line("**Notes:** {$this->notes}");
        }

        return $mail;
    }

    public function toArray($notifiable): array
    {
        return [
            'request_id'     => $this->facilityRequest->id,
            'control_number' => $this->facilityRequest->control_number,
            'activity'       => $this->facilityRequest->name_of_activity,
            'status'         => $this->status,
            'message'        => $this->buildConsolidatedMessage(),
            'notes'          => $this->notes,
            'actors' => [
                'venue_custodian' => $this->venueCustodian,
                'equipment_custodians' => $this->equipmentCustodians,
                'supply_office' => $this->supplyOffice,
            ],
        ];
    }
}