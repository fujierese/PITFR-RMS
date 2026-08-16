@php
    $currentUser = Auth::user();
    $requestClassification = 'Standard Request';
    $classificationTone = 'emerald';
    $urgentRequested = old('is_emergency') ? true : false;

    if ($currentUser?->isFacilityAdministrator()) {
        $requestClassification = 'Institutional Priority';
        $classificationTone = 'amber';
    } elseif ($currentUser?->isFaculty() || in_array($currentUser?->role, ['faculty', 'staff', 'office_staff'], true)) {
        $requestClassification = 'Institutional Priority';
        $classificationTone = 'amber';
    } elseif ($currentUser?->isOutsider() || $currentUser?->requestor_type === 'outsider' || $currentUser?->role === 'outsider') {
        $requestClassification = 'External Request';
        $classificationTone = 'sky';
    }
@endphp
<form method="POST" action="{{ route('requestor.store') }}" id="request-form" enctype="multipart/form-data" data-show-loading="true" data-equipment-availability-url="{{ route('equipment.availability') }}" data-conflict-check-url="{{ route('calendar.check-conflicts') }}" data-is-student="{{ ($currentUser->requestor_type ?? null) === 'student' ? '1' : '0' }}" data-venue-capacities="{{ htmlspecialchars(json_encode($venueCapacityMap ?? []), ENT_QUOTES, 'UTF-8') }}">
    @csrf

<div class="mx-auto w-full max-w-none overflow-hidden rounded-none border-0 bg-slate-950/90 shadow-none backdrop-blur-xl md:mx-auto md:max-w-7xl md:rounded-[40px] md:border md:border-white/10 md:shadow-[0_60px_120px_rgba(15,23,42,0.55)]">
    <section class="bg-white p-4 sm:p-6 lg:p-8">
        <div class="mx-auto w-full max-w-none md:max-w-6xl">
            <div class="mb-6 flex flex-col gap-3 rounded-[24px] border border-slate-200 bg-slate-50 px-4 py-5 shadow-sm sm:flex-row sm:items-start sm:justify-between sm:px-6 md:mb-8 lg:px-8">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.32em] text-emerald-600">Official request form</p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-950">Request for the Use of Facility and Equipment</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Please complete all required fields before submitting your request. Fields marked with (*) are required.</p>
                </div>
                <div class="flex items-center sm:justify-end">
                    <span id="draft-status" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm">
                        <span class="h-2 w-2 rounded-full bg-amber-400 animate-pulse"></span>
                        Draft autosave
                    </span>
                </div>
            </div>

            <div class="mb-6">
                <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 mb-3">Request Progress</p>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-emerald-700">Step 1</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Request Details</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">Step 2</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Venue & Schedule</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">Step 3</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Equipment</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">Step 4</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Review & Submit</p>
                    </div>
                </div>
            </div>

            <div class="rounded-[24px] border border-slate-200 bg-slate-50 p-4 shadow-sm sm:p-6 md:rounded-[28px] md:p-8 lg:p-10">
                <div class="space-y-8">

                    @if ($errors->any())
                <div role="alert" aria-live="assertive" aria-label="Validation errors" class="mb-6 rounded-3xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 shadow-sm">
                    <div class="flex gap-3">
                        <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <div class="flex-1">
                            <p class="font-semibold">Please fix the errors below before submitting.</p>
                            <ul class="mt-3 list-disc pl-5 space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <div class="space-y-9">
                <section class="rounded-[24px] border border-slate-200 bg-slate-50 p-4 shadow-sm sm:p-6 md:rounded-[44px] md:p-8 lg:p-10">
                    <div class="grid gap-4 grid-cols-1 md:grid-cols-3 md:gap-6">
                        <div class="flex items-center justify-center rounded-[24px] border border-slate-200 bg-white p-4">
                            <img src="{{ asset('images/PIT-LOGO.png') }}" alt="PIT Logo" class="h-20 w-20 rounded-full border border-slate-200 bg-slate-100 object-cover p-1 shadow-sm">
                        </div>
                        <div class="space-y-2 rounded-[24px] border border-slate-200 bg-white p-4 text-center">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Official Request Form</p>
                            <p class="mt-1 text-base font-bold uppercase tracking-[0.06em] text-slate-800">Palompon Institute of Technology</p>
                            <p class="text-[11px] uppercase tracking-[0.16em] text-slate-500">Quality Management System</p>
                            <p class="mt-2 border-t border-slate-200 pt-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-700">Request for the Use of Facility/Equipment</p>
                        </div>
                        <div class="space-y-3 rounded-[24px] border border-slate-200 bg-white p-4 text-xs text-slate-600">
                            <p>Ref. Code: <span class="font-semibold text-slate-800">PIT-PSMO-F-05-3.7-08</span></p>
                            <p>Rev. No: <span class="font-semibold text-slate-800">00</span></p>
                            <div class="mt-2 rounded-2xl border border-slate-200 bg-slate-50 p-2 text-center text-xs shadow-sm">
                                <p class="font-semibold text-slate-800">TUV NORD</p>
                                <p class="text-slate-500">ISO 9001:2015 Certified</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-[32px] border border-slate-200 bg-white p-7 shadow-sm">
                    <div class="mb-5 border-b border-slate-200 pb-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Section I. Requestor Information</p>
                        <p class="mt-1 text-sm text-slate-500">Provide the requesting office and the reference details for this submission.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-6">
                        <div class="space-y-3">
                            <label for="department" class="block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Dept. / Requisitioning Office <span class="text-red-500">*</span></label>
                            <input id="department" type="text" name="department" value="{{ $currentUser?->department ?? old('department') }}" required class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:bg-white @error('department') border-red-300 bg-red-50 @enderror" aria-invalid="{{ $errors->has('department') ? 'true' : 'false' }}">
                            @error('department')
                                <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 sm:gap-5">
                            <div class="space-y-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Control Number</p>
                                <input type="text" value="{{ $controlNumber }}" readonly class="w-full rounded-3xl border border-slate-200 bg-slate-100 px-5 py-4 text-sm text-slate-600 cursor-not-allowed">
                            </div>
                            <div class="space-y-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Date Requested</p>
                                <input type="text" value="{{ now()->format('m/d/Y') }}" readonly class="w-full rounded-3xl border border-slate-200 bg-slate-100 px-5 py-4 text-sm text-slate-600 cursor-not-allowed">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-[32px] border border-slate-200 bg-white p-7 shadow-sm">
                    <div class="mb-5 border-b border-slate-200 pb-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Section II. Activity Details</p>
                        <p class="mt-1 text-sm text-slate-500">State the purpose of the activity, expected attendance, and requested dates and times.</p>
                    </div>
                    <div class="space-y-4 md:space-y-6">
                        <div>
                            <label for="name_of_activity" class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Activity / Purpose <span class="text-red-500">*</span></label>
                            <input id="name_of_activity" type="text" name="name_of_activity" required value="{{ old('name_of_activity') }}" class="mt-2 w-full rounded-3xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:bg-white @error('name_of_activity') border-red-300 bg-red-50 @enderror" aria-invalid="{{ $errors->has('name_of_activity') ? 'true' : 'false' }}">
                            @error('name_of_activity')
                                <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="grid gap-4 md:grid-cols-3 md:gap-5">
                            <div>
                                <label for="start_date" class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Start Date <span class="text-red-500">*</span></label>
                                <input id="start_date" type="date" name="start_date" required min="{{ now()->toDateString() }}" value="{{ old('start_date') }}" class="mt-2 w-full rounded-3xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:bg-white @error('start_date') border-red-300 bg-red-50 @enderror" aria-invalid="{{ $errors->has('start_date') ? 'true' : 'false' }}">
                                @error('start_date')
                                    <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="end_date" class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">End Date (inclusive) <span class="text-red-500">*</span></label>
                                <input id="end_date" type="date" name="end_date" required min="{{ now()->toDateString() }}" value="{{ old('end_date') }}" class="mt-2 w-full rounded-3xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:bg-white @error('end_date') border-red-300 bg-red-50 @enderror" aria-invalid="{{ $errors->has('end_date') ? 'true' : 'false' }}">
                                @error('end_date')
                                    <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                                @enderror
                                <p id="overnight-hint" class="mt-2 text-xs text-emerald-600 hidden">Overnight booking detected: end date auto-updated to the next day.</p>
                            </div>
                            <div>
                                <label for="expected_participants" class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Expected No. of Participants <span class="text-red-500">*</span></label>
                                <input id="expected_participants" type="number" name="expected_participants" required min="1" value="{{ old('expected_participants') }}" class="mt-2 w-full rounded-3xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:bg-white @error('expected_participants') border-red-300 bg-red-50 @enderror" aria-invalid="{{ $errors->has('expected_participants') ? 'true' : 'false' }}">
                                @error('expected_participants')
                                    <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                                @enderror
                                <div id="capacity-warning-banner" role="status" aria-live="polite" class="mt-3 hidden rounded-lg border border-red-300 bg-red-50 px-3 py-3 text-sm text-red-900"></div>
                            </div>
                        </div>
                        <div class="reservation-duration-group mb-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Reservation Duration</p>
                            <div class="mt-3 flex flex-col gap-3 sm:flex-row">
                                <label class="reservation-duration-option flex items-center gap-2 rounded-2xl border border-emerald-500 bg-emerald-50 px-3 py-2 text-sm font-medium text-slate-900 shadow-sm">
                                    <input type="radio" name="reservation_duration" value="specific_time" checked>
                                    <span>Specific Time</span>
                                </label>
                                <label class="reservation-duration-option flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                    <input type="radio" name="reservation_duration" value="whole_day">
                                    <span>Whole Day</span>
                                    <span class="text-xs text-slate-500">08:00 AM – 12:00 AM</span>
                                </label>
                            </div>
                            <p class="reservation-duration-helper mt-3 text-xs text-emerald-700" aria-live="polite">Whole Day uses 8:00 AM–12:00 AM for each selected date.</p>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2 md:gap-5">
                            <div>
                                <label for="start_time" class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Start Time <span class="text-red-500">*</span></label>
                                <input id="start_time" type="time" name="start_time" required value="{{ old('start_time') }}" class="mt-2 w-full rounded-3xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:bg-white @error('start_time') border-red-300 bg-red-50 @enderror" aria-invalid="{{ $errors->has('start_time') ? 'true' : 'false' }}">
                                @error('start_time')
                                    <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="end_time" class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">End Time <span class="text-red-500">*</span></label>
                                <input id="end_time" type="time" name="end_time" required value="{{ old('end_time') }}" class="mt-2 w-full rounded-3xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:bg-white @error('end_time') border-red-300 bg-red-50 @enderror" aria-invalid="{{ $errors->has('end_time') ? 'true' : 'false' }}">
                                @error('end_time')
                                    <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-[32px] border border-slate-200 bg-white p-7 shadow-sm">
                    <div class="mb-5 border-b border-slate-200 pb-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Section III. Facility Selection</p>
                                <p class="mt-1 text-sm text-slate-500">Select the facility / venue requested for the activity.</p>
                            </div>
                            <p class="text-xs text-slate-500">Choose one</p>
                        </div>
                    </div>
                    <div class="mb-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                        <p class="font-semibold text-slate-800">External Requestor Guidance</p>
                        <p class="mt-1">Applicable rental fees and payment procedures</p>
                    </div>
                    <div class="mt-4 space-y-3">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach(['Conference Hall & Interaction Center (CHIC)', 'Gymnasium', 'Balay Alumni', 'Oval Grounds', 'Covered Court', 'Volleyball Court', 'Others (specify)'] as $v)
                                <label class="venue-option flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 transition hover:border-emerald-300 hover:bg-emerald-50/60 {{ in_array($v, (array) old('venue'), true) ? 'border-emerald-500 bg-emerald-50 shadow-sm' : '' }}">
                                    <input type="radio" name="venue" value="{{ $v }}"
                                           {{ in_array($v, (array) old('venue'), true) ? 'checked' : '' }}
                                           {{ $v === 'Others (specify)' ? 'data-other=venue-other' : '' }}
                                           required
                                           class="mt-0.5 h-4 w-4 rounded-full border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    <span class="flex-1 text-sm font-medium text-slate-700">{{ $v }}</span>
                                    <span class="hidden text-emerald-600">✓</span>
                                </label>
                            @endforeach
                        </div>
                        @error('venue')
                            <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                        <div id="venue-other-wrap" style="display:none" class="mt-2 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                            <label for="venue-other" class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Other Venue <span class="text-red-500">*</span></label>
                            <input type="text" name="other_venue" id="venue-other"
                                   value="{{ old('other_venue') }}"
                                   placeholder="Please specify venue"
                                   class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 focus:border-emerald-500 focus:outline-none @error('other_venue') border-red-300 bg-red-50 @enderror">
                            @error('other_venue')
                                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div id="venue-conflict-alert-wrap" class="mt-4"></div>
                    </div>
                </section>

                <section class="rounded-[32px] border border-slate-200 bg-white p-7 shadow-sm">
                    <div class="mb-5 border-b border-slate-200 pb-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Section IV. Equipment Request</p>
                                <p class="mt-1 text-sm text-slate-500">Indicate any equipment required for the activity and review availability.</p>
                            </div>
                            <p class="text-xs text-slate-500">Real-time availability</p>
                        </div>
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
                            $isSelected = in_array($itemName, old('equipment', []), true);
                        @endphp
                        <div class="equipment-row flex flex-col gap-3 rounded-3xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between {{ $itemAvailable > 0 ? 'hover:border-purple-400 hover:bg-purple-50/70' : 'border-red-100 bg-red-50/70 opacity-70' }} {{ $isSelected ? 'border-emerald-300 bg-emerald-50/60' : '' }}" data-available="{{ $itemAvailable }}" data-total="{{ $itemQty }}" data-name="{{ $itemName }}" data-index="{{ $loop->index }}">
                            <label class="flex flex-1 cursor-pointer items-center gap-3">
                                <input type="checkbox" name="equipment[]" value="{{ $itemName }}" {{ $itemAvailable <= 0 ? 'disabled' : '' }} {{ $isSelected ? 'checked' : '' }} class="equipment-checkbox h-4 w-4 rounded border-slate-300 text-purple-600" data-equipment="{{ $itemName }}">
                                <span class="text-sm font-medium text-slate-700">{{ $itemName }}</span>
                            </label>
                            <div class="flex flex-col items-start gap-2 sm:items-end sm:text-right">
                                <span class="availability-badge rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass }}">{{ $itemAvailable }} / {{ $itemQty }} available</span>
                                <div class="quantity-input-wrap {{ $isSelected ? '' : 'hidden' }}" id="qty-wrap-{{ $loop->index }}">
                                    <label class="mr-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">Qty</label>
                                    <input type="number" name="equipment_quantities[{{ $itemName }}]" min="1" max="{{ $itemAvailable }}" value="{{ old('equipment_quantities.'.$itemName, 1) }}" {{ $isSelected ? '' : 'disabled' }} class="w-full rounded-lg border border-slate-300 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500 sm:w-16">
                                </div>
                                <div class="equipment-utilization-card mt-2 hidden w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-left text-[11px] text-slate-600 sm:min-w-[220px]"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-[32px] border border-slate-200 bg-slate-50 p-7 shadow-sm">
                    <div class="mb-5 flex items-center justify-between gap-4 border-b border-slate-200 pb-4">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Selected Items Summary</p>
                            <p class="mt-1 text-sm text-slate-500">Review your selections before submitting.</p>
                        </div>
                        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.2em] text-emerald-700">Live</span>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">Activity</p>
                            <p id="summary-activity" class="mt-2 text-sm font-semibold text-slate-800">Not specified</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">Venue</p>
                            <p id="summary-venue" class="mt-2 text-sm font-semibold text-slate-800">Not selected</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">Date</p>
                            <p id="summary-date" class="mt-2 text-sm font-semibold text-slate-800">Not selected</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">Time</p>
                            <p id="summary-time" class="mt-2 text-sm font-semibold text-slate-800">Not selected</p>
                        </div>
                    </div>
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">Equipment</p>
                        <ul id="summary-equipment" class="mt-2 space-y-1 text-sm text-slate-700">
                            <li class="text-slate-500">No equipment selected</li>
                        </ul>
                    </div>
                </section>

                <section class="rounded-[32px] border border-slate-200 bg-slate-50 p-7 shadow-sm">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Name</p>
                            <input type="text" value="{{ $currentUser?->name ?? old('name', '') }}" readonly class="mt-2 w-full rounded-3xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 cursor-not-allowed">
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Position</p>
                            <input type="text" name="requested_by_position" required value="{{ old('requested_by_position', $currentUser?->position) }}" class="mt-2 w-full rounded-3xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:bg-white">
                        </div>
                    </div>
                </section>

                <section class="rounded-[32px] border border-slate-200 bg-white p-7 shadow-sm">
                    <div class="mb-5 border-b border-slate-200 pb-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Section VI. Request Classification</p>
                        <p class="mt-1 text-sm text-slate-500">Priority classification is determined during approval by the Custodian or Administrator. Requestors may only request urgent processing.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.2fr_0.8fr]">
                        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">Request Classification</p>
                            <div class="mt-4 flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-{{ $classificationTone === 'amber' ? 'amber' : ($classificationTone === 'sky' ? 'sky' : 'emerald') }}-100 text-{{ $classificationTone === 'amber' ? 'amber' : ($classificationTone === 'sky' ? 'sky' : 'emerald') }}-700">
                                    @if ($classificationTone === 'amber')
                                        <span class="text-lg">🟡</span>
                                    @elseif ($classificationTone === 'sky')
                                        <span class="text-lg">🔵</span>
                                    @else
                                        <span class="text-lg">🟢</span>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">Priority Level:</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-700">{{ $requestClassification }}</p>
                                </div>
                            </div>
                            <div id="urgent-request-indicator" class="mt-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 {{ $urgentRequested ? '' : 'hidden' }}">
                                <span class="font-semibold">🔴 Urgent Processing Requested</span>
                                <p class="mt-1 text-xs text-red-600">This is an indicator only and does not automatically change the request priority.</p>
                            </div>
                            <p class="mt-4 text-xs leading-5 text-slate-500">The final priority status remains under the authority of the Custodian or Administrator during the approval process.</p>
                        </div>
                        <div class="rounded-3xl border border-amber-200 bg-amber-50/80 p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700">Urgent Request</p>
                            <p class="mt-3 text-xs leading-5 text-slate-600">If your request requires immediate attention, you may request urgent processing. Final approval and priority classification remain subject to the Custodian or Administrator.</p>
                            <label class="mt-4 flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" name="is_emergency" id="urgent-processing-checkbox" value="1" {{ old('is_emergency') ? 'checked' : '' }} class="mt-1 h-4 w-4 rounded border-amber-300 text-amber-600">
                                <span class="text-sm font-semibold text-amber-800">Request Urgent Processing</span>
                            </label>
                            <div id="urgent-justification-wrap" class="mt-4 {{ old('is_emergency') ? '' : 'hidden' }} rounded-3xl border border-amber-200 bg-white p-3">
                                <label for="emergency-justification" class="text-xs font-semibold text-amber-700">Reason for Urgent Processing</label>
                                <textarea name="emergency_justification" id="emergency-justification" rows="3" class="mt-2 w-full rounded-2xl border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-amber-500" placeholder="Describe why this request requires urgent processing" {{ old('is_emergency') ? '' : 'disabled' }}>{{ old('emergency_justification') }}</textarea>
                                @error('emergency_justification')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-[32px] border border-slate-200 bg-white p-7 shadow-sm">
                    <div class="mb-5 border-b border-slate-200 pb-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Section VIII. Supporting Document</p>
                        <p class="mt-1 text-sm text-slate-500">Upload the activity proposal or supporting document required for this request.</p>
                    </div>
                    <label for="proposal_file" class="mb-3 block text-sm font-semibold text-slate-700">Upload activity proposal <span class="text-red-500">*</span></label>
                    <div class="rounded-[32px] border-2 border-dashed border-slate-300 bg-slate-50 px-8 pb-8 pt-8 transition hover:border-emerald-400">
                        <div class="space-y-3 text-center">
                            <svg class="mx-auto h-14 w-14 text-slate-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="flex flex-col items-center gap-1 text-sm text-slate-600 sm:flex-row sm:justify-center">
                                <label for="proposal_file" class="relative cursor-pointer rounded-full bg-white px-4 py-2 text-sm font-semibold text-emerald-600 shadow-sm hover:text-emerald-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-emerald-500">
                                    <span>Upload a file</span>
                                    <input id="proposal_file" name="proposal_file" type="file" accept=".pdf,.jpeg,.jpg,.png" class="sr-only">
                                </label>
                                <span>or drag and drop</span>
                            </div>
                            <p class="text-xs text-slate-500">PDF, JPEG, PNG up to 10MB</p>
                        </div>
                    </div>
                    <div id="file-preview" class="mt-3 hidden">
                        <p class="text-sm text-slate-600">Selected file: <span id="file-name" class="font-medium"></span></p>
                    </div>
                </section>

                <section class="rounded-[32px] border border-slate-200 bg-white p-7 shadow-sm">
                    <div class="mb-5 border-b border-slate-200 pb-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Submission checklist</p>
                        <p class="mt-1 text-sm text-slate-500">Please review the following before submitting your request.</p>
                    </div>
                    <div class="space-y-3">
                        <label class="flex items-start gap-3">
                            <input id="checklist-required-fields" type="checkbox" disabled class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600">
                            <span class="text-sm text-slate-700">All required fields are completed</span>
                        </label>
                        <label class="flex items-start gap-3">
                            <input id="checklist-venue-availability" type="checkbox" disabled class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600">
                            <span class="text-sm text-slate-700">Venue and equipment availability confirmed</span>
                        </label>
                        <label class="flex items-start gap-3">
                            <input id="checklist-document-upload" type="checkbox" disabled class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600">
                            <span class="text-sm text-slate-700">Activity proposal document uploaded</span>
                        </label>
                    </div>
                </section>

                <div class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <p class="text-sm leading-6 text-slate-600">I certify that the information provided is true, complete, and accurate. I understand that approval is subject to institutional policies, facility availability, and administrative review.</p>
                </div>

                <div class="flex flex-col-reverse gap-3 pt-2 md:flex-row md:items-center md:justify-end md:pt-4">
                    <a href="{{ route('requestor.index', ['tab' => 'dashboard']) }}" class="inline-flex items-center justify-center rounded-[20px] border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-[20px] bg-emerald-600 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-emerald-600/20 transition hover:bg-emerald-700 md:w-auto md:px-8">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Submit Request
                    </button>
                </div>
                </div>
            </div>
        </div>
    </section>
</div>
</form>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('request-form');
        if (!form) return;

        const durationInputs = document.querySelectorAll('input[name="reservation_duration"]');
        const startTimeInput = document.querySelector('input[name="start_time"]');
        const endTimeInput = document.querySelector('input[name="end_time"]');

        if (durationInputs.length && startTimeInput && endTimeInput) {
            const applyDurationState = () => {
                const duration = document.querySelector('input[name="reservation_duration"]:checked')?.value ?? 'specific_time';
                const isWholeDay = duration === 'whole_day';
                startTimeInput.disabled = isWholeDay;
                endTimeInput.disabled = isWholeDay;

                if (isWholeDay) {
                    startTimeInput.value = '08:00';
                    endTimeInput.value = '00:00';
                }
            };

            durationInputs.forEach((input) => input.addEventListener('change', applyDurationState));
            applyDurationState();
        }

        const summaryDetails = {
            activity: document.getElementById('summary-activity'),
            venue: document.getElementById('summary-venue'),
            date: document.getElementById('summary-date'),
            time: document.getElementById('summary-time'),
            equipment: document.getElementById('summary-equipment')
        };

        function formatDisplayDate(value) {
            if (!value) return 'Not selected';
            const date = new Date(`${value}T00:00:00`);
            if (Number.isNaN(date.getTime())) return value;
            return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' }).format(date);
        }

        function formatDisplayTime(value) {
            if (!value) return 'Not selected';
            const [hours, minutes] = value.split(':').map(Number);
            const safeHours = Number.isFinite(hours) ? hours : 0;
            const safeMinutes = Number.isFinite(minutes) ? minutes : 0;
            const date = new Date();
            date.setHours(safeHours, safeMinutes, 0, 0);
            return new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit' }).format(date);
        }

        function updateSummary() {
            const activityInput = form.querySelector('[name="name_of_activity"]');
            const selectedVenue = form.querySelector('input[name="venue"]:checked');
            const venueText = selectedVenue ? (selectedVenue.value === 'Others (specify)' ? (document.getElementById('venue-other')?.value.trim() || 'Other venue') : selectedVenue.value) : 'Not selected';
            const startDate = form.querySelector('[name="start_date"]')?.value;
            const endDate = form.querySelector('[name="end_date"]')?.value;
            const startTime = form.querySelector('[name="start_time"]')?.value;
            const endTime = form.querySelector('[name="end_time"]')?.value;

            if (summaryDetails.activity) summaryDetails.activity.textContent = activityInput?.value.trim() || 'Not specified';
            if (summaryDetails.venue) summaryDetails.venue.textContent = venueText;
            if (summaryDetails.date) summaryDetails.date.textContent = startDate && endDate ? `${formatDisplayDate(startDate)} – ${formatDisplayDate(endDate)}` : 'Not selected';
            if (summaryDetails.time) summaryDetails.time.textContent = startTime && endTime ? `${formatDisplayTime(startTime)} – ${formatDisplayTime(endTime)}` : 'Not selected';

            const selectedEquipment = [...form.querySelectorAll('.equipment-row')].filter((row) => {
                const checkbox = row.querySelector('.equipment-checkbox');
                return checkbox && checkbox.checked;
            }).map((row) => {
                const name = row.dataset.name || 'Equipment';
                const qtyInput = row.querySelector('input[type="number"]');
                const qty = qtyInput && qtyInput.value ? Number(qtyInput.value) : 1;
                return `${name} × ${qty}`;
            });

            if (summaryDetails.equipment) {
                if (selectedEquipment.length) {
                    summaryDetails.equipment.innerHTML = selectedEquipment.map((item) => `<li class="text-sm text-slate-700">${item}</li>`).join('');
                } else {
                    summaryDetails.equipment.innerHTML = '<li class="text-sm text-slate-500">No equipment selected</li>';
                }
            }
        }

        const venueInputs = form.querySelectorAll('input[name="venue"]');
        venueInputs.forEach((input) => input.addEventListener('change', function () {
            const otherWrap = document.getElementById('venue-other-wrap');
            if (otherWrap) {
                otherWrap.style.display = input.value === 'Others (specify)' && input.checked ? 'block' : 'none';
            }
            updateSummary();
        }));

        const otherVenueInput = document.getElementById('venue-other');
        if (otherVenueInput) {
            otherVenueInput.addEventListener('input', updateSummary);
        }

        form.querySelectorAll('input, textarea, select').forEach((field) => {
            field.addEventListener('input', updateSummary);
            field.addEventListener('change', updateSummary);
        });

        const checklistRequiredFields = document.getElementById('checklist-required-fields');
        const checklistVenueAvailability = document.getElementById('checklist-venue-availability');
        const checklistDocumentUpload = document.getElementById('checklist-document-upload');

        const updateChecklistState = () => {
            const requiredFields = [
                form.querySelector('[name="department"]'),
                form.querySelector('[name="name_of_activity"]'),
                form.querySelector('[name="start_date"]'),
                form.querySelector('[name="end_date"]'),
                form.querySelector('[name="expected_participants"]'),
                form.querySelector('[name="start_time"]'),
                form.querySelector('[name="end_time"]'),
                form.querySelector('[name="requested_by_position"]'),
                form.querySelector('[name="venue"]:checked')
            ];

            const allRequiredComplete = requiredFields.every((field) => {
                if (!field) return true;
                if (field.type === 'radio' || field.type === 'checkbox') {
                    return field.checked;
                }
                return String(field.value).trim() !== '';
            });

            const selectedVenue = form.querySelector('[name="venue"]:checked');
            const hasEquipmentSelected = form.querySelectorAll('.equipment-checkbox:checked').length > 0;
            const hasFileSelected = form.querySelector('[name="proposal_file"]')?.files?.length > 0;

            if (checklistRequiredFields) {
                checklistRequiredFields.checked = allRequiredComplete;
            }

            if (checklistVenueAvailability) {
                checklistVenueAvailability.checked = Boolean(selectedVenue) && (hasEquipmentSelected || !selectedVenue || selectedVenue.value !== '');
            }

            if (checklistDocumentUpload) {
                checklistDocumentUpload.checked = hasFileSelected;
            }
        };

        const venueSelectionState = () => {
            venueInputs.forEach((input) => {
                const label = input.closest('.venue-option');
                if (!label) return;
                const isSelected = input.checked;
                label.classList.toggle('border-emerald-500', isSelected);
                label.classList.toggle('bg-emerald-50', isSelected);
                label.classList.toggle('shadow-sm', isSelected);
                const checkmark = label.querySelector('span:last-child');
                if (checkmark) {
                    checkmark.classList.toggle('hidden', !isSelected);
                    checkmark.classList.toggle('inline', isSelected);
                }
            });
        };

        form.querySelectorAll('input, textarea, select').forEach((field) => {
            field.addEventListener('input', () => {
                updateSummary();
                updateChecklistState();
            });
            field.addEventListener('change', () => {
                updateSummary();
                updateChecklistState();
            });
        });

        venueInputs.forEach((input) => input.addEventListener('change', venueSelectionState));
        venueSelectionState();
        updateSummary();
        updateChecklistState();
    });
</script>
