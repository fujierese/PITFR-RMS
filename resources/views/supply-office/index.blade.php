@extends('layouts.app')
@section('title', 'Administration')

@section('content')
<div class="space-y-6">
    <div class="rounded-3xl bg-gradient-to-r from-emerald-700 via-emerald-600 to-emerald-800 p-4 text-white shadow-xl sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold sm:text-3xl">🏢 Supply Office</h1>
                <p class="mt-2 max-w-2xl text-sm text-emerald-50">Manage venues, equipment, custodial assignment, final approvals, and administrative oversight.</p>
            </div>
            <div class="text-left sm:text-right">
                <p class="text-sm uppercase tracking-[0.18em] text-emerald-100">Supply Office</p>
                <p class="mt-2 text-sm text-emerald-50">Venue and equipment governance remains read-write for authorized supply office users only.</p>
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-3xl border border-emerald-100 bg-white p-5 shadow-sm ring-1 ring-emerald-50">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Venues</p>
            <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $venues->count() }}</p>
        </div>
        <div class="rounded-3xl border border-emerald-100 bg-white p-5 shadow-sm ring-1 ring-emerald-50">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Equipment Items</p>
            <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $equipmentItems->count() }}</p>
        </div>
        <div class="rounded-3xl border border-emerald-100 bg-white p-5 shadow-sm ring-1 ring-emerald-50">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Availability Status</p>
            <p class="mt-3 text-3xl font-semibold text-emerald-700">Live</p>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('supply-office.final-approval') }}" class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm transition hover:bg-amber-100">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Review Queue</p>
            <p class="mt-3 text-2xl font-semibold text-slate-900">{{ $pendingFinalApprovalCount }}</p>
            <p class="mt-2 text-sm text-slate-600">Pending final approval requests</p>
        </a>
        <a href="{{ route('supply-office.users') }}" class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm transition hover:bg-emerald-100">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Users</p>
            <p class="mt-3 text-2xl font-semibold text-slate-900">{{ \App\Models\User::count() }}</p>
            <p class="mt-2 text-sm text-slate-600">Manage accounts and roles</p>
        </a>
        <a href="{{ route('supply-office.usage-reports') }}" class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm transition hover:bg-emerald-100">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Reports</p>
            <p class="mt-3 text-2xl font-semibold text-slate-900">{{ $allRequests->count() }}</p>
            <p class="mt-2 text-sm text-slate-600">Usage and activity reports</p>
        </a>
        <a href="{{ route('supply-office.audit-logs') }}" class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm transition hover:bg-emerald-100">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Audit Logs</p>
            <p class="mt-3 text-2xl font-semibold text-slate-900">Latest</p>
            <p class="mt-2 text-sm text-slate-600">Review recent changes</p>
        </a>
    </div>

    <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200/50">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Supply Office Review Queue</h2>
                <p class="mt-1 text-sm text-slate-500">Requests that have already passed custodian review and are waiting for final supply office action.</p>
            </div>
            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">{{ $pendingFinalApprovalCount }} waiting</span>
        </div>

        <form method="GET" action="{{ route('supply-office.index') }}" class="mb-6 grid gap-3 md:grid-cols-4">
            <label class="md:col-span-2">
                <span class="sr-only">Search requests</span>
                <input type="search" name="search" value="{{ $searchQuery }}" placeholder="Search request number, activity, organization, venue, equipment..." class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            </label>
            <button type="submit" class="rounded-xl bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Advanced Filters</button>
            <a href="{{ route('supply-office.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-100">Clear</a>
        </form>

        @if($finalApprovalQueue->isEmpty())
            <div class="rounded-[28px] border border-dashed border-slate-300 bg-slate-50 p-6 text-center shadow-sm sm:p-8">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-700 mb-4">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-900">No requests are currently awaiting final supply office review.</h3>
                <p class="mt-2 text-sm text-slate-600">Requests will appear here when they're ready for your approval.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm text-slate-600">
                    <thead class="border-b border-slate-200 text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">Reference ID</th>
                            <th class="px-4 py-3 font-medium">Requestor</th>
                            <th class="px-4 py-3 font-medium">Department</th>
                            <th class="px-4 py-3 font-medium">Venue</th>
                            <th class="px-4 py-3 font-medium">Equipment</th>
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                @foreach($finalApprovalQueue as $request)
                    <tr class="cursor-pointer transition hover:bg-slate-50 focus:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-amber-500" data-request-url="{{ route('request.show', $request->id) }}" role="link" tabindex="0" aria-label="Open request details for {{ $request->control_number }}">
                        <td class="px-4 py-4 font-semibold text-slate-900">{{ $request->control_number }}</td>
                        <td class="px-4 py-4">{{ $request->requester?->name ?? 'Unknown' }}</td>
                        <td class="px-4 py-4">{{ $request->department ?? '—' }}</td>
                        <td class="px-4 py-4">{{ implode(', ', $request->getVenueNames()) ?: '—' }}</td>
                        <td class="px-4 py-4">{{ implode(', ', $request->getEquipmentItems()) ?: '—' }}</td>
                        <td class="px-4 py-4 whitespace-nowrap">{{ $request->start_date ? \Carbon\Carbon::parse($request->start_date)->format('M d, Y') : '—' }}</td>
                        <td class="px-4 py-4"><x-request-status-badge :request="$request" /></td>
                    </tr>
                @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if(auth()->user()?->isCustodian())
    <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200/50">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Venue Management</h2>
                <p class="mt-1 text-sm text-slate-500">Add, edit, and remove venue records using the existing venue fields.</p>
            </div>
            <form method="GET" action="{{ route('supply-office.index') }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <input type="text" name="venue_search" value="{{ $venueSearch }}" placeholder="Search venues or custodians" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm sm:w-56">
                <button type="submit" class="w-full rounded-xl bg-slate-700 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800 sm:w-auto">Search</button>
            </form>
        </div>

        <form method="POST" action="{{ route('supply-office.venues.store') }}" class="mb-6 grid grid-cols-1 gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-5">
            @csrf
            <input type="text" name="name" placeholder="Venue name" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
            <input type="number" name="capacity" placeholder="Capacity" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" min="1" required>
            <select name="custodian_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                <option value="">Assign custodian</option>
                @foreach($custodians as $custodian)
                    <option value="{{ $custodian->id }}">{{ $custodian->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Add Venue</button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm text-slate-600">
                <thead class="border-b border-slate-200 text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Venue Name</th>
                        <th class="px-4 py-3 font-medium">Capacity</th>
                        <th class="px-4 py-3 font-medium">Assigned Custodian</th>
                        <th class="px-4 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($venues as $venue)
                        <tr>
                            <td class="px-4 py-4 font-medium text-slate-900">{{ $venue->name }}</td>
                            <td class="px-4 py-4">{{ $venue->capacity ?? '—' }}</td>
                            <td class="px-4 py-4">{{ $venue->custodian?->name ?? '—' }}</td>
                            <td class="px-4 py-4 text-right">
                                <a href="{{ route('supply-office.index', ['edit_venue' => $venue->id]) }}" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                                <form method="POST" action="{{ route('supply-office.venues.destroy', $venue) }}" class="inline-block" data-swal-confirm data-swal-title="Delete this venue?" data-swal-text="This action removes the venue record from the administration list." data-swal-confirm-text="Yes, delete it" data-swal-confirm-color="#dc2626">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 hover:bg-red-100">Delete</button>
                                </form>
                            </td>
                        </tr>

                        @if($editVenueId === $venue->id)
                            <tr>
                                <td colspan="4" class="px-4 py-4 bg-slate-50">
                                    <form method="POST" action="{{ route('supply-office.venues.update', $venue) }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name" value="{{ old('name', $venue->name) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                                        <input type="number" name="capacity" value="{{ old('capacity', $venue->capacity) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" min="1" required>
                                        <select name="custodian_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                                            <option value="">Assign custodian</option>
                                            @foreach($custodians as $custodian)
                                                <option value="{{ $custodian->id }}" {{ (old('custodian_id', $venue->custodian_id) == $custodian->id) ? 'selected' : '' }}>{{ $custodian->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Save Venue</button>
                                        <a href="{{ route('supply-office.index') }}" class="rounded-xl border border-slate-300 px-3 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-100">Cancel</a>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="4" class="px-4 py-12 text-center text-sm text-slate-500">No venues found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200/50">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Equipment Management</h2>
                <p class="mt-1 text-sm text-slate-500">Track equipment quantity, availability, and custodial assignments.</p>
            </div>
            <form method="GET" action="{{ route('supply-office.index') }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <input type="text" name="equipment_search" value="{{ $equipmentSearch }}" placeholder="Search equipment or custodians" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm sm:w-56">
                <button type="submit" class="w-full rounded-xl bg-slate-700 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800 sm:w-auto">Search</button>
            </form>
        </div>

        <form method="POST" action="{{ route('supply-office.equipment.store') }}" class="mb-6 grid grid-cols-1 gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-6">
            @csrf
            <input type="text" name="name" placeholder="Equipment name" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
            <input type="number" name="quantity" placeholder="Capacity / Total" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" min="1" required>
            <input type="number" name="quantity_available" placeholder="Available" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" min="0">
            <select name="custodian_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                <option value="">Assign custodian</option>
                @foreach($custodians as $custodian)
                    <option value="{{ $custodian->id }}">{{ $custodian->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Add Equipment</button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm text-slate-600">
                <thead class="border-b border-slate-200 text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Equipment Name</th>
                        <th class="px-4 py-3 font-medium">Capacity</th>
                        <th class="px-4 py-3 font-medium">Available</th>
                        <th class="px-4 py-3 font-medium">Assigned Custodian</th>
                        <th class="px-4 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($equipmentItems as $equipment)
                        <tr>
                            <td class="px-4 py-4 font-medium text-slate-900">{{ $equipment->name }}</td>
                            <td class="px-4 py-4">{{ $equipment->quantity }}</td>
                            <td class="px-4 py-4">{{ $equipment->quantity_available }}</td>
                            <td class="px-4 py-4">{{ $equipment->custodian?->name ?? '—' }}</td>
                            <td class="px-4 py-4 text-right">
                                <a href="{{ route('supply-office.index', ['edit_equipment' => $equipment->id]) }}" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                                <form method="POST" action="{{ route('supply-office.equipment.destroy', $equipment) }}" class="inline-block" data-swal-confirm data-swal-title="Delete this equipment item?" data-swal-text="This action removes the equipment record from inventory management." data-swal-confirm-text="Yes, delete it" data-swal-confirm-color="#dc2626">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 hover:bg-red-100">Delete</button>
                                </form>
                            </td>
                        </tr>

                        @if($editEquipmentId === $equipment->id)
                            <tr>
                                <td colspan="5" class="px-4 py-4 bg-slate-50">
                                    <form method="POST" action="{{ route('supply-office.equipment.update', $equipment) }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name" value="{{ old('name', $equipment->name) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                                        <input type="number" name="quantity" value="{{ old('quantity', $equipment->quantity) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" min="1" required>
                                        <input type="number" name="quantity_available" value="{{ old('quantity_available', $equipment->quantity_available) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" min="0">
                                        <select name="custodian_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                                            <option value="">Assign custodian</option>
                                            @foreach($custodians as $custodian)
                                                <option value="{{ $custodian->id }}" {{ (old('custodian_id', $equipment->custodian_id) == $custodian->id) ? 'selected' : '' }}>{{ $custodian->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="rounded-xl bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-700">Save Equipment</button>
                                        <a href="{{ route('supply-office.index') }}" class="rounded-xl border border-slate-300 px-3 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-100">Cancel</a>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-sm text-slate-500">No equipment found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
<script>
    document.querySelectorAll('[data-request-url]').forEach((requestTarget) => {
        requestTarget.addEventListener('click', (event) => {
            if (event.target.closest('a, button, form, input, select, textarea')) return;
            window.location.href = requestTarget.dataset.requestUrl;
        });

        requestTarget.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            window.location.href = requestTarget.dataset.requestUrl;
        });
    });
</script>
@endsection
