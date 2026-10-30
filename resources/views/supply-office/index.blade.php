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

    <section class="rounded-3xl border border-cyan-200 bg-white p-6 shadow-sm ring-1 ring-cyan-100">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Initial Availability Review</h2>
                <p class="mt-1 text-sm text-slate-600">This queue starts after the venue custodian confirms the venue is available. Recheck the venue and requested equipment here; this is still not final approval. After confirmation, equipment custodians can review their assigned items.</p>
            </div>
            <span class="rounded-full bg-cyan-100 px-3 py-1 text-xs font-semibold text-cyan-800">{{ $availabilityReviewQueue->total() }} waiting</span>
        </div>
        @forelse($availabilityReviewQueue as $availabilityRequest)
            @php($availabilityGroup = 'supply-availability-' . $availabilityRequest->id)
            <article class="mb-4 rounded-2xl border border-slate-200 p-4 last:mb-0">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <a href="{{ route('request.show', $availabilityRequest->id) }}" class="font-semibold text-slate-900 hover:text-cyan-700">{{ $availabilityRequest->control_number }} — {{ $availabilityRequest->name_of_activity }}</a>
                        <div class="mt-1 space-y-1 text-sm text-slate-600">
                            <p>{{ $availabilityRequest->requester?->name ?? 'Unknown' }}</p>
                            @foreach($availabilityRequest->getVenueScheduleSummaries() as $schedule)
                                <div class="flex flex-wrap items-center gap-2">
                                    <p>{{ $schedule['venue'] }} · {{ $schedule['label'] }}</p>
                                    <x-venue-calendar-button
                                        :venue="$schedule['venue']"
                                        :date="$availabilityRequest->getVenueSchedule($schedule['venue'])['start_date'] ?? now()->toDateString()"
                                        :end-date="$availabilityRequest->getVenueSchedule($schedule['venue'])['end_date'] ?? now()->toDateString()"
                                        :start-time="$availabilityRequest->getVenueSchedule($schedule['venue'])['start_time'] ?? null"
                                        :end-time="$availabilityRequest->getVenueSchedule($schedule['venue'])['end_time'] ?? null"
                                        :availability-check-url="route('calendar.request-availability-check', $availabilityRequest)"
                                        :availability-group="$availabilityGroup"
                                        label="Check calendar"
                                    />
                                </div>
                            @endforeach
                            <p data-availability-summary="{{ $availabilityGroup }}" class="mt-2 hidden text-xs font-medium text-slate-600" aria-live="polite"></p>
                        </div>
                        @if($availabilityRequest->is_emergency)
                            <p class="mt-2 text-sm font-semibold text-amber-700">Urgent: validate the attached proposal or memorandum order before confirming.</p>
                        @endif
                    </div>
                    <div data-availability-actions="{{ $availabilityGroup }}" class="hidden flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('supply-office.requests.availability-review') }}" data-availability-confirm="{{ $availabilityGroup }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="hidden" name="id" value="{{ $availabilityRequest->id }}">
                            <input type="hidden" name="decision" value="available">
                            @if($availabilityRequest->is_emergency)
                                <div>
                                    <label class="flex items-center gap-2 text-xs font-medium text-amber-800">
                                        <input type="checkbox" name="urgent_document_validated" value="1" required @checked(old('urgent_document_validated') === '1') class="rounded border-amber-400 text-amber-600">
                                        Memo/proposal validated
                                    </label>
                                    @error('urgent_document_validated')
                                        <p class="mt-1 max-w-64 text-xs font-medium text-red-700">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif
                            <button type="submit" class="rounded-xl bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">Confirm Availability & Send to Equipment Custodians</button>
                        </form>
                        <form method="POST" action="{{ route('supply-office.requests.availability-review') }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="hidden" name="id" value="{{ $availabilityRequest->id }}">
                            <input type="hidden" name="decision" value="unavailable">
                            <input type="text" name="notes" required maxlength="1000" placeholder="Conflict / reschedule note" class="w-52 rounded-xl border border-slate-300 px-3 py-2 text-xs">
                            <button type="submit" class="rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900 hover:bg-amber-100">Mark Unavailable</button>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <p class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-600">No requests are waiting for Supply Office availability confirmation. Requests appear here after their venue custodian confirms the venue.</p>
        @endforelse
        @if($availabilityReviewQueue->hasPages())
            <div class="mt-4">{{ $availabilityReviewQueue->links() }}</div>
        @endif
    </section>

    @if($venueRescheduleQueue->isNotEmpty())
        <section class="rounded-3xl border border-amber-200 bg-white p-6 shadow-sm" aria-labelledby="venue-reschedule-heading">
            <div class="mb-4">
                <h2 id="venue-reschedule-heading" class="text-xl font-semibold text-slate-900">Venue Unavailable — Schedule Coordination Needed</h2>
                <p class="mt-1 text-sm text-slate-600">The venue custodian could not confirm the requested slot. Contact the requestor and propose an available schedule from the request details page. The request will restart availability review after the proposal is accepted.</p>
            </div>
            <div class="space-y-3">
                @foreach($venueRescheduleQueue as $venueIssue)
                    <article class="flex flex-col gap-3 rounded-2xl border border-amber-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <a href="{{ route('request.show', $venueIssue) }}" class="font-semibold text-slate-900 hover:text-amber-700">{{ $venueIssue->control_number }} — {{ $venueIssue->name_of_activity }}</a>
                            <p class="mt-1 text-sm text-slate-600">Requestor: {{ $venueIssue->requester?->name ?? 'Unknown' }}</p>
                            <p class="mt-1 text-sm text-amber-900">Venue custodian note: {{ $venueIssue->venue_notes ?: 'No reason provided.' }}</p>
                        </div>
                        <a href="{{ route('request.show', $venueIssue) }}" class="inline-flex shrink-0 items-center justify-center rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900 hover:bg-amber-100">Propose an available schedule</a>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="rounded-3xl border border-rose-200 bg-white p-6 shadow-sm" aria-labelledby="cancellation-queue-heading">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 id="cancellation-queue-heading" class="text-xl font-semibold text-slate-900">Approved Reservation Cancellations</h2>
                <p class="mt-1 text-sm text-slate-600">Confirm with the requestor before cancelling; the booking remains active while awaiting your decision.</p>
            </div>
            <span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-800">{{ $cancellationQueue->count() }} awaiting</span>
        </div>
        @forelse($cancellationQueue as $cancellationRequest)
            <article class="mb-3 flex flex-col gap-3 rounded-2xl border border-rose-100 p-4 last:mb-0 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <a href="{{ route('request.show', $cancellationRequest) }}" class="font-semibold text-slate-900 hover:text-rose-700">{{ $cancellationRequest->control_number }} — {{ $cancellationRequest->name_of_activity }}</a>
                    <p class="mt-1 text-sm text-slate-600">Requestor: {{ $cancellationRequest->requester?->name ?? 'Unknown' }}</p>
                    <div class="mt-1 space-y-1 text-sm text-slate-600">
                        @foreach($cancellationRequest->getVenueScheduleSummaries() as $schedule)
                            <p>{{ $schedule['venue'] }} · {{ $schedule['label'] }}</p>
                        @endforeach
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Requested {{ $cancellationRequest->cancellation_requested_at?->format('M j, Y g:i A') }}@if($cancellationRequest->cancellation_notes) · {{ $cancellationRequest->cancellation_notes }}@endif</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('supply-office.requests.cancellation-review', $cancellationRequest) }}" class="flex flex-wrap gap-2">
                        @csrf
                        <input type="hidden" name="decision" value="confirm">
                        <button type="submit" class="rounded-xl bg-rose-700 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-800">Confirm cancellation</button>
                    </form>
                    <form method="POST" action="{{ route('supply-office.requests.cancellation-review', $cancellationRequest) }}" class="flex flex-wrap gap-2">
                        @csrf
                        <input type="hidden" name="decision" value="decline">
                        <input type="text" name="notes" required maxlength="1000" placeholder="Reason to decline" class="w-44 rounded-xl border border-slate-300 px-3 py-2 text-xs">
                        <button type="submit" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Keep reservation</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-600">No approved reservations are awaiting cancellation confirmation.</p>
        @endforelse
    </section>

    <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200/50">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Supply Office Review Queue</h2>
                <p class="mt-1 text-sm text-slate-500">Requests that have passed the initial availability review and custodian approvals, and are waiting for final Supply Office action.</p>
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
                            <th class="px-4 py-3 font-medium">Venue Schedules</th>
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
                        <td class="px-4 py-4">{{ implode(', ', $request->getVenueEquipmentLabels() ?: $request->getEquipmentItems()) ?: '—' }}</td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            @foreach($request->getVenueScheduleSummaries() as $schedule)
                                <p class="mb-2 last:mb-0"><span class="font-medium">{{ $schedule['venue'] }}</span><span class="mt-1 block text-xs text-slate-500">{{ $schedule['label'] }}</span></p>
                            @endforeach
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
