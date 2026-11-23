@extends('layouts.app')
@section('title', 'Notifications')

@section('content')
<div class="mx-auto max-w-4xl bg-white rounded-2xl shadow-sm ring-1 ring-gray-100 overflow-hidden">
    <div class="px-5 pt-5 pb-0 border-b border-gray-100">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600" aria-hidden="true">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.857 17H9.143m10.286 0H4.571c1.14-1.18 1.715-2.65 1.715-4.414V10a5.714 5.714 0 0111.428 0v2.586c0 1.764.572 3.234 1.715 4.414ZM9 20h6"/>
                </svg>
            </span>
            <div>
                <h2 class="text-lg font-bold text-gray-900">Notifications</h2>
                <p class="text-xs text-gray-500" data-unread-summary aria-live="polite">{{ $unreadCount > 0 ? $unreadCount . ' unread' : 'No unread notifications' }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('notifications.read-all') }}" data-mark-all-form>
            @csrf
            <button type="submit" data-mark-all-button @disabled($unreadCount === 0)
                class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-white px-3.5 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400 disabled:hover:bg-white">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4 12 4 4L18 6m-4 10 2 2 6-8"/></svg>
                Mark All as Read
            </button>
        </form>
    </div>
    <nav class="mt-4 flex gap-1" aria-label="Filter notifications">
        @foreach(['all' => ['All', $totalCount], 'unread' => ['Unread', $unreadCount]] as $key => [$label, $count])
            <a href="{{ route('notifications.index', $key === 'unread' ? ['filter' => 'unread'] : []) }}"
               @if($filter === $key) aria-current="page" @endif
               class="-mb-px inline-flex items-center gap-2 border-b-2 px-3 pb-3 text-sm font-semibold transition {{ $filter === $key ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-gray-500 hover:text-gray-800' }}">
                {{ $label }}
                <span class="rounded-full px-2 py-0.5 text-[11px] {{ $filter === $key ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}" @if($key === 'unread') data-unread-tab-count @endif>{{ $count }}</span>
            </a>
        @endforeach
    </nav>
    </div>

    <div class="divide-y divide-gray-100">
        @php $previousGroup = null; @endphp
        @forelse($notifications as $notification)
        @php
            $createdAt = $notification->created_at;
            $group = $createdAt->isToday() ? 'Today' : ($createdAt->isYesterday() ? 'Yesterday' : ($createdAt->isCurrentYear() ? $createdAt->format('l, F j') : $createdAt->format('F j, Y')));
        @endphp
        @if($group !== $previousGroup)
            <h3 class="bg-gray-50/80 px-5 py-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ $group }}</h3>
            @php $previousGroup = $group; @endphp
        @endif
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
            $linkedRequestId = $data['request_id'] ?? $data['facility_request_id'] ?? null;
            $linkedRequest = !empty($linkedRequestId) ? $requestDetails->get($linkedRequestId) : null;
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
        <div class="flex flex-col sm:flex-row sm:items-start {{ $notification->read_at ? 'bg-white' : 'bg-blue-50/70 border-l-4 border-blue-500' }}" data-notification="{{ $notification->id }}" data-state="{{ $notification->read_at ? 'read' : 'unread' }}">
        <a href="{{ route('notifications.open', $notification->id) }}" class="flex-1 min-w-0 p-4 flex items-start gap-4 hover:bg-gray-50/70 transition focus:outline-none focus:ring-2 focus:ring-inset focus:ring-emerald-500">
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
                <p class="mb-0.5 text-xs text-gray-500"><time datetime="{{ $notification->created_at->toIso8601String() }}" title="{{ $notification->created_at->format('M j, Y g:i A') }}">{{ $notification->created_at->format('g:i A') }} · {{ $notification->created_at->diffForHumans() }}</time></p>
                <p class="flex flex-wrap items-center gap-2 text-sm {{ $notification->read_at ? 'font-medium text-gray-700' : 'font-semibold text-gray-900' }}" data-notification-title>
                    <span>{{ $title }}</span>
                    @if($notification->read_at)
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-500" data-read-label>Read</span>
                    @else
                        <span class="rounded-full bg-blue-600 px-2 py-0.5 text-[11px] font-semibold text-white" data-read-label>Unread</span>
                    @endif
                </p>
                <p class="mt-1 text-sm text-gray-600">
                    {{ $notificationMessage }}
                </p>
                @if(!$isSecurityAlert && ($controlNumber || $activityName || $requestorName))
                <p class="mt-2 flex flex-wrap gap-1.5 text-[11px] text-gray-600">
                    @if($controlNumber)
                        <span class="rounded-md bg-gray-100 px-2 py-0.5">Control No: <strong>{{ $controlNumber }}</strong></span>
                    @endif
                    @if($activityName)
                        <span class="rounded-md bg-gray-100 px-2 py-0.5">Activity: <strong>{{ $activityName }}</strong></span>
                    @endif
                    @if($requestorName)
                        <span class="rounded-md bg-gray-100 px-2 py-0.5">Requestor: <strong>{{ $requestorName }}</strong></span>
                    @endif
                </p>
                @endif
                @if(!empty($data['notes']))
                    <p class="text-xs text-gray-400 mt-1 italic">Note: {{ $data['notes'] }}</p>
                @endif
            </div>
        </a>
        @unless($notification->read_at)
            <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="px-4 pb-4 sm:p-4 sm:pl-0 shrink-0" data-mark-read-form>
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="m5 12 5 5L20 7"/></svg>
                    Mark as Read
                </button>
            </form>
        @endunless
        </div>
        @empty
        <div class="py-16 text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-600">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.857 17H9.143m10.286 0H4.571c1.14-1.18 1.715-2.65 1.715-4.414V10a5.714 5.714 0 0111.428 0v2.586c0 1.764.572 3.234 1.715 4.414ZM9 20h6"/>
                </svg>
            </div>
            @if($filter === 'unread' && $totalCount > 0)
                <p class="text-gray-700 font-semibold">You're all caught up</p>
                <p class="text-gray-500 text-sm mt-1">No unread notifications. <a href="{{ route('notifications.index') }}" class="font-semibold text-emerald-700 hover:underline">View all</a></p>
            @else
                <p class="text-gray-700 font-semibold">No notifications yet</p>
                <p class="text-gray-500 text-sm mt-1">Updates about your requests will appear here.</p>
            @endif
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
(() => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const markAllButton = document.querySelector('[data-mark-all-button]');
    const summary = document.querySelector('[data-unread-summary]');

    const toast = (icon, text) => {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({ toast: true, position: 'top-end', icon, title: text, showConfirmButton: false, showCloseButton: true, timer: icon === 'success' ? 3000 : 6000, timerProgressBar: true, customClass: { popup: 'pitfr-toast' } });
        } else if (icon !== 'success') {
            alert(text);
        }
    };

    const showUnreadCount = (count) => {
        count = Math.max(0, parseInt(count, 10) || 0);
        if (summary) summary.textContent = count > 0 ? count + ' unread' : 'No unread notifications';
        if (markAllButton) markAllButton.disabled = count === 0;
        document.querySelectorAll('[data-unread-tab-count]').forEach((el) => { el.textContent = count; });
        document.querySelectorAll('[data-unread-badge]').forEach((badge) => {
            badge.textContent = count;
            badge.hidden = count === 0;
        });
    };

    const showAsRead = (row) => {
        if (!row || row.dataset.state === 'read') return;
        row.dataset.state = 'read';
        row.classList.remove('bg-blue-50/70', 'border-l-4', 'border-blue-500');
        row.classList.add('bg-white');
        const title = row.querySelector('[data-notification-title]');
        if (title) {
            title.classList.remove('font-semibold', 'text-gray-900');
            title.classList.add('font-medium', 'text-gray-700');
        }
        const label = row.querySelector('[data-read-label]');
        if (label) {
            label.textContent = 'Read';
            label.className = 'rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-500';
        }
        row.querySelector('[data-mark-read-form]')?.remove();
    };

    const send = async (form) => {
        const button = form.querySelector('button[type="submit"]');
        if (button?.disabled) return null;
        if (button) button.disabled = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Could not update the notification. Please try again.');
            }
            return data;
        } catch (error) {
            if (button) button.disabled = false;
            toast('error', error.message || 'Could not update the notification. Please try again.');
            return null;
        }
    };

    document.querySelectorAll('[data-mark-read-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const row = form.closest('[data-notification]');
            const data = await send(form);
            if (!data) return;
            showAsRead(row);
            showUnreadCount(data.unread_count);
            toast('success', data.message);
        });
    });

    document.querySelector('[data-mark-all-form]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = await send(event.currentTarget);
        if (!data) return;
        document.querySelectorAll('[data-notification]').forEach(showAsRead);
        showUnreadCount(data.unread_count);
        toast('success', data.message);
    });
})();
</script>
@endsection