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
                        <th class="px-4 py-3 font-medium">Reference ID</th>
                        <th class="px-4 py-3 font-medium">Requestor</th>
                        <th class="px-4 py-3 font-medium">Department</th>
                        <th class="px-4 py-3 font-medium">Venue</th>
                        <th class="px-4 py-3 font-medium">Equipment</th>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($requests as $request)
                        <tr class="cursor-pointer transition hover:bg-slate-50 focus:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-rose-500" data-request-url="{{ route('request.show', $request->id) }}" role="link" tabindex="0" aria-label="Open request details for {{ $request->control_number }}">
                            <td class="px-4 py-4 font-medium text-slate-900">{{ $request->control_number }}</td>
                            <td class="px-4 py-4">{{ $request->requester?->name ?? 'Unknown' }}</td>
                            <td class="px-4 py-4">{{ $request->department ?? '—' }}</td>
                            <td class="px-4 py-4">{{ implode(', ', $request->getVenueNames()) ?: '—' }}</td>
                            <td class="px-4 py-4">{{ implode(', ', $request->getEquipmentItems()) ?: '—' }}</td>
                            <td class="px-4 py-4">{{ $request->start_date ? \Carbon\Carbon::parse($request->start_date)->format('M d, Y') : '—' }}</td>
                            <td class="px-4 py-4"><x-request-status-badge :request="$request" /></td>
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
<script>
    document.querySelectorAll('[data-request-url]').forEach((requestTarget) => {
        requestTarget.addEventListener('click', () => { window.location.href = requestTarget.dataset.requestUrl; });
        requestTarget.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); window.location.href = requestTarget.dataset.requestUrl; }
        });
    });
</script>
@endsection
