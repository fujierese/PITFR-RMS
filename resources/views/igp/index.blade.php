@extends('layouts.app')
@section('title', 'IGP Dashboard')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <x-page-header eyebrow="IGP" title="Outsider Reservation Inquiries" description="Review new inquiries, forward them to the venue custodian, and coordinate requirements after the Supply Office approves. The Supply Office makes every approval decision; payment is handled outside PITFR." accent="emerald" />

    <x-search-filter-bar :action="route('igp.index')" :search="$search ?? ''" placeholder="Search control number, activity, client, organization, venue or date" />
    @if(($search ?? '') !== '')
        <p class="text-sm text-slate-600" aria-live="polite">Showing inquiries matching “{{ $search }}”. <a href="{{ route('igp.index') }}" class="font-semibold text-emerald-700 hover:underline">Show all</a></p>
    @endif

    <nav aria-label="Inquiry sections" class="flex flex-wrap gap-2">
        @foreach($sections as $key => $section)
            <a href="#igp-{{ $key }}" class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:border-sky-300 hover:text-sky-800">
                {{ $section['title'] }}
                <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $section['requests']->count() }}</span>
            </a>
        @endforeach
    </nav>

    @foreach($sections as $key => $section)
        <section id="igp-{{ $key }}" aria-labelledby="igp-{{ $key }}-heading" class="scroll-mt-24 rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="border-b border-slate-200 pb-3">
                <h2 id="igp-{{ $key }}-heading" class="text-lg font-semibold text-slate-900">{{ $section['title'] }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $section['hint'] }}</p>
            </div>

            @forelse($section['requests'] as $inquiry)
                <article class="flex flex-col gap-3 border-b border-slate-100 py-4 last:border-0 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-900">
                            {{ $inquiry->control_number }} · {{ $inquiry->name_of_activity }}
                        </p>
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
                        <span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-800">{{ \App\Models\FacilityRequest::OUTSIDER_STAGES[$inquiry->inferredOutsiderStage()] ?? 'Outsider request' }}</span>
                        <a href="{{ route('request.show', $inquiry->id) }}" class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800">
                            {{ in_array($key, ['review', 'coordinate', 'coordinating'], true) ? 'Open and act' : 'View details' }}
                        </a>
                    </div>
                </article>
            @empty
                <p class="py-4 text-sm text-slate-500">{{ ($search ?? '') !== '' ? 'No inquiries here match your search.' : 'Nothing here right now.' }}</p>
            @endforelse
        </section>
    @endforeach
</div>
@endsection
