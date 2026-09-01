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
    private function buildConsolidatedMessage($notifiable = null): string
    {
        $message = '';
        $recipientType = $this->determineRecipientType($notifiable);

        if ($this->status === 'approved') {
            if ($recipientType === 'requestor') {
                $message = 'Your request has been approved.';
            } elseif ($recipientType === 'venue_custodian') {
                $message = 'The venue portion of this request has been approved.';
            } elseif ($recipientType === 'equipment_custodian') {
                $message = 'The equipment portion of this request has been approved.';
            } else {
                $actors = [];

                if ($this->venueCustodian) {
                    $actors[] = $this->venueCustodian;
                }

                $actors = array_merge($actors, $this->equipmentCustodians);

                if ($this->supplyOffice) {
                    $actors[] = $this->supplyOffice;
                }

                if (empty($actors)) {
                    $message = 'Your request has been approved.';
                } elseif (count($actors) === 1) {
                    $message = "{$actors[0]} approves this request.";
                } else {
                    $lastActor = array_pop($actors);
                    $message = implode(', ', $actors) . ", and {$lastActor} approve this request.";
                }
            }

            $message .= " Control No: {$this->facilityRequest->control_number}";
            if ($this->supplyOffice) {
                $message .= ' Your approved request is ready for pickup from the Supply Office.';
            }
        } elseif ($this->status === 'rejected') {
            $rejecter = $this->venueCustodian ?? $this->supplyOffice ?? 'The system';
            $message = $recipientType === 'requestor'
                ? "Your request was rejected by {$rejecter}. Control No: {$this->facilityRequest->control_number}"
                : "This request was rejected by {$rejecter}. Control No: {$this->facilityRequest->control_number}";
        } elseif ($this->status === 'needs_reschedule') {
            $admin = $this->supplyOffice ?? 'Supply Office';
            $reason = $this->conflictReason ?? 'scheduling conflict';
            $message = $recipientType === 'requestor'
                ? "Your reservation needs to be rescheduled due to {$reason}. Control No: {$this->facilityRequest->control_number}"
                : "This reservation needs to be rescheduled due to {$reason}. Control No: {$this->facilityRequest->control_number}";
        } elseif ($this->status === 'equipment_returned') {
            $returnStatus = $this->facilityRequest->equipment_returned_status === 'fulfilled'
                ? 'All equipment has been accounted for and the return is complete.'
                : 'A partial equipment return has been recorded.';

            $returnedQty = (int) ($this->facilityRequest->equipment_returned_items ? array_sum(array_map(function ($custodianItems) {
                return array_sum((array) ($custodianItems['equipment'] ?? []));
            }, (array) $this->facilityRequest->equipment_returned_items)) : 0);
            $damagedQty = (int) ($this->facilityRequest->equipment_return_damaged_quantity ?? 0);
            $missingQty = (int) ($this->facilityRequest->equipment_return_missing_quantity ?? 0);

            $message = $returnStatus . " Returned: {$returnedQty}; Damaged: {$damagedQty}; Missing: {$missingQty}. Control No: {$this->facilityRequest->control_number}";
            if ($damagedQty > 0 || $missingQty > 0) {
                $message .= ' Additional review may be required.';
            }
        } else {
            $message = "Your request status has been updated to " . ucfirst($this->status) . ". Control No: {$this->facilityRequest->control_number}";
        }

        return $message;
    }

    private function getRequestRouteFor($notifiable): string
    {
        $requestId = $this->facilityRequest->id;

        if (! $notifiable) {
            return route('request.show', ['id' => $requestId]);
        }

        if ($notifiable->id === $this->facilityRequest->requested_by_id) {
            return route('request.show', ['id' => $requestId]);
        }

        if (($notifiable->isAdmin() || $notifiable->isCustodian()) && $notifiable->can('view', $this->facilityRequest)) {
            return route('request.show', ['id' => $requestId]);
        }

        return route('request.show', ['id' => $requestId]);
    }

    private function determineRecipientType($notifiable): string
    {
        if (! $notifiable) {
            return 'requestor';
        }

        if ($notifiable->id === $this->facilityRequest->requested_by_id) {
            return 'requestor';
        }

        if ($notifiable->isCustodianVenue()) {
            return 'venue_custodian';
        }

        if ($notifiable->isCustodianEquipment()) {
            return 'equipment_custodian';
        }

        return 'admin';
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'request_id' => $this->facilityRequest->id,
            'control_number' => $this->facilityRequest->control_number,
            'activity' => $this->facilityRequest->name_of_activity,
            'status' => $this->status,
            'message' => $this->buildConsolidatedMessage($notifiable),
            'route' => $this->getRequestRouteFor($notifiable),
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
            ->line($this->buildConsolidatedMessage($notifiable))
            ->line("**Activity:** {$this->facilityRequest->name_of_activity}")
            ->line("**Control No:** {$this->facilityRequest->control_number}")
            ->line("**Date:** {$this->facilityRequest->formatDateForDisplay($this->facilityRequest->start_date)}")
            ->line("**Time:** {$this->facilityRequest->formatTimeForDisplay($this->facilityRequest->start_time)}" . ($this->facilityRequest->end_time ? ' - ' . $this->facilityRequest->formatTimeForDisplay($this->facilityRequest->end_time) : ''))
            ->line("**Venue:** " . implode(', ', $this->facilityRequest->getVenueNames()))
            ->action('View Request', $this->getRequestRouteFor($notifiable));

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
            'message'        => $this->buildConsolidatedMessage($notifiable),
            'route'          => $this->getRequestRouteFor($notifiable),
            'notes'          => $this->notes,
            'actors' => [
                'venue_custodian' => $this->venueCustodian,
                'equipment_custodians' => $this->equipmentCustodians,
                'supply_office' => $this->supplyOffice,
            ],
            'return_status' => $this->facilityRequest->equipment_returned_status,
            'damaged_quantity' => (int) ($this->facilityRequest->equipment_return_damaged_quantity ?? 0),
            'missing_quantity' => (int) ($this->facilityRequest->equipment_return_missing_quantity ?? 0),
        ];
    }
}