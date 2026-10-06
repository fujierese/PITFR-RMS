@extends('layouts.app')
@section('title', 'Final Approval Queue')

@section('content')
<div class="space-y-6">
    <x-page-header eyebrow="Requests" title="Final Approval Queue" description="Review requests that have completed custodian endorsement and are waiting for final approval." accent="amber">
        <x-slot:actions><a href="{{ route('supply-office.index') }}" class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Back to Dashboard</a></x-slot:actions>
    </x-page-header>

    <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200/50">
        @include('supply-office.partials.request-filters', ['action' => route('supply-office.requests.final-approval')])

        @if($requests->isEmpty())
            <div class="rounded-[28px] border border-dashed border-slate-300 bg-slate-50 p-6 text-center shadow-sm sm:p-8">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-700 mb-4">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-900">No requests are waiting for final approval.</h3>
                <p class="mt-2 text-sm text-slate-600">Requests will appear here once they're ready for your final review.</p>
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
                @foreach($requests as $request)
                    <tr class="transition hover:bg-slate-50">
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

            <div class="mt-6">{{ $requests->links() }}</div>
        @endif
    </div>
</div>
@endsection
