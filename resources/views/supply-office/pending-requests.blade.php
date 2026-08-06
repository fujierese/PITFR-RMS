@extends('layouts.app')
@section('title', 'Pending Requests')

@section('content')
<div class="space-y-6">
    <div class="rounded-3xl bg-gradient-to-r from-slate-700 to-slate-600 p-6 text-white shadow-sm">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Pending Requests</h1>
                <p class="mt-2 text-sm text-slate-200">Review incoming reservations that still need custodial and final approval.</p>
            </div>
            <a href="{{ route('supply-office.index') }}" class="inline-flex items-center rounded-full border border-white/30 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">Back to Dashboard</a>
        </div>
    </div>

    <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200/50">
        <form method="GET" action="{{ route('supply-office.requests.pending') }}" class="mb-6 grid gap-3 md:grid-cols-4">
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
            <a href="{{ route('supply-office.requests.pending') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-100">Reset</a>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm text-slate-600">
                <thead class="border-b border-slate-200 text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Reference</th>
                        <th class="px-4 py-3 font-medium">Requestor</th>
                        <th class="px-4 py-3 font-medium">Department</th>
                        <th class="px-4 py-3 font-medium">Venue</th>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($requests as $request)
                        <tr>
                            <td class="px-4 py-4 font-medium text-slate-900">{{ $request->control_number }}</td>
                            <td class="px-4 py-4">{{ $request->requester?->name ?? 'Unknown' }}</td>
                            <td class="px-4 py-4">{{ $request->department ?? '—' }}</td>
                            <td class="px-4 py-4">{{ implode(', ', $request->getVenueNames()) ?: '—' }}</td>
                            <td class="px-4 py-4">{{ $request->start_date ? \Carbon\Carbon::parse($request->start_date)->format('M d, Y') : '—' }}</td>
                            <td class="px-4 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Pending</span></td>
                            <td class="px-4 py-4 text-right">
                                <a href="{{ route('request.show', $request->id) }}" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-slate-500">No pending requests found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $requests->links() }}</div>
    </div>
</div>
@endsection
