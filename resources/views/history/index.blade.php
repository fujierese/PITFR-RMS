@extends('layouts.app')

@section('title', 'Reservation History')

@section('content')
<main class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
    <header class="rounded-3xl bg-slate-900 p-6 text-white shadow-lg">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-300">Records</p>
        <h1 class="mt-2 text-3xl font-bold">Reservation History</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-300">Past reservation records are visible to signed-in users. Request details and documents remain protected by role-based access.</p>
    </header>

    <form method="GET" action="{{ route('history.index') }}" class="flex flex-col gap-3 sm:flex-row">
        <label class="sr-only" for="history-search">Search past reservations</label>
        <input id="history-search" name="search" value="{{ request('search') }}" type="search" placeholder="Search control number, activity, or venue" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm">
        <button class="rounded-xl bg-emerald-700 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-800" type="submit">Search</button>
    </form>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Control number</th>
                        <th class="px-5 py-3">Activity</th>
                        <th class="px-5 py-3">Venue</th>
                        <th class="px-5 py-3">Date and time</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($reservations as $reservation)
                        @php
                            $canViewDetails = auth()->user()->isAdmin()
                                || (int) auth()->user()->getKey() === (int) $reservation->requested_by_id
                                || in_array((int) auth()->user()->getKey(), array_map('intval', $reservation->getAssignedEquipmentCustodianIds()), true)
                                || \App\Models\Venue::query()->whereIn('name', $reservation->getVenueNames())->where('custodian_id', auth()->user()->getKey())->exists();
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap px-5 py-4 font-medium text-slate-900">{{ $reservation->control_number }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ $reservation->name_of_activity }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ implode(', ', $reservation->getVenueNames()) }}</td>
                            <td class="px-5 py-4 text-slate-700">
                                @foreach($reservation->getVenueScheduleSummaries() as $schedule)
                                    <p class="mb-1 last:mb-0">{{ $schedule['venue'] }}: {{ $schedule['label'] }}</p>
                                @endforeach
                            </td>
                            <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ ucfirst(str_replace('_', ' ', $reservation->status)) }}</span></td>
                            <td class="px-5 py-4">
                                @if($canViewDetails)
                                    <a class="font-semibold text-emerald-700 hover:text-emerald-900" href="{{ route('request.show', $reservation->id) }}">View details</a>
                                @else
                                    <span class="text-xs text-slate-400">Restricted</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-10 text-center text-slate-500" colspan="6">No past reservations found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 p-4">{{ $reservations->links() }}</div>
    </section>
</main>
@endsection
