<article class="flex flex-col gap-3 border-b border-slate-100 py-4 last:border-0 sm:flex-row sm:items-center sm:justify-between" data-igp-inquiry="{{ $inquiry->control_number }}">
    <div class="min-w-0">
        <p class="font-semibold text-slate-900">{{ $inquiry->control_number }} · {{ $inquiry->name_of_activity }}</p>
        <p class="mt-0.5 text-sm text-slate-600">
            {{ $inquiry->organization_name ?: ($inquiry->requester?->office_or_organization ?: 'Individual') }}
            · {{ $inquiry->contact_person ?: ($inquiry->requester?->name ?? 'Unknown client') }}
            @if($inquiry->contact_number) · {{ $inquiry->contact_number }} @endif
        </p>
        <div class="mt-1 space-y-0.5 text-sm text-slate-600">
            @forelse($inquiry->getVenueScheduleSummaries() as $schedule)
                <p>{{ $schedule['venue'] }} · {{ $schedule['label'] }}</p>
            @empty
                <p>No venue schedule listed</p>
            @endforelse
        </div>
        @if($inquiry->outsider_hold_until && $inquiry->hasActiveOutsiderHold())
            <p class="mt-1 text-xs font-medium text-amber-800">Schedule held until {{ $inquiry->outsider_hold_until->format('M j, Y') }}</p>
        @endif
    </div>
    <div class="flex shrink-0 flex-col items-start gap-2 sm:items-end">
        <span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-800" data-igp-status>{{ \App\Support\IgpInquiries::statusLabel($inquiry) }}</span>
        <a href="{{ route('request.show', $inquiry->id) }}" class="rounded-xl {{ $button === 'View details' || $button === 'View progress' ? 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' : 'bg-sky-700 text-white hover:bg-sky-800' }} px-4 py-2 text-sm font-semibold">{{ $button }}</a>
    </div>
</article>
