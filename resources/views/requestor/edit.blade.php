@extends('layouts.app')
@section('title', 'Edit Request')

@section('content')
@php
    // QA-7: editing uses the same form as a new request, pre-filled from this request.
    $isNeedsReschedule = $request->status === 'needs_reschedule'
        || $request->venue_status === 'needs_reschedule'
        || $request->equipment_status === 'needs_reschedule';
@endphp
<div class="mx-auto w-full max-w-none space-y-4 px-1 py-2 sm:px-2 lg:max-w-6xl">
    <div class="rounded-[24px] border border-slate-200 bg-white px-5 py-5 shadow-sm sm:px-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ $request->control_number }}</p>
                <h1 class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ $isNeedsReschedule ? 'Reschedule Request' : 'Edit Request' }}</h1>
                <p class="mt-2 text-sm text-slate-600">
                    {{ $isNeedsReschedule
                        ? 'Choose another venue, date or time. Saving sends the request back for venue review.'
                        : 'Change any details below. Saving sends the request back for review from the start.' }}
                </p>
            </div>
            <a href="{{ route('request.show', $request) }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to request</a>
        </div>
        <ul class="mt-4 space-y-1 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600" aria-label="What cannot be changed">
            <li>The control number, date requested and your account details (name, college, department, position) cannot be changed here.</li>
            <li>Documents you already uploaded are kept unless you upload a replacement.</li>
            @if($request->status === 'needs_reschedule')
                <li>After a priority transfer only the venue and schedule can change; the activity name and department stay as they are.</li>
            @endif
        </ul>
        @php
            // CHG-02: say when a venue this request asked for can no longer be booked.
            $unbookableVenues = collect($request->getVenueNames())
                ->filter(fn ($name) => \App\Models\FacilityRequest::matchVenueOption($name, $venueOptions ?? []) === null);
        @endphp
        @foreach($unbookableVenues as $unbookableVenue)
            <p class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">You asked for {{ $unbookableVenue }}, which can no longer be booked. Choose another venue.</p>
        @endforeach
        @error('form_version')
            <div class="mt-4 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">{{ $message }}</div>
        @enderror
    </div>

    @include('requestor.partials.request_form', ['editingRequest' => $request])
</div>
@endsection
