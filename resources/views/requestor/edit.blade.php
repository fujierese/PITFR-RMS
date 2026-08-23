@extends('layouts.app')
@section('title', 'Edit Request')

@section('content')
@php
    $startTimeValue = old('start_time', $request->start_time);
    $endTimeValue = old('end_time', $request->end_time);
    $isWholeDay = old('reservation_duration') === 'whole_day'
        || (($startTimeValue === '00:00' || $startTimeValue === '00:00:00') && ($endTimeValue === '23:59' || $endTimeValue === '23:59:00'));
    $isNeedsReschedule = $request->status === 'needs_reschedule'
        || $request->venue_status === 'needs_reschedule'
        || $request->equipment_status === 'needs_reschedule';

    if (!empty($startTimeValue)) {
        try {
            $startTimeValue = \Carbon\Carbon::parse($startTimeValue)->format('H:i');
        } catch (\Exception $e) {
            $startTimeValue = (string) $startTimeValue;
        }
    }

    if (!empty($endTimeValue)) {
        try {
            $endTimeValue = \Carbon\Carbon::parse($endTimeValue)->format('H:i');
        } catch (\Exception $e) {
            $endTimeValue = (string) $endTimeValue;
        }
    }
@endphp
<div class="mx-auto w-full max-w-none px-3 py-4 sm:px-4 sm:py-6 lg:max-w-5xl lg:px-6 lg:py-8">
    <div class="overflow-hidden rounded-[24px] bg-white shadow-xl ring-1 ring-slate-200/60 md:rounded-3xl">
        <div class="border-b border-slate-200 bg-slate-50 px-4 py-4 sm:px-6 sm:py-5">
            <h1 class="text-xl font-semibold text-slate-900 sm:text-2xl">{{ $isNeedsReschedule ? 'Reschedule Request' : 'Edit Pending Request' }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ $isNeedsReschedule ? 'Update the scheduling details so the request can re-enter the approval workflow.' : 'Update the request details while the status is still pending.' }}</p>
        </div>

        <form id="request-form" method="POST" action="{{ route('requestor.update', $request->id) }}" class="space-y-4 p-3 sm:space-y-6 sm:p-6" enctype="multipart/form-data" data-equipment-availability-url="{{ route('equipment.availability') }}" data-conflict-check-url="{{ route('calendar.check-conflicts') }}" data-is-student="{{ ($user->requestor_type ?? null) === 'student' ? '1' : '0' }}" data-exclude-request-id="{{ $request->id }}" data-is-needs-reschedule="{{ $isNeedsReschedule ? '1' : '0' }}" data-venue-capacities="{{ htmlspecialchars(json_encode($venueCapacityMap ?? []), ENT_QUOTES, 'UTF-8') }}">
            @csrf
            @method('PUT')

            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <h2 class="text-lg font-semibold text-slate-900">Request Information</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Activity Title</label>
                        <input type="text" name="name_of_activity" value="{{ old('name_of_activity', $request->name_of_activity) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2" {{ $isNeedsReschedule ? 'readonly' : 'required' }}>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Department</label>
                        <input type="text" name="department" value="{{ old('department', $request->department) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2" {{ $isNeedsReschedule ? 'readonly' : 'required' }}>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Start Date</label>
                        <input type="date" name="start_date" value="{{ old('start_date', $request->start_date?->format('Y-m-d')) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2" required>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">End Date</label>
                        <input type="date" name="end_date" value="{{ old('end_date', $request->end_date?->format('Y-m-d')) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Expected Participants</label>
                        <input type="number" name="expected_participants" min="1" value="{{ old('expected_participants', $request->expected_participants) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2" required>
                        <div id="capacity-warning-banner" role="status" aria-live="polite" class="mt-3 hidden rounded-lg border border-red-300 bg-red-50 px-3 py-3 text-sm text-red-900"></div>
                    </div>
                    <div class="reservation-duration-group md:col-span-2">
                        <label class="text-sm font-medium text-slate-700">Reservation Duration</label>
                        <div class="mt-2 flex flex-col gap-3 sm:flex-row">
                            <label class="reservation-duration-option flex items-center gap-2 rounded-2xl border border-emerald-500 bg-emerald-50 px-3 py-2 text-sm font-medium text-slate-900 shadow-sm">
                                <input type="radio" name="reservation_duration" value="specific_time" @checked(!$isWholeDay)>
                                <span>Specific Time</span>
                            </label>
                            <label class="reservation-duration-option flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                <input type="radio" name="reservation_duration" value="whole_day" @checked($isWholeDay)>
                                <span>Whole Day</span>
                                <span class="text-xs text-slate-500">12:00 AM – 11:59 PM</span>
                            </label>
                        </div>
                        <p class="reservation-duration-helper mt-3 text-xs text-emerald-700" aria-live="polite">Whole Day uses 12:00 AM–11:59 PM for each selected date.</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Start Time</label>
                        <input type="time" name="start_time" value="{{ old('start_time', $startTimeValue) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2" required>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">End Time</label>
                        <input type="time" name="end_time" value="{{ old('end_time', $endTimeValue) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2" required>
                    </div>
                </div>
            </div>

            <div class="rounded-[24px] border border-slate-200 bg-white p-4 sm:rounded-3xl sm:p-5">
                <h2 class="text-lg font-semibold text-slate-900">Venue and Equipment</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Venue</label>
                        <select name="venue" class="mt-1 min-h-[3rem] w-full rounded-2xl border border-slate-200 px-3 py-2" required>
                            <option value="" disabled {{ old('venue', $request->getVenueNames()[0] ?? '') === '' ? 'selected' : '' }}>Choose a venue</option>
                            @foreach(array_merge($venueOptions, ['Others (specify)']) as $venue)
                                <option value="{{ $venue }}" {{ old('venue', $request->getVenueNames()[0] ?? '') === $venue ? 'selected' : '' }}>{{ $venue }}</option>
                            @endforeach
                        </select>
                        <div id="venue-conflict-alert-wrap" class="mt-3"></div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <label class="text-sm font-medium text-slate-700">Equipment</label>
                                <p class="mt-1 text-sm text-slate-500">Select the equipment you need and enter the quantity.</p>
                            </div>
                            <p class="text-xs text-slate-500">Real-time availability</p>
                        </div>
                        <div class="mt-4 space-y-3">
                            @php
                                $equipmentList = $equipment instanceof \Illuminate\Support\Collection ? $equipment->all() : $equipment;
                                $equipmentItems = [];
                                if (!empty($equipmentList)) {
                                    foreach ($equipmentList as $eq) {
                                        $equipmentItems[] = $eq;
                                    }
                                } else {
                                    $equipmentItems = collect([
                                        ['name' => 'Sound System', 'quantity' => 1, 'quantity_available' => 1],
                                        ['name' => 'Microphones', 'quantity' => 2, 'quantity_available' => 2],
                                        ['name' => 'Canopies', 'quantity' => 3, 'quantity_available' => 3],
                                        ['name' => 'Industrial Fans', 'quantity' => 4, 'quantity_available' => 4],
                                        ['name' => 'Iwata Cooler Fans', 'quantity' => 2, 'quantity_available' => 2],
                                        ['name' => 'Tables', 'quantity' => 10, 'quantity_available' => 10],
                                        ['name' => 'Monobloc chairs', 'quantity' => 50, 'quantity_available' => 50],
                                    ]);
                                }
                            @endphp
                            @foreach($equipmentItems as $eq)
                                @php
                                    $itemName = is_object($eq) ? $eq->name : ($eq['name'] ?? '');
                                    $itemQty = is_object($eq) ? ($eq->quantity ?? 0) : ($eq['quantity'] ?? 0);
                                    $itemAvailable = max(0, (int) (is_object($eq) ? ($eq->quantity_available ?? $itemQty) : ($eq['quantity_available'] ?? $itemQty)));
                                    $badgeClass = $itemAvailable <= 0 ? 'bg-red-100 text-red-700' : ($itemAvailable == $itemQty ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700');
                                    $selected = in_array($itemName, old('equipment', $request->getEquipmentItems()), true);
                                    $savedQuantity = (int) old('equipment_quantities.' . $itemName, $request->getEquipmentQuantities()[$itemName] ?? 1);
                                    $maxAllowed = (int) ($equipmentQuantityLimits[$itemName] ?? $itemAvailable);
                                @endphp
                                <div class="flex flex-col gap-3 rounded-3xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between equipment-row {{ $itemAvailable > 0 ? 'hover:border-purple-400 hover:bg-purple-50/70' : 'border-red-100 bg-red-50/70 opacity-70' }}" data-available="{{ $itemAvailable }}" data-total="{{ $itemQty }}" data-name="{{ $itemName }}" data-index="{{ $loop->index }}" data-max-quantity="{{ $maxAllowed }}">
                                    <label class="flex items-center gap-3 cursor-pointer sm:flex-1">
                                        <input type="checkbox" name="equipment[]" value="{{ $itemName }}" {{ $itemAvailable <= 0 ? 'disabled' : '' }} {{ $selected ? 'checked' : '' }} class="h-4 w-4 rounded border-slate-300 text-purple-600 equipment-checkbox" data-equipment="{{ $itemName }}">
                                        <span class="text-sm font-medium text-slate-700">{{ $itemName }}</span>
                                    </label>
                                    <div class="flex flex-col items-start gap-2 sm:items-end sm:text-right">
                                        <span class="availability-badge rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass }}">{{ $itemAvailable <= 0 ? 'No units available.' : ($itemAvailable . ' / ' . $itemQty . ' available') }}</span>
                                        @if($selected)
                                            <div class="quantity-input-wrap" id="qty-wrap-{{ $loop->index }}" style="display:block;">
                                                <input type="number" name="equipment_quantities[{{ $itemName }}]" min="1" max="{{ $maxAllowed }}" value="{{ $savedQuantity }}" class="w-full rounded-lg border border-slate-300 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500 sm:w-20">
                                            </div>
                                        @else
                                            <div class="quantity-input-wrap" id="qty-wrap-{{ $loop->index }}" style="display:none;">
                                                <input type="number" name="equipment_quantities[{{ $itemName }}]" min="1" max="{{ $maxAllowed }}" value="{{ $savedQuantity }}" disabled class="w-full rounded-lg border border-slate-300 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500 sm:w-20">
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="mt-4 hidden">
                    <div id="summary-venue"></div>
                    <div id="summary-equipment"></div>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <h2 class="text-lg font-semibold text-slate-900">Requester Information</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Requested By</label>
                        <input type="text" value="{{ $user->name }}" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2" disabled>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Position</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input id="saved-position-field" type="text" value="{{ old('position', $request->requester?->position ?? '') }}" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-slate-700" readonly>
                            @if(!$isNeedsReschedule)
                                <button type="button" id="edit-position-button" class="rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</button>
                            @endif
                        </div>
                        <input type="hidden" name="position" id="position-input" value="{{ old('position', $request->requester?->position ?? '') }}">
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('request.show', $request->id) }}" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 sm:w-auto">Cancel</a>
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white sm:w-auto">Save Changes</button>
            </div>
        </form>
    </div>
</div>@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('request-form');
    const savedPositionField = document.getElementById('saved-position-field');
    const positionInput = document.getElementById('position-input');
    const editPositionButton = document.getElementById('edit-position-button');

    if (!savedPositionField || !positionInput || !editPositionButton || !form) {
        return;
    }

    const isNeedsReschedule = form.getAttribute('data-is-needs-reschedule') === '1';
    if (isNeedsReschedule) {
        savedPositionField.readOnly = true;
        savedPositionField.classList.add('bg-slate-50');
        savedPositionField.classList.remove('bg-white');
        if (editPositionButton) {
            editPositionButton.style.display = 'none';
        }
        return;
    }

    const makeEditable = function () {
        savedPositionField.readOnly = false;
        savedPositionField.classList.remove('bg-slate-50');
        savedPositionField.classList.add('bg-white');
        savedPositionField.focus();
        editPositionButton.textContent = 'Lock';
        editPositionButton.classList.add('bg-slate-900', 'text-white');
    };

    const lockEditable = function () {
        savedPositionField.readOnly = true;
        savedPositionField.classList.add('bg-slate-50');
        savedPositionField.classList.remove('bg-white');
        editPositionButton.textContent = 'Edit';
        editPositionButton.classList.remove('bg-slate-900', 'text-white');
        positionInput.value = savedPositionField.value;
    };

    editPositionButton.addEventListener('click', function () {
        if (savedPositionField.readOnly) {
            makeEditable();
            return;
        }

        lockEditable();
    });

    savedPositionField.addEventListener('input', function () {
        positionInput.value = savedPositionField.value;
    });
});
</script>
@endsection
