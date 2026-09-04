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
            $isNewRequest = ($data['status'] ?? '') === 'new_request';
            $notificationMessage = $isNewRequest
                ? 'This request is waiting for your verification.'
                : ($data['message'] ?? ('Status changed to ' . ucfirst(str_replace('_', ' ', $data['status'] ?? ''))));
            $resource = $data['resource'] ?? null;
            if (!$resource && $isNewRequest && auth()->user()?->isCustodian()) {
                $resource = auth()->user()->assignedCustodianResourceLabel();
            }
        @endphp
        <a href="{{ !empty($data['request_id']) ? route('request.show', $data['request_id']) : route('notifications.index') }}" data-read-url="{{ route('notifications.read', $notification->id) }}" class="block p-4 flex items-start gap-4 {{ $notification->read_at ? 'bg-white' : 'bg-blue-50' }} hover:bg-gray-50 transition focus:outline-none focus:ring-2 focus:ring-inset focus:ring-emerald-500">
            <div class="shrink-0 mt-1">
                @if(str_contains($data['status'] ?? '', 'approved'))
                    <span class="text-2xl">✅</span>
                @elseif(str_contains($data['status'] ?? '', 'rejected'))
                    <span class="text-2xl">❌</span>
                @else
                    <span class="text-2xl">🔔</span>
                @endif
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-800">
                    {{ $data['activity'] ?? 'Request Update' }}
                </p>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ $notificationMessage }}
                </p>
                @if(!empty($resource))
                    <p class="text-xs text-gray-500 mt-1">Resource: <strong>{{ $resource }}</strong></p>
                @endif
                @if(!empty($data['notes']))
                    <p class="text-xs text-gray-400 mt-1 italic">Note: {{ $data['notes'] }}</p>
                @endif
                <p class="text-xs text-gray-300 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
            </div>
            @if(!$notification->read_at)
                <span class="shrink-0 w-2 h-2 bg-blue-500 rounded-full mt-2"></span>
            @endif
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
            }).finally(() => {
                window.location.href = destination;
            });
        });
    });
</script>
@endsection