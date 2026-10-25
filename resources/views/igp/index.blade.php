@extends('layouts.app')
@section('title', 'IGP Reservations')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <header class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200/50">
        <h1 class="text-2xl font-bold text-slate-900">IGP Reservation Coordination</h1>
        <p class="mt-2 text-sm text-slate-600">Only reservations confirmed as available by the Supply Office appear here. Record payment after receiving the official receipt.</p>
    </header>

    @if(session('success'))
        <p class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('success') }}</p>
    @endif

    @forelse($requests as $facilityRequest)
        <article class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200/50">
            <div class="flex flex-col justify-between gap-4 sm:flex-row">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ $facilityRequest->control_number }} — {{ $facilityRequest->name_of_activity }}</h2>
                    <p class="mt-1 text-sm text-slate-600">Requestor: {{ $facilityRequest->requester?->name ?? 'Unknown' }} · {{ $facilityRequest->requester?->office_or_organization ?? 'Outsider' }}</p>
                    <div class="mt-1 space-y-1 text-sm text-slate-600">
                        @forelse($facilityRequest->getVenueScheduleSummaries() as $schedule)
                            <p>{{ $schedule['venue'] }} · {{ $schedule['label'] }}</p>
                        @empty
                            <p>No venue schedule listed</p>
                        @endforelse
                    </div>
                    <p class="mt-2 text-sm font-medium text-slate-700">Reservation status: {{ ucfirst(str_replace('_', ' ', $facilityRequest->status)) }} · Payment: {{ ucfirst(str_replace('_', ' ', $facilityRequest->igp_payment_status ?? 'awaiting payment')) }}</p>
                    @if($facilityRequest->igp_paid_at)
                        <p class="mt-1 text-xs text-slate-500">Receipt {{ $facilityRequest->igp_receipt_number }} recorded by {{ $facilityRequest->paymentRecorder?->name ?? 'IGP' }} on {{ $facilityRequest->igp_paid_at->format('M j, Y g:i A') }}.</p>
                    @endif
                    @if($facilityRequest->igp_refund_status === 'refunded')
                        <p class="mt-1 text-xs text-slate-500">Refund {{ $facilityRequest->igp_refund_reference }} recorded by {{ $facilityRequest->refundRecorder?->name ?? 'IGP' }} on {{ $facilityRequest->igp_refunded_at?->format('M j, Y g:i A') }}.</p>
                    @endif
                </div>

                <div class="flex min-w-64 flex-col gap-3">
                    @if($facilityRequest->igp_payment_status !== 'paid')
                        <form method="POST" action="{{ route('igp.payment', $facilityRequest) }}" class="space-y-2">
                            @csrf
                            <label for="receipt-{{ $facilityRequest->id }}" class="block text-sm font-medium text-slate-700">Official receipt number</label>
                            <input id="receipt-{{ $facilityRequest->id }}" name="igp_receipt_number" required maxlength="100" value="{{ old('igp_receipt_number') }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Enter receipt number">
                            <button type="submit" class="w-full rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Record payment</button>
                        </form>
                    @elseif($facilityRequest->cancellation_status === 'confirmed' && $facilityRequest->igp_refund_status !== 'refunded')
                        <form method="POST" action="{{ route('igp.refund', $facilityRequest) }}" class="space-y-2">
                            @csrf
                            <label for="refund-{{ $facilityRequest->id }}" class="block text-sm font-medium text-slate-700">Refund reference</label>
                            <input id="refund-{{ $facilityRequest->id }}" name="igp_refund_reference" required maxlength="100" value="{{ old('igp_refund_reference') }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Enter refund reference">
                            <label for="refund-notes-{{ $facilityRequest->id }}" class="block text-sm font-medium text-slate-700">Notes (optional)</label>
                            <textarea id="refund-notes-{{ $facilityRequest->id }}" name="igp_refund_notes" rows="2" maxlength="1000" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('igp_refund_notes') }}</textarea>
                            <button type="submit" class="w-full rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Record completed refund</button>
                        </form>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <div class="rounded-3xl bg-white p-8 text-center text-sm text-slate-500 shadow-sm ring-1 ring-slate-200/50">No outsider reservations are awaiting IGP coordination.</div>
    @endforelse

    {{ $requests->links() }}
</div>
@endsection
