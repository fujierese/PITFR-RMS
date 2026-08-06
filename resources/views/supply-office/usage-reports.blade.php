@extends('layouts.app')

@section('title', 'Usage Reports - Administrator')

@php
    $readableList = function ($value) {
        if (is_array($value)) {
            return collect($value)
                ->filter(fn ($item) => $item !== null && $item !== '')
                ->map(fn ($item) => is_array($item) ? implode(', ', array_filter($item)) : (string) $item)
                ->implode(', ');
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return collect($decoded)
                    ->filter(fn ($item) => $item !== null && $item !== '')
                    ->map(fn ($item) => is_array($item) ? implode(', ', array_filter($item)) : (string) $item)
                    ->implode(', ');
            }

            return $value;
        }

        return $value ?? '—';
    };
@endphp

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-7xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Usage Reports</h1>
                    <p class="text-sm text-gray-600 mt-1">Facility and equipment usage statistics and analytics.</p>
                </div>
            </div>

            <!-- Date Range Filter -->
            <form method="GET" class="mb-6 bg-gray-50 p-4 rounded-lg">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                        <input type="date" name="date_from" value="{{ $dateFrom }}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                        <input type="date" name="date_to" value="{{ $dateTo }}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-semibold transition">
                        Update Report
                    </button>
                </div>
            </form>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Equipment Usage -->
                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Equipment Usage</h3>
                    <div class="space-y-3">
                        @forelse($equipmentUsage as $usage)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm font-medium text-gray-700">{{ $readableList($usage['equipment'] ?? null) }}</span>
                                <span class="text-sm font-semibold text-blue-600">{{ $usage['total_used'] }} units</span>
                            </div>
                        @empty
                            <p class="text-gray-500 text-sm">No equipment usage data for the selected period.</p>
                        @endforelse
                    </div>
                </div>

                <!-- Venue Usage -->
                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Venue Usage</h3>
                    <div class="space-y-3">
                        @forelse($venueUsage as $usage)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm font-medium text-gray-700">{{ $readableList($usage['venue'] ?? null) }}</span>
                                <span class="text-sm font-semibold text-green-600">{{ $usage['total_bookings'] }} bookings</span>
                            </div>
                        @empty
                            <p class="text-gray-500 text-sm">No venue usage data for the selected period.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Department Usage -->
                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Department Usage</h3>
                    <div class="space-y-3">
                        @forelse($departmentUsage as $dept)
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm font-medium text-gray-700">{{ $dept->department }}</span>
                                    <span class="text-sm font-semibold text-purple-600">{{ $dept->total_requests }} requests</span>
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ $dept->total_participants }} total participants
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500 text-sm">No department usage data for the selected period.</p>
                        @endforelse
                    </div>
                </div>

                <!-- Priority Distribution -->
                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Priority Distribution</h3>
                    <div class="space-y-3">
                        @forelse($priorityStats as $priority)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm font-medium text-gray-700 capitalize">{{ $priority->priority }}</span>
                                <span class="text-sm font-semibold text-orange-600">{{ $priority->count }} requests</span>
                            </div>
                        @empty
                            <p class="text-gray-500 text-sm">No priority data for the selected period.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Summary ({{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }})</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600">{{ $equipmentUsage->sum('total_used') }}</div>
                        <div class="text-sm text-gray-600">Total Equipment Units Used</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600">{{ $venueUsage->sum('total_bookings') }}</div>
                        <div class="text-sm text-gray-600">Total Venue Bookings</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-purple-600">{{ $departmentUsage->sum('total_requests') }}</div>
                        <div class="text-sm text-gray-600">Total Requests</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-orange-600">{{ $priorityStats->sum('count') }}</div>
                        <div class="text-sm text-gray-600">Approved Requests</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection