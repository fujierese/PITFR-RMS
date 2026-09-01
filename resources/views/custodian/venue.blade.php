@extends('layouts.app')

@section('title', 'Venue Management')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="space-y-6">
        <!-- Header -->
        <section class="rounded-3xl bg-gradient-to-r from-slate-900 to-indigo-700 p-4 shadow-xl ring-1 ring-slate-200/10 text-white sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <span class="inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-200">
                        Venue Management
                    </span>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight">Manage Your Venues</h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-300">Add, edit, or manage the status of your assigned venues.</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-white/15 px-4 py-2 text-sm font-semibold text-slate-100 ring-1 ring-white/10">
                    {{ $venues->count() }} {{ $venues->count() === 1 ? 'venue assigned' : 'venues assigned' }}
                </span>
            </div>
        </section>

        <!-- Venues List -->
        <section class="rounded-3xl bg-white shadow-xl ring-1 ring-slate-200/50 overflow-hidden">
            <div class="border-b border-slate-200/80 px-4 py-5 sm:px-6 sm:py-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-900">Your Venues</h2>
                        <p class="mt-1 text-sm text-slate-500">View and manage your assigned venues.</p>
                    </div>
                    <a href="{{ route('custodian.index') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 sm:w-auto">
                        ← Back to Dashboard
                    </a>
                </div>
            </div>

            <div class="space-y-6 p-4 sm:p-6">
                <!-- Venues Table -->
                @if($venues->isEmpty())
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/50 p-8 text-center">
                        <p class="text-sm text-slate-600">No venues assigned to you yet.</p>
                        <p class="mt-1 text-xs text-slate-500">Venues added by administrators will appear here.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50">
                                    <th class="px-4 py-3 text-left font-semibold text-slate-900">Venue Name</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-900">Capacity</th>
                                    <th class="px-4 py-3 text-center font-semibold text-slate-900">Status</th>
                                    <th class="px-4 py-3 text-right font-semibold text-slate-900">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @foreach($venues as $venue)
                                    <tr class="transition hover:bg-slate-50">
                                        <td class="px-4 py-4 font-medium text-slate-900">{{ $venue->name }}</td>
                                        <td class="px-4 py-4 text-slate-600">
                                            @if($venue->capacity)
                                                {{ number_format($venue->capacity) }} capacity
                                            @else
                                                <span class="text-slate-400">Not specified</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            @if($venue->is_active)
                                                <span class="inline-flex items-center rounded-full bg-green-50 px-3 py-1 text-sm font-medium text-green-700 ring-1 ring-green-600/10">
                                                    Active
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-red-50 px-3 py-1 text-sm font-medium text-red-700 ring-1 ring-red-600/10">
                                                    Disabled
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <!-- Edit Form -->
                                                <form method="GET" action="#" class="inline" x-data="{ open: false }">
                                                    <button type="button" @click="open = !open" class="rounded-lg bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                                        Edit
                                                    </button>
                                                    
                                                    <div x-show="open" @click.outside="open = false" class="absolute right-0 z-10 mt-2 w-80 rounded-lg bg-white shadow-xl ring-1 ring-slate-200/50 p-6">
                                                        <h4 class="text-sm font-semibold text-slate-900 mb-4">Edit Venue</h4>
                                                        <form method="POST" action="{{ route('custodian.venues.update', $venue) }}" class="space-y-4">
                                                            @csrf
                                                            @method('PUT')
                                                            <div class="flex flex-col gap-1">
                                                                <label class="text-sm font-medium text-slate-700">Venue Name</label>
                                                                <input type="text" name="name" value="{{ $venue->name }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" required>
                                                            </div>
                                                            <div class="flex flex-col gap-1">
                                                                <label class="text-sm font-medium text-slate-700">Capacity</label>
                                                                <input type="number" name="capacity" value="{{ $venue->capacity }}" min="1" class="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                                            </div>
                                                            <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                                                                Save Changes
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                                </form>

                                                <!-- Toggle Status Form -->
                                                <form method="POST" action="{{ route('custodian.venues.toggle', $venue) }}" class="inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="rounded-lg px-3 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-offset-2 @if($venue->is_active) bg-red-50 text-red-700 hover:bg-red-100 focus:ring-red-500 @else bg-green-50 text-green-700 hover:bg-green-100 focus:ring-green-500 @endif">
                                                        @if($venue->is_active)
                                                            Disable
                                                        @else
                                                            Enable
                                                        @endif
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </div>
</div>

@push('scripts')
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush
@endsection
