<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select reservation date</title>
    @if (! app()->runningUnitTests())
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-100 p-2 text-slate-900 sm:p-4">
    @include('calendar._calendar', [
        'role' => $role,
        'dashboardData' => array_merge($dashboardData, [
            'stats' => ['upcoming' => 0, 'pending' => 0, 'approved' => 0],
            'personalRequests' => collect(),
            'verificationQueue' => collect(),
            'inventory' => collect(),
            'finalApprovalQueue' => collect(),
            'totalCount' => 0,
            'userCount' => 0,
            'totalRequests' => 0,
            'hideHeader' => true,
            'showStatsCards' => false,
            'showRequestList' => false,
            'showVerificationQueue' => false,
            'showExport' => false,
            'showUsageReports' => false,
            'showUserManagement' => false,
            'showAuditLogs' => false,
            'showAvailabilityPanel' => false,
            'showHowToRequest' => false,
            'showViewOnlyCalendar' => false,
        ]),
        'dateSelectionMode' => true,
    ])
</body>
</html>
