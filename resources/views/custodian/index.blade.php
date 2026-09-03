@extends('layouts.app')
@section('title', 'Custodian Dashboard')

@section('content')

<div class="mb-6">
    <x-page-header
        eyebrow="Requests"
        :title="$filter === 'all' ? ucfirst($custodianType) . ' Custodian Dashboard' : ucfirst($filter) . ' Requests'"
        :description="$filter === 'all' ? 'Manage facility requests, track assigned resources, and review approvals from a single page.' : 'Review ' . $filter . ' requests assigned to your ' . $custodianType . ' resources.'"
        accent="slate"
    >
        <x-slot:actions>
            <a href="{{ route('custodian.index', ['filter' => 'all']) }}" class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Back to Dashboard</a>
        </x-slot:actions>
    </x-page-header>
</div>

<div class="rounded-3xl bg-white shadow-xl ring-1 ring-slate-200/50 overflow-hidden">
    <form method="GET" action="{{ route('custodian.index') }}" class="mt-6 rounded-[24px] border border-slate-200 bg-slate-50 p-4 shadow-sm sm:p-5 md:mt-8 mx-6 mb-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 flex-1 gap-3">
                <label class="min-w-0 flex-1">
                <span class="sr-only">Search requests</span>
                <input type="search" name="search" value="{{ $search }}" placeholder="Search requests" class="w-full rounded-3xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                </label>
                <button type="submit" class="inline-flex shrink-0 items-center justify-center rounded-full bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700">Search</button>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <details class="relative" {{ ($venueFilter || $dateFrom || $dateTo || $sort !== 'latest') ? 'open' : '' }}>
                    <summary class="cursor-pointer list-none rounded-full border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100">Advanced Filters</summary>
                    <div class="absolute right-0 z-10 mt-3 w-[min(720px,calc(100vw-2rem))] rounded-[24px] border border-slate-200 bg-white p-4 shadow-xl sm:p-5">
                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label for="custodian-venue" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Venue</label>
                                <select id="custodian-venue" name="venue" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                    <option value="">All</option>
                                    @foreach($requestVenueOptions as $venueOption)
                                        <option value="{{ $venueOption }}" @selected($venueFilter === $venueOption)>{{ $venueOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="custodian-sort" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Sort</label>
                                <select id="custodian-sort" name="sort" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                    <option value="latest" @selected($sort === 'latest')>Latest</option>
                                    <option value="oldest" @selected($sort === 'oldest')>Oldest</option>
                                </select>
                            </div>
                            <div>
                                <label for="custodian-date-from" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Date from</label>
                                <input id="custodian-date-from" type="date" name="date_from" value="{{ $dateFrom }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            </div>
                            <div>
                                <label for="custodian-date-to" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Date to</label>
                                <input id="custodian-date-to" type="date" name="date_to" value="{{ $dateTo }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            </div>
                        </div>
                        <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-full bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700">Apply Filters</button>
                    </div>
                </details>
                <a href="{{ route('custodian.index', ['filter' => $filter]) }}" class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100">Clear</a>
            </div>
        </div>
        <input type="hidden" name="filter" value="{{ $filter }}">
    </form>

    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm text-slate-600">
            <thead class="border-b border-slate-200 text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Reference ID</th>
                    <th class="px-4 py-3 font-medium">Requestor</th>
                    <th class="px-4 py-3 font-medium">Department</th>
                    <th class="px-4 py-3 font-medium">{{ $custodianType === 'venue' ? 'Venue' : 'Equipment' }}</th>
                    <th class="px-4 py-3 font-medium">Date</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($requests as $req)
                    @php
                        $statusField = $custodianType . '_status';
                        $displayStatus = $custodianType === 'equipment' ? ($req->custodian_status ?? 'pending') : $req->$statusField;
                        $resourceNames = $custodianType === 'venue' ? $req->getVenueNames() : $req->getEquipmentItems();
                    @endphp
                    <tr class="transition hover:bg-slate-50 focus-within:bg-slate-50" data-request-row data-status="{{ $displayStatus }}">
                        <td class="px-4 py-4 font-medium text-slate-900">
                            <a href="{{ route('request.show', $req->id) }}" class="rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-600">{{ $req->control_number }}</a>
                            <p class="mt-1 max-w-xs text-xs text-slate-500">{{ $req->name_of_activity }}</p>
                        </td>
                        <td class="px-4 py-4">{{ $req->requester?->name ?? $req->requested_by }}</td>
                        <td class="px-4 py-4">{{ $req->department ?? '—' }}</td>
                        <td class="px-4 py-4">
                            <span>{{ implode(', ', $resourceNames) ?: '—' }}</span>
                            @if($custodianType === 'equipment' && $req->equipment_returned_status)
                                <p class="mt-1 text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', $req->equipment_returned_status)) }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <p>{{ $req->start_date ? $req->start_date->format('M d, Y') : '—' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ \Carbon\Carbon::parse($req->start_time)->format('g:i A') }}@if(!empty($req->end_time)) - {{ \Carbon\Carbon::parse($req->end_time)->format('g:i A') }}@endif</p>
                        </td>
                        <td class="px-4 py-4">
                            <x-status-badge :status="$displayStatus" />
                            @if(!empty($req->is_emergency))
                                <span class="mt-2 inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700 ring-1 ring-red-200">Urgent</span>
                            @endif
                            @if($displayStatus === 'rejected')
                                @php $notesField = $custodianType . '_notes'; @endphp
                                @if($req->$notesField)
                                    <p class="mt-2 max-w-xs text-xs text-rose-700">{{ $req->$notesField }}</p>
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-4 text-right align-top">
                            <div class="flex min-w-48 flex-col items-stretch gap-2 sm:min-w-56">
                                @if($displayStatus === 'pending')
                                    <input type="text" id="notes-{{ $req->id }}" placeholder="Add notes (optional)" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <form method="POST" action="{{ route('custodian.update') }}">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $req->id }}">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="notes" id="approve-notes-{{ $req->id }}">
                                            <button type="submit" onclick="document.getElementById('approve-notes-{{ $req->id }}').value = document.getElementById('notes-{{ $req->id }}').value" class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('custodian.update') }}">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $req->id }}">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="notes" id="reject-notes-{{ $req->id }}">
                                            <button type="submit" onclick="document.getElementById('reject-notes-{{ $req->id }}').value = document.getElementById('notes-{{ $req->id }}').value" class="inline-flex items-center justify-center rounded-xl bg-red-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-red-700">Reject</button>
                                        </form>
                                    </div>
                                @elseif($custodianType === 'equipment' && $req->equipment_status === 'approved' && $req->equipment_returned_status !== 'returned' && $req->start_date <= now()->toDateString())
                                    <input type="text" id="return-notes-{{ $req->id }}" placeholder="Return notes (optional)" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                    <form method="POST" action="{{ route('custodian.update') }}">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $req->id }}">
                                        <input type="hidden" name="action" value="return">
                                        <input type="hidden" name="notes" id="return-notes-hidden-{{ $req->id }}">
                                        <button type="submit" onclick="document.getElementById('return-notes-hidden-{{ $req->id }}').value = document.getElementById('return-notes-{{ $req->id }}').value" class="inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700">Mark Returned</button>
                                    </form>
                                @else
                                    <a href="{{ route('request.show', $req->id) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">View Details</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-slate-500">No {{ $filter }} requests found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
