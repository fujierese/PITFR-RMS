@extends('layouts.app')

@section('title', 'Usage Reports - Administrator')

@php
    $periodParams = ['date_from' => $dateFrom, 'date_to' => $dateTo, 'preset' => 'custom'];
    $breakdownUrl = fn ($type, $value) => route('supply-office.usage-reports', array_merge($periodParams, [
        'breakdown' => $type,
        'value' => $value,
    ]));
    $maxMonthlyCount = max(1, max($monthlyUsage ?: [0]));
@endphp

@section('content')
<div class="usage-report-page container mx-auto px-4 py-8">
    <div class="mx-auto max-w-7xl">
        <div class="usage-report-card rounded-3xl bg-white p-5 shadow-md sm:p-6">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Usage Reports</h1>
                    <p class="mt-1 text-sm text-slate-600">Review approved reservation activity for a selected date range.</p>
                    <p class="usage-report-print-meta mt-1 text-xs text-slate-500">Generated {{ now()->format('M d, Y g:i A') }}</p>
                </div>
                <div class="usage-report-screen-only print:hidden" style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    <a download="usage-report-{{ $dateFrom }}-to-{{ $dateTo }}.csv" href="{{ route('supply-office.usage-reports.export', ['date_from' => $dateFrom, 'date_to' => $dateTo, 'preset' => 'custom']) }}" style="display: inline-flex; align-items: center; justify-content: center; border-radius: 0.75rem; background: #047857; padding: 0.5rem 1rem; color: #fff; font-size: 0.875rem; font-weight: 600; text-decoration: none;">Download CSV</a>
                    <button type="button" onclick="window.print()" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Print report</button>
                </div>
            </div>

            <div class="mb-6 rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950">
                <p class="font-semibold">How to read this report</p>
                <p class="mt-1">Only approved reservations whose scheduled start falls within this date range are included. Equipment units are the approved quantities requested (not a count of returned or consumed items). Venue bookings count each venue-reservation pairing; participant totals add the expected participants for each request.</p>
            </div>

            <div class="mb-4 flex flex-wrap gap-2 print:hidden" aria-label="Quick date ranges">
                @foreach(['this_month' => 'This month', 'last_month' => 'Last month', 'calendar_half_year' => 'Calendar half-year', 'this_year' => 'This year'] as $key => $label)
                    <a href="{{ route('supply-office.usage-reports', ['preset' => $key]) }}" class="rounded-full border px-4 py-2 text-sm font-semibold transition {{ $preset === $key ? 'border-blue-700 bg-blue-700 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">{{ $label }}</a>
                @endforeach
            </div>
            <p class="mb-4 text-xs text-slate-500 print:hidden">Calendar half-year means January–June or July–December; use a custom range for your institution's academic term.</p>

            <form method="GET" class="mb-6 rounded-2xl bg-slate-50 p-4 print:hidden">
                <input type="hidden" name="preset" value="custom">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                    <div>
                        <label for="report-date-from" class="mb-1 block text-sm font-medium text-slate-700">From date</label>
                        <input id="report-date-from" type="date" name="date_from" value="{{ $dateFrom }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" required>
                    </div>
                    <div>
                        <label for="report-date-to" class="mb-1 block text-sm font-medium text-slate-700">To date</label>
                        <input id="report-date-to" type="date" name="date_to" value="{{ $dateTo }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" required>
                    </div>
                    <button type="submit" class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Apply date range</button>
                </div>
            </form>

            @if($approvedRequestCount === 0)
                <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">
                    <p class="font-semibold">No approved reservations in this period.</p>
                    <p class="mt-1">Try a wider date range or choose one of the quick filters above. No report data has been removed.</p>
                </div>
            @endif

            @if($breakdown && $breakdownValue !== '')
                <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-blue-200 bg-blue-50 p-4">
                    <p class="text-sm text-blue-950">Showing reservations matching <strong>{{ ucfirst($breakdown) }}: {{ $breakdownValue }}</strong>.</p>
                    <a href="{{ route('supply-office.usage-reports', $periodParams) }}" class="text-sm font-semibold text-blue-800 underline">Clear detail filter</a>
                </div>
            @endif

            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Report period</p>
                    <p class="mt-2 text-sm font-semibold text-slate-800">{{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Approved reservations</p>
                    <p class="mt-2 text-2xl font-bold text-blue-700">{{ $approvedRequestCount }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Previous period</p>
                    <p class="mt-2 text-sm font-semibold text-slate-800">{{ $previousPeriodCount }} reservations</p>
                    <p class="mt-1 text-xs text-slate-500">
                        @if($changePercent === null)
                            No previous-period baseline
                        @elseif($changePercent > 0)
                            Up {{ $changePercent }}%
                        @elseif($changePercent < 0)
                            Down {{ abs($changePercent) }}%
                        @else
                            No change
                        @endif
                        ({{ \Carbon\Carbon::parse($previousFrom)->format('M d') }} – {{ \Carbon\Carbon::parse($previousTo)->format('M d, Y') }})
                    </p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Expected participants</p>
                    <p class="mt-2 text-2xl font-bold text-purple-700">{{ number_format($totalParticipants) }}</p>
                </div>
            </div>

            <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <section class="usage-report-panel rounded-2xl border border-slate-200 p-5">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900">Equipment usage</h2>
                    <div class="space-y-2">
                        @forelse($equipmentUsage as $usage)
                            <a href="{{ $breakdownUrl('equipment', $usage['equipment']) }}" class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-3 hover:bg-blue-50">
                                <span class="text-sm font-medium text-slate-700">{{ $usage['equipment'] }}</span>
                                <span class="shrink-0 text-sm font-semibold text-blue-700">{{ $usage['total_used'] }} units</span>
                            </a>
                        @empty
                            <p class="text-sm text-slate-500">No approved equipment usage was found.</p>
                        @endforelse
                    </div>
                </section>

                <section class="usage-report-panel rounded-2xl border border-slate-200 p-5">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900">Venue usage</h2>
                    <div class="space-y-2">
                        @forelse($venueUsage as $usage)
                            <a href="{{ $breakdownUrl('venue', $usage['venue']) }}" class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-3 hover:bg-blue-50">
                                <span class="text-sm font-medium text-slate-700">{{ $usage['venue'] }}</span>
                                <span class="shrink-0 text-sm font-semibold text-emerald-700">{{ $usage['total_bookings'] }} bookings</span>
                            </a>
                        @empty
                            <p class="text-sm text-slate-500">No approved venue bookings were found.</p>
                        @endforelse
                    </div>
                </section>

                <section class="usage-report-panel rounded-2xl border border-slate-200 p-5">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900">Department usage</h2>
                    <div class="space-y-2">
                        @forelse($departmentUsage as $dept)
                            <a href="{{ $breakdownUrl('department', $dept->department) }}" class="block rounded-xl bg-slate-50 p-3 hover:bg-blue-50">
                                <span class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-medium text-slate-700">{{ $dept->department }}</span>
                                    <span class="text-sm font-semibold text-purple-700">{{ $dept->total_requests }} requests</span>
                                </span>
                                <span class="mt-1 block text-xs text-slate-500">{{ number_format($dept->total_participants) }} expected participants</span>
                            </a>
                        @empty
                            <p class="text-sm text-slate-500">No department usage data for this period.</p>
                        @endforelse
                    </div>
                </section>

                <section class="usage-report-panel rounded-2xl border border-slate-200 p-5">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900">Priority distribution</h2>
                    <div class="space-y-2">
                        @forelse($priorityStats as $priority)
                            <a href="{{ $breakdownUrl('priority', $priority->priority) }}" class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-3 hover:bg-blue-50">
                                <span class="text-sm font-medium capitalize text-slate-700">{{ str_replace('_', ' ', $priority->priority) }}</span>
                                <span class="text-sm font-semibold text-orange-700">{{ $priority->count }} requests</span>
                            </a>
                        @empty
                            <p class="text-sm text-slate-500">No priority data for this period.</p>
                        @endforelse
                    </div>
                </section>
            </div>

            <section class="usage-report-panel mb-6 rounded-2xl border border-slate-200 p-5">
                <h2 class="mb-1 text-lg font-semibold text-slate-900">Monthly reservation trend</h2>
                <p class="mb-4 text-sm text-slate-500">Approved reservation counts by scheduled start month.</p>
                @if(count($monthlyUsage))
                    <div class="space-y-3">
                        @foreach($monthlyUsage as $month => $count)
                            <div class="grid grid-cols-[5rem_1fr_2rem] items-center gap-3">
                                <span class="text-xs font-medium text-slate-600">{{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('M Y') }}</span>
                                <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-blue-600" style="width: {{ max(4, ($count / $maxMonthlyCount) * 100) }}%"></div>
                                </div>
                                <span class="text-right text-sm font-semibold text-slate-800">{{ $count }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section id="request-details" class="rounded-2xl border border-slate-200 p-5">
                <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Reservation details</h2>
                        <p class="text-sm text-slate-500">Approved requests included in this report. Select a row to inspect the request.</p>
                    </div>
                    <span class="text-sm font-medium text-slate-600">{{ $detailRows->count() }} matching {{ \Illuminate\Support\Str::plural('reservation', $detailRows->count()) }}</span>
                </div>
                @if($detailRows->isEmpty())
                    <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">No reservations match this detail filter.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="px-3 py-3">Activity / Control #</th>
                                    <th class="px-3 py-3">Requestor / Department</th>
                                    <th class="px-3 py-3">Schedule</th>
                                    <th class="px-3 py-3">Venue</th>
                                    <th class="px-3 py-3">Equipment</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($detailRows as $row)
                                    <tr class="usage-report-table-row align-top hover:bg-slate-50">
                                        <td class="px-3 py-3">
                                            <a href="{{ route('request.show', $row['request']) }}" class="font-semibold text-blue-700 hover:underline">{{ $row['request']->name_of_activity }}</a>
                                            <span class="mt-1 block text-xs text-slate-500">{{ $row['request']->control_number }}</span>
                                        </td>
                                        <td class="px-3 py-3 text-slate-700">
                                            {{ $row['request']->requester?->name ?? $row['request']->user?->name ?? 'Unknown' }}
                                            <span class="mt-1 block text-xs text-slate-500">{{ $row['department'] }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-slate-700">
                                            {{ $row['request']->reservationSchedule->start_datetime->format('M d, Y g:i A') }}
                                            <span class="mt-1 block text-xs text-slate-500">to {{ $row['request']->reservationSchedule->end_datetime->format('M d, Y g:i A') }}</span>
                                        </td>
                                        <td class="px-3 py-3 text-slate-700">{{ $row['venues'] ? implode(', ', $row['venues']) : '—' }}</td>
                                        <td class="px-3 py-3 text-slate-700">
                                            @forelse($row['equipment'] as $name => $quantity)
                                                <span class="block">{{ $name }} ({{ $quantity }})</span>
                                            @empty
                                                —
                                            @endforelse
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
<style>
@media screen {
    .usage-report-print-meta { display: none; }
}
@media print {
    @page { size: A4 landscape; margin: 12mm; }
    html, body, body > div { width: 100% !important; min-height: 0 !important; margin: 0 !important; padding: 0 !important; background: #fff !important; }
    #dashboard-sidebar, #sidebar-backdrop, #sidebar-toggle, footer, #faq-modal,
    .usage-report-screen-only, .print\:hidden { display: none !important; }
    main { width: 100% !important; min-height: 0 !important; margin: 0 !important; padding: 0 !important; overflow: visible !important; }
    main > div, .usage-report-page, .usage-report-page > div { width: 100% !important; max-width: none !important; margin: 0 !important; padding: 0 !important; }
    .usage-report-card { padding: 0 !important; border: 0 !important; border-radius: 0 !important; box-shadow: none !important; }
    .usage-report-page * { color: #111827 !important; box-shadow: none !important; }
    .usage-report-page h1 { margin: 0 !important; font-size: 20pt !important; }
    .usage-report-page h2 { font-size: 12pt !important; }
    .usage-report-page p { line-height: 1.35 !important; }
    .usage-report-panel { margin-bottom: 4mm !important; padding: 3mm !important; border-color: #cbd5e1 !important; border-radius: 2mm !important; break-inside: auto !important; }
    .usage-report-page .mb-6 { margin-bottom: 4mm !important; }
    .usage-report-page .gap-6 { gap: 4mm !important; }
    .usage-report-page .p-5, .usage-report-page .sm\:p-6 { padding: 3mm !important; }
    .usage-report-page .rounded-xl, .usage-report-page .rounded-2xl { border-radius: 1mm !important; }
    .usage-report-page a { color: #111827 !important; text-decoration: none !important; }
    .usage-report-page table { width: 100% !important; border-collapse: collapse !important; font-size: 8pt !important; }
    .usage-report-page th, .usage-report-page td { padding: 2mm !important; border-bottom: 1px solid #cbd5e1 !important; }
    .usage-report-table-row { break-inside: avoid; }
    .usage-report-print-meta { display: block !important; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
</style>
@endsection
