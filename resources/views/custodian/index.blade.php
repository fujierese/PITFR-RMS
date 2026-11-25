@extends('layouts.app')
@section('title', $filter === 'all' ? 'All Requests' : ucfirst($filter) . ' Requests')

@section('content')

<div class="mb-6">
    <x-page-header
        eyebrow="Requests"
        :title="$filter === 'all' ? 'All Requests' : ucfirst($filter) . ' Requests'"
        :description="$filter === 'all' ? 'Manage facility requests, track assigned resources, and review approvals from a single page.' : 'Review ' . $filter . ' requests assigned to your ' . $custodianType . ' resources.'"
        accent="slate"
    >
        <x-slot:actions>
            <a href="{{ route('custodian.index') }}" class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Back to Dashboard</a>
        </x-slot:actions>
    </x-page-header>
</div>

<section class="mb-6 rounded-3xl border border-cyan-200 bg-cyan-50 p-4 shadow-sm sm:p-5" aria-labelledby="custodian-workflow-heading">
    <div class="flex items-start gap-3">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-cyan-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 21a9 9 0 110-18 9 9 0 010 18z"/>
        </svg>
        <div>
            <h2 id="custodian-workflow-heading" class="text-sm font-semibold text-slate-950">Your review step</h2>
            @if($custodianType === 'equipment')
                <p class="mt-1 text-sm leading-6 text-slate-700">After availability is confirmed, endorse the equipment you can provide or decline it with a reason. Record returns after the activity.</p>
            @else
                <p class="mt-1 text-sm leading-6 text-slate-700">Confirm whether the venue is free for the requested date and time. This is an availability check, not an approval.</p>
            @endif
        </div>
    </div>
</section>

<div class="rounded-3xl bg-white shadow-xl ring-1 ring-slate-200/50 overflow-hidden">
    <x-search-filter-bar
        class="mt-6 mx-6 mb-6"
        :action="route('custodian.index')"
        :search="$search"
        placeholder="Search requests"
        :hidden="['filter' => $filter]"
        :filters="[
            ['type' => 'select', 'name' => 'venue', 'label' => 'Venue', 'value' => $venueFilter, 'options' => ['' => 'All'] + collect($requestVenueOptions)->mapWithKeys(fn ($v) => [$v => $v])->all()],
            ['type' => 'select', 'name' => 'sort', 'label' => 'Sort', 'value' => $sort, 'default' => 'oldest', 'options' => ['oldest' => 'Earliest reservation first', 'latest' => 'Latest reservation first']],
            ['type' => 'date', 'name' => 'date_from', 'label' => 'Date from', 'value' => $dateFrom],
            ['type' => 'date', 'name' => 'date_to', 'label' => 'Date to', 'value' => $dateTo],
        ]"
        :clear-url="route('custodian.index', ['filter' => $filter])"
    />

    <div class="overflow-x-auto">
        <table class="min-w-[1050px] w-full text-left text-sm text-slate-600">
            <thead class="border-b border-slate-200 text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Control Number</th>
                    <th class="px-4 py-3 font-medium">Activity</th>
                    <th class="px-4 py-3 font-medium" colspan="3">Venue schedules and requested equipment</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($requests as $req)
                    @php
                        $statusField = $custodianType . '_status';
                        $displayStatus = $custodianType === 'equipment' ? ($req->custodian_status ?? 'pending') : $req->$statusField;
                    @endphp
                    <tr class="cursor-pointer transition hover:bg-slate-50 focus:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-emerald-500" data-request-row data-request-url="{{ route('request.show', $req->id) }}" data-status="{{ $displayStatus }}" role="link" tabindex="0" aria-label="Open request details for {{ $req->control_number }}">
                        <td class="px-4 py-4 font-medium text-slate-900">{{ $req->control_number }}</td>
                        <td class="px-4 py-4 text-slate-800">
                            <p class="font-medium">{{ $req->name_of_activity ?? '—' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $req->requester?->name ?? $req->requested_by }}@if($req->department) <span aria-hidden="true">·</span> {{ $req->department }}@endif</p>
                        </td>
                        <td class="px-4 py-4" colspan="3">
                            @include('custodian.partials.request-resources', [
                                'requestItem' => $req,
                                'custodianType' => $custodianType,
                                'assignedVenueNames' => $assignedVenueNames,
                                'assignedEquipmentNames' => $assignedEquipmentNames,
                            ])
                        </td>
                        <td class="px-4 py-4">
                            <x-request-status-badge :request="$req" />
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
                        <td class="px-4 py-4 whitespace-nowrap">
                            <a href="{{ route('request.show', $req->id) }}" class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-100" aria-label="Review request {{ $req->control_number }}">Review</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-sm text-slate-500">
                        @if($search !== '')
                            No requests match “{{ $search }}”. <a href="{{ route('custodian.index', ['filter' => $filter]) }}" class="font-semibold text-emerald-700 hover:underline">Clear search</a>
                        @else
                            No {{ $filter }} requests found.
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
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
