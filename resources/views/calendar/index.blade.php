@extends('layouts.app')

@section('title', 'Calendar')

@section('content')
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
@endsection