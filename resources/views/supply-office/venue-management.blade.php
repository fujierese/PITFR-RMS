@extends('layouts.app')
@section('title', 'Venue Management')

@section('content')
<div class="space-y-6">
    <div class="rounded-3xl p-4 text-white shadow-xl sm:p-6" style="background: linear-gradient(115deg, #065f46 0%, #047857 52%, #064e3b 100%); color: #fff;">
        <p class="text-xs font-semibold uppercase tracking-[0.18em]" style="color: #d1fae5;">Supply Office</p>
        <h1 class="mt-2 text-2xl font-bold sm:text-3xl" style="color: #fff;">Venue Management</h1>
        <p class="mt-2 text-sm" style="color: #ecfdf5;">Add venues and assign each one to a Venue Custodian.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200/50">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Venues</h2>
                <p class="mt-1 text-sm text-slate-500">Manage venue details and custodial assignments.</p>
            </div>
            <form method="GET" action="{{ route('supply-office.venues.index') }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <input type="search" name="search" value="{{ $search }}" placeholder="Search venues or custodians" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm sm:w-56">
                <button type="submit" class="w-full rounded-xl bg-slate-700 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800 sm:w-auto">Search</button>
            </form>
        </div>

        <form method="POST" action="{{ route('supply-office.venues.store') }}" class="mb-6 grid grid-cols-1 gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            <input type="text" name="name" value="{{ old('name') }}" placeholder="Venue name" maxlength="200" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
            <input type="number" name="capacity" value="{{ old('capacity') }}" placeholder="Capacity" min="1" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
            <select name="custodian_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                <option value="">Assign Venue Custodian</option>
                @foreach($venueCustodians as $custodian)
                    <option value="{{ $custodian->id }}" @selected(old('custodian_id') == $custodian->id)>{{ $custodian->name }} — {{ $custodian->role_label }}</option>
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
                        <th class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($venues as $venue)
                        <tr>
                            <td class="px-4 py-4 font-medium text-slate-900">{{ $venue->name }}</td>
                            <td class="px-4 py-4">{{ $venue->capacity ?? '—' }}</td>
                            <td class="px-4 py-4">{{ $venue->custodian?->name ?? '—' }}</td>
                            <td class="px-4 py-4 text-right">
                                <a href="{{ route('supply-office.venues.index', ['edit_venue' => $venue->id]) }}" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                                <form method="POST" action="{{ route('supply-office.venues.destroy', $venue) }}" class="inline-block" data-swal-confirm data-swal-title="Delete this venue?" data-swal-text="This action removes the venue record from the administration list." data-swal-confirm-text="Yes, delete it" data-swal-confirm-color="#dc2626">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 hover:bg-red-100">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @if($editVenueId === $venue->id)
                            <tr>
                                <td colspan="4" class="bg-slate-50 px-4 py-4">
                                    <form method="POST" action="{{ route('supply-office.venues.update', $venue) }}" class="space-y-4">
                                        @csrf
                                        @method('PUT')
                                        <div>
                                            <h3 class="text-sm font-semibold text-slate-900">Editing: {{ $venue->name }}</h3>
                                            <p class="mt-1 text-xs text-slate-500">Update the venue details and its assigned custodian.</p>
                                        </div>
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                            <label class="block text-xs font-medium text-slate-600">Venue name
                                                <input type="text" name="name" value="{{ old('name', $venue->name) }}" maxlength="200" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900" required>
                                            </label>
                                            <label class="block text-xs font-medium text-slate-600">Capacity
                                                <input type="number" name="capacity" value="{{ old('capacity', $venue->capacity) }}" min="1" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900">
                                            </label>
                                            <label class="block text-xs font-medium text-slate-600">Assigned venue custodian
                                                <select name="custodian_id" class="mt-1 w-full min-w-0 rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900" required>
                                                    <option value="">Choose a venue custodian</option>
                                                    @foreach($venueCustodians as $custodian)
                                                        <option value="{{ $custodian->id }}" @selected(old('custodian_id', $venue->custodian_id) == $custodian->id)>{{ $custodian->name }} — {{ $custodian->role_label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                        </div>
                                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                            <a href="{{ route('supply-office.venues.index') }}" class="rounded-xl border border-slate-300 bg-white px-5 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-100">Cancel</a>
                                            <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Save changes</button>
                                        </div>
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
</div>
@endsection
