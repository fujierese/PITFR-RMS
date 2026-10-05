@extends('layouts.app')
@section('title', 'Notifications')

@section('content')
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="p-5 border-b border-gray-100">
        <h2 class="text-lg font-bold text-gray-800">🔔 Notifications</h2>
        <p class="text-xs text-gray-400 mt-0.5">All your request status updates</p>
    </div>

    <div class="divide-y divide-gray-50">
        @forelse($notifications as $notification)
        @php
            $data = $notification->data;
            $status = strtolower((string) ($data['status'] ?? ''));
            $isNewRequest = $status === 'new_request';
            $isSecurityAlert = ($data['category'] ?? '') === 'security_alert';
            $statusLabels = [
                'approved' => 'Approved',
                'rejected' => 'Rejected',
                'needs_reschedule' => 'Needs Reschedule',
                'needs_revision' => 'Needs Revision',
                'request_cancelled' => 'Request Cancelled',
                'venue_approved' => 'Venue Approved',
                'equipment_approved' => 'Equipment Approved',
                'equipment_returned' => 'Equipment Returned',
                'change_requested' => 'Request Updated',
                'change_request_rejected' => 'Change Request Rejected',
            ];
            $statusTitle = $statusLabels[$status] ?? ($status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Request Update');
            $title = $isSecurityAlert
                ? ($data['title'] ?? 'Security Alert')
                : ($isNewRequest
                ? ($data['title'] ?? 'New Request Submitted')
                : ($status !== '' ? 'Request ' . $statusTitle : ($data['title'] ?? 'Request Update')));
            $linkedRequest = !empty($data['request_id']) ? $requestDetails->get($data['request_id']) : null;
            $requestorName = $data['requestor_name'] ?? $linkedRequest?->requester?->name ?? $linkedRequest?->requested_by;
            $activityName = $data['activity'] ?? $linkedRequest?->name_of_activity;
            $controlNumber = $data['control_number'] ?? $linkedRequest?->control_number;
            $notificationMessage = $data['body'] ?? ($isNewRequest
                ? 'This request is waiting for your verification.'
                : ($data['message'] ?? ('Status changed to ' . $statusTitle)));
            if ($isNewRequest && ($requestorName || $activityName)) {
                $notificationMessage = 'A new request'
                    . ($requestorName ? ' from ' . $requestorName : '')
                    . ($activityName ? ' for "' . $activityName . '"' : '')
                    . ' is waiting for your verification.';
            }
            $notificationMessage = preg_replace_callback(
                '/\b(?:needs_revision|needs_reschedule|venue_approved|equipment_approved|final_approved|request_cancelled|equipment_returned|change_requested|change_request_rejected)\b/i',
                fn ($match) => $statusLabels[strtolower($match[0])] ?? ucfirst(str_replace('_', ' ', $match[0])),
                $notificationMessage
            );
        @endphp
        <a href="{{ !empty($data['request_id']) ? route('request.show', $data['request_id']) : route('notifications.index') }}" data-read-url="{{ route('notifications.read', $notification->id) }}" class="block p-4 flex items-start gap-4 {{ $notification->read_at ? 'bg-white' : 'bg-blue-50' }} hover:bg-gray-50 transition focus:outline-none focus:ring-2 focus:ring-inset focus:ring-emerald-500">
            <div class="shrink-0 mt-1">
                @if(str_contains($status, 'approved'))
                    <span class="text-2xl">✅</span>
                @elseif(str_contains($status, 'rejected'))
                    <span class="text-2xl">❌</span>
                @elseif(str_contains($status, 'resched') || str_contains($status, 'revision'))
                    <span class="text-2xl">🔄</span>
                @elseif($isSecurityAlert)
                    <span class="text-2xl">🛡️</span>
                @elseif($isNewRequest)
                    <span class="text-2xl">📨</span>
                @else
                    <span class="text-2xl">🔔</span>
                @endif
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-800">
                    {{ $title }}
                </p>
                <p class="mt-1 text-sm text-gray-600">
                    {{ $notificationMessage }}
                </p>
                @unless($isSecurityAlert)
                <p class="mt-1 text-xs text-gray-600">
                    Requestor: <strong>{{ $requestorName ?: 'Details unavailable' }}</strong>
                    @if($activityName)
                        <span class="mx-1" aria-hidden="true">·</span>
                        Activity: <strong>{{ $activityName }}</strong>
                    @endif
                    @if($controlNumber)
                        <span class="mx-1" aria-hidden="true">·</span>
                        Control No: <strong>{{ $controlNumber }}</strong>
                    @endif
                </p>
                @endunless
                @if(!empty($data['notes']))
                    <p class="text-xs text-gray-400 mt-1 italic">Note: {{ $data['notes'] }}</p>
                @endif
                <p class="text-xs text-gray-500 mt-2">{{ $notification->created_at->diffForHumans() }}</p>
            </div>
            @if(!$notification->read_at)
                <span class="shrink-0 w-2 h-2 bg-blue-500 rounded-full mt-2"></span>
            @endif
            <span class="hidden mt-2 text-sm text-red-700" data-read-error role="alert">Unable to mark this notification as read. Please try again.</span>
        </a>
        @empty
        <div class="py-16 text-center">
            <div class="text-5xl mb-4">🔔</div>
            <p class="text-gray-400 font-semibold">No notifications yet</p>
            <p class="text-gray-300 text-sm mt-1">You'll be notified when your request status changes</p>
        </div>
        @endforelse
    </div>

    @if($notifications->hasPages())
    <div class="p-4 border-t border-gray-100">
        {{ $notifications->links() }}
    </div>
    @endif
</div>
<script>
    document.querySelectorAll('[data-read-url]').forEach((notificationLink) => {
        notificationLink.addEventListener('click', (event) => {
            event.preventDefault();
            const destination = notificationLink.href;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            fetch(notificationLink.dataset.readUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken || '',
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json'
                }
            }).then((response) => {
                if (!response.ok) {
                    throw new Error('Unable to mark notification as read.');
                }
                window.location.href = destination;
            }).catch(() => {
                const error = notificationLink.querySelector('[data-read-error]');
                if (error) {
                    error.hidden = false;
                }
            });
        });
    });
</script>
@endsection