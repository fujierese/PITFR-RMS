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

            <div>
                <label for="override_reason" class="block text-sm font-medium text-slate-700">Override Reason</label>
                <textarea id="override_reason" name="override_reason" rows="4" required class="mt-2 w-full rounded-2xl border border-slate-300 px-3 py-2 text-sm text-slate-700"></textarea>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('supply-office.index') }}" class="inline-flex items-center rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel Override</a>
                <button type="submit" class="inline-flex items-center rounded-2xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Confirm Override</button>
            </div>
        </form>
    </div>
</div>
@endsection
