@extends('layouts.app')

@section('title', 'Audit Logs - Supply Office')

@php
    $formatAuditValue = function ($value): string {
        if ($value === null || $value === '') {
            return '—';
        }

        return is_scalar($value)
            ? (string) $value
            : (json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '—');
    };
@endphp

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-7xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">System Audit Logs</h1>
                    <p class="text-sm text-gray-600 mt-1">Complete history of all facility request actions and changes.</p>
                </div>
                <a href="{{ route('supply-office.audit-logs.export', request()->query()) }}" style="display: inline-flex; align-items: center; justify-content: center; border-radius: 0.75rem; background: #047857; padding: 0.625rem 1rem; color: #fff; font-size: 0.875rem; font-weight: 700; line-height: 1.25rem; text-decoration: none; white-space: nowrap;">
                    Download CSV
                </a>
            </div>

            <!-- Filters -->
            <x-search-filter-bar
                class="mb-6"
                :action="url()->current()"
                :search="$filters['search'] ?? ''"
                placeholder="Action, detail, or user..."
                :filters="[
                    ['type' => 'select', 'name' => 'user_id', 'label' => 'User', 'value' => (string) ($filters['user_id'] ?? ''), 'options' => ['' => 'All Users'] + collect($users)->mapWithKeys(fn ($u) => [(string) $u->id => $u->name])->all()],
                    ['type' => 'select', 'name' => 'action', 'label' => 'Action', 'value' => $filters['action'] ?? '', 'options' => ['' => 'All Actions'] + collect($actions)->mapWithKeys(fn ($a) => [$a => ucfirst(str_replace('_', ' ', $a))])->all()],
                    ['type' => 'date', 'name' => 'date_from', 'label' => 'Date from', 'value' => $filters['date_from'] ?? ''],
                    ['type' => 'date', 'name' => 'date_to', 'label' => 'Date to', 'value' => $filters['date_to'] ?? ''],
                ]"
            />

            <!-- Audit Logs Table -->
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm text-gray-600">Showing <span class="font-semibold text-gray-900">{{ $auditLogs->count() }}</span> of <span class="font-semibold text-gray-900">{{ $totalMatchingLogs }}</span> matching audit entries</p>
                <p class="text-xs text-gray-500">Page {{ $auditLogs->currentPage() }} of {{ max(1, $auditLogs->lastPage()) }}</p>
            </div>
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-[900px] w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Changes</th>
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
                                    @if($log instanceof \App\Models\RequestHistory && $log->actorRoleLabel())
                                        <span class="block text-xs text-gray-500">as {{ $log->actorRoleLabel() }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                        @if(str_contains($log->action, 'approved')) bg-green-100 text-green-800
                                        @elseif(str_contains($log->action, 'rejected')) bg-red-100 text-red-800
                                        @elseif(str_contains($log->action, 'submitted')) bg-blue-100 text-blue-800
                                        @elseif(str_contains($log->action, 'returned')) bg-purple-100 text-purple-800
                                        @elseif(str_contains($log->action, 'cancelled')) bg-gray-100 text-gray-800
                                        @elseif(str_contains($log->action, 'user_')) bg-orange-100 text-orange-800
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
                                        @if($log->request_version)
                                            <span class="block text-xs text-gray-500">Version {{ $log->request_version }}</span>
                                        @endif
                                    @elseif($log->targetUser)
                                        <span class="text-gray-700">{{ $log->targetUser->name }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <details class="max-w-xs">
                                        <summary class="cursor-pointer font-medium text-blue-700 hover:text-blue-900">
                                            View details
                                        </summary>
                                        <p class="mt-2 whitespace-pre-wrap break-words text-xs leading-5 text-gray-700">{{ $log->detail ?: 'No additional details.' }}</p>
                                    </details>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @php
                                        $oldValues = is_array($log->old_values ?? null) ? $log->old_values : [];
                                        $newValues = is_array($log->new_values ?? null) ? $log->new_values : [];
                                        $changedFields = collect(array_unique(array_merge(array_keys($oldValues), array_keys($newValues))))
                                            ->filter(fn ($field) => ($oldValues[$field] ?? null) !== ($newValues[$field] ?? null));
                                    @endphp
                                    @if($changedFields->isNotEmpty())
                                        <details class="min-w-40">
                                            <summary class="cursor-pointer rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-center text-xs font-semibold text-blue-800 hover:bg-blue-100">
                                                View {{ $changedFields->count() }} {{ \Illuminate\Support\Str::plural('change', $changedFields->count()) }}
                                            </summary>
                                            <div class="mt-2 space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                            @foreach($changedFields as $field)
                                                @php
                                                    $oldValue = $oldValues[$field] ?? null;
                                                    $newValue = $newValues[$field] ?? null;
                                                @endphp
                                                <div class="text-xs">
                                                    <p class="font-semibold text-gray-700">{{ ucfirst(str_replace('_', ' ', $field)) }}</p>
                                                    <p class="mt-1 whitespace-pre-wrap break-words text-red-700">Before: {{ $formatAuditValue($oldValue) }}</p>
                                                    <p class="mt-1 whitespace-pre-wrap break-words text-emerald-700">After: {{ $formatAuditValue($newValue) }}</p>
                                                </div>
                                            @endforeach
                                            </div>
                                        </details>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
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