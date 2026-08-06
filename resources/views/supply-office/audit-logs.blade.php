@extends('layouts.app')

@section('title', 'Audit Logs - Supply Office')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-7xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">System Audit Logs</h1>
                    <p class="text-sm text-gray-600 mt-1">Complete history of all facility request actions and changes.</p>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" class="mb-6 bg-gray-50 p-4 rounded-lg">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Action, detail, or user..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
                        <select name="action" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Actions</option>
                            <option value="submitted" {{ ($filters['action'] ?? '') === 'submitted' ? 'selected' : '' }}>Submitted</option>
                            <option value="venue_approved" {{ ($filters['action'] ?? '') === 'venue_approved' ? 'selected' : '' }}>Venue Approved</option>
                            <option value="venue_rejected" {{ ($filters['action'] ?? '') === 'venue_rejected' ? 'selected' : '' }}>Venue Rejected</option>
                            <option value="equipment_approved" {{ ($filters['action'] ?? '') === 'equipment_approved' ? 'selected' : '' }}>Equipment Approved</option>
                            <option value="equipment_rejected" {{ ($filters['action'] ?? '') === 'equipment_rejected' ? 'selected' : '' }}>Equipment Rejected</option>
                            <option value="approved" {{ ($filters['action'] ?? '') === 'approved' ? 'selected' : '' }}>Final Approved</option>
                            <option value="rejected" {{ ($filters['action'] ?? '') === 'rejected' ? 'selected' : '' }}>Final Rejected</option>
                            <option value="equipment_returned" {{ ($filters['action'] ?? '') === 'equipment_returned' ? 'selected' : '' }}>Equipment Returned</option>
                            <option value="cancelled" {{ ($filters['action'] ?? '') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                    <button type="submit" class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 sm:w-auto">
                        Apply Filters
                    </button>
                    <a href="{{ route('supply-office.audit-logs') }}" class="flex w-full items-center justify-center rounded-md bg-gray-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-600 sm:w-auto">
                        Clear Filters
                    </a>
                </div>
            </form>

            <!-- Audit Logs Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($auditLogs as $log)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $log->occurred_at->format('M d, Y H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $log->user ? $log->user->name : 'System' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                        @if(str_contains($log->action, 'approved')) bg-green-100 text-green-800
                                        @elseif(str_contains($log->action, 'rejected')) bg-red-100 text-red-800
                                        @elseif(str_contains($log->action, 'submitted')) bg-blue-100 text-blue-800
                                        @elseif(str_contains($log->action, 'returned')) bg-purple-100 text-purple-800
                                        @elseif(str_contains($log->action, 'cancelled')) bg-gray-100 text-gray-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if($log->facilityRequest)
                                        <a href="{{ route('request.show', $log->facilityRequest->id) }}" class="text-blue-600 hover:text-blue-800">
                                            {{ $log->facilityRequest->control_number }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 max-w-xs truncate">
                                    {{ $log->detail ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                    No audit logs found matching the current filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($auditLogs->hasPages())
                <div class="mt-6">
                    {{ $auditLogs->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection