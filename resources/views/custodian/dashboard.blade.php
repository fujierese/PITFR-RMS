@extends('layouts.app')
@section('title', 'Custodian Dashboard')

@section('content')
<div class="mb-6">
    <x-page-header
        :eyebrow="'Welcome, ' . ($user->first_name ?: $user->name)"
        title="Custodian Workspace"
        :description="'A quick overview of requests assigned to your ' . $custodianType . ' resources.'"
        accent="slate"
    >
    </x-page-header>
</div>

<section class="mb-6 grid gap-4 sm:grid-cols-3" aria-label="Request summary">
    @foreach([
        ['label' => 'Total Requests', 'value' => $stats['total'], 'filter' => 'all'],
        ['label' => 'Pending Review', 'value' => $stats['pending'], 'filter' => 'pending'],
        ['label' => 'Approved', 'value' => $stats['approved'], 'filter' => 'approved'],
    ] as $summary)
        <a href="{{ route('custodian.index', ['filter' => $summary['filter']]) }}" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <p class="text-sm font-medium text-slate-600">{{ $summary['label'] }}</p>
            <p class="mt-2 text-3xl font-bold text-slate-950">{{ $summary['value'] }}</p>
            <span class="mt-3 inline-flex text-sm font-semibold text-emerald-700">View requests <span class="ml-1" aria-hidden="true">→</span></span>
        </a>
    @endforeach
</section>

<section class="mb-6 rounded-3xl border border-cyan-200 bg-cyan-50 p-4 shadow-sm sm:p-5" aria-labelledby="custodian-workflow-heading">
    <div class="flex items-start gap-3">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-cyan-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 21a9 9 0 110-18 9 9 0 010 18z"/>
        </svg>
        <div>
            <h2 id="custodian-workflow-heading" class="text-sm font-semibold text-slate-950">Your review step</h2>
            @if($custodianType === 'equipment')
                <p class="mt-1 text-sm leading-6 text-slate-700">After the venue custodian and Supply Office confirm availability, check requested quantities and equipment availability. Approve the items you can provide or reject with a clear reason. Record equipment returns after the activity.</p>
            @else
                <p class="mt-1 text-sm leading-6 text-slate-700">Your first step is to confirm whether the venue is available for the requested date and time. This is an availability check, not an approval. The Supply Office will recheck availability next; custodian approvals and final approval happen afterward.</p>
            @endif
        </div>
    </div>
</section>

<section class="mb-6 rounded-3xl border border-cyan-200 bg-white p-5 shadow-sm ring-1 ring-cyan-100 sm:p-6" aria-labelledby="custodian-review-queue-heading">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 id="custodian-review-queue-heading" class="text-xl font-semibold text-slate-900">
                {{ $custodianType === 'venue' ? 'Venue Availability Review Required' : 'Equipment Review Required' }}
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                @if($custodianType === 'venue')
                    Check each assigned venue schedule. Confirm availability if it is open, or propose a reschedule if there is a conflict.
                @else
                    Review only your assigned equipment after the venue and Supply Office availability checks are complete.
                @endif
            </p>
        </div>
        <span class="rounded-full bg-cyan-100 px-3 py-1 text-xs font-semibold text-cyan-800">{{ $reviewRequiredCount }} waiting</span>
    </div>

    <div class="space-y-3">
        @forelse($reviewRequiredRequests as $req)
            <article class="rounded-2xl border border-slate-200 p-4">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('request.show', $req->id) }}" class="font-semibold text-slate-900 hover:text-cyan-700">
                            {{ $req->control_number }} — {{ $req->name_of_activity ?? 'Untitled activity' }}
                        </a>
                        <p class="mt-1 text-sm text-slate-600">{{ $req->requester?->name ?? $req->requested_by }}@if($req->department) <span aria-hidden="true">·</span> {{ $req->department }}@endif</p>
                        <div class="mt-3">
                            @include('custodian.partials.request-resources', [
                                'requestItem' => $req,
                                'custodianType' => $custodianType,
                                'assignedVenueNames' => $assignedVenueNames,
                                'assignedEquipmentNames' => $assignedEquipmentNames,
                            ])
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-col items-start gap-2 sm:items-end">
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                            {{ $custodianType === 'venue' ? 'Availability check required' : 'Your equipment review required' }}
                        </span>
                        <a href="{{ route('request.show', $req->id) }}" class="inline-flex items-center justify-center rounded-xl bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800">
                            Review request
                        </a>
                    </div>
                </div>
            </article>
        @empty
            <p class="rounded-2xl bg-slate-50 p-5 text-sm text-slate-600">
                {{ $custodianType === 'venue' ? 'No venue availability checks are waiting for you.' : 'No equipment reviews are waiting for you.' }}
            </p>
        @endforelse
    </div>
    @if($reviewRequiredCount > $reviewRequiredRequests->count())
        <div class="mt-4 text-right">
            <a href="{{ route('custodian.index', ['filter' => 'pending']) }}" class="text-sm font-semibold text-cyan-800 hover:text-cyan-900">View all {{ $reviewRequiredCount }} waiting requests</a>
        </div>
    @endif
</section>

@if($custodianType === 'equipment' && $equipmentReturnsPending->isNotEmpty())
    <section class="mb-6 rounded-3xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm sm:p-6" aria-labelledby="equipment-returns-heading">
        <div class="mb-4">
            <h2 id="equipment-returns-heading" class="text-xl font-semibold text-slate-900">Equipment Returns Required</h2>
            <p class="mt-1 text-sm text-slate-600">Record returned, damaged, or missing equipment for completed activities.</p>
        </div>
        <div class="space-y-3">
            @foreach($equipmentReturnsPending as $req)
                <article class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-white p-4">
                    <div>
                        <a href="{{ route('request.show', $req->id) }}" class="font-semibold text-slate-900 hover:text-amber-800">{{ $req->control_number }} — {{ $req->name_of_activity ?? 'Untitled activity' }}</a>
                        <p class="mt-1 text-sm text-slate-600">{{ $req->requester?->name ?? $req->requested_by }}</p>
                    </div>
                    <a href="{{ route('request.show', $req->id) }}" class="inline-flex items-center justify-center rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-100">Record equipment return</a>
                </article>
            @endforeach
        </div>
    </section>
@endif

<section class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200/70" aria-labelledby="upcoming-reservations-heading">
    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <div>
            <h2 id="upcoming-reservations-heading" class="text-lg font-semibold text-slate-950">Upcoming &amp; Active Reservations</h2>
            <p class="mt-1 text-sm text-slate-500">Approved bookings for your assigned resources.</p>
        </div>
        <a href="{{ route('custodian.index', ['filter' => 'all']) }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">View all</a>
    </div>
    @forelse($upcomingReservations as $req)
        <article class="border-b border-slate-100 px-5 py-4 last:border-b-0">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                    <a href="{{ route('request.show', $req->id) }}" class="font-semibold text-slate-900 hover:text-emerald-700">{{ $req->name_of_activity ?? 'Untitled activity' }}</a>
                    <p class="mt-1 text-sm text-slate-600">{{ $req->control_number }} <span aria-hidden="true">·</span> {{ $req->requester?->name ?? $req->requested_by }}</p>
                    <div class="mt-3">
                        @include('custodian.partials.request-resources', [
                            'requestItem' => $req,
                            'custodianType' => $custodianType,
                            'assignedVenueNames' => $assignedVenueNames,
                            'assignedEquipmentNames' => $assignedEquipmentNames,
                        ])
                    </div>
                </div>
                <div class="w-full sm:w-64 sm:shrink-0">
                    <x-request-status-badge :request="$req" />
                </div>
            </div>
        </article>
    @empty
        <p class="px-5 py-8 text-sm text-slate-500">There are no upcoming or active approved reservations for your resources.</p>
    @endforelse
</section>
@endsection
