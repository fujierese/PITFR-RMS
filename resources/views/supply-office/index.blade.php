@extends('layouts.app')
@section('title', 'Administration')

@section('content')
@php
    $advancedFiltersActive = collect(['department', 'venue', 'date_from', 'date_to', 'priority'])
        ->contains(fn ($key) => filled(request($key)));
@endphp
<div class="space-y-6">
    <div class="rounded-3xl bg-gradient-to-r from-emerald-700 via-emerald-600 to-emerald-800 p-4 text-white shadow-xl sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-100">Overview</p>
                <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Dashboard</h1>
            </div>
            <div class="flex items-center gap-3">
                <div class="rounded-2xl border border-white/20 bg-white/10 px-3 py-2 text-left">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-emerald-100">Pending review</p>
                    <p class="mt-1 text-lg font-semibold text-white">{{ $pendingFinalApprovalCount }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-3xl border border-emerald-100 bg-white p-5 shadow-sm ring-1 ring-emerald-50">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Venues</p>
            <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $venuesCount }}</p>
        </div>
        <div class="rounded-3xl border border-emerald-100 bg-white p-5 shadow-sm ring-1 ring-emerald-50">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Equipment Items</p>
            <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $equipmentCount }}</p>
        </div>
        <div class="rounded-3xl border border-emerald-100 bg-white p-5 shadow-sm ring-1 ring-emerald-50">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Availability Status</p>
            <p class="mt-3 text-2xl font-semibold text-emerald-700">Real-time</p>
        </div>
    </div>

    <section class="rounded-3xl border border-cyan-200 bg-cyan-50 p-4 shadow-sm sm:p-5" aria-labelledby="supply-office-workflow-heading">
        <div class="flex items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-cyan-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 21a9 9 0 110-18 9 9 0 010 18z"/>
            </svg>
            <div>
                <h2 id="supply-office-workflow-heading" class="text-sm font-semibold text-slate-950">Final review guide</h2>
                <p class="mt-1 text-sm leading-6 text-slate-700">Open a request after custodian review and confirm the complete schedule, venue, and equipment details. Approve when everything is ready, request a reschedule when the request needs changes, or reject it when it cannot proceed. Add a clear note whenever you reschedule or reject.</p>
            </div>
        </div>
    </section>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('supply-office.final-approval') }}" class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm transition hover:bg-amber-100">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Review Queue</p>
            <p class="mt-3 text-2xl font-semibold text-slate-900">{{ $pendingFinalApprovalCount }}</p>
            <p class="mt-2 text-sm text-slate-600">Pending final approval requests</p>
        </a>
        <a href="{{ route('supply-office.users') }}" class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm transition hover:bg-emerald-100">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Users</p>
            <p class="mt-3 text-2xl font-semibold text-slate-900">{{ \App\Models\User::count() }}</p>
            <p class="mt-2 text-sm text-slate-600">Manage accounts and roles</p>
        </a>
        <a href="{{ route('supply-office.usage-reports') }}" class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm transition hover:bg-emerald-100">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Reports</p>
            <p class="mt-3 text-2xl font-semibold text-slate-900">{{ $totalCount }}</p>
            <p class="mt-2 text-sm text-slate-600">Usage and activity reports</p>
        </a>
        <a href="{{ route('supply-office.audit-logs') }}" class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm transition hover:bg-emerald-100">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Audit Logs</p>
            <p class="mt-3 text-2xl font-semibold text-slate-900">Latest</p>
            <p class="mt-2 text-sm text-slate-600">Review recent changes</p>
        </a>
    </div>

    <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200/50">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Supply Office Review Queue</h2>
                <p class="mt-1 text-sm text-slate-500">Requests that have already passed custodian review and are waiting for final supply office action.</p>
            </div>
            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">{{ $pendingFinalApprovalCount }} waiting</span>
        </div>

        <form method="GET" action="{{ route('supply-office.index') }}" class="mb-6">
            <div class="grid gap-3 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
                <label>
                    <span class="sr-only">Search requests</span>
                    <input type="search" name="search" value="{{ $searchQuery }}" placeholder="Search request number, activity, organization, venue, equipment..." class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                </label>
                <button type="submit" class="rounded-xl bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Search</button>
                <button type="button" id="advanced-filter-toggle" aria-expanded="{{ $advancedFiltersActive ? 'true' : 'false' }}" aria-controls="advanced-request-filters" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Advanced Filters <span aria-hidden="true">▾</span>
                </button>
            </div>
            <div id="advanced-request-filters" @if(!$advancedFiltersActive) hidden @endif class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label>
                        <span class="mb-1 block text-xs font-semibold text-slate-600">Department</span>
                        <input type="search" name="department" value="{{ request('department') }}" placeholder="Any department" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    </label>
                    <label>
                        <span class="mb-1 block text-xs font-semibold text-slate-600">Venue</span>
                        <input type="search" name="venue" value="{{ request('venue') }}" placeholder="Any venue" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    </label>
                    <label>
                        <span class="mb-1 block text-xs font-semibold text-slate-600">From date</span>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    </label>
                    <label>
                        <span class="mb-1 block text-xs font-semibold text-slate-600">To date</span>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    </label>
                    <label>
                        <span class="mb-1 block text-xs font-semibold text-slate-600">Priority</span>
                        <select name="priority" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="">Any priority</option>
                            <option value="regular" @selected(request('priority') === 'regular')>Regular</option>
                            <option value="institutional" @selected(request('priority') === 'institutional')>Institutional</option>
                        </select>
                    </label>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="submit" class="rounded-xl bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Apply filters</button>
                    <a href="{{ route('supply-office.index') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-100">Clear filters</a>
                </div>
            </div>
        </form>

        @if($finalApprovalQueue->count() === 0)
            <div class="rounded-[28px] border border-dashed border-slate-300 bg-slate-50 p-6 text-center shadow-sm sm:p-8">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-700 mb-4">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-900">No requests are currently awaiting final supply office review.</h3>
                <p class="mt-2 text-sm text-slate-600">Requests will appear here when they're ready for your approval.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-[1050px] w-full text-left text-sm text-slate-600">
                    <thead class="border-b border-slate-200 text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">Control Number</th>
                            <th class="px-4 py-3 font-medium">Activity</th>
                            <th class="px-4 py-3 font-medium">Venue</th>
                            <th class="px-4 py-3 font-medium">Equipment</th>
                            <th class="px-4 py-3 font-medium">Date and Time</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                @foreach($finalApprovalQueue as $request)
                    <tr class="cursor-pointer transition hover:bg-slate-50 focus:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-amber-500" data-request-url="{{ route('request.show', $request->id) }}" role="link" tabindex="0" aria-label="Open request details for {{ $request->control_number }}">
                        <td class="px-4 py-4 font-semibold text-slate-900">{{ $request->control_number }}</td>
                        <td class="px-4 py-4">
                            <p class="font-medium text-slate-900">{{ $request->name_of_activity ?? '—' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $request->requester?->name ?? 'Unknown' }}@if($request->department) <span aria-hidden="true">·</span> {{ $request->department }}@endif</p>
                        </td>
                        <td class="px-4 py-4">{{ implode(', ', $request->getVenueNames()) ?: '—' }}</td>
                        <td class="px-4 py-4">{{ implode(', ', $request->getEquipmentItems()) ?: '—' }}</td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <p>{{ $request->start_date ? \Carbon\Carbon::parse($request->start_date)->format('M d, Y') : '—' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $request->start_time ? \Carbon\Carbon::parse($request->start_time)->format('g:i A') : '—' }}@if($request->end_time) - {{ \Carbon\Carbon::parse($request->end_time)->format('g:i A') }}@endif</p>
                        </td>
                        <td class="px-4 py-4"><x-request-status-badge :request="$request" /></td>
                        <td class="px-4 py-4 whitespace-nowrap"><a href="{{ route('request.show', $request->id) }}" class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 transition hover:bg-amber-100" aria-label="Review request {{ $request->control_number }}">Review</a></td>
                    </tr>
                @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if($finalApprovalQueue->hasPages())
            <div class="mt-6">{{ $finalApprovalQueue->links() }}</div>
        @endif
    </div>

</div>
<script>
    (() => {
        const advancedFilterToggle = document.getElementById('advanced-filter-toggle');
        const advancedRequestFilters = document.getElementById('advanced-request-filters');

        if (advancedFilterToggle && advancedRequestFilters) {
            const hasActiveAdvancedFilters = new URLSearchParams(window.location.search);
            const shouldOpenFilters = ['department', 'venue', 'date_from', 'date_to', 'priority']
                .some((key) => hasActiveAdvancedFilters.has(key) && hasActiveAdvancedFilters.get(key) !== '');

            if (shouldOpenFilters) {
                advancedRequestFilters.hidden = false;
                advancedFilterToggle.setAttribute('aria-expanded', 'true');
            }

            advancedFilterToggle.addEventListener('click', () => {
                const isExpanded = advancedFilterToggle.getAttribute('aria-expanded') === 'true';
                advancedFilterToggle.setAttribute('aria-expanded', String(!isExpanded));
                advancedRequestFilters.hidden = isExpanded;
            });
        }

        document.querySelectorAll('[data-request-url]').forEach((requestTarget) => {
            requestTarget.addEventListener('click', (event) => {
                if (event.target.closest('a, button, form, input, select, textarea')) return;
                window.location.href = requestTarget.dataset.requestUrl;
            });

            requestTarget.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                event.preventDefault();
                window.location.href = requestTarget.dataset.requestUrl;
            });
        });
    })();
</script>
@endsection
