@props(['request'])

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

    $badgeStatus = match ($overallStatus) {
        'approved', 'completed' => 'approved',
        'rejected' => 'rejected',
        'cancelled' => 'cancelled',
        default => 'pending',
    };
@endphp

<x-status-badge :status="$badgeStatus" :label="$statusLabel" />
@if ($badgeStatus === 'pending' && $overallStatus === 'pending')
    <div class="mt-1 space-y-0.5 text-[11px] leading-4 text-slate-500" title="Current request handlers">
        @if ($venueStatus === 'pending')
            <p><span class="font-semibold text-slate-700">Venue:</span> {{ $formatCustodians($venueCustodians) }}</p>
        @endif
        @if ($equipmentStatus === 'pending')
            <p><span class="font-semibold text-slate-700">Equipment:</span> {{ $formatCustodians($equipmentCustodians) }}</p>
        @endif
        @if ($venueStatus === 'approved' && $equipmentStatus === 'approved')
            <p><span class="font-semibold text-slate-700">Next:</span> Supply Office</p>
        @endif
    </div>
@endif
