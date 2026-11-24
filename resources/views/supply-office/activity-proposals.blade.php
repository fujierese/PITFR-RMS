@extends('layouts.app')
@section('title', 'Activity Proposals')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <x-page-header eyebrow="Supply Office" title="Activity Proposals" description="Track proposal submissions, verification and deadlines. This does not change any reservation." accent="emerald" />

    @php
        $tabs = [
            'all' => 'All Documents',
            'draft_submitted' => 'Draft Submitted',
            'for_verification' => 'For Verification',
            'requires_correction' => 'Requires Correction',
            'verified' => 'Verified',
            'overdue' => 'Overdue',
        ];
        $docTone = fn ($s) => match ($s) {
            'verified' => 'bg-emerald-100 text-emerald-800',
            'for_verification' => 'bg-sky-100 text-sky-800',
            'requires_correction' => 'bg-amber-100 text-amber-900',
            'draft_submitted' => 'bg-slate-100 text-slate-700',
            default => 'bg-rose-100 text-rose-800',
        };
    @endphp

    <nav class="flex flex-wrap gap-2" aria-label="Filter activity proposals">
        @foreach($tabs as $key => $label)
            <a href="{{ route('supply-office.activity-proposals', $key === 'all' ? [] : ['filter' => $key]) }}" @if($filter === $key) aria-current="page" @endif
               class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-semibold {{ $filter === $key ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-300' }}">
                {{ $label }}
                <span class="rounded-full px-2 py-0.5 text-[11px] {{ $filter === $key ? 'bg-white/20' : 'bg-slate-100 text-slate-600' }}">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Request</th>
                        <th class="px-4 py-3">Requestor</th>
                        <th class="px-4 py-3">Activity date</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Document</th>
                        <th class="px-4 py-3">Compliance</th>
                        <th class="px-4 py-3">Deadline</th>
                        <th class="px-4 py-3">Last submitted</th>
                        <th class="px-4 py-3"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        @php $req = $row['request']; $latest = $row['latest']; @endphp
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-900">{{ $req->control_number }}</p>
                                <p class="text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($req->name_of_activity, 50) }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $req->requester?->name ?? $req->requested_by }}</td>
                            <td class="px-4 py-3 text-slate-700 whitespace-nowrap">{{ $req->start_date?->format('M j, Y') }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $latest ? ($latest->isFinal() ? 'Final' : 'Draft') : '—' }}</td>
                            <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $docTone($row['document']) }}">{{ $row['document_label'] }}</span></td>
                            <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $row['compliance'] === 'overdue' ? 'bg-rose-100 text-rose-800' : ($row['compliance'] === 'within_deadline' ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600') }}">{{ $row['compliance_label'] }}</span></td>
                            <td class="px-4 py-3 text-slate-700 whitespace-nowrap">{{ $req->proposal_deadline?->format('M j, Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-700 whitespace-nowrap">{{ $latest?->submitted_at?->format('M j, Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('request.show', $req->id) }}#activity-proposal" class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100">Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-10 text-center text-sm text-slate-500">No activity proposals in this list.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
