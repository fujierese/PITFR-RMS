@props(['request'])

@php
    $overallStatus = strtolower((string) ($request->status ?? 'pending'));
    $venueStatus = strtolower((string) ($request->venue_status ?? 'pending'));
    $equipmentStatus = strtolower((string) ($request->equipment_status ?? 'pending'));

    $statusLabel = match ($overallStatus) {
        'cancelled' => 'Cancelled',
        'approved' => 'Approved',
        'completed' => 'Completed',
        'rejected' => 'Rejected',
        'needs_reschedule' => 'Needs Revision',
        default => match (true) {
            $venueStatus === 'rejected' => 'Rejected: Venue Custodian',
            $equipmentStatus === 'rejected' => 'Rejected: Equipment Custodian',
            $venueStatus === 'pending' && $equipmentStatus === 'pending' => 'Pending: Venue & Equipment Custodians',
            $venueStatus === 'pending' => 'Pending: Venue Custodian',
            $equipmentStatus === 'pending' => 'Pending: Equipment Custodian',
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
