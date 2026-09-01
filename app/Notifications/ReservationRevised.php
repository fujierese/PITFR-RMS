<?php

namespace App\Notifications;

use App\Models\FacilityRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationRevised extends Notification implements ShouldQueue
{
    use Queueable;

    protected FacilityRequest $facilityRequest;
    protected array $oldState;
    protected array $newState;
    protected string $revisionReason;
    protected ?string $revisedByName;
    protected bool $conflictDetected;
    protected bool $conflictOverridden;
    protected ?string $conflictDetails;

    public function __construct(
        FacilityRequest $facilityRequest,
        array $oldState,
        array $newState,
        string $revisionReason,
        ?string $revisedByName = null,
        bool $conflictDetected = false,
        bool $conflictOverridden = false,
        ?string $conflictDetails = null
    ) {
        $this->facilityRequest = $facilityRequest;
        $this->oldState = $oldState;
        $this->newState = $newState;
        $this->revisionReason = $revisionReason;
        $this->revisedByName = $revisedByName;
        $this->conflictDetected = $conflictDetected;
        $this->conflictOverridden = $conflictOverridden;
        $this->conflictDetails = $conflictDetails;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'control_number' => $this->facilityRequest->control_number,
            'facility_request_id' => $this->facilityRequest->id,
            'title' => $this->getTitle($notifiable),
            'message' => $this->getMessage($notifiable),
            'old_state' => $this->oldState,
            'new_state' => $this->newState,
            'revision_reason' => $this->revisionReason,
            'revised_by_name' => $this->revisedByName,
            'conflict_detected' => $this->conflictDetected,
            'conflict_overridden' => $this->conflictOverridden,
            'conflict_details' => $this->conflictDetails,
            'route' => route('request.show', $this->facilityRequest),
        ];
    }

    /**
     * Get recipient-specific title
     */
    private function getTitle(object $notifiable): string
    {
        if ($this->isRequestor($notifiable)) {
            return 'Your reservation has been revised.';
        }

        return 'A reservation has been revised.';
    }

    /**
     * Get recipient-specific message with changes
     */
    private function getMessage(object $notifiable): string
    {
        $changes = $this->describeChanges();
        $baseMessage = "Request {$this->facilityRequest->control_number} has been revised. Previous schedule: {$this->formatOldSchedule()}. New schedule: {$this->formatNewSchedule()}. {$changes}";

        if ($this->conflictDetected && $this->conflictOverridden) {
            $baseMessage .= " ⚠️ Scheduling conflict was overridden: {$this->conflictDetails}";
        }

        if ($this->revisionReason) {
            $baseMessage .= " Reason: {$this->revisionReason}";
        }

        return $baseMessage;
    }

    /**
     * Describe what changed
     */
    private function describeChanges(): string
    {
        $changes = [];

        if ($this->oldState['start_date'] !== $this->newState['start_date']) {
            $changes[] = "Date: {$this->oldState['start_date']} → {$this->newState['start_date']}";
        }

        if ($this->oldState['start_time'] !== $this->newState['start_time'] ||
            $this->oldState['end_time'] !== $this->newState['end_time']) {
            $changes[] = "Time: {$this->oldState['start_time']}-{$this->oldState['end_time']} → {$this->newState['start_time']}-{$this->newState['end_time']}";
        }

        if ($this->oldState['venue'] !== $this->newState['venue']) {
            $oldVenues = implode(', ', (array) $this->oldState['venue']);
            $newVenues = implode(', ', (array) $this->newState['venue']);
            $changes[] = "Venue: $oldVenues → $newVenues";
        }

        return !empty($changes) ? implode(' | ', $changes) : 'Schedule details updated.';
    }

    /**
     * Format new schedule for requestor notification
     */
    private function formatOldSchedule(): string
    {
        $date = $this->oldState['start_date'] ?? 'TBD';
        $time = ($this->oldState['start_time'] ?? 'TBD') . '-' . ($this->oldState['end_time'] ?? 'TBD');
        $venue = implode(', ', (array) ($this->oldState['venue'] ?? []));

        return "$date from $time at $venue";
    }

    private function formatNewSchedule(): string
    {
        $date = $this->newState['start_date'] ?? 'TBD';
        $time = ($this->newState['start_time'] ?? 'TBD') . '-' . ($this->newState['end_time'] ?? 'TBD');
        $venue = implode(', ', (array) ($this->newState['venue'] ?? []));

        return "$date from $time at $venue";
    }

    /**
     * Check if notifiable is the requestor
     */
    private function isRequestor(object $notifiable): bool
    {
        return $notifiable->id === $this->facilityRequest->requested_by_id;
    }
}
