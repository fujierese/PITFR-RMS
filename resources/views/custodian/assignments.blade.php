@extends('layouts.app')

@section('title', 'My Assignments')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="space-y-6">
        <x-page-header
            eyebrow="Resource management"
            title="My Assignments"
            :description="'A quick overview of the venues and equipment you are responsible for today.'"
        >
            <x-slot:actions>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700">Assigned to {{ $user->name }}</span>
            </x-slot:actions>
        </x-page-header>

        <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">

            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @if($custodianType === 'venue')
                    <div class="rounded-3xl bg-white/10 p-4 border border-white/10 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">Assigned Venues</p>
                        <p class="mt-3 text-3xl font-semibold text-white">{{ $venues->count() }}</p>
                        <p class="mt-2 text-sm text-slate-300">{{ $venues->count() === 1 ? 'venue assigned' : 'venues assigned' }}</p>
                    </div>
                @elseif($custodianType === 'equipment')
                    <div class="rounded-3xl bg-white/10 p-4 border border-white/10 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">Assigned Equipment</p>
                        <p class="mt-3 text-3xl font-semibold text-white">{{ $equipment->count() }}</p>
                        <p class="mt-2 text-sm text-slate-300">{{ $equipment->count() === 1 ? 'item assigned' : 'items assigned' }}</p>
                    </div>
                @else
                    <div class="rounded-3xl bg-white/10 p-4 border border-white/10 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">Assigned Venues</p>
                        <p class="mt-3 text-3xl font-semibold text-white">{{ $venues->count() }}</p>
                        <p class="mt-2 text-sm text-slate-300">{{ $venues->count() === 1 ? 'venue assigned' : 'venues assigned' }}</p>
                    </div>
                    <div class="rounded-3xl bg-white/10 p-4 border border-white/10 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">Assigned Equipment</p>
                        <p class="mt-3 text-3xl font-semibold text-white">{{ $equipment->count() }}</p>
                        <p class="mt-2 text-sm text-slate-300">{{ $equipment->count() === 1 ? 'item assigned' : 'items assigned' }}</p>
                    </div>
                    <div class="rounded-3xl bg-white/10 p-4 border border-white/10 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">Total Assignments</p>
                        <p class="mt-3 text-3xl font-semibold text-white">{{ $venues->count() + $equipment->count() }}</p>
                        <p class="mt-2 text-sm text-slate-300">All active custodian resources</p>
                    </div>
                @endif
            </div>

            <div class="mt-6 rounded-3xl bg-slate-950/40 p-4 ring-1 ring-white/10">
                @if($custodianType === 'venue')
                    <p class="text-sm text-slate-200">Assigned venue: <span class="font-semibold text-white">{{ $venues->pluck('name')->join(', ') ?: 'None' }}</span></p>
                @elseif($custodianType === 'equipment')
                    <p class="text-sm text-slate-200">Assigned equipment: <span class="font-semibold text-white">{{ $equipment->pluck('name')->join(', ') ?: 'None' }}</span></p>
                @else
                    <p class="text-sm text-slate-200">Assigned resources: <span class="font-semibold text-white">{{ $venues->pluck('name')->merge($equipment->pluck('name'))->join(', ') ?: 'None' }}</span></p>
                @endif
            </div>
        </section>

        <section class="rounded-3xl bg-white shadow-xl ring-1 ring-slate-200/50 overflow-hidden">
            <div class="border-b border-slate-200/80 px-4 py-5 sm:px-6 sm:py-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-900">Assigned Resources</h2>
                        <p class="mt-1 text-sm text-slate-500">Browse your current assignments and review resource details.</p>
                    </div>
                    <a href="{{ route('custodian.index') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 sm:w-auto">
                        ← Back to Dashboard
                    </a>
                </div>
            </div>

            <div class="space-y-10 p-4 sm:p-6">
                @if($custodianType === 'venue' || empty($custodianType))
                    <div>
                        <div class="flex items-center justify-between gap-4">
                            <h3 class="text-lg font-semibold text-slate-900">Assigned Venues</h3>
                            <span class="text-sm font-medium text-slate-500">{{ $venues->count() }} total</span>
                        </div>

                        @if($venues->isEmpty())
                            <p class="mt-4 text-sm text-slate-500">No venues assigned to you.</p>
                        @else
                            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($venues as $venue)
                                    <div class="group rounded-3xl border border-slate-200 bg-slate-50 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <h4 class="text-lg font-semibold text-slate-900">{{ $venue->name }}</h4>
                                                <p class="mt-2 text-sm text-slate-600">ID: {{ $venue->id }}</p>
                                            </div>
                                            <x-status-badge :status="$venue->is_active ? 'active' : 'inactive'" :label="$venue->is_active ? 'Enabled' : 'Disabled'" />
                                        </div>
                                        @if($venue->description)
                                            <p class="mt-4 text-sm leading-6 text-slate-600">{{ $venue->description }}</p>
                                        @endif
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <form method="POST" action="{{ route('custodian.venues.toggle', $venue) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700">{{ $venue->is_active ? 'Disable' : 'Enable' }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('custodian.venues.update', $venue) }}" class="flex flex-wrap gap-2">
                                                @csrf
                                                @method('PUT')
                                                <input type="text" name="name" value="{{ $venue->name }}" class="w-40 rounded-xl border border-slate-300 px-2 py-1 text-xs" required>
                                                <input type="number" name="capacity" value="{{ $venue->capacity }}" min="1" class="w-24 rounded-xl border border-slate-300 px-2 py-1 text-xs">
                                                <button type="submit" class="rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white">Save</button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                @if($custodianType === 'equipment' || empty($custodianType))
                    <div>
                        <div class="flex items-center justify-between gap-4">
                            <h3 class="text-lg font-semibold text-slate-900">Assigned Equipment</h3>
                            <span class="text-sm font-medium text-slate-500">{{ $equipment->count() }} total</span>
                        </div>

                        @if($equipment->isEmpty())
                            <p class="mt-4 text-sm text-slate-500">No equipment assigned to you.</p>
                        @else
                            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($equipment as $item)
                                    <div class="group rounded-3xl border border-slate-200 bg-slate-50 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <h4 class="text-lg font-semibold text-slate-900">{{ $item->name }}</h4>
                                                <p class="mt-2 text-sm text-slate-600">ID: {{ $item->id }}</p>
                                            </div>
                                            <x-status-badge :status="$item->is_active ? 'active' : 'inactive'" :label="$item->is_active ? 'Enabled' : 'Disabled'" />
                                        </div>
                                        <div class="mt-4 space-y-2 text-sm text-slate-600">
                                            <p>Available: <span class="font-semibold text-slate-900">{{ $item->quantity_available }} / {{ $item->quantity }}</span></p>
                                            @if($item->description)
                                                <p>{{ $item->description }}</p>
                                            @endif
                                        </div>
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <form method="POST" action="{{ route('custodian.equipment.toggle', $item) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700">{{ $item->is_active ? 'Disable' : 'Enable' }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('custodian.equipment.update', $item) }}" class="flex flex-wrap gap-2">
                                                @csrf
                                                @method('PUT')
                                                <input type="text" name="name" value="{{ $item->name }}" class="w-36 rounded-xl border border-slate-300 px-2 py-1 text-xs" required>
                                                <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" class="w-20 rounded-xl border border-slate-300 px-2 py-1 text-xs" required>
                                                <input type="number" name="quantity_available" value="{{ $item->quantity_available }}" min="0" class="w-20 rounded-xl border border-slate-300 px-2 py-1 text-xs" required>
                                                <button type="submit" class="rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white">Save</button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection