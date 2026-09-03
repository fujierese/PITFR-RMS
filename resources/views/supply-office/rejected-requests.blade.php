@extends('layouts.app')
@section('title', 'Rejected Requests')

@section('content')
<div class="space-y-6">
    <x-page-header eyebrow="Requests" title="Rejected Requests" description="Review declined reservations and their notes." accent="rose">
        <x-slot:actions><a href="{{ route('supply-office.index') }}" class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Back to Dashboard</a></x-slot:actions>
    </x-page-header>

    <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200/50">
        @include('supply-office.partials.request-filters', ['action' => route('supply-office.requests.rejected')])

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
                        <tr class="transition hover:bg-slate-50 focus-within:bg-slate-50">
                            <td class="px-4 py-4 font-medium text-slate-900"><a href="{{ route('request.show', $request->id) }}" class="rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-600">{{ $request->control_number }}</a></td>
                            <td class="px-4 py-4">{{ $request->requester?->name ?? 'Unknown' }}</td>
                            <td class="px-4 py-4">{{ $request->department ?? '—' }}</td>
                            <td class="px-4 py-4">{{ implode(', ', $request->getVenueNames()) ?: '—' }}</td>
                            <td class="px-4 py-4">{{ $request->start_date ? \Carbon\Carbon::parse($request->start_date)->format('M d, Y') : '—' }}</td>
                            <td class="px-4 py-4"><x-status-badge status="rejected" label="Rejected" /></td>
                            <td class="px-4 py-4 text-right"></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-slate-500">No rejected requests found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $requests->links() }}</div>
    </div>
</div>
@endsection
