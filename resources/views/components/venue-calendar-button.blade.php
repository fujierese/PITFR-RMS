@props([
    'venue',
    'date',
    'endDate' => null,
    'startTime' => null,
    'endTime' => null,
    'label' => 'Check calendar',
    'availabilityCheckUrl' => null,
    'availabilityGroup' => null,
    'facilityRequest' => null,
    'useProvidedSchedule' => false,
])

@php
    $calendarUser = auth()->user();
    $calendarDescription = $calendarUser && $calendarUser->isCustodianVenue()
        ? 'Review bookings and tentative requests for your assigned venue; use the request workflow to confirm availability.'
        : ($calendarUser && $calendarUser->isCustodianEquipment()
            ? 'Review venue bookings for context; confirm assigned equipment in your equipment review queue.'
            : ($availabilityCheckUrl
                ? 'Review this request against current bookings and tentative requests before confirming availability.'
                : ($calendarUser && $calendarUser->isAdmin()
                    ? 'Review approved bookings and tentative requests to assess conflicts and coordinate availability.'
                    : 'Review venue bookings and availability before submitting or updating your reservation.')));
    $canonicalStartTime = $startTime;
    $canonicalEndTime = $endTime;
    $canonicalStartDate = $date;
    $canonicalEndDate = $endDate ?? $date;

    if ($facilityRequest instanceof \App\Models\FacilityRequest && ! $useProvidedSchedule) {
        $canonical = $facilityRequest->getCanonicalCalendarSchedule($venue);
        $canonicalStartTime = $canonical['start']?->format('H:i');
        $canonicalEndTime = $canonical['end']?->format('H:i');
        $canonicalStartDate = $canonical['start']?->toDateString() ?? $canonicalStartDate;
        $canonicalEndDate = $canonical['end']?->toDateString() ?? $canonicalEndDate;
    }

    $calendarUrl = route('calendar.embed', array_filter([
        'venue' => $venue,
        'date' => $canonicalStartDate,
        'start_date' => $canonicalStartDate,
        'end_date' => $canonicalEndDate,
        'start_time' => $canonicalStartTime,
        'end_time' => $canonicalEndTime,
        'request_id' => $facilityRequest?->id,
    ], static fn ($value) => $value !== null && $value !== ''));
@endphp

<span class="inline-flex flex-wrap items-center gap-2">
    <button
        type="button"
        data-venue-calendar-open
        data-venue-calendar-url="{{ $calendarUrl }}"
        data-venue-calendar-title="Availability calendar for {{ $venue }}"
        data-venue-calendar-description="{{ $calendarDescription }}"
        @if($availabilityCheckUrl)
            data-availability-check-url="{{ $availabilityCheckUrl }}"
            data-availability-group="{{ $availabilityGroup }}"
            data-availability-venue="{{ $venue }}"
            data-availability-state="unchecked"
        @endif
        aria-haspopup="dialog"
        aria-controls="staff-venue-calendar-modal"
        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-sky-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-sky-800 shadow-sm transition hover:border-sky-300 hover:bg-sky-50 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1"
    >
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v14H4V6a1 1 0 011 1z"/>
        </svg>
        {{ $label }}
    </button>
    @if($availabilityCheckUrl)
        <span data-availability-venue-status="{{ $availabilityGroup }}:{{ $venue }}" class="text-xs font-medium text-slate-500" aria-live="polite"></span>
    @endif
</span>
