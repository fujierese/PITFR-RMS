@extends('layouts.app')

@section('title', 'Priority Override Confirmation')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8">
    <div class="rounded-3xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-slate-900">Priority Override Confirmation</h1>
        <p class="mt-2 text-sm text-slate-600">A conflict was detected with an already approved reservation. Review the details below before proceeding.</p>

        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-lg font-semibold text-slate-900">Urgent Request</h2>
                <dl class="mt-3 space-y-2 text-sm text-slate-700">
                    <div><dt class="font-medium text-slate-500">Request ID</dt><dd>{{ $urgentRequestId }}</dd></div>
                    <div><dt class="font-medium text-slate-500">Requester</dt><dd>{{ $urgentRequesterName }}</dd></div>
                    <div><dt class="font-medium text-slate-500">Activity</dt><dd>{{ $urgentActivityName }}</dd></div>
                </dl>
            </div>

            <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-lg font-semibold text-slate-900">Conflicting Approved Request</h2>
                <dl class="mt-3 space-y-2 text-sm text-slate-700">
                    <div><dt class="font-medium text-slate-500">Request ID</dt><dd>{{ $conflictingRequestId }}</dd></div>
                    <div><dt class="font-medium text-slate-500">Requester</dt><dd>{{ $conflictingRequesterName }}</dd></div>
                    <div><dt class="font-medium text-slate-500">Activity</dt><dd>{{ $conflictingActivityName }}</dd></div>
                </dl>
            </div>
        </div>

        <div class="mt-6 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-lg font-semibold text-slate-900">Conflict Details</h2>
            <dl class="mt-3 grid gap-3 text-sm text-slate-700 md:grid-cols-2">
                <div><dt class="font-medium text-slate-500">Venue</dt><dd>{{ $venue }}</dd></div>
                <div><dt class="font-medium text-slate-500">Date</dt><dd>{{ $date }}</dd></div>
                <div><dt class="font-medium text-slate-500">Time</dt><dd>{{ $time }}</dd></div>
                <div><dt class="font-medium text-slate-500">Priority</dt><dd>{{ ucfirst($priority) }}</dd></div>
            </dl>
        </div>

        <form method="POST" action="{{ route('supply-office.priority-override.submit') }}" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="urgent_request_id" value="{{ $urgentRequestId }}">
            <input type="hidden" name="conflicting_request_id" value="{{ $conflictingRequestId }}">

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="new_start_date" class="block text-sm font-medium text-slate-700">Proposed Start Date</label>
                    <input id="new_start_date" type="date" name="new_start_date" value="{{ old('new_start_date', $date) }}" required class="mt-2 w-full rounded-2xl border border-slate-300 px-3 py-2 text-sm text-slate-700">
                </div>
                <div>
                    <label for="new_end_date" class="block text-sm font-medium text-slate-700">Proposed End Date</label>
                    <input id="new_end_date" type="date" name="new_end_date" value="{{ old('new_end_date', $date) }}" required class="mt-2 w-full rounded-2xl border border-slate-300 px-3 py-2 text-sm text-slate-700">
                </div>
                <div>
                    <label for="new_start_time" class="block text-sm font-medium text-slate-700">Proposed Start Time</label>
                    <input id="new_start_time" type="time" name="new_start_time" value="{{ old('new_start_time', $time) }}" required class="mt-2 w-full rounded-2xl border border-slate-300 px-3 py-2 text-sm text-slate-700">
                </div>
                <div>
                    <label for="new_end_time" class="block text-sm font-medium text-slate-700">Proposed End Time</label>
                    <input id="new_end_time" type="time" name="new_end_time" value="{{ old('new_end_time', '12:00') }}" required class="mt-2 w-full rounded-2xl border border-slate-300 px-3 py-2 text-sm text-slate-700">
                </div>
            </div>

            <div>
                <label for="override_reason" class="block text-sm font-medium text-slate-700">Priority Transfer Reason</label>
                <textarea id="override_reason" name="override_reason" rows="4" required class="mt-2 w-full rounded-2xl border border-slate-300 px-3 py-2 text-sm text-slate-700">{{ old('override_reason') }}</textarea>
            </div>

            <p class="rounded-2xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">This creates a formal rescheduling proposal for the approved reservation. Neither reservation is automatically cancelled or replaced.</p>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('supply-office.index') }}" class="inline-flex items-center rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel Override / Transfer</a>
                <button type="submit" class="inline-flex items-center rounded-2xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Confirm Override / Create Priority Transfer Proposal</button>
            </div>
        </form>
    </div>
</div>
@endsection
