@extends('layouts.app')

@section('title', 'Calendar')

@section('content')
<div class="space-y-6">
    <x-page-header
        title="Calendar"
        description="View confirmed bookings, availability, and upcoming facility activity across the system."
        eyebrow="Schedule overview"
    />

    @include('calendar._calendar', [
        'dashboardData' => [
            'hideHeader' => true,
            'showStatsCards' => false,
            'showRequestList' => false,
            'showVerificationQueue' => false,
            'showExport' => false,
            'showUsageReports' => false,
            'showUserManagement' => false,
            'showAuditLogs' => false,
        ],
    ])
</div>
@endsection