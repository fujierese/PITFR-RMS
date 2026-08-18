@php
    use Illuminate\Support\Str;

    $dashboardData = $dashboardData ?? [];
    $user = auth()->user();
    $role = $dashboardData['role'] ?? ($user ? ($user->isAdmin() ? 'admin' : ($user->isCustodian() ? 'custodian' : ($user->isRequestee() ? 'requestor' : 'guest'))) : 'guest');
    $roleLabel = $dashboardData['roleLabel'] ?? match($role) {
        'guest' => 'Guest',
        'requestor' => 'Requestor',
        'custodian' => 'Custodian',
        'admin' => 'Administrator',
        default => 'User',
    };
    $hideHeader = $dashboardData['hideHeader'] ?? false;
    $showCreateRequest = $dashboardData['showCreateRequest'] ?? ($user && $user->isRequestee());
    $showLoginCTA = $dashboardData['showLoginCTA'] ?? ($role === 'guest');
    $showUsageReports = $dashboardData['showUsageReports'] ?? ($role === 'admin');
    $showExport = $dashboardData['showExport'] ?? ($role === 'admin');
    $showStatsCards = $dashboardData['showStatsCards'] ?? ($user && $user->isRequestee());
    $showRequestList = $dashboardData['showRequestList'] ?? ($user && $user->isRequestee());
    $showVerificationQueue = $dashboardData['showVerificationQueue'] ?? ($user && $user->isCustodian());
    $showUserManagement = $dashboardData['showUserManagement'] ?? ($user && $user->isAdmin());
    $showAuditLogs = $dashboardData['showAuditLogs'] ?? ($user && $user->isAdmin());
    $showHowToRequest = $dashboardData['showHowToRequest'] ?? ($role === 'guest');
    $showViewOnlyCalendar = $dashboardData['showViewOnlyCalendar'] ?? ($role === 'guest');
    $showAvailabilityPanel = $dashboardData['showAvailabilityPanel'] ?? true;
    $stats = $dashboardData['stats'] ?? [
        'upcoming' => 0,
        'pending' => 0,
        'approved' => 0,
    ];
    $personalRequests = $dashboardData['personalRequests'] ?? ($user && $user->isRequestee() ? \App\Models\FacilityRequest::where('requested_by_id', $user->id)->orderByDesc('created_at')->get() : collect([]));
    $verificationQueue = $dashboardData['verificationQueue'] ?? ($user && $user->isCustodian() ? (function() use ($user) {
        if ($user->isCustodianVenue()) {
            $venueNames = $user->venues()->pluck('name');
            if ($venueNames->isEmpty()) return collect([]);
            return \App\Models\FacilityRequest::where(function ($query) use ($venueNames) {
                foreach ($venueNames as $name) {
                    $query->orWhereJsonContains('venue', $name);
                }
            })->orderByDesc('created_at')->get();
        }
        if ($user->isCustodianEquipment()) {
            $equipmentNames = \App\Models\Equipment::where('custodian_id', $user->id)->pluck('name');
            if ($equipmentNames->isEmpty()) return collect([]);
            return \App\Models\FacilityRequest::where(function ($query) use ($equipmentNames) {
                foreach ($equipmentNames as $name) {
                    $query->orWhereJsonContains('equipment', $name);
                }
            })->orderByDesc('created_at')->get();
        }
        return collect([]);
    })() : collect([]));
    $inventoryItems = $dashboardData['inventory'] ?? ($user && $user->isCustodian() ? \App\Models\Equipment::where('custodian_id', $user->id)->get() : collect([]));
    $finalApprovalQueue = $dashboardData['finalApprovalQueue'] ?? $finalApprovalQueue ?? ($role === 'admin' ? \App\Models\FacilityRequest::where('venue_status', 'approved')->where('equipment_status', 'approved')->orderByDesc('created_at')->get() : collect([]));
    $totalCount = $dashboardData['totalCount'] ?? $totalCount ?? ($role === 'admin' ? \App\Models\FacilityRequest::where('venue_status', 'approved')->where('equipment_status', 'approved')->count() : 0);
    $userCount = $dashboardData['userCount'] ?? ($user && $user->isAdmin() ? \App\Models\User::count() : 0);
    $totalRequests = $dashboardData['totalRequests'] ?? ($user && $user->isAdmin() ? \App\Models\FacilityRequest::count() : 0);
@endphp

<div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
    @unless($hideHeader)
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
            <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-800">
                        @if ($role === 'guest') 📅 Facility Calendar
                        @elseif ($role === 'requestor') 📅 Requestor Dashboard
                        @elseif ($role === 'custodian') 🛡️ Custodian Dashboard
                        @elseif ($role === 'admin') 👑 Administrator Dashboard
                        @else 📅 Facility Calendar
                        @endif
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">
                        @switch($role)
                            @case('guest')
                                Review venue and equipment availability before signing in to submit a request.
                                @break
                            @case('requestor')
                                Review venue and equipment availability, track your requests, and submit a new reservation request.
                                @break
                            @case('custodian')
                                Review verification tasks for your assigned venues or equipment and keep inventory status updated.
                                @break
                            @case('admin')
                                View governance summaries, operational oversight, and audit-ready system details for facility administration.
                                @break
                            @default
                                View the facility calendar and system summaries appropriate for your role.
                        @endswitch
                    </p>
            </div>

            <div class="flex flex-wrap gap-3 items-center justify-end">
                @if ($showLoginCTA)
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                        Login to Request
                    </a>
                @endif

                @if ($showCreateRequest)
                    <a href="{{ route('requestor.index', ['tab' => 'create']) }}" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                        Create Request
                    </a>
                @endif

                @if ($showExport)
                    <a href="{{ route('supply-office.export') }}" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 12v8m-4-4l4 4 4-4M8 4h8M8 8h8" />
                        </svg>
                        Export CSV
                    </a>
                @endif

                @if ($showUsageReports)
                    <a href="{{ route('supply-office.usage-reports') }}" class="inline-flex items-center gap-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                        Usage Reports
                    </a>
                @endif
            </div>
        </div>
    @endunless

    @if ($showStatsCards)
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3 mb-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">My Upcoming</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $stats['upcoming'] }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">My Pending</p>
                <p class="mt-3 text-3xl font-semibold text-amber-600">{{ $stats['pending'] }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">My Approved</p>
                <p class="mt-3 text-3xl font-semibold text-emerald-700">{{ $stats['approved'] }}</p>
            </div>
        </div>
    @endif

    @if ($showRequestList)
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 mb-6">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">My Requests</h2>
                    <p class="text-sm text-slate-500">A quick summary of requests submitted under your account.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" data-action="clear-request-filter" class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 px-4 py-2 text-sm font-semibold transition hover:bg-slate-50">
                        Clear Filter
                    </button>
                    <a href="{{ route('requestor.index', ['tab' => 'create']) }}" class="inline-flex items-center gap-2 rounded-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 text-sm font-semibold transition">
                        Submit New Request
                    </a>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm text-left">
                    <thead class="bg-slate-100 text-slate-700 uppercase tracking-[0.12em] text-xs">
                        <tr>
                            <th class="px-4 py-3">Control #</th>
                            <th class="px-4 py-3">Activity</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($personalRequests as $request)
                            <tr data-request-row data-status="{{ $request->status }}" data-returned="{{ $request->equipment_returned_status ?? '' }}" data-upcoming="{{ $request->start_date && $request->start_date->toDateString() >= now()->toDateString() ? 'true' : 'false' }}">
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $request->control_number }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ Str::limit($request->name_of_activity, 36) }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ optional($request->start_date)->format('M d, Y') }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $request->status === 'approved' ? 'bg-emerald-100 text-emerald-700' : ($request->status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700') }}">
                                        {{ $request->status === 'needs_reschedule' ? 'Needs Reschedule' : ucfirst($request->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('request.show', $request->id) }}" class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100">View</a>
                                        @if($request->status === 'pending')
                                            <a href="{{ route('requestor.edit', $request->id) }}" class="inline-flex items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">Edit</a>
                                            <form method="POST" action="{{ route('requestor.destroy') }}" class="inline-flex" data-swal-confirm data-swal-title="Delete this pending request?" data-swal-text="This action will permanently remove the pending request record." data-swal-confirm-text="Yes, delete it" data-swal-confirm-color="#dc2626">
                                                @csrf
                                                <input type="hidden" name="id" value="{{ $request->id }}">
                                                <button type="submit" class="inline-flex items-center justify-center rounded-full border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 transition hover:bg-red-100">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <p class="text-sm font-medium text-slate-700">No requests found.</p>
                                        <p class="text-xs text-slate-500">Your request list is empty. Create a new request to get started.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($showVerificationQueue)
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 mb-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Verification Queue</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $verificationQueue->count() }}</p>
                <p class="mt-2 text-sm text-slate-500">Requests pending your initial custodial verification.</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Inventory Status</p>
                <div class="mt-3 space-y-3">
                    @if ($inventoryItems->isEmpty())
                        <p class="text-sm text-slate-500">No equipment assigned to your custodial area yet.</p>
                    @else
                        @foreach ($inventoryItems as $item)
                            <div class="flex items-center justify-between gap-3 rounded-2xl bg-slate-50 px-4 py-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">{{ $item->name }}</p>
                                    <p class="text-xs text-slate-500">Available / Total</p>
                                </div>
                                <span class="text-sm font-semibold text-slate-700">{{ $item->quantity_available }} / {{ $item->quantity }}</span>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 mb-6">
            <h2 class="text-lg font-semibold text-slate-900">Initial Verification Queue</h2>
            <p class="mt-1 text-sm text-slate-500">Review requests assigned to your custodial responsibilities.</p>
            @if ($verificationQueue->isEmpty())
                <div class="mt-4 rounded-2xl bg-slate-50 p-4 text-center border border-slate-200 shadow-sm">
                    <p class="text-sm font-medium text-slate-700">No requests are currently waiting for your verification.</p>
                    <p class="text-xs text-slate-500 mt-1">Requests will appear here when they need your initial review.</p>
                </div>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm text-left">
                        <thead class="bg-slate-100 text-slate-700 uppercase tracking-[0.12em] text-xs">
                            <tr>
                                <th class="px-4 py-3">Control #</th>
                                <th class="px-4 py-3">Activity</th>
                                <th class="px-4 py-3">Requested Date</th>
                                <th class="px-4 py-3">Venue / Equipment</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @foreach ($verificationQueue as $request)
                                <tr>
                                    <td class="px-4 py-3 text-slate-700">{{ $request->control_number }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ Str::limit($request->name_of_activity, 36) }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ optional($request->start_date)->format('M d, Y') }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ implode(', ', $request->getVenueNames()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    @if ($showExport)
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 mb-6">
            <h2 class="text-lg font-semibold text-slate-900">Final Approval Queue</h2>
            <p class="mt-1 text-sm text-slate-500">Requests are ready for final approval before being released.</p>
            @if ($totalCount == 0)
                <div class="mt-4 rounded-2xl bg-slate-50 p-4 text-center border border-slate-200 shadow-sm">
                    <p class="text-sm font-medium text-slate-700">No requests are waiting for final approval.</p>
                    <p class="text-xs text-slate-500 mt-1">Requests will appear here when they're ready for your final review.</p>
                </div>
            @else
                <div class="mt-4 overflow-x-auto" id="approval-queue-container">
                    <table class="min-w-full divide-y divide-slate-200 text-sm text-left">
                        <thead class="bg-slate-100 text-slate-700 uppercase tracking-[0.12em] text-xs">
                            <tr>
                                <th class="px-4 py-3">Control #</th>
                                <th class="px-4 py-3">Activity</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white" id="requests-tbody">
                            @foreach ($finalApprovalQueue as $request)
                                <tr data-request-row data-request-id="{{ $request->id }}" data-status="{{ strtolower($request->status) }}" class="group hover:bg-slate-50 transition">
                                    <td class="px-4 py-3 text-slate-700">{{ $request->control_number }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ Str::limit($request->name_of_activity, 36) }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ optional($request->start_date)->format('M d, Y') }}</td>
                                    <td class="px-4 py-3">
                                        @if($request->status === 'pending')
                                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold bg-amber-100 text-amber-700">Pending</span>
                                        @elseif($request->status === 'approved')
                                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold bg-emerald-100 text-emerald-700">Approved</span>
                                        @elseif($request->status === 'rejected')
                                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold bg-red-100 text-red-700">Rejected</span>
                                        @elseif($request->status === 'needs_reschedule')
                                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold bg-amber-100 text-amber-700">Needs Reschedule</span>
                                        @else
                                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold bg-slate-100 text-slate-700">{{ ucfirst(str_replace('_', ' ', (string) $request->status)) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('request.show', $request->id) }}" class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div id="no-filter-results" class="hidden mt-4 rounded-2xl bg-slate-50 p-4 text-center text-sm text-slate-600 border border-slate-200 shadow-sm">
                        <p class="font-medium text-slate-700">No matching requests found.</p>
                        <p class="text-xs text-slate-500 mt-1">Try adjusting your filters or search criteria.</p>
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if ($showUserManagement || $showAuditLogs)
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 mb-6">
            @if ($showUserManagement)
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">User Management</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $userCount }}</p>
                    <p class="mt-2 text-sm text-slate-500">Total users registered in the system.</p>
                </div>
            @endif
            @if ($showAuditLogs)
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">System Audit Logs</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $totalRequests }}</p>
                    <p class="mt-2 text-sm text-slate-500">Audit-ready summary of recent facility request activity.</p>
                    <a href="{{ route('supply-office.audit-logs') }}" class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-blue-600 hover:text-blue-700">
                        View Audit Logs →
                    </a>
                </div>
            @endif
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 mb-6">
            <p class="text-sm text-slate-600">Admin dashboards are for oversight only. Approve/reject actions are intentionally hidden from this view to maintain separation of duties.</p>
        </div>
    @endif

    <div class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_20px_50px_rgba(15,23,42,0.06)]">
        <div class="border-b border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Interactive calendar</p>
                    <p class="mt-1 text-sm text-slate-600">Tap a date to inspect request details and availability.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-sm font-medium text-slate-500">
                    <span class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700"><span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>Pending</span>
                    <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Reserved</span>
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600"><span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>Available</span>
                </div>
            </div>
        </div>
        <div id="calendar" class="w-full p-4 sm:p-6 min-h-[420px] sm:min-h-[520px] lg:min-h-[600px]"></div>
    </div>

    <meta name="login-route" content="{{ route('login') }}">

    <div id="requestDetailsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 overflow-y-auto flex items-start sm:items-center justify-center p-4" onclick="if(event.target.id === 'requestDetailsModal') closeRequestDetailsModal();">
        <div class="w-full max-w-3xl sm:max-w-4xl rounded-3xl bg-white shadow-2xl overflow-hidden">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 px-4 py-4 sm:px-6">
                <div>
                    <h2 id="requestDetailsModalTitle" class="text-lg font-semibold text-slate-900">Request Details</h2>
                    <p id="requestDetailsModalSubtitle" class="text-sm text-slate-500">Loading request information...</p>
                </div>
                <button type="button" class="text-slate-500 hover:text-slate-900" onclick="closeRequestDetailsModal(); event.stopPropagation();" aria-label="Close request details modal">×</button>
            </div>
            <div id="requestDetailsModalBody" class="max-h-[85vh] overflow-y-auto px-4 py-6 sm:px-6 sm:py-6 text-slate-700">
                <div class="text-center py-10 text-slate-600">Loading request details…</div>
            </div>
            <div id="requestDetailsModalFooter" class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-200 bg-slate-50">
                <button type="button" class="px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition" onclick="closeRequestDetailsModal();">Cancel</button>
                <button type="button" id="rejectButton" class="px-4 py-2 text-sm font-semibold text-white bg-red-600 border border-red-600 rounded-lg hover:bg-red-700 transition" onclick="rejectRequest(this.dataset.requestId);" style="display: none;">Reject</button>
                <button type="button" id="approveButton" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 border border-emerald-600 rounded-lg hover:bg-emerald-700 transition" onclick="approveRequest(this.dataset.requestId);" style="display: none;">Approve</button>
            </div>
        </div>
    </div>

    <script>
        const loginRoute = document.querySelector('meta[name="login-route"]')?.getAttribute('content') || '/login';

        async function openRequestDetails(requestId) {
            const modal = document.getElementById('requestDetailsModal');
            const body = document.getElementById('requestDetailsModalBody');
            const title = document.getElementById('requestDetailsModalTitle');
            const subtitle = document.getElementById('requestDetailsModalSubtitle');
            if (!modal || !body || !title || !subtitle) return;

            modal.classList.remove('hidden');
            title.textContent = 'Request Details';
            subtitle.textContent = 'Loading request information...';
            body.innerHTML = '<div class="text-center py-10 text-slate-600">Loading request details…</div>';

            try {
                // Use the browser session (cookies) with CSRF token instead of a token from localStorage.
                const response = await fetch(`/api/facility-requests/${requestId}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    credentials: 'same-origin'
                });

                const responseText = await response.text();
                const contentType = response.headers.get('content-type') || '';
                const looksLikeHtml = responseText.trim().startsWith('<');

                if (!response.ok) {
                    if (response.status === 401 || response.status === 403) {
                        window.location.href = loginRoute;
                        return;
                    }
                    let message = `Failed to load request details (${response.status}).`;
                    if (contentType.includes('application/json')) {
                        const errorJson = JSON.parse(responseText || '{}');
                        message = errorJson.error || errorJson.message || message;
                    } else if (looksLikeHtml) {
                        message = 'Request details endpoint returned HTML. Please verify the API route and authentication state.';
                    }
                    throw new Error(message);
                }

                if (!contentType.includes('application/json')) {
                    if (looksLikeHtml) {
                        throw new Error('Expected JSON but received HTML from the request details API.');
                    }
                    throw new Error('Expected JSON response from the request details API.');
                }

                const request = JSON.parse(responseText);
                title.textContent = `Request #${request.control_number || request.id}`;
                subtitle.textContent = request.status ? `Status: ${request.status}` : 'Status unavailable';
                body.innerHTML = renderRequestDetails(request);

                // Show action buttons for administrators
                const approveBtn = document.getElementById('approveButton');
                const rejectBtn = document.getElementById('rejectButton');
                if (approveBtn && rejectBtn) {
                    approveBtn.dataset.requestId = requestId;
                    rejectBtn.dataset.requestId = requestId;
                    approveBtn.style.display = 'inline-block';
                    rejectBtn.style.display = 'inline-block';
                }
            } catch (error) {
                const errDiv = document.createElement('div');
                errDiv.className = 'rounded-2xl border border-red-200 bg-red-50 p-6 text-sm text-red-700';
                errDiv.textContent = error.message || 'An error occurred while loading request details.';
                body.innerHTML = '';
                body.appendChild(errDiv);
            }
        }

        function closeRequestDetailsModal() {
            const modal = document.getElementById('requestDetailsModal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        async function approveRequest(requestId) {
            const result = await Swal.fire({
                title: 'Final Approval',
                text: 'Are you sure you want to grant final approval for this request? This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Approve it!',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#059669',
                cancelButtonColor: '#9CA3AF',
                reverseButtons: false
            });

            if (!result.isConfirmed) return;

            try {
                // Use session cookie auth; include CSRF token. Do not rely on localStorage tokens.
                const response = await fetch(`/api/facility-requests/${requestId}/approve`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    credentials: 'same-origin'
                });

                if (response.ok) {
                    await Swal.fire({
                        title: 'Success!',
                        text: 'Request approved successfully!',
                        icon: 'success',
                        confirmButtonColor: '#059669',
                        timer: 2000,
                        timerProgressBar: true
                    });
                    closeRequestDetailsModal();
                    location.reload(); // Refresh to update the table
                } else {
                    const error = await response.json();
                    await Swal.fire({
                        title: 'Error',
                        text: 'Failed to approve: ' + (error.message || 'Unknown error'),
                        icon: 'error',
                        confirmButtonColor: '#059669'
                    });
                }
            } catch (error) {
                await Swal.fire({
                    title: 'Error',
                    text: 'Error: ' + error.message,
                    icon: 'error',
                    confirmButtonColor: '#059669'
                });
            }
        }

        async function rejectRequest(requestId) {
            const result = await Swal.fire({
                title: 'Reject Request',
                input: 'textarea',
                inputLabel: 'Rejection Reason',
                inputPlaceholder: 'Please provide a reason for rejection...',
                inputAttributes: {
                    'aria-label': 'Rejection reason'
                },
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Reject it!',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#DC2626',
                cancelButtonColor: '#9CA3AF',
                reverseButtons: false,
                inputValidator: (value) => {
                    if (!value) {
                        return 'Please provide a reason for rejection!';
                    }
                }
            });

            if (!result.isConfirmed) return;

            const reason = result.value;
            try {
                // Use session cookie auth; include CSRF token. Do not rely on localStorage tokens.
                const response = await fetch(`/api/facility-requests/${requestId}/reject`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ reason }),
                    credentials: 'same-origin'
                });

                if (response.ok) {
                    await Swal.fire({
                        title: 'Success!',
                        text: 'Request rejected successfully!',
                        icon: 'success',
                        confirmButtonColor: '#059669',
                        timer: 2000,
                        timerProgressBar: true
                    });
                    closeRequestDetailsModal();
                    location.reload(); // Refresh to update the table
                } else {
                    const error = await response.json();
                    await Swal.fire({
                        title: 'Error',
                        text: 'Failed to reject: ' + (error.message || 'Unknown error'),
                        icon: 'error',
                        confirmButtonColor: '#059669'
                    });
                }
            } catch (error) {
                await Swal.fire({
                    title: 'Error',
                    text: 'Error: ' + error.message,
                    icon: 'error',
                    confirmButtonColor: '#059669'
                });
            }
        }

        function renderRequestDetails(request) {
            const escapeHtml = (value) => {
                const element = document.createElement('div');
                element.textContent = String(value ?? '');
                return element.innerHTML;
            };
            const equipmentList = request.equipment ? request.equipment.map(item => `<li class="mb-2"><span class="font-semibold text-slate-800">${escapeHtml(item.name)}</span> &times; ${escapeHtml(item.quantity)}</li>`).join('') : '<li class="text-slate-500">No equipment items attached.</li>';
            const venue = escapeHtml(request.venue ? request.venue.name : 'N/A');
            const date = escapeHtml(request.start_date || 'N/A');
            const startTime = escapeHtml(request.start_time || 'N/A');
            const endTime = escapeHtml(request.end_time || 'N/A');
            const activity = escapeHtml(request.name_of_activity || 'N/A');
            const requester = escapeHtml(request.user ? `${request.user.name} (${request.user.email})` : 'N/A');
            const department = escapeHtml(request.department || 'N/A');
            const participants = escapeHtml(request.expected_participants || 'N/A');
            const priority = escapeHtml(request.priority || 'regular');
            const status = escapeHtml(request.status || 'N/A');
            const controlNumber = escapeHtml(request.control_number || 'N/A');

            return `
                <form class="space-y-6">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Activity Name</label>
                                <input type="text" value="${activity}" readonly class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 text-slate-700">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Department</label>
                                <input type="text" value="${department}" readonly class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 text-slate-700">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Expected Participants</label>
                                <input type="number" value="${participants}" readonly class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 text-slate-700">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Requesting Date</label>
                                <input type="date" value="${date}" readonly class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 text-slate-700">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Start Time</label>
                                <input type="time" value="${startTime}" readonly class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 text-slate-700">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">End Time</label>
                                <input type="time" value="${endTime}" readonly class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 text-slate-700">
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Venue</label>
                                <input type="text" value="${venue}" readonly class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 text-slate-700">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Priority</label>
                                <input type="text" value="${priority.charAt(0).toUpperCase() + priority.slice(1)}" readonly class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 text-slate-700">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Status</label>
                                <input type="text" value="${status.charAt(0).toUpperCase() + status.slice(1)}" readonly class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 text-slate-700">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Requestor</label>
                                <input type="text" value="${requester}" readonly class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 text-slate-700">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Control Number</label>
                                <input type="text" value="${controlNumber}" readonly class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 text-slate-700">
                            </div>
                        </div>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                        <label class="block text-sm font-semibold text-slate-700 mb-4">Equipment & Supplies</label>
                        <ul class="list-disc list-inside text-slate-700">${equipmentList}</ul>
                    </div>
                </form>
            `;
        }
    </script>

    <!-- Modal for "No Reservation" message -->
    <div id="noReservationModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center" onclick="if(event.target.id === 'noReservationModal') document.getElementById('noReservationModal').classList.add('hidden');">
        <div class="bg-white rounded-lg p-6 max-w-sm mx-4 shadow-xl">
            <div class="text-center">
                <div class="text-5xl mb-4">📅</div>
                <h2 class="text-xl font-bold text-gray-800 mb-2">Availability Check</h2>
                <p class="text-gray-600 mb-6">There are no public requests on this date. The venue appears available for a new reservation request.</p>
                @if ($role === 'requestor')
                    <a href="{{ route('requestor.index', ['tab' => 'create']) }}" class="inline-flex items-center justify-center bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2 rounded-lg font-semibold transition">
                        Create Request
                    </a>
                @elseif ($role === 'guest')
                    <a href="{{ route('login') }}" class="inline-flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">
                        Login to Submit a Request
                    </a>
                @else
                    <button onclick="document.getElementById('noReservationModal').classList.add('hidden')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg font-semibold transition">
                        Close
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- FullCalendar CSS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">

<style>
/* Event styling based on status */
.rejected-event {
    opacity: 0.75 !important;
    border-style: solid !important;
    text-decoration: line-through !important;
}

.pending-event {
    opacity: 0.95 !important;
    border-style: dashed !important;
}

.approved-event {
    opacity: 1 !important;
    font-weight: 700 !important;
}

.needs-reschedule-event {
    opacity: 0.96 !important;
    box-shadow: inset 0 0 0 1px rgba(234, 88, 12, 0.4) !important;
}

.neutral-event {
    opacity: 0.9 !important;
}

/* Keep event content clipped within the event area */
.fc-event-main,
.fc-event-main-frame,
.fc-event-title-container,
.fc-event-title,
.fc-event-time,
.fc-event-label,
.fc-event-body,
.fc-event-line,
.fc-event-meta-line,
.fc-event-meta-item {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
}

.fc-event-main-frame {
    width: 100%;
}

.fc-event-compact {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: normal;
}

.fc-event-label {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.fc-event-meta-line {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.fc-event-meta-item {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 100%;
}

.legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-right: 1rem;
    margin-bottom: 0.5rem;
}

.legend-color {
    width: 1rem;
    height: 1rem;
    border-radius: 0.25rem;
    border: 1px solid #e5e7eb;
}

/* FullCalendar Button Styling */
.fc-button-primary {
    background-color: #f3f4f6 !important;
    border-color: #d1d5db !important;
    color: #6b7280 !important;
    border-radius: 8px !important;
    padding: 6px 14px !important;
    font-weight: 500 !important;
    text-transform: none !important;
    letter-spacing: normal !important;
    transition: all 0.2s ease !important;
}

.fc-button-primary:hover:not(.fc-button-active) {
    background-color: #e5e7eb !important;
    border-color: #d1d5db !important;
}

.fc-button-primary.fc-button-active,
.fc-button-primary:active {
    background-color: #fbbf24 !important;
    border-color: #fbbf24 !important;
    color: #1e293b !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

.fc-button-primary.fc-button-active:hover,
.fc-button-primary:active:hover {
    background-color: #fcd34d !important;
    border-color: #fcd34d !important;
}

/* Enhanced FullCalendar Styling */
.fc {
    font-family: inherit !important;
}

.fc-toolbar-title {
    font-size: 1.25rem !important;
    font-weight: 700 !important;
    color: #1e293b !important;
    letter-spacing: -0.02em !important;
}

@media (min-width: 640px) {
    .fc-toolbar-title {
        font-size: 1.5rem !important;
    }
}

.fc-col-header-cell {
    background-color: #1e293b !important;
    color: #fef08a !important;
    font-weight: 700 !important;
    padding: 12px 4px !important;
}

.fc-daygrid-day {
    border-color: #e5e7eb !important;
}

.fc-daygrid-day:hover {
    background-color: #f1f5f9 !important;
}

.fc-daygrid-day.fc-today {
    background-color: #f0fdf4 !important;
}

/* ============================================
   HIGH-DENSITY CALENDAR LAYOUT
   ============================================ */

/* Day Cell Container - Compact Layout */
.fc-daygrid-day {
    position: relative;
    vertical-align: top !important;
    padding: 0 !important;
    background-color: #ffffff !important;
    border: 1px solid #e5e7eb !important;
}

.fc-daygrid-day.fc-day-other {
    background-color: #f9fafb !important;
}

.fc-daygrid-day.fc-day-weekend,
.fc-timegrid-col.fc-day-sat,
.fc-timegrid-col.fc-day-sun,
.fc-daygrid-day.fc-day-weekend .fc-daygrid-day-number,
.fc-daygrid-day.fc-day-weekend .fc-daygrid-day-frame {
    background-color: #ffffff !important;
    color: #1f2937 !important;
}

/* Day Cell Inner Container */
.fc-daygrid-day-inner {
    display: flex;
    flex-direction: column;
    height: 100%;
    padding: 4px 6px !important;
    gap: 2px;
}

/* Day Cell Frame */
.fc-daygrid-day-frame {
    display: flex;
    flex-direction: column;
    height: 100% !important;
}

/* Date Number Header */
.fc-daygrid-day-number {
    padding: 4px 6px !important;
    font-weight: 700 !important;
    font-size: 0.95rem !important;
    color: #1f2937 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 4px;
    margin-bottom: 3px !important;
    border-bottom: 1px solid #e5e7eb;
    position: relative;
}

/* Weekend dates keep the same neutral background as weekdays while preserving labels */
.fc-day-weekend .fc-daygrid-day-number {
    background-color: #ffffff !important;
    color: #1f2937 !important;
}

/* Status Icons Container (Pending/Conflict) */
.day-status-icons {
    display: flex;
    gap: 2px;
    flex-shrink: 0;
}

.status-icon {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 8px;
    color: white;
    font-weight: bold;
    flex-shrink: 0;
}

.status-icon.pending {
    background-color: #f59e0b;
}

.status-icon.conflict {
    background-color: #dc2626;
}

.status-icon.approved {
    background-color: #10b981;
}

.status-icon.rejected {
    background-color: #ef4444;
}

.status-icon.review {
    background-color: #2563eb;
}

.event-status-badge {
    margin-left: 0.4rem;
    border-radius: 9999px;
    padding: 0.15rem 0.5rem;
    font-size: 0.62rem;
    line-height: 1.2;
    font-weight: 700;
    letter-spacing: 0.02em;
    white-space: nowrap;
    border: 1px solid transparent;
    align-self: flex-start;
}

.event-status-badge.status-approved {
    background: rgba(16, 185, 129, 0.12);
    color: #065f46;
    border-color: rgba(16, 185, 129, 0.24);
}

.event-status-badge.status-pending {
    background: rgba(245, 158, 11, 0.12);
    color: #92400e;
    border-color: rgba(245, 158, 11, 0.26);
}

.event-status-badge.status-rejected,
.event-status-badge.status-conflict,
.event-status-badge.status-urgent {
    background: rgba(239, 68, 68, 0.12);
    color: #991b1b;
    border-color: rgba(239, 68, 68, 0.24);
}

.event-status-badge.status-needs-reschedule {
    background: rgba(249, 115, 22, 0.12);
    color: #9a4d00;
    border-color: rgba(249, 115, 22, 0.22);
}

.event-status-badge.status-completed {
    background: rgba(148, 163, 184, 0.14);
    color: #334155;
    border-color: rgba(148, 163, 184, 0.26);
}

.event-status-badge.status-neutral {
    background: rgba(100, 116, 139, 0.12);
    color: #334155;
    border-color: rgba(100, 116, 139, 0.2);
}

.fc-event-text {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}

.fc-event-body {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}

.fc-event-line {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}

.fc-event-meta-line {
    display: flex;
    flex-wrap: wrap;
    gap: 0.15rem 0.5rem;
    font-size: 0.6rem;
    line-height: 1.3;
    opacity: 0.92;
    min-width: 0;
    margin-top: 0.1rem;
}

.fc-event-meta-item {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

/* Events List Container - Compact */
.fc-daygrid-day-events {
    display: flex;
    flex-direction: column;
    gap: 1px !important;
    flex: 1 !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    padding: 0 !important;
    max-height: calc(100% - 30px);
    min-height: 0;
}

/* Individual Event in High-Density Mode */
.fc-event {
    cursor: pointer;
    transition: all 0.2s ease;
    border-radius: 0.75rem !important;
    margin: 0 !important;
    padding: 0 !important;
    font-size: 0.75rem !important;
    line-height: 1.2 !important;
    display: flex !important;
    align-items: center !important;
    gap: 6px;
    flex: 0 0 auto;
    /* Allow FullCalendar's native positioning to work correctly */
    /* position: NOT static - let FullCalendar use absolute positioning */
    /* height: NOT auto - let FullCalendar calculate based on duration */
    /* width: NOT 100% - let FullCalendar set based on column layout */
    box-shadow: none !important;
    border: 1px solid transparent !important;
    background-color: transparent !important;
}

.fc-timegrid-event {
    /* Enable FullCalendar's calculated positioning for time grid events */
    position: absolute !important;
}

.fc-timegrid .fc-event-main {
    /* Allow height to be calculated by FullCalendar */
    height: 100%;
}

.fc-event:hover {
    transform: translateX(1px);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.12) !important;
}

/* Event Block Content */
.fc-event-compact {
    display: flex;
    align-items: center;
    width: 100%;
    gap: 8px;
    padding: 5px 7px;
    border-radius: 0.75rem;
    color: inherit;
    min-width: 0;
}

.fc-event-label {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
    font-weight: 700;
    color: inherit;
    min-width: 0;
}

.fc-event-meta {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.85);
}

/* Event Dot (status indicator) */
.event-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
    display: inline-block;
    background-color: #cbd5e1;
}

.event-dot.gymnasium,
.event-dot.chic,
.event-dot.oval,
.event-dot.balay,
.event-dot.covered-court,
.event-dot.volleyball,
.event-dot.other {
    background-color: #cbd5e1;
}

/* Event Title (Truncated) */
.fc-event-title {
    font-weight: 600 !important;
    font-size: 0.7rem !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    flex: 1;
    min-width: 0;
    color: #1f2937 !important;
}

.fc-event-short-title {
    display: inline;
    font-size: 0.7rem;
}

/* Daily Total Participants Badge */
.daily-participants-badge {
    position: absolute;
    top: 4px;
    right: 6px;
    background-color: #1e293b;
    color: #fef08a;
    font-weight: 700;
    font-size: 0.7rem;
    padding: 2px 5px;
    border-radius: 3px;
    min-width: 28px;
    text-align: center;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2px;
}

.daily-participants-badge small {
    font-size: 0.6rem;
    opacity: 0.9;
}

/* Max Capacity Reached Indicator */
.max-capacity-reached .daily-participants-badge {
    background-color: #dc2626;
    color: #ffffff;
    font-weight: 800;
}

/* Calendar Header & Button Branding */
.fc .fc-toolbar, .fc .fc-toolbar-chunk {
    background-color: #ffffff !important;
}

.fc .fc-button-primary {
    background-color: #1e293b !important;
    color: #fef08a !important;
    border-color: #1e293b !important;
}

.fc .fc-button-primary:not(:disabled):hover,
.fc .fc-button-primary:not(:disabled):focus {
    background-color: #334155 !important;
    color: #fef08a !important;
}

.fc .fc-button-primary.fc-button-active {
    background-color: #fef08a !important;
    color: #1e293b !important;
    border-color: #1e293b !important;
}

.fc .fc-toolbar-title {
    color: #1e293b !important;
    font-weight: 800 !important;
}

.fc .fc-button {
    border-radius: 9999px !important;
}

/* Max Capacity Reached Day */
.max-capacity-reached {
    background-color: #fee2e2 !important;
}

.max-capacity-reached .fc-daygrid-day-number {
    background-color: #fecaca !important;
    color: #991b1b !important;
}

.status-icon.maintenance {
    background-color: #2563eb;
} 

/* Status-based event colors override any venue-driven color mapping */
.fc-event-gymnasium,
.fc-event-chic,
.fc-event-oval,
.fc-event-balay,
.fc-event-covered-court,
.fc-event-volleyball,
.fc-event-other {
    background-color: transparent !important;
    border-color: transparent !important;
}

/* Ensure all events are visible in month view */
.fc .fc-daygrid-event {
    visibility: visible !important;
}

/* Responsive Calendar */
#calendar {
    min-height: auto !important;
}

.fc {
    min-height: auto !important;
}

@media (max-width: 640px) {
    .fc-toolbar {
        flex-direction: column;
        gap: 12px !important;
    }
    
    .fc-toolbar-title {
        font-size: 1.125rem !important;
    }
    
    .fc .fc-button {
        font-size: 0.75rem;
        padding: 0.4rem 0.6rem;
    }

    .fc-event-compact {
        font-size: 0.65rem !important;
        padding: 1px 3px !important;
    }

    .fc-event-title {
        font-size: 0.65rem !important;
    }

    .daily-participants-badge {
        font-size: 0.6rem;
        padding: 1px 4px;
    }
}

/* Tippy Tooltip Styling */
.tippy-box[data-theme~='light'] {
    background-color: #ffffff;
    color: #1f2937;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

.tippy-box[data-theme~='light'][data-placement^='top'] > .tippy-arrow::before {
    border-top-color: #ffffff;
}

.tippy-content {
    padding: 12px;
    font-size: 0.875rem;
    line-height: 1.5;
}

.tippy-content strong {
    font-weight: 600;
    color: #1e293b;
}

</style>

<!-- FullCalendar JS & CSS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<!-- Tippy.js for Tooltips -->
<script src="https://unpkg.com/@popperjs/core@2"></script>
<script src="https://unpkg.com/tippy.js@6"></script>
<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/themes/light.css">

<script>
// Helper function to initialize tooltips for all event elements
function initializeTooltips() {
    if (typeof tippy === 'undefined') return;
    
    var eventElements = document.querySelectorAll('.fc-event');
    eventElements.forEach(function(el) {
        // Destroy existing tooltip if any
        if (el._tippy) {
            el._tippy.destroy();
        }
        
        // Initialize Tippy for hover
        var title = el.getAttribute('title') || el.textContent;
        tippy(el, {
            content: title || 'Event Details',
            theme: 'light',
            arrow: true,
            placement: 'top',
            interactive: false,
            delay: [400, 0]
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var currentRole = '{{ $role }}';

    function normalizeVenueName(value) {
        return String(value || '').toLowerCase().trim();
    }

    function normalizeStatusLabel(statusValue) {
                var normalized = String(statusValue || '').trim();
                if (!normalized) return '';
                var lower = normalized.toLowerCase();
                var labelMap = {
                    approved: 'Approved',
                    pending: 'Pending',
                    rejected: 'Rejected',
                    cancelled: 'Cancelled',
                    completed: 'Completed',
                    needs_reschedule: 'Needs Reschedule',
                    conflict: 'Conflict',
                    urgent: 'Urgent',
                    'under review': 'Under Review'
                };
                return labelMap[lower] || normalized.replace(/_/g, ' ').replace(/\b\w/g, function(letter) {
                    return letter.toUpperCase();
                });
            }

            function getStatusBadgeTone(statusValue) {
                var normalized = String(statusValue || '').trim().toLowerCase();
                if (normalized === 'approved') {
                    return 'status-approved';
                }
                if (normalized === 'pending') {
                    return 'status-pending';
                }
                if (['rejected', 'cancelled', 'conflict', 'urgent'].includes(normalized)) {
                    return 'status-rejected';
                }
                if (normalized === 'needs_reschedule') {
                    return 'status-needs-reschedule';
                }
                if (normalized === 'completed') {
                    return 'status-completed';
                }
                return 'status-neutral';
            }

            function getStatusColorInfo(statusValue) {
                var normalized = String(statusValue || '').toLowerCase();
                if (normalized === 'approved') {
                    return { bg: '#10b981', border: '#059669', text: '#ffffff' };
                }
                if (normalized === 'pending') {
                    return { bg: '#f59e0b', border: '#d97706', text: '#111827' };
                }
                if (['rejected', 'cancelled', 'conflict', 'urgent'].includes(normalized)) {
                    return { bg: '#dc2626', border: '#b91c1c', text: '#ffffff' };
                }
                if (normalized === 'needs_reschedule') {
                    return { bg: '#f97316', border: '#ea580c', text: '#ffffff' };
                }
                if (normalized === 'completed') {
                    return { bg: '#64748b', border: '#475569', text: '#ffffff' };
                }
                return { bg: '#e5e7eb', border: '#cbd5e1', text: '#111827' };
            }

    if (!calendarEl) {
        console.error('Calendar element not found!');
        var errorDiv = document.createElement('div');
        errorDiv.className = 'p-4 bg-red-50 border border-red-200 rounded-lg text-red-800';
        var strong = document.createElement('strong');
        strong.textContent = 'Error:';
        errorDiv.appendChild(strong);
        errorDiv.appendChild(document.createTextNode(' Calendar could not be loaded. Please refresh the page.'));
        document.querySelector('.bg-white.rounded-lg.shadow-md.p-6').appendChild(errorDiv);
        return;
    }
    
    try {
        function getInitialCalendarView() {
            if (window.innerWidth < 640) {
                return 'timeGridDay';
            }
            if (window.innerWidth < 1024) {
                return 'timeGridWeek';
            }
            return 'dayGridMonth';
        }

        // Track current view for use in event callbacks
        var currentViewType = getInitialCalendarView();

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: getInitialCalendarView(),
            nextDayThreshold: '00:00:00',
            displayEventEnd: true,
            contentHeight: 'auto',
            timeZone: 'Asia/Manila',
            locale: 'en',
            eventDisplay: 'block',
            dayMaxEvents: false, // Show all events, don't limit
            dayMaxEventRows: false, // Don't cap rows for daygrid
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            slotLabelFormat: {
                meridiem: 'short',
                hour: 'numeric',
                minute: '2-digit'
            },
            eventTimeFormat: {
                meridiem: 'short',
                hour: 'numeric',
                minute: '2-digit'
            },
            slotLabelInterval: '01:00',
            // Visible time range only. This must not alter the actual reservation start/end time.
            slotMinTime: '08:00',
            slotMaxTime: '24:00',
            // Keep a single continuous timed grid; no all-day section.
            allDaySlot: false,
            windowResizeDelay: 100,
            windowResize: function() {
                var nextView = getInitialCalendarView();
                if (calendar.view.type !== nextView) {
                    calendar.changeView(nextView);
                }
            },
            viewDidMount: function(info) {
                // Update the current view type whenever the view changes
                currentViewType = info.view.type;
                
                // Refetch events with new view type to trigger transformation
                setTimeout(function() {
                    calendar.refetchEvents();
                }, 100);
            },
            dayCellClassNames: function(info) {
                var classes = [];
                if (info.date.getDay() === 0 || info.date.getDay() === 6) {
                    classes.push('fc-day-weekend');
                }
                return classes;
            },
            events: function(fetchInfo, successCallback, failureCallback) {
                // Detect view type from fetch range: month view fetches longer ranges
                var startDate = new Date(fetchInfo.startStr);
                var endDate = new Date(fetchInfo.endStr);
                var daysDiff = (endDate - startDate) / (1000 * 60 * 60 * 24);
                
                // Heuristic: if range > 20 days, it's likely a month view; if ~7 days, it's week view
                var viewType = currentViewType;
                if (daysDiff > 20) {
                    viewType = 'dayGridMonth';
                } else if (daysDiff > 6 && daysDiff <= 20) {
                    viewType = 'timeGridWeek';
                }
                
                console.log('📅 Events:', viewType, '|', Math.round(daysDiff), 'days');

                fetch('{{ route("calendar.events") }}')
                    .then(function(response) {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.status + ' ' + response.statusText);
                        }
                        return response.json();
                    })
                    .then(function(data) {
                        console.log('✅ Calendar events loaded successfully:', data);
                        
                        // Store all events globally for dayCell rendering
                        window.allEventsData = data;

                        // Helper function to create tooltip content
                        function createTooltipContent(event, startDatetime, status) {
                            function __esc(s) { var d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }
                            var tooltipContent = '<div style="text-align: left; padding: 0; margin: 0;">';
                            var displayTitle = event.extendedProps && event.extendedProps.purpose ? event.extendedProps.purpose : (event.title || 'Facility Request');
                            tooltipContent += '<div style="margin-bottom: 8px;"><strong style="font-size: 0.95rem;">' + __esc(displayTitle) + '</strong></div>';
                            if (event.extendedProps && event.extendedProps.venue) {
                                tooltipContent += '<div style="margin-bottom: 6px;"><strong>Venue:</strong> ' + __esc(event.extendedProps.venue) + '</div>';
                            }
                            if (startDatetime) {
                                tooltipContent += '<div style="margin-bottom: 6px;"><strong>Schedule:</strong> ' + __esc(startDatetime.replace('T', ' ')) + '</div>';
                            }
                            if (status) {
                                var statusColors = getStatusColorInfo(status);
                                tooltipContent += '<div style="margin-bottom: 6px;"><strong>Status:</strong> <span style="color: ' + __esc(statusColors.bg) + '; font-weight: 700;">' + __esc(status) + '</span></div>';
                            }
                            if (event.extendedProps && event.extendedProps.requestor) {
                                tooltipContent += '<div style="margin-bottom: 6px;"><strong>Requestor:</strong> ' + __esc(event.extendedProps.requestor) + '</div>';
                            }
                            if (event.extendedProps && event.extendedProps.priority) {
                                tooltipContent += '<div style="margin-bottom: 6px;"><strong>Priority:</strong> ' + __esc(event.extendedProps.priority) + '</div>';
                            }
                            if (event.extendedProps && event.extendedProps.isUrgent) {
                                tooltipContent += '<div><strong>Urgent:</strong> Yes</div>';
                            }
                            tooltipContent += '</div>';
                            return tooltipContent;
                        }

                        // Helper function to create a single FC event object
                        function createFcEvent(event, startDatetime, endDatetime, isSegment) {
                            var status = event.extendedProps && event.extendedProps.status ? event.extendedProps.status : event.status || '';
                            var statusPalette = getStatusColorInfo(status);
                            var displayTitle = event.extendedProps && event.extendedProps.purpose ? event.extendedProps.purpose : (event.title || 'Facility Request');

                            return {
                                id: event.id,
                                title: displayTitle,
                                start: startDatetime,
                                end: endDatetime,
                                allDay: false,
                                backgroundColor: statusPalette.bg,
                                borderColor: statusPalette.border,
                                textColor: statusPalette.text,
                                classNames: ['status-event', 'status-' + String(status || 'neutral').toLowerCase().replace(/\s+/g, '-')],
                                extendedProps: Object.assign({}, event.extendedProps, {
                                    tooltipContent: createTooltipContent(event, startDatetime, status),
                                    venueClass: 'status-indicator',
                                    venueDotColor: statusPalette.bg,
                                    participants: event.extendedProps.expected_participants || 0,
                                    statusClass: String(status || 'neutral').toLowerCase(),
                                    displayStatus: status,
                                    isSegment: !!isSegment
                                })
                            };
                        }

                        // Helper: check if event spans multiple days
                        function isMultiDay(startStr, endStr) {
                            var startDate = startStr.substring(0, 10);
                            var endDate = endStr.substring(0, 10);
                            return startDate !== endDate;
                        }

                        // Helper: create end-of-day datetime
                        function getEndOfDay(dateStr) {
                            return dateStr + 'T23:59:59';
                        }

                        // Helper: create start-of-day datetime
                        function getStartOfDay(dateStr) {
                            return dateStr + 'T00:00:00';
                        }

                        // Helper: get next day at 00:00 (for month view spanning)
                        function getNextDayStart(dateStr) {
                            var date = new Date(dateStr);
                            date.setDate(date.getDate() + 1);
                            return date.toISOString().substring(0, 10) + 'T00:00:00';
                        }

                        // Transform events based on view type
                        var isTimeGridView = viewType === 'timeGridWeek' || viewType === 'timeGridDay';
                        var isMonthView = viewType === 'dayGridMonth';

                        var mapped = [];

                        data.forEach(function(event) {
                            var startDatetime = event.start || '';
                            var endDatetime = event.end || '';

                            // For Month View: create separate events for each day so clicking any day shows the event
                            if (isMonthView) {
                                if (isMultiDay(startDatetime, endDatetime)) {
                                    // Create per-day events for multi-day reservations in Month View
                                    var currentDate = startDatetime.substring(0, 10);
                                    var endDate = endDatetime.substring(0, 10);
                                    var startTime = startDatetime.substring(11, 19);
                                    var endTime = endDatetime.substring(11, 19);

                                    // First day: from actual start time to end of day
                                    var firstDayEnd = getEndOfDay(currentDate);
                                    mapped.push(createFcEvent(event, startDatetime, firstDayEnd, true));

                                    // Middle days: full day
                                    currentDate = new Date(currentDate);
                                    currentDate.setDate(currentDate.getDate() + 1);
                                    while (currentDate.toISOString().substring(0, 10) < endDate) {
                                        var dateStr = currentDate.toISOString().substring(0, 10);
                                        mapped.push(createFcEvent(
                                            event,
                                            getStartOfDay(dateStr),
                                            getEndOfDay(dateStr),
                                            true
                                        ));
                                        currentDate.setDate(currentDate.getDate() + 1);
                                    }

                                    // Last day: from start of day to actual end time
                                    mapped.push(createFcEvent(event, getStartOfDay(endDate), endDatetime, true));
                                } else {
                                    // Single day: keep as-is
                                    mapped.push(createFcEvent(event, startDatetime, endDatetime, false));
                                }
                                return;
                            }

                            // For timeGrid views (Week/Day): handle multi-day events specially
                            if (isTimeGridView && isMultiDay(startDatetime, endDatetime)) {
                                // Create per-day visual segments for multi-day reservations in Week View
                                var currentDate = startDatetime.substring(0, 10);
                                var endDate = endDatetime.substring(0, 10);
                                var startTime = startDatetime.substring(11, 19);
                                var endTime = endDatetime.substring(11, 19);

                                // First day: from actual start time to end of day
                                var firstDayEnd = getEndOfDay(currentDate);
                                mapped.push(createFcEvent(event, startDatetime, firstDayEnd, true));

                                // Middle days: full day (or at least start to end of day within viewport)
                                currentDate = new Date(currentDate);
                                currentDate.setDate(currentDate.getDate() + 1);
                                while (currentDate.toISOString().substring(0, 10) < endDate) {
                                    var dateStr = currentDate.toISOString().substring(0, 10);
                                    mapped.push(createFcEvent(
                                        event,
                                        getStartOfDay(dateStr),
                                        getEndOfDay(dateStr),
                                        true
                                    ));
                                    currentDate.setDate(currentDate.getDate() + 1);
                                }

                                // Last day: from start of day to actual end time
                                mapped.push(createFcEvent(event, getStartOfDay(endDate), endDatetime, true));
                            } else {
                                // Single-day or Day View: use canonical event
                                mapped.push(createFcEvent(event, startDatetime, endDatetime, false));
                            }
                        });

                        console.log('✅ Rendered', mapped.length, 'segments for', viewType);
                        successCallback(mapped);
                        
                        // ✅ Initialize tooltips after events are rendered
                        setTimeout(initializeTooltips, 300);
                    })
                    .catch(function(error) {
                        console.error('❌ Failed to load calendar events:', error);
                        failureCallback(error);
                        var errorDiv = document.createElement('div');
                        errorDiv.className = 'p-4 bg-red-50 border border-red-200 rounded-lg text-red-800 mb-4';
                        var strong = document.createElement('strong');
                        strong.textContent = 'Error loading events:';
                        errorDiv.appendChild(strong);
                        var msg = (error && error.message) ? error.message : 'Unknown error';
                        errorDiv.appendChild(document.createTextNode(' ' + msg + '. Check console for details.'));
                        if (calendarEl && calendarEl.parentNode) {
                            calendarEl.parentNode.insertBefore(errorDiv, calendarEl);
                        }
                    });
            },
            eventContent: function(info) {
                function __esc(s) { var d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }
                function formatTimeTo12Hour(dateStr) {
                    if (!dateStr) return '';
                    // Parse the ISO string directly without timezone conversion
                    var parts = dateStr.substring(11, 19).split(':');
                    if (parts.length < 2) return '';
                    var hours = parseInt(parts[0], 10);
                    var minutes = parseInt(parts[1], 10);
                    var ampm = hours >= 12 ? 'PM' : 'AM';
                    hours = hours % 12 || 12;
                    minutes = minutes < 10 ? '0' + minutes : minutes;
                    return hours + ':' + minutes + ' ' + ampm;
                }
                
                var title = info.event.title || '';
                var status = info.event.extendedProps.displayStatus || info.event.extendedProps.status || info.event.status || '';
                var statusTone = getStatusBadgeTone(status);
                var statusText = normalizeStatusLabel(status);
                var statusLabel = statusText ? '<span class="event-status-badge ' + __esc(statusTone) + '">' + __esc(statusText) + '</span>' : '';
                var dot = '<span class="event-dot"></span>';
                var label = '<span class="fc-event-label">' + __esc(title) + '</span>';

                var metaItems = [];
                if (info.event.start && info.event.end) {
                    var startStr = typeof info.event.start === 'string' ? info.event.start : info.event.start.toISOString();
                    var endStr = typeof info.event.end === 'string' ? info.event.end : info.event.end.toISOString();
                    metaItems.push('<span class="fc-event-meta-item">' + __esc(formatTimeTo12Hour(startStr) + '–' + formatTimeTo12Hour(endStr)) + '</span>');
                }

                if (info.event.extendedProps && info.event.extendedProps.venue) {
                    metaItems.push('<span class="fc-event-meta-item">' + __esc(info.event.extendedProps.venue) + '</span>');
                }

                var metaLine = metaItems.length ? '<div class="fc-event-meta-line">' + metaItems.join(' · ') + '</div>' : '';

                return { html: '<div class="fc-event-compact">' + dot + '<div class="fc-event-body"><div class="fc-event-line">' + label + statusLabel + '</div>' + metaLine + '</div></div>' };
            },
            eventClick: function(info) {
                let requestId = info.event.id || info.event.extendedProps.facilityRequestId;

                if (requestId) {
                    window.location.href = '{{ url("/request") }}/' + requestId;
                }
            },
            dayCellDidMount: function(info) {
                var dayDate = info.date.toISOString().slice(0, 10);
                var eventsOnDay = (window.allEventsData || []).filter(function(event) {
                    var startDatetime = event.start_datetime || event.start || '';
                    if (startDatetime.indexOf(' ') !== -1) {
                        startDatetime = startDatetime.replace(' ', 'T');
                    }
                    return startDatetime.slice(0, 10) === dayDate;
                });

                var totalParticipants = eventsOnDay.reduce(function(sum, event) {
                    var participants = event.extendedProps && event.extendedProps.expected_participants ? event.extendedProps.expected_participants : event.expected_participants || 0;
                    return sum + (parseInt(participants, 10) || 0);
                }, 0);

                var dayNumberEl = info.el.querySelector('.fc-daygrid-day-number');
                if (dayNumberEl) {
                    var statusContainer = dayNumberEl.querySelector('.day-status-icons');
                    if (!statusContainer) {
                        statusContainer = document.createElement('div');
                        statusContainer.className = 'day-status-icons';
                        dayNumberEl.appendChild(statusContainer);
                    }
                    statusContainer.innerHTML = '';

                    var statusSet = {};
                    eventsOnDay.forEach(function(event) {
                        var status = event.extendedProps && event.extendedProps.status ? event.extendedProps.status : event.status;
                        if (!status) {
                            return;
                        }
                        status = status.toString().trim();
                        if (['Approved', 'approved'].includes(status)) {
                            statusSet['approved'] = 'Approved';
                        } else if (['Pending', 'pending'].includes(status)) {
                            statusSet['pending'] = 'Pending';
                        } else if (['Rejected', 'rejected'].includes(status)) {
                            statusSet['rejected'] = 'Rejected';
                        } else if (['Under Review', 'under review', 'Under Review'].includes(status) || ['Conflict', 'Maintenance'].includes(status)) {
                            statusSet['review'] = 'Under Review';
                        }
                    });

                    Object.keys(statusSet).forEach(function(key) {
                        var icon = document.createElement('span');
                        icon.className = 'status-icon ' + key;
                        icon.textContent = key === 'approved' ? '✔' : (key === 'rejected' ? '✕' : '•');
                        icon.title = statusSet[key];
                        statusContainer.appendChild(icon);
                    });
                }

                var badge = info.el.querySelector('.daily-participants-badge');
                    if (totalParticipants > 0) {
                    if (!badge) {
                        badge = document.createElement('div');
                        badge.className = 'daily-participants-badge';
                        info.el.appendChild(badge);
                    }
                    // Use textContent and a small node to avoid injecting HTML
                    badge.textContent = String(totalParticipants) + ' ';
                    var small = document.createElement('small');
                    small.textContent = 'pax';
                    badge.appendChild(small);
                } else if (badge) {
                    badge.remove();
                }

                var hasMaxCapacity = eventsOnDay.some(function(event) {
                    return event.extendedProps && event.extendedProps.max_capacity_reached;
                });
                if (hasMaxCapacity) {
                    info.el.classList.add('max-capacity-reached');
                } else {
                    info.el.classList.remove('max-capacity-reached');
                }

                if (info.date.toDateString() === new Date().toDateString()) {
                    info.el.classList.add('fc-today');
                    info.el.style.backgroundColor = '#f0fdf4';
                }
            },
            dateClick: function(info) {
                if (currentRole === 'admin') {
                    document.getElementById('noReservationModal').classList.remove('hidden');
                    return;
                }

                var eventsOnDate = calendar.getEvents().filter(function(event) {
                    var eventStart = event.start;
                    var clickedDate = info.dateStr;
                    var eventDate = eventStart ? eventStart.toISOString().split('T')[0] : null;
                    return eventDate === clickedDate;
                });

                if (eventsOnDate.length === 0) {
                    document.getElementById('noReservationModal').classList.remove('hidden');
                }
            },
            loading: function(isLoading) {
                if (isLoading) {
                    if (!document.getElementById('calendar-loading')) {
                        var loadingDiv = document.createElement('div');
                        loadingDiv.id = 'calendar-loading';
                        loadingDiv.className = 'absolute inset-0 flex items-center justify-center bg-white bg-opacity-75 z-10 rounded-lg';
                        loadingDiv.innerHTML = '<div class="text-emerald-600 flex items-center gap-2"><div class="animate-spin rounded-full h-4 w-4 border-b-2 border-emerald-600"></div>Loading calendar events...</div>';
                        calendarEl.style.position = 'relative';
                        calendarEl.appendChild(loadingDiv);
                    }
                } else {
                    var loading = document.getElementById('calendar-loading');
                    if (loading) loading.remove();
                }
            },
            eventDidMount: function(info) {
                // Add tooltip on mount
                if (info.event.extendedProps.tooltipContent && typeof tippy !== 'undefined') {
                    tippy(info.el, {
                        content: info.event.extendedProps.tooltipContent,
                        allowHTML: true,
                        theme: 'light',
                        arrow: true,
                        placement: 'top',
                        interactive: false,
                        delay: [400, 0]
                    });
                }
                if (info.event.backgroundColor) {
                    info.el.style.backgroundColor = info.event.backgroundColor;
                }
                if (info.event.borderColor) {
                    info.el.style.borderColor = info.event.borderColor;
                }
                if (info.event.textColor) {
                    info.el.style.color = info.event.textColor;
                }
            }
        });

        window.calendar = calendar;
        calendar.render();
        console.log('✅ Calendar initialized successfully');
    } catch (error) {
        console.error('❌ Calendar initialization failed:', error);
        var errorDiv = document.createElement('div');
        errorDiv.className = 'p-4 bg-red-50 border border-red-200 rounded-lg text-red-800';
        var strong = document.createElement('strong');
        strong.textContent = 'Calendar Error:';
        errorDiv.appendChild(strong);
        var msg = (error && error.message) ? error.message : '';
        errorDiv.appendChild(document.createTextNode(' ' + msg + '. Check console for details.'));
        calendarEl.parentNode.insertBefore(errorDiv, calendarEl);
    }
});
</script>
