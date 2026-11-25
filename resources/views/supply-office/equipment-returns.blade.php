@extends('layouts.app')
@section('title', 'Equipment Returns')

@section('content')
<div class="space-y-6">
    <div class="rounded-3xl bg-gradient-to-r from-sky-600 to-indigo-600 p-6 text-white shadow-sm">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Equipment Returns</h1>
                <p class="mt-2 text-sm text-sky-100">Track pending, partial, returned, and overdue equipment returns.</p>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-sky-50">Open a request to record returned quantities and condition with its equipment custodian. Follow up on partial and overdue returns.</p>
            </div>
            <a href="{{ route('supply-office.index') }}" class="inline-flex items-center rounded-full border border-white/30 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">Back to Dashboard</a>
        </div>
    </div>

    <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200/50">
        <x-search-filter-bar
            class="mb-6"
            :action="route('supply-office.requests.returns')"
            :search="$searchQuery"
            placeholder="Search request number, activity, organization, venue, equipment..."
            :filters="[
                ['type' => 'text', 'name' => 'department', 'label' => 'Department', 'value' => $departmentFilter, 'placeholder' => 'Department'],
                ['type' => 'text', 'name' => 'venue', 'label' => 'Venue', 'value' => $venueFilter, 'placeholder' => 'Venue'],
                ['type' => 'select', 'name' => 'priority', 'label' => 'Priority', 'value' => $priorityFilter ?? '', 'options' => ['' => 'All priorities', 'regular' => 'Regular', 'institutional' => 'Institutional']],
                ['type' => 'date', 'name' => 'date_from', 'label' => 'Date from', 'value' => $dateFrom],
                ['type' => 'date', 'name' => 'date_to', 'label' => 'Date to', 'value' => $dateTo],
            ]"
        />

        <div class="grid gap-4 lg:grid-cols-2">
            @php($sections = [
                ['label' => 'Pending Return', 'items' => $pendingReturns, 'tone' => 'slate'],
                ['label' => 'Partial Return', 'items' => $partialReturns, 'tone' => 'amber'],
                ['label' => 'Returned', 'items' => $returnedRequests, 'tone' => 'emerald'],
                ['label' => 'Overdue', 'items' => $overdueRequests, 'tone' => 'rose'],
            ])
            @foreach($sections as $section)
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <h2 class="text-lg font-semibold text-slate-900">{{ $section['label'] }}</h2>
                    <div class="mt-4 space-y-3">
                        @forelse($section['items'] as $request)
                            <div class="group rounded-xl border border-slate-200 bg-white p-3 transition hover:border-sky-300 hover:bg-sky-50 focus-within:ring-2 focus-within:ring-sky-500">
                                <a href="{{ route('request.show', $request->id) }}" class="block rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600" aria-label="Open Request Details for {{ $request->control_number }}">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $request->control_number }}</p>
                                        <p class="text-sm text-slate-600">{{ $request->requester?->name ?? 'Unknown' }} • {{ $request->department ?? '—' }}</p>
                                    </div>
                                    <span class="rounded-full bg-{{ $section['tone'] }}-100 px-2.5 py-1 text-xs font-semibold text-{{ $section['tone'] }}-700">{{ $section['label'] }}</span>
                                </div>
                                <div class="mt-2 text-sm text-slate-600">
                                    <p>Venue: {{ implode(', ', $request->getVenueNames()) ?: '—' }}</p>
                                    <p>Equipment: {{ implode(', ', $request->getVenueEquipmentLabels() ?: $request->getEquipmentItems()) ?: '—' }}</p>
                                </div>
                                </a>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No requests in this category.</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
