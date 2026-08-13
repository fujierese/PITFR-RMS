@props(['user' => Auth::user()])

@php
    $currentRoute = request()->route()?->getName();
    $activeTab = request()->query('tab');
    $dashboardRoute = $user?->getDashboardRoute() ?? route('home');
    $calendarRoute = route('calendar.index');
    $calendarRouteName = 'calendar.index';
    $notificationCount = $user ? $user->unreadNotifications()->count() : 0;

    if ($user?->isAdmin()) {
        $calendarRoute = route('supply-office.calendar');
        $calendarRouteName = 'supply-office.calendar';
    }

    $navigation = [];
    $activeKey = null;

    if ($user) {
        if ($user->isAdmin()) {
            // Overview
            $navigation[] = [
                'section' => 'Overview',
                'type' => 'section-header',
            ];
            $navigation[] = [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'route' => $dashboardRoute,
                'route_name' => 'supply-office.index',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9 9 9v9a2 2 0 01-2 2H5a2 2 0 01-2-2v-9z"/></svg>',
            ];

            // Requests Workflow
            $navigation[] = [
                'section' => 'Requests',
                'type' => 'section-header',
            ];
            $navigation[] = [
                'key' => 'pending-requests',
                'label' => 'Pending / For Review',
                'route' => route('supply-office.requests.pending'),
                'route_name' => 'supply-office.requests.pending',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7 12a5 5 0 1110 0 5 5 0 01-10 0z"/></svg>',
            ];
            $navigation[] = [
                'key' => 'needs-revision',
                'label' => 'Needs Revision',
                'route' => route('supply-office.requests.needs-reschedule'),
                'route_name' => 'supply-office.requests.needs-reschedule',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            ];
            $navigation[] = [
                'key' => 'final-approval',
                'label' => 'Final Approval Queue',
                'route' => route('supply-office.requests.final-approval'),
                'route_name' => 'supply-office.requests.final-approval',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            ];
            $navigation[] = [
                'key' => 'rejected-requests',
                'label' => 'Rejected',
                'route' => route('supply-office.requests.rejected'),
                'route_name' => 'supply-office.requests.rejected',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>',
            ];

            // Scheduling & Activities
            $navigation[] = [
                'section' => 'Scheduling',
                'type' => 'section-header',
            ];
            $navigation[] = [
                'key' => 'calendar',
                'label' => 'Calendar',
                'route' => route('supply-office.calendar'),
                'route_name' => 'supply-office.calendar',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
            ];
            $navigation[] = [
                'key' => 'final-approved-activities',
                'label' => 'Final Approved Activities',
                'route' => route('supply-office.requests.approved'),
                'route_name' => 'supply-office.requests.approved',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m7 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            ];

            // Management
            $navigation[] = [
                'section' => 'Management',
                'type' => 'section-header',
            ];
            $navigation[] = [
                'key' => 'users',
                'label' => 'User Management',
                'route' => route('supply-office.users'),
                'route_name' => 'supply-office.users',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4a4 4 0 100 8 4 4 0 000-8zm-7 16a7 7 0 0114 0"/></svg>',
            ];

            // Monitoring
            $navigation[] = [
                'section' => 'Monitoring',
                'type' => 'section-header',
            ];
            $navigation[] = [
                'key' => 'notifications',
                'label' => 'Notifications',
                'route' => route('notifications.index'),
                'route_name' => 'notifications.index',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>',
            ];
            $navigation[] = [
                'key' => 'audit-logs',
                'label' => 'Audit Logs',
                'route' => route('supply-office.audit-logs'),
                'route_name' => 'supply-office.audit-logs',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M5 21h14a2 2 0 002-2V7l-5-5H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
            ];

            // Reports & Documentation
            $navigation[] = [
                'section' => 'Reports & Documentation',
                'type' => 'section-header',
            ];
            $navigation[] = [
                'key' => 'reports',
                'label' => 'Usage Reports',
                'route' => route('supply-office.usage-reports'),
                'route_name' => 'supply-office.usage-reports',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 19h16M7 16V8m5 8V5m5 11v-6"/></svg>',
            ];

            // System
            $navigation[] = [
                'section' => 'System',
                'type' => 'section-header',
            ];
            $navigation[] = [
                'key' => 'settings',
                'label' => 'Settings',
                'route' => route('supply-office.settings'),
                'route_name' => 'supply-office.settings',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
            ];
        }

        if ($user->isCustodian()) {
            $navigation[] = [
                'key' => 'overview',
                'label' => 'Overview',
                'route' => $dashboardRoute,
                'route_name' => 'custodian.index',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9 9 9v9a2 2 0 01-2 2H5a2 2 0 01-2-2v-9z"/></svg>',
            ];
            $navigation[] = [
                'key' => 'incoming-requests',
                'label' => 'Incoming Requests',
                'route' => route('custodian.index'),
                'route_name' => 'custodian.index',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7 7h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
            ];
            $navigation[] = [
                'key' => 'calendar',
                'label' => 'Calendar',
                'route' => $calendarRoute,
                'route_name' => $calendarRouteName,
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
            ];
            $navigation[] = [
                'key' => 'assigned-items',
                'label' => 'Assigned Venues / Equipment',
                'route' => route('custodian.index'),
                'route_name' => 'custodian.index',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 12h14M5 16h14"/></svg>',
            ];
            $navigation[] = [
                'key' => 'notifications',
                'label' => 'Notifications',
                'route' => route('notifications.index'),
                'route_name' => 'notifications.index',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>',
            ];
            $navigation[] = [
                'key' => 'settings',
                'label' => 'Account Settings',
                'route' => route('custodian.settings'),
                'route_name' => 'custodian.settings',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
            ];
        }

        if (! $user->isAdmin() && ! $user->isCustodian()) {
            $navigation[] = [
                'key' => 'overview',
                'label' => 'Dashboard',
                'route' => route('requestor.index', ['tab' => 'overview']),
                'route_name' => 'requestor.index',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9 9 9v9a2 2 0 01-2 2H5a2 2 0 01-2-2v-9z"/></svg>',
            ];
            $navigation[] = [
                'key' => 'create-request',
                'label' => 'Create Request',
                'route' => route('requestor.index', ['tab' => 'create']),
                'route_name' => 'requestor.index',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>',
            ];
            $navigation[] = [
                'key' => 'my-requests',
                'label' => 'My Requests',
                'route' => route('requestor.index', ['tab' => 'requests']),
                'route_name' => 'requestor.index',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
            ];
            $navigation[] = [
                'key' => 'notifications',
                'label' => 'Notifications',
                'route' => route('notifications.index'),
                'route_name' => 'notifications.index',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>',
            ];
            $navigation[] = [
                'key' => 'settings',
                'label' => 'Account Settings',
                'route' => route('requestor.settings'),
                'route_name' => 'requestor.settings',
                'icon' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
            ];
        }
    }

    if ($currentRoute === 'requestor.index' && $activeTab === 'create') {
        $activeKey = 'create-request';
    } elseif ($currentRoute === 'requestor.index' && $activeTab === 'requests') {
        $activeKey = 'my-requests';
    } elseif ($currentRoute === 'requestor.index') {
        $activeKey = 'overview';
    } elseif ($currentRoute === 'requestor.settings') {
        $activeKey = 'settings';
    } elseif ($currentRoute === 'notifications.index') {
        $activeKey = 'notifications';
    } elseif ($currentRoute === 'calendar.index' || $currentRoute === 'supply-office.calendar') {
        $activeKey = 'calendar';
    } elseif ($currentRoute === 'supply-office.index') {
        $activeKey = 'dashboard';
    } elseif ($currentRoute === 'supply-office.requests.pending') {
        $activeKey = 'pending-requests';
    } elseif ($currentRoute === 'supply-office.requests.needs-reschedule') {
        $activeKey = 'needs-revision';
    } elseif ($currentRoute === 'supply-office.requests.final-approval') {
        $activeKey = 'final-approval';
    } elseif ($currentRoute === 'supply-office.requests.rejected') {
        $activeKey = 'rejected-requests';
    } elseif ($currentRoute === 'supply-office.requests.approved') {
        $activeKey = 'final-approved-activities';
    } elseif ($currentRoute === 'supply-office.users') {
        $activeKey = 'users';
    } elseif ($currentRoute === 'supply-office.audit-logs') {
        $activeKey = 'audit-logs';
    } elseif ($currentRoute === 'supply-office.usage-reports') {
        $activeKey = 'reports';
    } elseif ($currentRoute === 'supply-office.settings') {
        $activeKey = 'settings';
    }

    $isActive = fn($key) => $activeKey === $key;
@endphp

<div class="flex h-screen flex-col bg-slate-950 text-slate-100 shadow-[0_20px_60px_rgba(2,6,23,0.35)]">
    <div class="border-b border-white/10 p-4">
        <div class="flex items-center justify-between gap-2">
            <a href="{{ $dashboardRoute }}" class="flex min-w-0 items-center gap-3">
                <img src="{{ asset('images/PIT-LOGO.jpg') }}" alt="PIT Logo" class="h-10 w-10 rounded-full border border-white/10 object-cover shadow-sm">
                <div class="min-w-0">
                    <p class="truncate text-xs font-semibold uppercase tracking-[0.18em] text-white">PIT</p>
                    <p class="truncate text-[11px] text-slate-400">Facility Request</p>
                </div>
            </a>
            <button id="sidebar-close" type="button" aria-label="Close navigation menu" title="Close navigation menu" class="rounded-lg border border-white/10 bg-white/5 p-2 text-slate-200 transition hover:bg-emerald-500/20 hover:text-white lg:hidden">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="mt-3 rounded-xl border border-emerald-400/30 bg-gradient-to-br from-emerald-500/20 via-emerald-500/10 to-slate-800/80 p-2.5">
            <p class="text-[9px] font-semibold uppercase tracking-[0.28em] text-slate-400">Signed in as</p>
            <p class="mt-1 text-sm font-semibold text-white">{{ $user?->name ?? 'Guest' }}</p>
            <p class="text-xs text-slate-300">{{ $user?->role_label ?? 'Requestor' }}</p>
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-2.5 py-3">
        <div class="space-y-1">
            @foreach($navigation as $item)
                @if(($item['type'] ?? null) === 'section-header')
                    <div class="mt-4 first:mt-0 px-3 py-2">
                        <p class="text-[10px] font-bold uppercase tracking-[0.32em] text-slate-500">{{ $item['section'] }}</p>
                    </div>
                @elseif($item['label'] === 'Settings' || $item['label'] === 'Account Settings')
                    <div class="space-y-1">
                        <a href="{{ $item['route'] }}" data-sidebar-close="true"
                           class="group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition {{ $isActive($item['key']) ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg {{ $isActive($item['key']) ? 'bg-white/15 text-white' : 'bg-slate-800 text-slate-400 group-hover:text-white' }}">
                                {!! $item['icon'] !!}
                            </span>
                            <span>{{ $item['label'] }}</span>
                        </a>

                        <form method="POST" action="{{ route('logout') }}" class="ml-8">
                            @csrf
                            <button type="submit" data-sidebar-close="true" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-400 transition hover:bg-white/10 hover:text-white">
                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-slate-800 text-slate-400">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1"/></svg>
                                </span>
                                <span>Logout</span>
                            </button>
                        </form>
                    </div>
                @else
                    <a href="{{ $item['route'] }}" data-sidebar-close="true"
                       class="group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition {{ $isActive($item['key']) ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg {{ $isActive($item['key']) ? 'bg-white/15 text-white' : 'bg-slate-800 text-slate-400 group-hover:text-white' }}">
                            {!! $item['icon'] !!}
                        </span>
                        <span>{{ $item['label'] }}</span>
                        @if($item['key'] === 'notifications' && $notificationCount > 0)
                            <span class="ml-auto rounded-full bg-rose-500 px-2 py-0.5 text-[10px] font-semibold text-white">{{ $notificationCount }}</span>
                        @endif
                    </a>
                @endif
            @endforeach
        </div>
    </nav>
</div>
