@extends('layouts.app')

@section('title', 'Reservation History')

@section('content')
<main class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
    <header class="rounded-3xl bg-slate-900 p-6 text-white shadow-lg">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-300">Records</p>
        <h1 class="mt-2 text-3xl font-bold">Reservation History</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-300">Completed reservations appear here after they end and any borrowed equipment is returned.</p>
    </header>

    <form method="GET" action="{{ route('history.index') }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="sm:col-span-2 lg:col-span-2">
                <label class="sr-only" for="history-search">Search past reservations</label>
                <input id="history-search" name="search" value="{{ request('search') }}" type="search" placeholder="Search control number, activity, or venue" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100">
            </div>
            <div>
                <label class="sr-only" for="history-year">Filter by year</label>
                <select id="history-year" name="year" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-700 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                    <option value="">All years</option>
                    @foreach($years as $year)
                        <option value="{{ $year }}" @selected((string) request('year') === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="sr-only" for="history-venue">Filter by venue</label>
                <select id="history-venue" name="venue" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-700 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                    <option value="">All venues</option>
                    @foreach($venues as $venue)
                        <option value="{{ $venue }}" @selected(mb_strtolower((string) request('venue')) === mb_strtolower($venue))>{{ $venue }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="sr-only" for="history-sort">Sort reservations</label>
                <select id="history-sort" name="sort" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-700 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                    <option value="newest" @selected(request('sort', 'newest') !== 'oldest')>Newest first</option>
                    <option value="oldest" @selected(request('sort') === 'oldest')>Oldest first</option>
                </select>
            </div>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <button class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2" type="submit">Apply filters</button>
            @if(request()->hasAny(['search', 'year', 'venue', 'sort']))
                <a class="text-sm font-semibold text-slate-600 underline decoration-slate-300 underline-offset-4 hover:text-slate-900" href="{{ route('history.index') }}">Clear filters</a>
            @endif
            <p class="ml-auto text-sm text-slate-500" aria-live="polite">
                {{ $reservations->total() }} {{ \Illuminate\Support\Str::plural('reservation', $reservations->total()) }} found
            </p>
        </div>
    </form>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="divide-y divide-slate-100">
            @forelse($reservations as $reservation)
                @php
                    $canViewDetails = auth()->user()->isAdmin()
                        || (int) auth()->user()->getKey() === (int) $reservation->requested_by_id
                        || in_array((int) auth()->user()->getKey(), array_map('intval', $reservation->getAssignedEquipmentCustodianIds()), true)
                        || \App\Models\Venue::query()->whereIn('name', $reservation->getVenueNames())->where('custodian_id', auth()->user()->getKey())->exists();
                @endphp
                <article class="space-y-4 p-4 transition hover:bg-slate-50/70 sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Control number</p>
                            <p class="mt-0.5 font-semibold text-slate-900">{{ $reservation->control_number }}</p>
                        </div>
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                            Completed
                        </span>
                    </div>
                    <p class="text-base font-semibold text-slate-900">{{ $reservation->name_of_activity }}</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Venue</p>
                            <p class="mt-1 text-sm text-slate-700">{{ implode(', ', $reservation->getVenueNames()) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Date and time</p>
                            <div class="mt-1 space-y-1 text-sm text-slate-700">
                            @foreach($reservation->getVenueScheduleSummaries() as $schedule)
                                <p>{{ $schedule['label'] }}</p>
                            @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="pt-1">
                        @if($canViewDetails)
                            <a class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 px-3 py-2 text-sm font-semibold text-emerald-800 hover:border-emerald-400 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500" href="{{ route('request.show', $reservation->id) }}">
                                View details <span aria-hidden="true">→</span>
                            </a>
                        @else
                            <span class="text-xs text-slate-400">Details restricted</span>
                        @endif
                    </div>
                </article>
            @empty
                <p class="px-5 py-10 text-center text-sm text-slate-500">{{ request()->filled('search') ? 'No past reservations match “' . request('search') . '”.' : 'No past reservations match these filters.' }}</p>
            @endforelse
        </div>

        <div class="border-t border-slate-100 p-4">{{ $reservations->links() }}</div>
    </section>
</main>
@endsection
