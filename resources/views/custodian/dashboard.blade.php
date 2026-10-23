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
                <p class="mt-1 text-sm leading-6 text-slate-700">Check requested quantities and equipment availability. Verify requests you can fulfill, reject requests that cannot proceed with a clear note, and record equipment returns after the activity.</p>
            @else
                <p class="mt-1 text-sm leading-6 text-slate-700">Check the venue, date, time, and capacity. Verify requests that can proceed, or reject requests that cannot be accommodated with a clear note. The Supply Office handles final approval.</p>
            @endif
        </div>
    </div>
</section>

<section class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200/70" aria-labelledby="assigned-requests-heading">
    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <h2 id="assigned-requests-heading" class="text-lg font-semibold text-slate-950">Requests for Your Resources</h2>
        <a href="{{ route('custodian.index', ['filter' => 'all']) }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">View all</a>
    </div>
    @forelse($allRequests as $req)
        <a href="{{ route('request.show', $req->id) }}" class="block border-b border-slate-100 px-5 py-4 last:border-b-0 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-emerald-500">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-slate-900">{{ $req->name_of_activity ?? 'Untitled activity' }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $req->control_number }} <span aria-hidden="true">·</span> {{ $req->requester?->name ?? $req->requested_by }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $req->start_date ? $req->start_date->format('M d, Y') : 'Date not set' }} <span aria-hidden="true">·</span> {{ implode(', ', $req->getVenueNames()) ?: 'No venue listed' }}</p>
                    <p class="mt-1 text-sm text-slate-500">Equipment: {{ implode(', ', $req->getEquipmentItems()) ?: 'None requested' }}</p>
                </div>
                <div class="w-full sm:w-64 sm:shrink-0">
                    <x-request-status-badge :request="$req" :show-workflow-stage="true" />
                </div>
            </div>
        </a>
    @empty
        <p class="px-5 py-8 text-sm text-slate-500">There are no requests for your assigned resources yet.</p>
    @endforelse
</section>
@endsection
