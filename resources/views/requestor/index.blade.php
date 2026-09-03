@extends('layouts.app')
@section('title', 'Requestor Dashboard')

@section('content')

@php
    $requestedTab = request()->query('tab', 'dashboard');
    $activeTab = $requestedTab === 'create' ? 'create' : ($requestedTab === 'requests' ? 'requests' : 'dashboard');

    $search = trim((string) request()->query('search', ''));
    $statusFilter = strtolower((string) request()->query('status', ''));
    $venueFilter = trim((string) request()->query('venue', ''));
    $dateFrom = request()->query('date_from');
    $dateTo = request()->query('date_to');
    $sort = request()->query('sort', 'latest');

    $filteredRequests = $requests->filter(function ($requestItem) use ($search, $statusFilter, $venueFilter, $dateFrom, $dateTo) {
        $matchesSearch = true;
        if ($search !== '') {
            $searchable = collect([
                $requestItem->control_number ?? '',
                $requestItem->name_of_activity ?? '',
                $requestItem->department ?? '',
                $requestItem->office_or_organization ?? '',
                $requestItem->requester?->name ?? '',
                $requestItem->status ?? '',
                implode(', ', $requestItem->getVenueNames()),
                implode(', ', $requestItem->getEquipmentItems()),
                $requestItem->start_date instanceof \Carbon\Carbon ? $requestItem->start_date->format('M d, Y') : (string) ($requestItem->start_date ?? ''),
                $requestItem->end_date instanceof \Carbon\Carbon ? $requestItem->end_date->format('M d, Y') : (string) ($requestItem->end_date ?? ''),
            ])->join(' ');
            $matchesSearch = stripos($searchable, $search) !== false;
        }

        $matchesStatus = true;
        if ($statusFilter !== '') {
            $matchesStatus = strtolower((string) ($requestItem->status ?? '')) === $statusFilter;
        }

        $matchesVenue = true;
        if ($venueFilter !== '') {
            $venueValue = implode(', ', $requestItem->getVenueNames());
            $matchesVenue = stripos($venueValue, $venueFilter) !== false;
        }

        $matchesDateRange = true;
        if ($dateFrom !== null && $dateFrom !== '') {
            $requestDate = $requestItem->start_date instanceof \Carbon\Carbon ? $requestItem->start_date->toDateString() : (string) ($requestItem->start_date ?? '');
            $matchesDateRange = $matchesDateRange && $requestDate !== '' && $requestDate >= $dateFrom;
        }
        if ($dateTo !== null && $dateTo !== '') {
            $requestDate = $requestItem->start_date instanceof \Carbon\Carbon ? $requestItem->start_date->toDateString() : (string) ($requestItem->start_date ?? '');
            $matchesDateRange = $matchesDateRange && $requestDate !== '' && $requestDate <= $dateTo;
        }

        return $matchesSearch && $matchesStatus && $matchesVenue && $matchesDateRange;
    });

    if ($sort === 'oldest') {
        $filteredRequests = $filteredRequests->sortBy([['start_date', 'asc'], ['created_at', 'asc']]);
    } else {
        $filteredRequests = $filteredRequests->sortBy([['start_date', 'desc'], ['created_at', 'desc']]);
    }

    $requestVenueOptions = $requests->flatMap(function ($requestItem) {
        return $requestItem->getVenueNames();
    })->filter()->unique()->sort()->values();

    $statusLabels = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'needs_reschedule' => 'Needs Reschedule',
    ];

    $statusBadgeClasses = [
        'pending' => 'bg-amber-100 text-amber-800 ring-amber-200',
        'approved' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        'rejected' => 'bg-rose-100 text-rose-800 ring-rose-200',
        'completed' => 'bg-sky-100 text-sky-800 ring-sky-200',
        'cancelled' => 'bg-slate-200 text-slate-700 ring-slate-300',
        'needs_reschedule' => 'bg-amber-100 text-amber-800 ring-amber-200',
    ];

    $priorityBadgeClasses = [
        'institutional' => 'bg-violet-100 text-violet-700 ring-violet-200',
        'regular' => 'bg-slate-100 text-slate-700 ring-slate-200',
    ];

    $upcomingReservations = $requests
        ->filter(function ($requestItem) {
            $requestDate = $requestItem->start_date instanceof \Carbon\Carbon ? $requestItem->start_date : null;
            return $requestDate && in_array(strtolower((string) ($requestItem->status ?? '')), ['approved', 'completed'], true) && $requestDate->toDateString() >= now()->toDateString();
        })
        ->sortBy('start_date')
        ->take(5)
        ->values();

    $totalCount = $requests->count();
    $pendingCount = $requests->where('status', 'pending')->count();
    $approvedCount = $requests->where('status', 'approved')->count();
    $rejectedCount = $requests->where('status', 'rejected')->count();
    $completedCount = $requests->where('status', 'completed')->count();
    $upcomingCount = $upcomingReservations->count();
    $firstName = explode(' ', trim((string) Auth::user()->name))[0] ?? '';
@endphp

<div class="space-y-4 md:space-y-6">
    <x-page-header
        :title="$activeTab === 'requests' ? 'My Requests' : 'Dashboard'"
        :description="$activeTab === 'requests' ? 'View, monitor, and manage all reservation requests submitted under your account.' : 'Stay on top of your reservation activity with a clear overview of the most important updates.'"
        eyebrow="Requestor workspace"
    >
        <x-slot:actions>
                <a href="{{ route('requestor.index', ['tab' => 'dashboard']) }}" class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Dashboard</a>
                <a href="{{ route('requestor.index', ['tab' => 'requests']) }}" class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">My Requests</a>
        </x-slot:actions>
    </x-page-header>

    @if ($activeTab === 'create')
        <section class="w-full">
            @include('requestor.partials.request_form')
        </section>
    @elseif ($activeTab === 'requests')
        <section class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-[0_20px_60px_rgba(15,23,42,0.08)] sm:p-6 md:rounded-[32px] lg:p-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-emerald-600">Reservation management</p>
                    <h2 class="mt-3 text-2xl font-semibold text-slate-950 sm:text-3xl">My Requests</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">View, monitor, and manage all reservation requests submitted under your account.</p>
                </div>
            </div>

            @php
                $filtersExpanded = $statusFilter || $venueFilter || $dateFrom || $dateTo || $sort !== 'latest';
            @endphp

            <form id="request-filters-form" method="GET" action="{{ route('requestor.index', ['tab' => 'requests']) }}" class="mt-6 rounded-[24px] border border-slate-200 bg-slate-50 p-4 shadow-sm sm:p-5 md:mt-8 md:rounded-[28px]">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <label for="search" class="sr-only">Search requests</label>
                        <input type="text" id="search" name="search" value="{{ old('search', $search) }}" placeholder="Search request number, activity, organization, venue, equipment..." class="w-full rounded-3xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" id="filters-toggle-button" aria-expanded="{{ $filtersExpanded ? 'true' : 'false' }}" class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100">
                            Advanced Filters
                            <svg id="filters-toggle-icon" class="h-4 w-4 transition-transform duration-200 {{ $filtersExpanded ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <button type="button" id="clear-filters-button" class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100">Clear</button>
                    </div>
                </div>

                <div id="advanced-filters-panel" class="mt-4 grid gap-4 lg:grid-cols-5 {{ $filtersExpanded ? '' : 'hidden' }}">
                    <div>
                        <label for="status" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Status</label>
                        <select id="status" name="status" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            <option value="">All</option>
                            <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ $statusFilter === 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="rejected" {{ $statusFilter === 'rejected' ? 'selected' : '' }}>Rejected</option>
                            <option value="completed" {{ $statusFilter === 'completed' ? 'selected' : '' }}>Completed</option>
                            <option value="cancelled" {{ $statusFilter === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            <option value="needs_reschedule" {{ $statusFilter === 'needs_reschedule' ? 'selected' : '' }}>Needs Reschedule</option>
                        </select>
                    </div>
                    <div>
                        <label for="venue" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Venue</label>
                        <select id="venue" name="venue" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            <option value="">All</option>
                            @foreach ($requestVenueOptions as $venueOption)
                                <option value="{{ $venueOption }}" {{ $venueFilter === $venueOption ? 'selected' : '' }}>{{ $venueOption }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="sort" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Sort</label>
                        <select id="sort" name="sort" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            <option value="latest" {{ $sort === 'latest' ? 'selected' : '' }}>Latest</option>
                            <option value="oldest" {{ $sort === 'oldest' ? 'selected' : '' }}>Oldest</option>
                        </select>
                    </div>
                    <div>
                        <label for="date_from" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Date from</label>
                        <input type="date" id="date_from" name="date_from" value="{{ $dateFrom }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </div>
                    <div>
                        <label for="date_to" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Date to</label>
                        <input type="date" id="date_to" name="date_to" value="{{ $dateTo }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </div>
                </div>
            </form>

            <div id="request-results-panel" data-refresh="requests" class="mt-8">
                <div id="request-count-info" class="mb-4 text-sm font-medium text-slate-600">Showing {{ $filteredRequests->count() }} of {{ $requests->count() }} requests</div>

                @if ($requests->isEmpty())
                    <div class="mt-8 rounded-[28px] border border-dashed border-slate-300 bg-slate-50 p-6 text-center shadow-sm sm:p-8 lg:p-10">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <h3 class="mt-6 text-xl font-semibold text-slate-900">You haven't submitted any reservation requests yet.</h3>
                        <p class="mt-2 text-sm text-slate-600">Start your first reservation request and keep everything organized from one professional workspace.</p>
                        <a href="{{ route('requestor.index', ['tab' => 'create']) }}" class="mt-6 inline-flex items-center justify-center rounded-full bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-600/20 transition hover:bg-emerald-700">Create Your First Request</a>
                    </div>
                @elseif ($filteredRequests->isEmpty())
                    <div class="mt-8 rounded-[28px] border border-dashed border-slate-300 bg-slate-50 p-6 text-center shadow-sm sm:p-8 lg:p-10">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-700">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <h3 class="mt-6 text-xl font-semibold text-slate-900">No matching requests found.</h3>
                        <p class="mt-2 text-sm text-slate-600">Try another keyword or adjust the filters.</p>
                    </div>
                @else
                    <div class="mt-8 hidden overflow-hidden rounded-[28px] border border-slate-200 shadow-[0_12px_40px_rgba(15,23,42,0.05)] lg:block">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 bg-white">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">
                                <tr>
                                    <th class="sticky top-0 bg-slate-50 px-4 py-4">Control Number</th>
                                    <th class="sticky top-0 bg-slate-50 px-4 py-4">Activity</th>
                                    <th class="sticky top-0 bg-slate-50 px-4 py-4">Venue</th>
                                    <th class="sticky top-0 bg-slate-50 px-4 py-4">Equipment</th>
                                    <th class="sticky top-0 bg-slate-50 px-4 py-4">Reservation</th>
                                    <th class="sticky top-0 bg-slate-50 px-4 py-4">Status</th>
                                    <th class="sticky top-0 bg-slate-50 px-4 py-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white text-sm text-slate-700">
                                @foreach ($filteredRequests as $requestItem)
                                    @php
                                        $statusKey = strtolower((string) ($requestItem->status ?? 'pending'));
                                        $statusBadgeClass = $statusBadgeClasses[$statusKey] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
                                        $statusLabel = $statusLabels[$statusKey] ?? ucfirst(str_replace('_', ' ', $statusKey));
                                        $priorityValue = strtolower((string) ($requestItem->priority ?? ''));
                                        $priorityLabel = $priorityValue === 'institutional' ? 'Institutional' : ($priorityValue === 'regular' ? 'Regular' : null);
                                        $priorityBadgeClass = $priorityLabel ? ($priorityBadgeClasses[$priorityValue] ?? 'bg-slate-100 text-slate-700 ring-slate-200') : null;
                                        $venueLabel = implode(', ', $requestItem->getVenueNames()) ?: '-';
                                        $equipmentLabel = implode(', ', $requestItem->getEquipmentItems()) ?: '-';
                                        $reservationDate = $requestItem->start_date ? $requestItem->start_date->format('M d, Y') : '-';
                                        $reservationTime = $requestItem->start_time ? $requestItem->start_time : '-';
                                        $canEdit = in_array($statusKey, ['pending', 'needs_reschedule'], true)
                                            || $requestItem->venue_status === 'needs_reschedule'
                                            || $requestItem->equipment_status === 'needs_reschedule';
                                        $canCancel = $statusKey === 'pending';
                                        $progressLabels = match ($statusKey) {
                                            'rejected' => ['Submitted', 'Under Review', 'Rejected'],
                                            'cancelled' => ['Submitted', 'Cancelled'],
                                            'completed' => ['Submitted', 'Under Review', 'Venue Approved', 'Equipment Approved', 'Completed'],
                                            'approved' => ['Submitted', 'Under Review', 'Venue Approved', 'Equipment Approved'],
                                            default => ['Submitted', 'Under Review'],
                                        };
                                        $progressActiveIndex = match ($statusKey) {
                                            'pending' => 2,
                                            'approved' => 3,
                                            'rejected' => 3,
                                            'completed' => count($progressLabels),
                                            'cancelled' => 2,
                                            default => 2,
                                        };
                                    @endphp
                                    <tr class="cursor-pointer transition hover:bg-slate-50 focus:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-emerald-500" data-request-url="{{ route('request.show', $requestItem->id) }}" role="link" tabindex="0" aria-label="Open request details for {{ $requestItem->control_number ?? 'request' }}">
                                        <td class="whitespace-nowrap px-4 py-4 font-semibold text-slate-900">{{ $requestItem->control_number ?? '-' }}</td>
                                        <td class="px-4 py-4">
                                            <div class="font-semibold text-slate-900">{{ $requestItem->name_of_activity ?? '-' }}</div>
                                            <div class="mt-1 text-xs text-slate-500">{{ $requestItem->department ?? 'N/A' }}</div>
                                        </td>
                                        <td class="px-4 py-4">{{ $venueLabel }}</td>
                                        <td class="px-4 py-4">{{ $equipmentLabel }}</td>
                                        <td class="px-4 py-4">
                                            <div class="font-medium text-slate-900">{{ $reservationDate }}</div>
                                            <div class="mt-1 text-xs text-slate-500">{{ $reservationTime }}</div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex flex-wrap gap-2">
                                                <x-status-badge :status="$statusKey" :label="$statusLabel" />
                                                @if ($priorityLabel)
                                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $priorityBadgeClass }}">{{ $priorityLabel }}</span>
                                                @endif
                                            </div>
                                            <div class="mt-3 space-y-2">
                                                @foreach ($progressLabels as $progressIndex => $progressLabel)
                                                    <div class="flex items-center gap-2 text-xs text-slate-500">
                                                        <span class="flex h-5 w-5 items-center justify-center rounded-full {{ $progressIndex + 1 <= $progressActiveIndex ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-500' }}">
                                                            {{ $progressIndex + 1 }}
                                                        </span>
                                                        <span class="{{ $progressIndex + 1 <= $progressActiveIndex ? 'font-semibold text-slate-900' : '' }}">{{ $progressLabel }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex flex-wrap gap-2">
                                                @if ($canEdit)
                                                    <a href="{{ route('requestor.edit', $requestItem->id) }}" class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100">Edit</a>
                                                @endif
                                                @if ($canCancel)
                                                    <form method="POST" action="{{ route('request.cancel', $requestItem->id) }}" class="inline-block" data-swal-confirm data-swal-title="Cancel this request?" data-swal-text="This will remove the pending request from the queue." data-swal-confirm-text="Yes, cancel it" data-swal-confirm-color="#dc2626">
                                                        @csrf
                                                        <button type="submit" class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">Cancel</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-6 space-y-4 lg:hidden">
                    @foreach ($filteredRequests as $requestItem)
                        @php
                            $statusKey = strtolower((string) ($requestItem->status ?? 'pending'));
                            $statusBadgeClass = $statusBadgeClasses[$statusKey] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
                            $statusLabel = $statusLabels[$statusKey] ?? ucfirst(str_replace('_', ' ', $statusKey));
                            $priorityValue = strtolower((string) ($requestItem->priority ?? ''));
                            $priorityLabel = $priorityValue === 'institutional' ? 'Institutional' : ($priorityValue === 'regular' ? 'Regular' : null);
                            $priorityBadgeClass = $priorityLabel ? ($priorityBadgeClasses[$priorityValue] ?? 'bg-slate-100 text-slate-700 ring-slate-200') : null;
                            $venueLabel = implode(', ', $requestItem->getVenueNames()) ?: '-';
                            $equipmentLabel = implode(', ', $requestItem->getEquipmentItems()) ?: '-';
                            $reservationDate = $requestItem->start_date ? $requestItem->start_date->format('M d, Y') : '-';
                            $reservationTime = $requestItem->start_time ? $requestItem->start_time : '-';
                            $canEdit = in_array($statusKey, ['pending', 'needs_reschedule'], true)
                                || $requestItem->venue_status === 'needs_reschedule'
                                || $requestItem->equipment_status === 'needs_reschedule';
                            $canCancel = $statusKey === 'pending';
                        @endphp
                        <div class="cursor-pointer rounded-[24px] border border-slate-200 bg-slate-50 p-4 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50/40 focus:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500" data-request-url="{{ route('request.show', $requestItem->id) }}" role="link" tabindex="0" aria-label="Open request details for {{ $requestItem->control_number ?? 'request' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">{{ $requestItem->control_number ?? '-' }}</p>
                                    <h4 class="mt-2 text-lg font-semibold text-slate-900">{{ $requestItem->name_of_activity ?? '-' }}</h4>
                                </div>
                                <x-status-badge :status="$statusKey" :label="$statusLabel" />
                            </div>
                            <div class="mt-4 space-y-2 text-sm text-slate-600">
                                <div><span class="font-semibold text-slate-900">Venue:</span> {{ $venueLabel }}</div>
                                <div><span class="font-semibold text-slate-900">Equipment:</span> {{ $equipmentLabel }}</div>
                                <div><span class="font-semibold text-slate-900">Reservation:</span> {{ $reservationDate }} · {{ $reservationTime }}</div>
                                @if ($priorityLabel)
                                    <div><span class="font-semibold text-slate-900">Priority:</span> <span class="ml-1 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $priorityBadgeClass }}">{{ $priorityLabel }}</span></div>
                                @endif
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                @if ($canEdit)
                                    <a href="{{ route('requestor.edit', $requestItem->id) }}" class="inline-flex flex-1 items-center justify-center rounded-full border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700">Edit</a>
                                @endif
                                @if ($canCancel)
                                    <form method="POST" action="{{ route('request.cancel', $requestItem->id) }}" class="flex-1" data-swal-confirm data-swal-title="Cancel this request?" data-swal-text="This will remove the pending request from the queue." data-swal-confirm-text="Yes, cancel it" data-swal-confirm-color="#dc2626">
                                        @csrf
                                        <button type="submit" class="w-full rounded-full border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">Cancel</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @else
        <section class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-[0_20px_60px_rgba(15,23,42,0.08)] sm:p-6 md:rounded-[32px] lg:p-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-emerald-600">Overview</p>
                    <h2 class="mt-3 text-2xl font-semibold text-slate-950 sm:text-3xl">Welcome, {{ $firstName }}</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Monitor your reservation requests and upcoming bookings from one centralized workspace.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('requestor.index', ['tab' => 'requests']) }}" class="inline-flex items-center justify-center rounded-full bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-600/20 transition hover:bg-emerald-700">View All Requests</a>
                </div>
            </div>

            @if ($requests->isEmpty())
                <div class="mt-8 rounded-[28px] border border-dashed border-slate-300 bg-slate-50 p-8 text-center shadow-sm">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="mt-6 text-xl font-semibold text-slate-900">No reservations yet</h3>
                    <p class="mt-2 text-sm text-slate-600">Start with a new request and your dashboard will begin showing your upcoming reservations and status updates.</p>
                    <a href="{{ route('requestor.index', ['tab' => 'create']) }}" class="mt-6 inline-flex items-center justify-center rounded-full bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-600/20 transition hover:bg-emerald-700">Create Request</a>
                </div>
            @else
                <div class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @php
                        $stats = [
                            ['label' => 'Total Requests', 'value' => $totalCount, 'description' => 'All requests submitted', 'icon' => '<svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>', 'tone' => 'bg-slate-50 text-slate-700'],
                            ['label' => 'Pending Requests', 'value' => $pendingCount, 'description' => 'Awaiting review', 'icon' => '<svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>', 'tone' => 'bg-amber-50 text-amber-700'],
                            ['label' => 'Approved Requests', 'value' => $approvedCount, 'description' => 'Ready for use', 'icon' => '<svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/></svg>', 'tone' => 'bg-emerald-50 text-emerald-700'],
                            ['label' => 'Rejected Requests', 'value' => $rejectedCount, 'description' => 'Needs attention', 'icon' => '<svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12"/></svg>', 'tone' => 'bg-rose-50 text-rose-700'],
                            ['label' => 'Upcoming Reservations', 'value' => $upcomingCount, 'description' => 'Scheduled ahead', 'icon' => '<svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>', 'tone' => 'bg-sky-50 text-sky-700'],
                            ['label' => 'Completed Reservations', 'value' => $completedCount, 'description' => 'Finished successfully', 'icon' => '<svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>', 'tone' => 'bg-violet-50 text-violet-700'],
                        ];
                    @endphp
                    @foreach ($stats as $stat)
                        <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <div class="rounded-2xl p-3 {{ $stat['tone'] }}">{!! $stat['icon'] !!}</div>
                                <div class="text-right">
                                    <p class="text-3xl font-semibold text-slate-900">{{ $stat['value'] }}</p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $stat['label'] }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $stat['description'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8 rounded-[24px] border border-slate-200 bg-slate-50 p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Upcoming reservations</p>
                            <h3 class="mt-2 text-xl font-semibold text-slate-900">Next upcoming bookings</h3>
                        </div>
                    </div>

                    @if ($upcomingReservations->isEmpty())
                        <div class="mt-6 rounded-[20px] border border-dashed border-slate-300 bg-white p-5 text-sm text-slate-600">No approved reservations are scheduled yet.</div>
                    @else
                        <div class="mt-6 grid gap-4 xl:grid-cols-2">
                            @foreach ($upcomingReservations as $upcomingReservation)
                                @php
                                    $statusKey = strtolower((string) ($upcomingReservation->status ?? 'pending'));
                                    $statusBadgeClass = $statusBadgeClasses[$statusKey] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
                                    $statusLabel = $statusLabels[$statusKey] ?? ucfirst(str_replace('_', ' ', $statusKey));
                                @endphp
                                <div class="rounded-[20px] border border-slate-200 bg-white p-4 shadow-sm">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">{{ $upcomingReservation->control_number ?? '-' }}</p>
                                            <h4 class="mt-2 text-lg font-semibold text-slate-900">{{ $upcomingReservation->name_of_activity ?? '-' }}</h4>
                                        </div>
                                        <x-status-badge :status="$statusKey" :label="$statusLabel" />
                                    </div>
                                    <div class="mt-4 space-y-2 text-sm text-slate-600">
                                        <div><span class="font-semibold text-slate-900">Venue:</span> {{ empty($upcomingReservation->getVenueNames()) ? '-' : implode(', ', $upcomingReservation->getVenueNames()) }}</div>
                                        <div><span class="font-semibold text-slate-900">Reservation Date:</span> {{ $upcomingReservation->start_date ? $upcomingReservation->start_date->format('M d, Y') : '-' }}</div>
                                        <div><span class="font-semibold text-slate-900">Time:</span> {{ $upcomingReservation->start_time ? $upcomingReservation->start_time : '-' }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </section>
    @endif
</div>

<script>
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
</script>

@endsection