@extends('layouts.app')
@section('title', 'Final Approval Queue')

@section('content')
<div class="space-y-6">
    <div class="rounded-3xl bg-gradient-to-r from-amber-600 to-orange-500 p-6 text-white shadow-sm">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Final Approval Queue</h1>
                <p class="mt-2 text-sm text-amber-100">Review requests that have completed custodian endorsement and are waiting for final approval.</p>
            </div>
            <a href="{{ route('supply-office.index') }}" class="inline-flex items-center rounded-full border border-white/30 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">Back to Dashboard</a>
        </div>
    </div>

    <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200/50">
        <form method="GET" action="{{ route('supply-office.requests.final-approval') }}" class="mb-6 grid gap-3 md:grid-cols-4">
            <input type="text" name="search" value="{{ $searchQuery }}" placeholder="Search request" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
            <input type="text" name="department" value="{{ $departmentFilter }}" placeholder="Department" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
            <input type="text" name="venue" value="{{ $venueFilter }}" placeholder="Venue" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
            <select name="priority" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                <option value="">All priorities</option>
                <option value="regular" {{ $priorityFilter === 'regular' ? 'selected' : '' }}>Regular</option>
                <option value="institutional" {{ $priorityFilter === 'institutional' ? 'selected' : '' }}>Institutional</option>
            </select>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
            <input type="date" name="date_to" value="{{ $dateTo }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
            <button type="submit" class="rounded-xl bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Apply Filters</button>
            <a href="{{ route('supply-office.requests.final-approval') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-100">Reset</a>
        </form>

        @if($requests->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
                No requests are waiting for final approval.
            </div>
        @else
            <div class="space-y-4">
                @foreach($requests as $request)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Reference</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $request->control_number }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Requestor</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $request->requester?->name ?? 'Unknown' }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Department / Organization</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $request->department ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Requested Facility</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ implode(', ', $request->getVenueNames()) ?: '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Requested Equipment</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ implode(', ', $request->getEquipmentItems()) ?: '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Reservation Date</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $request->start_date ? \Carbon\Carbon::parse($request->start_date)->format('M d, Y') : '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Time Schedule</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $request->formatTimeForDisplay($request->start_time) }} - {{ $request->formatTimeForDisplay($request->end_time) }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Purpose</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $request->name_of_activity }}</p>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('request.show', $request->id) }}" class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">View Details</a>
                                <a href="{{ route('supply-office.requests.needs-reschedule') }}?id={{ $request->id }}" class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-100">Needs Reschedule</a>
                            </div>
                            <form method="POST" action="{{ route('supply-office.update') }}" class="flex flex-col gap-2 md:flex-row md:items-end">
                                @csrf
                                <input type="hidden" name="id" value="{{ $request->id }}">
                                <textarea name="notes" rows="2" placeholder="Add remarks before approving or rejecting" class="w-full md:w-80 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"></textarea>
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <button type="submit" name="action" value="approve" class="inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 md:w-auto">Approve</button>
                                    <button type="submit" name="action" value="reject" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 md:w-auto">Reject</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">{{ $requests->links() }}</div>
        @endif
    </div>
</div>
@endsection
