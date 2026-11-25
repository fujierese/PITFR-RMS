<?php

namespace App\Notifications;

use App\Models\FacilityRequest;
use App\Notifications\Concerns\QueuesMailAfterCommit;
use App\Notifications\Concerns\UsesWorkflowNotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Follow-up fixes: tells an equipment custodian (or the Supply Office, for items with
 * no custodian) that a reservation includes their equipment, including items
 * provided with the venue. One notice per custodian per event, listing only their items.
 */
class EquipmentReservationNotice extends Notification implements ShouldQueueAfterCommit
{
    use Queueable, QueuesMailAfterCommit;
    use UsesWorkflowNotificationChannels;

    public const TITLES = [
        'submitted' => 'Reservation Includes Your Equipment',
        'updated' => 'Reservation Equipment Updated',
        'rescheduled' => 'Reservation Rescheduled',
        'cancelled' => 'Reservation Cancelled',
        'unassigned' => 'Equipment Without a Custodian',
    ];

    /**
     * @param  list<array{name: string, quantity: int, venue: string, provided: bool}>  $items
     */
    public function __construct(
        public FacilityRequest $facilityRequest,
        public string $event,
        public array $items,
        public string $noticeKey,
    ) {
    }

    public function via($notifiable): array
    {
        return $this->workflowChannels($notifiable, ['database', 'mail']);
    }

    private function itemsText(): string
    {
        return collect($this->items)
            ->map(fn (array $item) => $item['name'] . ' ×' . $item['quantity'] . ($item['provided'] ? ' (provided with venue)' : ''))
            ->implode(', ');
    }

    private function scheduleText(): string
    {
        $request = $this->facilityRequest;
        $date = $request->start_date?->format('M j, Y') ?? '';
        $end = $request->end_date && $request->end_date->toDateString() !== $request->start_date?->toDateString() ? ' – ' . $request->end_date->format('M j, Y') : '';
        $times = $request->start_time ? ' ' . \Carbon\Carbon::parse($request->start_time)->format('g:i A') . ($request->end_time ? '–' . \Carbon\Carbon::parse($request->end_time)->format('g:i A') : '') : '';

        return trim($date . $end . $times);
    }

    private function venuesText(): string
    {
        return collect($this->items)->pluck('venue')->filter()->unique()->implode(', ') ?: implode(', ', $this->facilityRequest->getVenueNames());
    }

    public function toArray($notifiable): array
    {
        $requestor = $this->facilityRequest->requester?->name ?? $this->facilityRequest->requested_by ?? 'Requestor';

        return [
            'request_id' => $this->facilityRequest->id,
            'control_number' => $this->facilityRequest->control_number,
            'activity' => $this->facilityRequest->name_of_activity,
            'status' => 'equipment_' . $this->event,
            'title' => self::TITLES[$this->event] ?? 'Reservation Equipment',
            'body' => $requestor . ' · ' . $this->itemsText() . ' · ' . $this->venuesText() . ' · ' . $this->scheduleText(),
            'decision' => true,
            'notice_key' => $this->noticeKey,
            'items' => $this->items,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $title = self::TITLES[$this->event] ?? 'Reservation Equipment';

        return (new MailMessage)
            ->subject("{$title} — {$this->facilityRequest->control_number}")
            ->greeting("Hello {$notifiable->name}!")
            ->line('Requestor: ' . ($this->facilityRequest->requester?->name ?? $this->facilityRequest->requested_by ?? '—'))
            ->line('Equipment: ' . $this->itemsText())
            ->line('Venue: ' . $this->venuesText())
            ->line('Schedule: ' . $this->scheduleText())
            ->action('View request details', route('request.show', ['id' => $this->facilityRequest->id]));
    }
}
