@extends('layouts.app')
@section('title', 'Custodian Dashboard')

@section('content')

<div class="mb-6">
    <div class="rounded-3xl bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-700 p-4 sm:p-6 shadow-xl text-white">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold">{{ $custodianType === 'venue' ? '🏛️' : '🔧' }} {{ ucfirst($custodianType) }} Custodian Dashboard</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-200">Manage facility requests, track assigned resources, and review approvals from a single page.</p>
            </div>
        </div>
    </div>
</div>

<div class="dashboard-stat-cards grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 mb-6">
    <div class="stat-card cursor-pointer transform transition duration-200 ease-out hover:-translate-y-0.5 hover:shadow-xl rounded-3xl border border-slate-200 bg-white p-5 shadow-sm" data-filter="all">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Total</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $stats['total'] }}</p>
            </div>
            <div class="rounded-2xl bg-emerald-100 p-3 text-emerald-700">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
        </div>
    </div>
    <div class="stat-card cursor-pointer transform transition duration-200 ease-out hover:-translate-y-0.5 hover:shadow-xl rounded-3xl border border-slate-200 bg-white p-5 shadow-sm" data-filter="pending">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Pending</p>
                <p class="mt-3 text-3xl font-semibold text-amber-600">{{ $stats['pending'] }}</p>
            </div>
            <div class="rounded-2xl bg-amber-100 p-3 text-amber-700">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>
    <div class="stat-card cursor-pointer transform transition duration-200 ease-out hover:-translate-y-0.5 hover:shadow-xl rounded-3xl border border-slate-200 bg-white p-5 shadow-sm" data-filter="approved">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Approved</p>
                <p class="mt-3 text-3xl font-semibold text-emerald-700">{{ $stats['approved'] }}</p>
            </div>
            <div class="rounded-2xl bg-emerald-100 p-3 text-emerald-700">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>
</div>

<div class="rounded-3xl bg-white shadow-xl ring-1 ring-slate-200/50 overflow-hidden">
    <div class="p-5 border-b border-slate-200/80 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-slate-900">
                {{ $custodianType === 'venue' ? '🏛️' : '🔧' }} Requests for {{ ucfirst($custodianType) }} Approval
            </h2>
            <p class="mt-1 text-sm text-slate-500">Showing {{ $requests->count() }} request{{ $requests->count() === 1 ? '' : 's' }}.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach(['all'=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $val => $label)
                <a href="?filter={{ $val }}"
                   class="w-full rounded-full px-4 py-1.5 text-center text-xs font-semibold transition sm:w-auto
                   {{ $filter === $val
                       ? 'bg-slate-900 text-white shadow-sm'
                       : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="divide-y divide-slate-200/70">
        @forelse($requests as $req)
        @php
            $statusField = $custodianType . '_status';
            $displayStatus = $custodianType === 'equipment'
                ? ($req->custodian_status ?? 'pending')
                : $req->$statusField;
            $displayStart = $req->reservationSchedule?->start_datetime ?? $req->start_date;
        @endphp
        <div class="p-6 hover:bg-slate-50 transition" data-request-row data-status="{{ $displayStatus }}" data-upcoming="{{ $displayStart && optional($displayStart)->toDateString() >= now()->toDateString() ? 'true' : 'false' }}">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <a href="{{ route('request.show', $req->id) }}" class="group flex-1 min-w-0 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg sm:p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 text-slate-900">
                                <h3 class="text-xl font-semibold truncate">{{ $req->name_of_activity }}</h3>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide
                                    {{ $displayStatus === 'approved' ? 'bg-emerald-100 text-emerald-700' :
                                       ($displayStatus === 'rejected' ? 'bg-red-100 text-red-700' :
                                       'bg-amber-100 text-amber-700') }}">
                                    {{ ucfirst($displayStatus) }}
                                </span>
                                @if(!empty($req->is_emergency))
                                    <span class="rounded-full border border-red-200 bg-red-100 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-red-700">
                                        🔴 URGENT
                                    </span>
                                @endif
                            </div>
                            @if($custodianType === 'equipment' && $req->equipment_status !== $displayStatus)
                                <p class="mt-2 text-xs text-slate-500">Overall status: <span class="font-semibold text-slate-700">{{ ucfirst($req->equipment_status) }}</span></p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">{{ $req->control_number }}</span>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">{{ $req->department }}</span>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-2xl bg-slate-50 p-3 text-slate-700">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Date</p>
                            <p class="mt-1 text-sm font-semibold">{{ $req->start_date->format('M j, Y') }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-3 text-slate-700">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Time</p>
                            <p class="mt-1 text-sm font-semibold">
                                {{ \Carbon\Carbon::parse($req->start_time)->format('g:i A') }}@if(!empty($req->end_time)) — {{ \Carbon\Carbon::parse($req->end_time)->format('g:i A') }}@endif
                            </p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-3 text-slate-700">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Requested by</p>
                            <p class="mt-1 text-sm font-semibold">{{ $req->requester?->name ?? $req->requested_by }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-3 text-slate-700">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Position</p>
                            <p class="mt-1 text-sm font-semibold">{{ $req->requester?->position ?? $req->requested_by_position }}</p>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        @if($custodianType === 'venue')
                            @foreach($req->getVenueNames() as $v)
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 border border-emerald-100">📍 {{ $v }}</span>
                            @endforeach
                        @else
                            @foreach($req->getEquipmentItems() as $e)
                                <span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-medium text-violet-700 border border-violet-100">🔧 {{ $e }}@if(!empty($req->getEquipmentQuantities()[$e])) (×{{ $req->getEquipmentQuantities()[$e] }})@endif</span>
                            @endforeach
                            @if($req->equipment_returned_status)
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 border border-slate-200">🔄 {{ ucfirst(str_replace('_', ' ', $req->equipment_returned_status)) }}</span>
                            @endif
                        @endif
                    </div>

                    @if($custodianType === 'equipment')
                        @php $myEquipment = $req->getAssignedEquipmentForCustodian((int) Auth::id()); @endphp
                        <div class="mt-4">
                            @if(!empty($myEquipment))
                                <p class="text-sm text-slate-500 mb-2">Your assigned equipment in this request:</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($myEquipment as $item => $qty)
                                        <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 border border-indigo-100">🔧 {{ $item }} (×{{ $qty }})</span>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm italic text-slate-400">No equipment assigned to you in this request.</p>
                            @endif
                        </div>
                    @endif

                    @if($displayStatus === 'rejected')
                        @php $notesField = $custodianType . '_notes'; @endphp
                        @if($req->$notesField)
                            <div class="mt-4 rounded-2xl bg-red-50 p-4 text-sm text-red-700 border border-red-100">
                                <strong>Note:</strong> {{ $req->$notesField }}
                            </div>
                        @endif
                    @endif
                </a>

                <div class="flex w-full flex-col gap-3 lg:w-72">
                    @if($displayStatus === 'pending')
                        <input type="text" id="notes-{{ $req->id }}" placeholder="Add notes (optional)" class="text-sm border border-slate-200 rounded-2xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <form method="POST" action="{{ route('custodian.update') }}" class="space-y-2">
                            @csrf
                            <input type="hidden" name="id" value="{{ $req->id }}">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="notes" id="approve-notes-{{ $req->id }}">
                            <button type="submit" onclick="document.getElementById('approve-notes-{{ $req->id }}').value = document.getElementById('notes-{{ $req->id }}').value" class="w-full rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 transition">✓ Approve</button>
                        </form>
                        <form method="POST" action="{{ route('custodian.update') }}" class="space-y-2">
                            @csrf
                            <input type="hidden" name="id" value="{{ $req->id }}">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="notes" id="reject-notes-{{ $req->id }}">
                            <button type="submit" onclick="document.getElementById('reject-notes-{{ $req->id }}').value = document.getElementById('notes-{{ $req->id }}').value" class="w-full rounded-2xl bg-red-500 px-4 py-3 text-sm font-semibold text-white hover:bg-red-600 transition">✕ Reject</button>
                        </form>
                    @else
                        @if($custodianType === 'equipment' && $req->equipment_status === 'approved' && $req->equipment_returned_status !== 'returned' && $req->start_date <= now()->toDateString())
                            <input type="text" id="return-notes-{{ $req->id }}" placeholder="Return notes (optional)" class="text-sm border border-slate-200 rounded-2xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <form method="POST" action="{{ route('custodian.update') }}">
                                @csrf
                                <input type="hidden" name="id" value="{{ $req->id }}">
                                <input type="hidden" name="action" value="return">
                                <input type="hidden" name="notes" id="return-notes-hidden-{{ $req->id }}">
                                <button type="submit" onclick="document.getElementById('return-notes-hidden-{{ $req->id }}').value = document.getElementById('return-notes-{{ $req->id }}').value" class="w-full rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 transition">🔄 Mark Returned</button>
                            </form>
                        @else
                            <div class="rounded-3xl bg-slate-50 p-4 text-sm text-slate-600 border border-slate-200">
                                <p>{{ $displayStatus === 'approved' ? '✅ You approved this request' : '❌ You rejected this request' }}</p>
                                @if($custodianType === 'equipment' && $req->equipment_returned_status === 'returned')
                                    <p class="mt-2 text-emerald-700">🔄 Equipment returned</p>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="py-20 text-center text-slate-500">
            <div class="text-5xl mb-4">{{ $custodianType === 'venue' ? '🏛️' : '🔧' }}</div>
            <p class="font-semibold text-slate-700 text-lg">No requests assigned to you</p>
            <p class="mt-1 text-sm">Requests for your {{ $custodianType }}s will appear here once assigned.</p>
        </div>
        @endforelse
        <div id="no-filter-results" class="hidden mt-4 rounded-2xl bg-white p-4 text-slate-600 text-center">
            No requests found for the selected filter.
        </div>
    </div>
</div>

<div class="rounded-3xl bg-white shadow-xl ring-1 ring-slate-200/50 overflow-hidden mt-6">
    <div class="p-5 border-b border-slate-200/80">
        <h2 class="text-xl font-semibold text-slate-900">Custodian Calendar</h2>
        <p class="mt-1 text-sm text-slate-500">Only events assigned to your managed venue or equipment are shown here.</p>
    </div>
    <div class="p-6">
        @include('calendar._calendar', [
            'dashboardData' => [
                'role' => 'custodian',
                'hideHeader' => true,
                'showVerificationQueue' => false,
                'showExport' => false,
                'showUserManagement' => false,
                'showAuditLogs' => false,
                'showHowToRequest' => false,
                'showViewOnlyCalendar' => false,
                'showStatsCards' => false,
                'showRequestList' => false,
            ]
        ])
    </div>
</div>

<style>
.stat-card.active {
    border-color: #10b981;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statCards = document.querySelectorAll('.dashboard-stat-cards .stat-card');
    const tableRows = document.querySelectorAll('[data-request-row]');
    const noFilterResults = document.getElementById('no-filter-results');

    function updateVisibleRows(filter) {
        let visibleCount = 0;

        tableRows.forEach(function(row) {
            const status = row.dataset.status;
            const shouldShow = filter === 'all' || status === filter;
            row.style.display = shouldShow ? '' : 'none';
            if (shouldShow) visibleCount++;
        });

        if (noFilterResults) {
            if (visibleCount === 0 && tableRows.length > 0) {
                noFilterResults.classList.remove('hidden');
            } else {
                noFilterResults.classList.add('hidden');
            }
        }
    }

    statCards.forEach(function(card) {
        card.addEventListener('click', function() {
            const filter = this.dataset.filter;
            statCards.forEach(function(otherCard) {
                otherCard.classList.remove('active');
            });
            this.classList.add('active');
            updateVisibleRows(filter);
        });
    });

    if (statCards.length) {
        statCards[0].classList.add('active');
    }
});
</script>
@endsection
