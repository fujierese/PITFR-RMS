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
        public string $notes = ''
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'request_id' => $this->facilityRequest->id,
            'control_number' => $this->facilityRequest->control_number,
            'activity' => $this->facilityRequest->name_of_activity,
            'status' => $this->status,
            'notes' => $this->notes,
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    public function toMail($notifiable): MailMessage
    {
        $statusLabel = match($this->status) {
            'approved'          => '✅ Approved',
            'rejected'          => '❌ Rejected',
            'venue_approved'    => '🏛️ Venue Approved by Custodian',
            'equipment_approved'=> '🔧 Equipment Approved by Custodian',
            'venue_rejected'    => '🏛️ Venue Rejected by Custodian',
            'equipment_rejected'=> '🔧 Equipment Rejected by Custodian',
            'equipment_returned'=> '🔄 Equipment Returned',
            'request_cancelled' => '❌ Request Cancelled',
            'needs_reschedule'  => '🔄 Needs Reschedule',
            default             => ucfirst($this->status),
        };

        $mail = (new MailMessage)
            ->subject("Request {$statusLabel} — {$this->facilityRequest->control_number}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("Your request **{$this->facilityRequest->name_of_activity}** has been updated.")
            ->line("**Status:** {$statusLabel}")
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
            'notes'          => $this->notes,
        ];
    }
}