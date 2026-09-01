@extends('layouts.app')

@section('title', 'Equipment Management')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="space-y-6">
        <!-- Header -->
        <section class="rounded-3xl bg-gradient-to-r from-slate-900 to-indigo-700 p-4 shadow-xl ring-1 ring-slate-200/10 text-white sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <span class="inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-200">
                        Equipment Management
                    </span>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight">Manage Your Equipment</h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-300">Add, edit, or manage the status of your assigned equipment.</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-white/15 px-4 py-2 text-sm font-semibold text-slate-100 ring-1 ring-white/10">
                    {{ $equipment->count() }} {{ $equipment->count() === 1 ? 'item assigned' : 'items assigned' }}
                </span>
            </div>
        </section>

        <!-- Equipment List -->
        <section class="rounded-3xl bg-white shadow-xl ring-1 ring-slate-200/50 overflow-hidden">
            <div class="border-b border-slate-200/80 px-4 py-5 sm:px-6 sm:py-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-900">Your Equipment</h2>
                        <p class="mt-1 text-sm text-slate-500">View and manage your assigned equipment.</p>
                    </div>
                    <a href="{{ route('custodian.index') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 sm:w-auto">
                        ← Back to Dashboard
                    </a>
                </div>
            </div>

            <div class="space-y-6 p-4 sm:p-6">
                <!-- Equipment Table -->
                @if($equipment->isEmpty())
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/50 p-8 text-center">
                        <p class="text-sm text-slate-600">No equipment assigned to you yet.</p>
                        <p class="mt-1 text-xs text-slate-500">Equipment added by administrators will appear here.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50">
                                    <th class="px-4 py-3 text-left font-semibold text-slate-900">Equipment Name</th>
                                    <th class="px-4 py-3 text-center font-semibold text-slate-900">Total Qty</th>
                                    <th class="px-4 py-3 text-center font-semibold text-slate-900">Available</th>
                                    <th class="px-4 py-3 text-center font-semibold text-slate-900">Status</th>
                                    <th class="px-4 py-3 text-right font-semibold text-slate-900">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @foreach($equipment as $item)
                                    <tr class="transition hover:bg-slate-50" x-data="{ open: false, issueOpen_{{ $item->id }}: false, returnOpen_{{ $item->id }}: false }">
                                        <td class="px-4 py-4 font-medium text-slate-900">{{ $item->name }}</td>
                                        <td class="px-4 py-4 text-center text-slate-600">{{ $item->quantity }}</td>
                                        <td class="px-4 py-4 text-center text-slate-600">{{ $item->quantity_available }}</td>
                                        <td class="px-4 py-4 text-center">
                                            @if($item->is_active)
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
                                                <form method="POST" action="{{ route('custodian.equipment.update', $item) }}" class="inline" x-data="{ open: false }">
                                                    @csrf
                                                    @method('PUT')
                                                    <button type="button" @click="open = !open" class="rounded-lg bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                                        Edit
                                                    </button>
                                                    
                                                    <div x-show="open" @click.outside="open = false" class="absolute right-0 z-10 mt-2 w-96 rounded-lg bg-white shadow-xl ring-1 ring-slate-200/50 p-6">
                                                        <h4 class="text-sm font-semibold text-slate-900 mb-4">Edit Equipment</h4>
                                                        <div class="space-y-4">
                                                            <div class="flex flex-col gap-1">
                                                                <label class="text-sm font-medium text-slate-700">Equipment Name</label>
                                                                <input type="text" name="name" value="{{ $item->name }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" required>
                                                            </div>
                                                            <div class="flex flex-col gap-1">
                                                                <label class="text-sm font-medium text-slate-700">Total Quantity</label>
                                                                <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" class="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" required>
                                                            </div>
                                                            <div class="flex flex-col gap-1">
                                                                <label class="text-sm font-medium text-slate-700">Available Quantity</label>
                                                                <input type="number" name="quantity_available" value="{{ $item->quantity_available }}" min="0" class="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" required>
                                                            </div>
                                                            <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                                                                Save Changes
                                                            </button>
                                                        </div>
                                                    </div>
                                                </form>

                                                <!-- Toggle Status Form -->
                                                <form method="POST" action="{{ route('custodian.equipment.toggle', $item) }}" class="inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="rounded-lg px-3 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-offset-2 @if($item->is_active) bg-red-50 text-red-700 hover:bg-red-100 focus:ring-red-500 @else bg-green-50 text-green-700 hover:bg-green-100 focus:ring-green-500 @endif">
                                                        @if($item->is_active)
                                                            Disable
                                                        @else
                                                            Enable
                                                        @endif
                                                    </button>
                                                </form>

                                                <!-- Report Issue Button -->
                                                <button type="button" @click="issueOpen_{{ $item->id }} = !issueOpen_{{ $item->id }}" class="rounded-lg bg-orange-50 px-3 py-2 text-sm font-medium text-orange-700 transition hover:bg-orange-100 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
                                                    Report Issue
                                                </button>

                                                <!-- Return Equipment Button -->
                                                <button type="button" @click="returnOpen_{{ $item->id }} = !returnOpen_{{ $item->id }}" class="rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                                                    Return Equipment
                                                </button>

                                                <!-- Report Issue Modal -->
                                                <div x-show="issueOpen_{{ $item->id }}" @click.outside="issueOpen_{{ $item->id }} = false" class="absolute right-0 z-10 mt-2 w-96 rounded-lg bg-white shadow-xl ring-1 ring-slate-200/50 p-6">
                                                    <h4 class="text-sm font-semibold text-slate-900 mb-4">Report Equipment Issue</h4>
                                                    <form method="POST" action="{{ route('custodian.equipment.report-issue', $item) }}" class="space-y-4">
                                                        @csrf
                                                        <div class="flex flex-col gap-1">
                                                            <label class="text-sm font-medium text-slate-700">Equipment</label>
                                                            <p class="text-sm text-slate-600">{{ $item->name }}</p>
                                                        </div>
                                                        <div class="flex flex-col gap-1">
                                                            <label for="issue_type_{{ $item->id }}" class="text-sm font-medium text-slate-700">Issue Type</label>
                                                            <select id="issue_type_{{ $item->id }}" name="issue_type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" required>
                                                                <option value="">-- Select Issue Type --</option>
                                                                <option value="damaged">Damaged</option>
                                                                <option value="lost">Lost/Missing</option>
                                                                <option value="non_functional">Non-functional</option>
                                                                <option value="other">Other</option>
                                                            </select>
                                                        </div>
                                                        <div class="flex flex-col gap-1">
                                                            <label for="quantity_affected_{{ $item->id }}" class="text-sm font-medium text-slate-700">Quantity Affected</label>
                                                            <input type="number" id="quantity_affected_{{ $item->id }}" name="quantity_affected" value="1" min="1" max="{{ $item->quantity }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" required>
                                                        </div>
                                                        <div class="flex flex-col gap-1">
                                                            <label for="description_{{ $item->id }}" class="text-sm font-medium text-slate-700">Description/Remarks</label>
                                                            <textarea id="description_{{ $item->id }}" name="description" rows="3" placeholder="Describe the issue..." class="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"></textarea>
                                                        </div>
                                                        <button type="submit" class="w-full rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-700">
                                                            Submit Report
                                                        </button>
                                                    </form>
                                                </div>

                                                <!-- Return Equipment Modal -->
                                                <div x-show="returnOpen_{{ $item->id }}" @click.outside="returnOpen_{{ $item->id }} = false" class="absolute right-0 z-10 mt-2 w-96 rounded-lg bg-white shadow-xl ring-1 ring-slate-200/50 p-6">
                                                    <h4 class="text-sm font-semibold text-slate-900 mb-4">Return Equipment</h4>
                                                    <form method="POST" action="{{ route('custodian.equipment.return', $item) }}" class="space-y-4">
                                                        @csrf
                                                        <div class="flex flex-col gap-1">
                                                            <label class="text-sm font-medium text-slate-700">Equipment</label>
                                                            <p class="text-sm text-slate-600">{{ $item->name }}</p>
                                                        </div>
                                                        <div class="flex flex-col gap-1">
                                                            <label class="text-sm font-medium text-slate-700">Available to Return</label>
                                                            <p class="text-sm text-slate-600">{{ $item->quantity - $item->quantity_available }} items</p>
                                                        </div>
                                                        <div class="flex flex-col gap-1">
                                                            <label for="quantity_returned_{{ $item->id }}" class="text-sm font-medium text-slate-700">Quantity Returned</label>
                                                            <input type="number" id="quantity_returned_{{ $item->id }}" name="quantity_returned" value="1" min="1" max="{{ $item->quantity - $item->quantity_available }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" required>
                                                        </div>
                                                        <div class="flex flex-col gap-1">
                                                            <label for="condition_{{ $item->id }}" class="text-sm font-medium text-slate-700">Equipment Condition</label>
                                                            <select id="condition_{{ $item->id }}" name="condition" class="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" required>
                                                                <option value="">-- Select Condition --</option>
                                                                <option value="good">Good</option>
                                                                <option value="acceptable">Acceptable</option>
                                                                <option value="poor">Poor</option>
                                                            </select>
                                                        </div>
                                                        <div class="flex flex-col gap-1">
                                                            <label for="remarks_{{ $item->id }}" class="text-sm font-medium text-slate-700">Remarks</label>
                                                            <textarea id="remarks_{{ $item->id }}" name="remarks" rows="3" placeholder="Any additional remarks..." class="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"></textarea>
                                                        </div>
                                                        <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                                                            Confirm Return
                                                        </button>
                                                    </form>
                                                </div>
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
