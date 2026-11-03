@extends('layouts.app')
@section('title', 'Notifications')

@section('content')
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="p-5 border-b border-gray-100">
        <h2 class="flex items-center gap-2 text-lg font-bold text-gray-800">
            <svg class="h-5 w-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.857 17H9.143m10.286 0H4.571c1.14-1.18 1.715-2.65 1.715-4.414V10a5.714 5.714 0 0111.428 0v2.586c0 1.764.572 3.234 1.715 4.414ZM9 20h6"/>
            </svg>
            Notifications
        </h2>
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
                'venue_availability_unavailable' => 'Venue Unavailable',
                'request_cancelled' => 'Request Cancelled',
                'venue_approved' => 'Venue Approved',
                'equipment_approved' => 'Equipment Approved',
                'final_approved' => 'Final Approved',
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
                '/\b(?:needs_revision|needs_reschedule|venue_availability_unavailable|venue_approved|equipment_approved|final_approved|request_cancelled|equipment_returned|change_requested|change_request_rejected)\b/i',
                fn ($match) => $statusLabels[strtolower($match[0])] ?? ucfirst(str_replace('_', ' ', $match[0])),
                $notificationMessage
            );
            $isCompletedReturn = in_array($data['return_status'] ?? null, ['returned', 'fulfilled'], true);
            $notificationIcon = match (true) {
                $isSecurityAlert => 'security',
                $isNewRequest => 'new-request',
                in_array($status, ['venue_approved'], true) => 'venue',
                in_array($status, ['equipment_approved', 'equipment_returned'], true) => $status === 'equipment_returned' ? 'equipment-return' : 'equipment',
                in_array($status, ['approved', 'final_approved'], true) => 'final-approval',
                str_contains($status, 'rejected') => 'rejected',
                str_contains($status, 'resched') || str_contains($status, 'revision') || str_contains($status, 'unavailable') || str_starts_with($status, 'change_') => 'reschedule',
                str_contains($status, 'cancel') => 'cancelled',
                str_contains($status, 'reminder') => 'reminder',
                default => 'update',
            };
            $notificationIconColor = match ($notificationIcon) {
                'venue', 'equipment', 'final-approval' => 'bg-emerald-100 text-emerald-700',
                'equipment-return' => $isCompletedReturn ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700',
                'rejected' => 'bg-rose-100 text-rose-700',
                'reschedule', 'cancelled' => 'bg-amber-100 text-amber-700',
                'security' => 'bg-sky-100 text-sky-700',
                'new-request', 'reminder' => 'bg-cyan-100 text-cyan-700',
                default => 'bg-slate-100 text-slate-600',
            };
        @endphp
        <a href="{{ !empty($data['request_id']) ? route('request.show', $data['request_id']) : route('notifications.index') }}" data-read-url="{{ route('notifications.read', $notification->id) }}" class="block p-4 flex items-start gap-4 {{ $notification->read_at ? 'bg-white' : 'bg-blue-50' }} hover:bg-gray-50 transition focus:outline-none focus:ring-2 focus:ring-inset focus:ring-emerald-500">
            <div class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $notificationIconColor }}" data-notification-icon="{{ $notificationIcon }}" aria-hidden="true">
                @if($notificationIcon === 'venue')
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9v.01M9 12v.01M9 15v.01M9 18v.01M16 13v.01M16 16v.01M16 19v.01"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m14 5 1.5 1.5L19 3"/>
                    </svg>
                @elseif($notificationIcon === 'equipment' || $notificationIcon === 'equipment-return')
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m4 7 8-4 8 4v10l-8 4-8-4V7Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m4 7 8 4 8-4M12 11v10m-3-12 8-4"/>
                        @if($notificationIcon === 'equipment-return')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14H5m0 0 2.5-2.5M5 14l2.5 2.5"/>
                        @endif
                    </svg>
                @elseif($notificationIcon === 'final-approval')
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9" stroke-width="1.8"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8 12 2.5 2.5L16 9"/>
                    </svg>
                @elseif($notificationIcon === 'rejected')
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9" stroke-width="1.8"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 9 6 6m0-6-6 6"/>
                    </svg>
                @elseif($notificationIcon === 'reschedule' || $notificationIcon === 'cancelled')
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <rect x="3" y="5" width="18" height="16" rx="2" stroke-width="1.8"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 3v4M8 3v4M3 10h18"/>
                        @if($notificationIcon === 'cancelled')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 14 6 4m0-4-6 4"/>
                        @else
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 14h7m0 0-2-2m2 2-2 2"/>
                        @endif
                    </svg>
                @elseif($notificationIcon === 'security')
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3 19 6v5c0 4.5-2.9 8-7 10-4.1-2-7-5.5-7-10V6l7-3Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 12 2 2 4-4"/>
                    </svg>
                @elseif($notificationIcon === 'new-request')
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5h16v14H4zM4 7l8 6 8-6"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m-2-2h4"/>
                    </svg>
                @elseif($notificationIcon === 'reminder')
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9" stroke-width="1.8"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 7v5l3 2"/>
                    </svg>
                @else
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14.857 17H9.143m10.286 0H4.571c1.14-1.18 1.715-2.65 1.715-4.414V10a5.714 5.714 0 0111.428 0v2.586c0 1.764.572 3.234 1.715 4.414ZM9 20h6"/>
                    </svg>
                @endif
            </div>
            <div class="flex-1">
                <p class="mb-1 text-xs text-gray-500">{{ $notification->created_at->format('M j, Y g:i A') }} <span aria-hidden="true">·</span> {{ $notification->created_at->diffForHumans() }}</p>
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
            </div>
            @if(!$notification->read_at)
                <span class="shrink-0 w-2 h-2 bg-blue-500 rounded-full mt-2"></span>
            @endif
            <span class="hidden mt-2 text-sm text-red-700" data-read-error role="alert">Unable to mark this notification as read. Please try again.</span>
        </a>
        @empty
        <div class="py-16 text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-600">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.857 17H9.143m10.286 0H4.571c1.14-1.18 1.715-2.65 1.715-4.414V10a5.714 5.714 0 0111.428 0v2.586c0 1.764.572 3.234 1.715 4.414ZM9 20h6"/>
                </svg>
            </div>
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