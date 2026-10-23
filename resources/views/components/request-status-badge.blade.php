@props([
    'request',
    'showWorkflowStage' => false,
])

@php
    $overallStatus = strtolower((string) ($request->status ?? 'pending'));
    $venueStatus = strtolower((string) ($request->venue_status ?? 'pending'));
    $equipmentStatus = strtolower((string) ($request->equipment_status ?? 'pending'));
    $venueCustodians = $request->requestVenues()->with('venue.custodian')->get()
        ->map(fn ($requestVenue) => $requestVenue->venue?->custodian?->name)
        ->filter()
        ->unique()
        ->values();
    $equipmentCustodians = $request->requestEquipment()->with('equipment.custodian')->get()
        ->map(fn ($requestEquipment) => $requestEquipment->equipment?->custodian?->name)
        ->filter()
        ->unique()
        ->values();
    $formatCustodians = fn ($names) => $names->isNotEmpty() ? $names->join(', ') : 'assigned custodian';

    $statusLabel = match ($overallStatus) {
        'cancelled' => 'Cancelled',
        'approved' => 'Approved',
        'completed' => 'Completed',
        'rejected' => 'Rejected',
        'needs_reschedule' => 'Needs Revision',
        default => match (true) {
            $venueStatus === 'rejected' => 'Rejected: Venue Custodian',
            $equipmentStatus === 'rejected' => 'Rejected: Equipment Custodian',
            $venueStatus === 'pending' && $equipmentStatus === 'pending' => 'Pending',
            $venueStatus === 'pending' => 'Pending',
            $equipmentStatus === 'pending' => 'Pending',
            $venueStatus === 'approved' && $equipmentStatus === 'approved' => 'Pending: Supply Office',
            default => 'Pending',
        },
    };
    $workflowStageLabel = match (true) {
        $venueStatus === 'approved' && $equipmentStatus === 'approved' => 'Forwarded to Supply Office',
        $venueStatus === 'pending' && $equipmentStatus === 'pending' => 'Awaiting venue & equipment review',
        $venueStatus === 'pending' => 'Awaiting venue review',
        $equipmentStatus === 'pending' => 'Awaiting equipment review',
        default => 'Pending review',
    };

    $badgeStatus = match ($overallStatus) {
        'approved', 'completed' => 'approved',
        'rejected' => 'rejected',
        'cancelled' => 'cancelled',
        default => 'pending',
    };
@endphp

@if ($showWorkflowStage && $overallStatus === 'pending')
    <div class="w-full rounded-2xl border border-slate-200 bg-slate-50 p-3 text-left shadow-sm" aria-label="Request review progress">
        <div class="flex items-start gap-2.5">
            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $venueStatus === 'approved' && $equipmentStatus === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                @if ($venueStatus === 'approved' && $equipmentStatus === 'approved')
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 4 4L19 6"/>
                    </svg>
                @else
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                @endif
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Request progress</p>
                <p class="mt-0.5 text-sm font-semibold leading-5 {{ $venueStatus === 'approved' && $equipmentStatus === 'approved' ? 'text-emerald-800' : 'text-amber-900' }}">{{ $workflowStageLabel }}</p>
            </div>
        </div>

        <div class="mt-3 space-y-2 border-t border-slate-200 pt-3">
            @foreach([
                ['label' => 'Venue review', 'status' => $venueStatus, 'custodians' => $venueCustodians],
                ['label' => 'Equipment review', 'status' => $equipmentStatus, 'custodians' => $equipmentCustodians],
            ] as $review)
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-slate-700">{{ $review['label'] }}</p>
                        <p class="mt-0.5 break-words text-[11px] leading-4 text-slate-500">{{ $formatCustodians($review['custodians']) }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $review['status'] === 'approved' ? 'bg-emerald-100 text-emerald-800' : ($review['status'] === 'rejected' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">{{ ucfirst($review['status']) }}</span>
                </div>
            @endforeach
            @if ($venueStatus === 'approved' && $equipmentStatus === 'approved')
                <div class="flex items-center justify-between gap-2 border-t border-slate-200 pt-2">
                    <p class="text-xs font-semibold text-slate-700">Next step</p>
                    <span class="text-xs font-semibold text-sky-800">Supply Office review</span>
                </div>
            @endif
        </div>
    </div>
@else
    <x-status-badge :status="$badgeStatus" :label="$statusLabel" />
@endif
@if (!$showWorkflowStage && $badgeStatus === 'pending' && $overallStatus === 'pending')
    <div class="mt-1 space-y-0.5 text-[11px] leading-4 text-slate-500" title="Current request handlers">
        @if ($venueStatus === 'pending')
            <p><span class="font-semibold text-slate-700">{{ $showWorkflowStage ? 'Venue review by:' : 'Venue:' }}</span> {{ $formatCustodians($venueCustodians) }}</p>
        @endif
        @if ($equipmentStatus === 'pending')
            <p><span class="font-semibold text-slate-700">{{ $showWorkflowStage ? 'Equipment review by:' : 'Equipment:' }}</span> {{ $formatCustodians($equipmentCustodians) }}</p>
        @endif
        @if (!$showWorkflowStage && $venueStatus === 'approved' && $equipmentStatus === 'approved')
            <p><span class="font-semibold text-slate-700">Next:</span> Supply Office</p>
        @endif
    </div>
@endif
