@php
    $currentUser = Auth::user();
    $colleges = $colleges ?? collect();
    $departments = $departments ?? collect();
    $studentOrganizations = $studentOrganizations ?? collect();
    $profileCollegeId = $profileCollegeId ?? null;
    $profileDepartmentId = $profileDepartmentId ?? null;
    $effectiveRequestorType = $currentUser?->requestor_type ?? ($requestorType ?? null);
    $venueDefaultEquipmentMap = $venueDefaultEquipmentMap ?? [];
    $venueEquipmentVisibilityMap = $venueEquipmentVisibilityMap ?? [];
    $selectedVenues = (array) old('venue', []);
    $oldVenueEquipment = (array) old('venue_equipment', []);
    $oldVenueEquipmentQuantities = (array) old('venue_equipment_quantities', []);
    $requestClassification = 'Standard Request';
    $classificationTone = 'emerald';
    $urgentRequested = old('is_emergency') ? true : false;

    if ($currentUser?->isFacilityAdministrator()) {
        $requestClassification = 'Institutional Priority';
        $classificationTone = 'amber';
    } elseif ($currentUser?->isFaculty() || $currentUser?->role === 'faculty') {
        $requestClassification = 'Institutional Priority';
        $classificationTone = 'amber';
    } elseif ($currentUser?->isOutsider() || $currentUser?->requestor_type === 'outsider' || $currentUser?->role === 'outsider') {
        $requestClassification = 'Outside Organization Request';
        $classificationTone = 'sky';
    }

    $isStudent = $effectiveRequestorType === 'student';
    $isFaculty = $effectiveRequestorType === 'faculty' || ($currentUser?->role ?? null) === 'faculty';
    $isExternal = in_array($effectiveRequestorType, ['outsider'], true);
    $selectedCollegeId = old('college_id', $profileCollegeId ?? null);
    $selectedDepartmentId = old('department_id', $profileDepartmentId ?? null);
    $positionOptions = ['Student', 'Faculty', 'Staff', 'Instructor', 'Professor', 'Department Chair', 'Coordinator', 'Office Staff', 'External Partner', 'Student Organization', 'Other'];
    $savedPosition = old('requested_by_position', $currentUser?->position);
    $selectedPosition = in_array($savedPosition, $positionOptions, true) ? $savedPosition : ($savedPosition ? 'Other' : '');
    $otherPosition = old('requested_by_position_other', $selectedPosition === 'Other' ? $savedPosition : '');
    $profileCollege = $colleges->firstWhere('id', $profileCollegeId);
    $profileDepartment = $departments->firstWhere('id', $profileDepartmentId);
    $activeStudentOrganization = $currentUser && $currentUser->isStudent() ? $currentUser->studentOrganizations()->first() : null;
    $trustedOrganizationDisplay = $activeStudentOrganization?->name ?? $currentUser?->office_or_organization ?? 'Organization not provided';
@endphp
<style>
    .required-asterisk {
        color: #dc2626;
        font-weight: 800;
        font-size: 1rem;
        line-height: 1;
        vertical-align: middle;
    }

    .venue-option [data-venue-schedule] {
        display: none;
    }

    .venue-option:has(> label input[name="venue[]"]:checked) [data-venue-schedule] {
        display: block;
    }
</style>

<form method="POST" action="{{ route('requestor.store') }}" id="request-form" enctype="multipart/form-data" data-swal-confirm data-swal-title="Submit this request?" data-swal-text="Please confirm that the request details are complete and correct." data-swal-icon="question" data-swal-confirm-text="Yes, submit request" data-swal-confirm-color="#059669" data-equipment-availability-url="{{ route('equipment.availability') }}" data-venue-availability-url="{{ route('requestor.venue.availability') }}" data-is-student="{{ ($currentUser->requestor_type ?? null) === 'student' ? '1' : '0' }}" data-requestor-type="{{ $currentUser->requestor_type ?? '' }}" data-has-saved-signature="{{ $currentUser?->e_signature_file ? '1' : '0' }}" data-venue-capacities="{{ json_encode($venueCapacityMap ?? []) }}" data-venue-default-equipment="{{ json_encode($venueDefaultEquipmentMap ?? []) }}">
    @csrf

<div class="mx-auto w-full max-w-none overflow-hidden rounded-none border-0 bg-slate-950/90 shadow-none backdrop-blur-xl md:mx-auto md:max-w-7xl md:rounded-[40px] md:border md:border-white/10 md:shadow-[0_60px_120px_rgba(15,23,42,0.55)]">
    <section class="bg-white p-4 sm:p-6 lg:p-8">
        <div class="mx-auto w-full max-w-none md:max-w-6xl">
            <div class="mb-6 flex flex-col gap-3 rounded-[24px] border border-slate-200 bg-slate-50 px-4 py-5 shadow-sm sm:flex-row sm:items-start sm:justify-between sm:px-6 md:mb-8 lg:px-8">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.32em] text-emerald-600">Official request form</p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-950">Request for the Use of Facility and Equipment</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Please complete all required fields before submitting your request. Fields marked with <span class="required-asterisk">*</span> are required.</p>
                </div>
                <div class="flex shrink-0 items-center sm:justify-end">
                    <span id="draft-status" class="inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 shadow-sm ring-1 ring-amber-200">
                        <span data-draft-status-indicator class="h-2.5 w-2.5 shrink-0 rounded-full bg-amber-500 animate-pulse"></span>
                        <span data-draft-status-label>Draft autosave</span>
                    </span>
                </div>
            </div>

            <div class="mb-6">
                <p class="mb-3 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Request Submission Steps</p>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-emerald-700">Step 1</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Request Details</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">Step 2</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Choose Venue & Schedule</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">Step 3</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Select Equipment</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">Step 4</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Review & Submit</p>
                    </div>
                </div>
                <p class="mt-3 text-xs leading-5 text-slate-600">
                    After submission, the Supply Office checks availability first. If available, the request goes to the relevant custodian(s) for review, then returns to the Supply Office for the final decision. Calendar availability is not a confirmation.
                </p>
            </div>

            <div class="mb-6 rounded-[24px] border border-cyan-200 bg-cyan-50 p-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-cyan-100 text-cyan-700 shadow-sm">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M12 16v-4M12 8h.01"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-lg font-bold text-cyan-900">Need help? Start here.</p>
                        <ul class="mt-2 space-y-1 text-base text-cyan-800">
                            <li>• Enter your details, then choose the venue, date, and time you need.</li>
                            <li>• Select any equipment you need for your activity.</li>
                            <li>• Upload clear PDF, JPG, or PNG files smaller than 10MB.</li>
                        </ul>
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
                            <div class="mt-2 flex min-h-14 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 p-2 shadow-sm">
                                <img src="{{ asset('images/TUVNORD-Logo.png') }}" alt="TUV NORD ISO 9001:2015 Certified" class="max-h-12 w-auto max-w-full object-contain">
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
                        @if ($isExternal)
                            <div class="space-y-3">
                                <label for="organization_name" class="block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Organization Name <span class="required-asterisk">*</span></label>
                                <input id="organization_name" type="text" name="organization_name" value="{{ old('organization_name', $currentUser?->office_or_organization) }}" readonly required class="w-full rounded-3xl border border-slate-200 bg-slate-100 px-5 py-4 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:bg-white @error('organization_name') border-red-300 bg-red-50 @enderror">
                                <p class="text-xs text-slate-500">This is your registered organization. If it is missing or incorrect, update your account details before submitting.</p>
                                @error('organization_name')
                                    <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @elseif ($isStudent)
                            <div class="space-y-3">
                                <p class="block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Student Organization</p>
                                <div class="rounded-3xl border border-slate-200 bg-slate-100 px-5 py-4 text-sm text-slate-600" aria-readonly="true">{{ $trustedOrganizationDisplay }}</div>
                                <input type="hidden" name="organization_name" value="{{ old('organization_name', $trustedOrganizationDisplay) }}">
                                <input type="hidden" name="student_organization_id" value="{{ old('student_organization_id', $activeStudentOrganization?->id) }}">
                                <input type="hidden" name="college_id" value="{{ $profileCollegeId }}">
                                <input type="hidden" name="department_id" value="{{ $profileDepartmentId }}">
                                @error('department_id')
                                    <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @else
                            <div class="space-y-3">
                                <p class="block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">College</p>
                                <div class="rounded-3xl border border-slate-200 bg-slate-100 px-5 py-4 text-sm text-slate-600" aria-readonly="true">{{ $profileCollege?->name ?? 'College not provided' }}</div>
                                <input type="hidden" name="college_id" value="{{ $profileCollegeId }}">
                                @error('college_id')
                                    <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="space-y-3">
                                <p class="block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Department / Requisitioning Office</p>
                                <div class="rounded-3xl border border-slate-200 bg-slate-100 px-5 py-4 text-sm text-slate-600" aria-readonly="true">{{ $profileDepartment?->name ?? $currentUser?->department ?? 'Department not provided' }}</div>
                                <input type="hidden" name="department_id" value="{{ $profileDepartmentId }}">
                                @error('department_id')
                                    <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
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
                            <label for="name_of_activity" class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Activity Name <span class="required-asterisk">*</span></label>
                            <input id="name_of_activity" type="text" name="name_of_activity" required value="{{ old('name_of_activity') }}" class="mt-2 w-full rounded-3xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:bg-white @error('name_of_activity') border-red-300 bg-red-50 @enderror" aria-invalid="{{ $errors->has('name_of_activity') ? 'true' : 'false' }}">
                            @error('name_of_activity')
                                <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="purpose" class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Purpose <span class="required-asterisk">*</span></label>
                            <textarea id="purpose" name="purpose" rows="3" required class="mt-2 w-full rounded-3xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:bg-white @error('purpose') border-red-300 bg-red-50 @enderror">{{ old('purpose') }}</textarea>
                            @error('purpose')
                                <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                                <label for="expected_participants" class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Expected No. of Participants <span class="required-asterisk">*</span></label>
                                <input id="expected_participants" type="number" name="expected_participants" required min="1" value="{{ old('expected_participants') }}" class="mt-2 w-full rounded-3xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:bg-white @error('expected_participants') border-red-300 bg-red-50 @enderror" aria-invalid="{{ $errors->has('expected_participants') ? 'true' : 'false' }}">
                                @error('expected_participants')
                                    <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                                @enderror
                                <div id="capacity-warning-banner" role="status" aria-live="polite" class="mt-3 hidden rounded-lg border border-red-300 bg-red-50 px-3 py-3 text-sm text-red-900"></div>
                        </div>
                    </div>
                </section>

                <section class="rounded-[32px] border border-slate-200 bg-white p-7 shadow-sm">
                    <div class="mb-5 border-b border-slate-200 pb-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Section III. Venue</p>
                                <p class="mt-1 text-sm text-slate-500">Select every facility / venue needed for the activity.</p>
                            </div>
                            <p class="text-xs text-slate-500">Choose one or more</p>
                        </div>
                    </div>
                            @if ($isExternal)
                                <div class="mb-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                                    <p class="font-semibold text-slate-800">Outsider Guidance</p>
                                    <p class="mt-1">Applicable rental fees and payment procedures</p>
                                </div>
                            @elseif ($isStudent)
                                <div id="student-personal-guidance" class="mb-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                                    <p class="font-semibold text-slate-800">Personal request guidance</p>
                                    <p class="mt-1">Personal requests require the applicable payment receipt.</p>
                                </div>
                            @endif
                    <div class="mt-4 space-y-3">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach($venueOptions as $v)
                                @php
                                    $venueCapacity = $venueCapacityMap[$v] ?? null;
                                    $venueScheduleOld = old('venue_schedules', [])[$v] ?? [];
                                    $isVenueSelected = in_array($v, $selectedVenues, true);
                                    $venueCalendarUrl = route('calendar.embed', array_filter([
                                        'date' => $venueScheduleOld['start_date'] ?? now()->toDateString(),
                                        'venue' => $v,
                                        'select' => 1,
                                    ]));
                                @endphp
                                <div class="venue-option rounded-2xl border border-slate-200 bg-slate-50 p-3 transition hover:border-emerald-300 hover:bg-emerald-50/60 {{ $isVenueSelected ? 'border-emerald-500 bg-emerald-50 shadow-sm' : '' }}" data-venue-option data-venue-name="{{ $v }}">
                                    <label class="flex cursor-pointer items-start gap-3">
                                        <input type="checkbox" name="venue[]" value="{{ $v }}"
                                               {{ $isVenueSelected ? 'checked' : '' }}
                                               class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                        <span class="flex-1 text-sm font-medium text-slate-700">{{ $v }}</span>
                                        <span class="shrink-0 text-xs text-slate-500">{{ $venueCapacity !== null ? $venueCapacity . ' people' : 'Capacity not set' }}</span>
                                        <span class="venue-checkmark {{ $isVenueSelected ? '' : 'hidden' }} text-emerald-600">✓</span>
                                    </label>
                                    <div class="venue-schedule mt-4 {{ $isVenueSelected ? '' : 'hidden' }}" data-venue-schedule data-venue-name="{{ $v }}">
                                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3">
                                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">Reservation Schedule</p>
                                            <button type="button" data-open-venue-calendar data-calendar-url="{{ $venueCalendarUrl }}" data-venue-name="{{ $v }}" class="inline-flex items-center gap-1 rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-50">
                                                Choose date on calendar
                                            </button>
                                        </div>
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <label class="text-xs font-semibold text-slate-600">Start Date <span class="required-asterisk">*</span></label>
                                                <input type="date" name="venue_schedules[{{ $v }}][start_date]" {{ $isVenueSelected ? 'required' : '' }} min="{{ now()->toDateString() }}" value="{{ $venueScheduleOld['start_date'] ?? old('start_date') }}" class="venue-schedule-input mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800">
                                            </div>
                                            <div>
                                                <label class="text-xs font-semibold text-slate-600">End Date (inclusive) <span class="required-asterisk">*</span></label>
                                                <input type="date" name="venue_schedules[{{ $v }}][end_date]" {{ $isVenueSelected ? 'required' : '' }} min="{{ now()->toDateString() }}" value="{{ $venueScheduleOld['end_date'] ?? old('end_date') }}" class="venue-schedule-input mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800">
                                            </div>
                                            <div>
                                                <label class="text-xs font-semibold text-slate-600">Start Time <span class="required-asterisk">*</span></label>
                                                <input type="time" name="venue_schedules[{{ $v }}][start_time]" {{ $isVenueSelected ? 'required' : '' }} value="{{ $venueScheduleOld['start_time'] ?? old('start_time') }}" class="venue-schedule-input mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800">
                                            </div>
                                            <div>
                                                <label class="text-xs font-semibold text-slate-600">End Time <span class="required-asterisk">*</span></label>
                                                <input type="time" name="venue_schedules[{{ $v }}][end_time]" {{ $isVenueSelected ? 'required' : '' }} value="{{ $venueScheduleOld['end_time'] ?? old('end_time') }}" class="venue-schedule-input mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800">
                                            </div>
                                        </div>
                                        <fieldset class="mt-3 flex flex-wrap gap-3">
                                            <legend class="sr-only">Reservation duration for {{ $v }}</legend>
                                            <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                                <input type="radio" name="venue_schedules[{{ $v }}][reservation_duration]" value="specific_time" @checked(($venueScheduleOld['reservation_duration'] ?? 'specific_time') === 'specific_time')>
                                                Specific time
                                            </label>
                                            <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                                <input type="radio" name="venue_schedules[{{ $v }}][reservation_duration]" value="whole_day" @checked(($venueScheduleOld['reservation_duration'] ?? '') === 'whole_day')>
                                                Whole day (8:00 AM–5:00 PM)
                                            </label>
                                        </fieldset>
                                        <p class="venue-availability mt-3 text-xs text-slate-500" aria-live="polite">Select dates and times to check availability.</p>
                                        @error('venue_schedules.' . $v . '.start_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                        @error('venue_schedules.' . $v . '.end_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                        @error('venue_schedules.' . $v . '.start_time')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                        @error('venue_schedules.' . $v . '.end_time')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('venue')
                            <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                        <div id="venue-conflict-alert-wrap" class="mt-4"></div>
                    </div>
                </section>

                <section id="equipment-selection-panel" class="rounded-[32px] border border-slate-200 bg-white p-7 shadow-sm">
                    <div class="mb-5 border-b border-slate-200 pb-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Equipment by Venue</p>
                                <p class="mt-1 text-sm text-slate-500">Select equipment separately for each venue. Venue-included equipment is preselected and still goes through custodian review.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <p class="text-xs text-slate-500">Real-time availability</p>
                            </div>
                        </div>
                    </div>
                    <p class="mb-3 text-xs text-slate-500">Choose either Wireless or Non-Wireless Microphones. Sir Rogelio Guillemer handles Sound System and microphone requests.</p>
                    <p id="microphone-selection-error" class="mb-3 hidden text-sm font-medium text-red-600" role="alert">Select either Wireless or Non-Wireless Microphones, not both.</p>
                    @php
                        $equipmentList = $equipment instanceof \Illuminate\Support\Collection ? $equipment->all() : $equipment;
                        $equipmentItems = !empty($equipmentList) ? $equipmentList : collect([
                            ['name' => 'Sound System', 'quantity' => 3, 'quantity_available' => 3],
                            ['name' => 'Wireless Microphones', 'quantity' => 3, 'quantity_available' => 3],
                            ['name' => 'Non-Wireless Microphones', 'quantity' => 3, 'quantity_available' => 3],
                            ['name' => 'Aircon', 'quantity' => 1, 'quantity_available' => 1],
                            ['name' => 'Canopies', 'quantity' => 3, 'quantity_available' => 3],
                            ['name' => 'Industrial Fans', 'quantity' => 4, 'quantity_available' => 4],
                            ['name' => 'Iwata Cooler Fans', 'quantity' => 2, 'quantity_available' => 2],
                            ['name' => 'Tables', 'quantity' => 10, 'quantity_available' => 10],
                            ['name' => 'Chairs', 'quantity' => 50, 'quantity_available' => 50],
                            ['name' => 'Monobloc Chairs', 'quantity' => 12, 'quantity_available' => 12],
                            ['name' => 'Lights', 'quantity' => 1, 'quantity_available' => 1],
                            ['name' => 'Podium', 'quantity' => 1, 'quantity_available' => 1],
                            ['name' => 'Television (CHIC)', 'quantity' => 1, 'quantity_available' => 1],
                            ['name' => 'HDMI', 'quantity' => 1, 'quantity_available' => 1],
                        ]);
                    @endphp
                    <div class="mt-4 space-y-4">
                        @foreach($venueOptions as $venueName)
                            @php
                                $venueDefaults = $venueDefaultEquipmentMap[$venueName] ?? [];
                                $venueIncompatible = \App\Services\VenueEquipmentPolicy::getIncompatibleEquipment($venueName);
                                $venueIsSelected = in_array($venueName, $selectedVenues, true);
                            @endphp
                            <div class="venue-equipment-group {{ $venueIsSelected ? '' : 'hidden' }} rounded-2xl border border-slate-200 p-4" data-venue-equipment-group data-venue-name="{{ $venueName }}">
                                <h4 class="mb-3 font-semibold text-slate-800">{{ $venueName }} equipment</h4>
                                @if($venueName === 'Balay Alumni')
                                    <div class="mb-3 rounded-xl border border-cyan-200 bg-cyan-50 px-3 py-2 text-xs text-cyan-900" role="note">
                                        Balay Alumni has 50 Chairs onsite. Request Monobloc Chairs only if additional seating is needed.
                                    </div>
                                @endif
                                <div class="space-y-3">
                                    @foreach($equipmentItems as $eq)
                                        @php
                                            $itemName = is_object($eq) ? $eq->name : ($eq['name'] ?? '');
                                            $itemQty = (int) (is_object($eq) ? ($eq->quantity ?? 0) : ($eq['quantity'] ?? 0));
                                            $itemAvailable = max(0, (int) (is_object($eq) ? ($eq->quantity_available ?? $itemQty) : ($eq['quantity_available'] ?? $itemQty)));
                                            $badgeClass = $itemAvailable <= 0 ? 'bg-red-100 text-red-700' : ($itemAvailable == $itemQty ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700');
                                            $isVenueRequired = in_array($itemName, $venueDefaults, true);
                                            $oldVenueItems = (array) ($oldVenueEquipment[$venueName] ?? []);
                                            $legacySelected = !array_key_exists('venue_equipment', session()->getOldInput()) && $venueName === ($selectedVenues[0] ?? null) && in_array($itemName, (array) old('equipment', []), true);
                                            $isSelected = in_array($itemName, $oldVenueItems, true) || $legacySelected || ($isVenueRequired && $venueIsSelected);
                                            $allowedVenues = $venueEquipmentVisibilityMap[$itemName] ?? [];
                                            $isOtherVenueEquipment = in_array($itemName, ['Podium', 'HDMI', 'Television (CHIC)'], true);
                                            $isVisible = !in_array($itemName, $venueIncompatible, true) && ($allowedVenues === [] || in_array($venueName, $allowedVenues, true));
                                            $itemIndex = $loop->parent->index . '-' . $loop->index;
                                            $itemQuantity = $oldVenueEquipmentQuantities[$venueName][$itemName] ?? ($isSelected ? 1 : '');
                                        @endphp
                                        @if($isVisible)
                                            <div class="equipment-row flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between {{ $itemAvailable > 0 ? 'hover:border-purple-400 hover:bg-purple-50/70' : 'border-red-100 bg-red-50/70 opacity-70' }} {{ $isSelected ? 'border-emerald-300 bg-emerald-50/60' : '' }}" data-available="{{ $itemAvailable }}" data-total="{{ $itemQty }}" data-name="{{ $itemName }}" data-venue="{{ $venueName }}" data-venue-required="{{ $isVenueRequired ? '1' : '0' }}" data-index="{{ $itemIndex }}">
                                                <label class="flex flex-1 cursor-pointer items-center gap-3">
                                                    <input type="checkbox" name="venue_equipment[{{ $venueName }}][]" value="{{ $itemName }}" {{ $itemAvailable <= 0 && !$isVenueRequired ? 'disabled' : '' }} {{ $isSelected ? 'checked' : '' }} class="equipment-checkbox h-4 w-4 rounded border-slate-300 text-purple-600" data-equipment="{{ $itemName }}" data-venue-required="{{ $isVenueRequired ? '1' : '0' }}">
                                                    <div class="flex flex-1 items-center gap-2">
                                                        <span class="text-sm font-medium text-slate-700">{{ $itemName }}</span>
                                                        <span data-equipment-included-badge class="{{ $isVenueRequired ? 'inline-flex' : 'hidden' }} items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-[0.14em] text-emerald-700">Included in venue</span>
                                                        @if($isOtherVenueEquipment)
                                                            <span data-other-venue-equipment-badge class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[9px] font-semibold uppercase tracking-[0.14em] text-slate-600">{{ $venueName === 'Conference Hall & Interaction Center (CHIC)' ? 'CHIC equipment' : 'Others' }}</span>
                                                        @endif
                                                    </div>
                                                </label>
                                                <div class="flex flex-col items-start gap-2 sm:items-end sm:text-right">
                                                    <span class="availability-badge rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass }}">{{ $itemAvailable }} / {{ $itemQty }} available</span>
                                                    <div class="quantity-input-wrap {{ $isSelected ? '' : 'hidden' }}" id="qty-wrap-{{ $itemIndex }}">
                                                        <label class="mr-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">Qty</label>
                                                        <input type="number" inputmode="numeric" step="1" name="venue_equipment_quantities[{{ $venueName }}][{{ $itemName }}]" min="1" max="{{ $itemAvailable }}" value="{{ $itemQuantity }}" {{ $isSelected ? 'required' : 'disabled' }} class="w-24 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" placeholder="Enter qty" aria-label="{{ $venueName }} {{ $itemName }} quantity">
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section id="venue-default-equipment-panel" class="hidden rounded-[32px] border border-emerald-200 bg-emerald-50/70 p-7 shadow-sm">
                    <div class="mb-5 border-b border-emerald-200 pb-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-emerald-700">Venue Equipment Included</p>
                                <p class="mt-1 text-sm text-emerald-700">This venue already includes the following equipment.</p>
                            </div>
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-emerald-700">Included by venue</span>
                        </div>
                    </div>
                    <ul id="venue-default-equipment-list" class="space-y-2 text-sm text-emerald-800"></ul>
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
                            <input type="text" value="{{ $currentUser?->position ?? 'Position not provided' }}" readonly class="mt-2 w-full rounded-3xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 cursor-not-allowed">
                            <input type="hidden" name="requested_by_position" value="{{ $currentUser?->position ?? '' }}">
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
                            <p class="mt-3 text-xs leading-5 text-slate-600">Urgent processing is available only for requests starting within the next 48 hours. Enter a reason and upload the required documents. Final approval and priority classification remain subject to the Custodian or Administrator.</p>
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
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Section IV. Required Documents</p>
                        <p class="mt-1 text-sm text-slate-500">Upload the required supporting documents for this request.</p>
                    </div>

                    <div class="space-y-6">
                        @if ($isStudent || $isFaculty)
                            {{-- Activity Proposal for Student/Faculty --}}
                            <div>
                                <label for="activity_proposal_file" class="mb-3 block text-sm font-semibold text-slate-700">
                                    Activity Proposal <span class="required-asterisk">*</span>
                                </label>
                                <p class="mb-3 text-xs text-slate-500">Required document: Comprehensive proposal describing the activity objectives, expected outcomes, and how facilities/equipment support the activity goals.</p>
                                <div class="mb-3 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                    Tip: Upload the proposal as a PDF when possible. Supported files are PDF, JPG, JPEG, or PNG, and each file must be under 10MB.
                                </div>
                                <div data-upload-box class="rounded-[32px] border-2 border-dashed border-slate-300 bg-slate-50 px-8 pb-8 pt-8 transition duration-200 hover:border-emerald-400">
                                    <div class="space-y-3 text-center">
                                        <svg class="mx-auto h-14 w-14 text-slate-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex flex-col items-center gap-1 text-sm text-slate-600 sm:flex-row sm:justify-center">
                                            <label for="activity_proposal_file" class="relative cursor-pointer rounded-full bg-white px-4 py-2 text-sm font-semibold text-emerald-600 shadow-sm hover:text-emerald-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-emerald-500">
                                                <span data-upload-action-label>Upload a file</span>
                                                <input id="activity_proposal_file" name="activity_proposal_file" type="file" accept=".pdf,.jpeg,.jpg,.png" class="sr-only">
                                            </label>
                                            <span>or drag and drop</span>
                                        </div>
                                        <p class="text-xs text-slate-500">PDF, JPEG, PNG up to 10MB</p>
                                        <div data-upload-progress class="mt-3 hidden">
                                            <div class="mb-2 flex items-center justify-between gap-4 text-xs text-slate-600">
                                                <span data-upload-file-name class="truncate max-w-[220px] text-left">activity-proposal.pdf</span>
                                                <span data-upload-percent>0%</span>
                                            </div>
                                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-200">
                                                <div data-upload-bar class="h-full w-0 rounded-full bg-emerald-600 transition-all duration-300"></div>
                                            </div>
                                        </div>
                                        <p data-upload-status class="hidden text-xs font-medium text-slate-700">Uploading...</p>
                                        <div data-upload-error class="mt-3 hidden flex items-center justify-between gap-3 rounded-2xl border border-red-200 bg-red-50 px-3 py-2 text-left text-sm text-red-700">
                                            <div>
                                                <div class="font-semibold">Upload failed</div>
                                                <div class="text-xs text-red-600">Connection lost</div>
                                            </div>
                                            <button type="button" data-retry-upload class="rounded-full border border-red-200 bg-white px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100">Retry</button>
                                        </div>
                                    </div>
                                </div>
                                <div data-upload-file-card class="mt-3 hidden rounded-2xl border border-slate-200 bg-white p-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-slate-600">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"></path>
                                                    <path d="M14 2v6h6"></path>
                                                    <path d="M9 13h6M9 17h6"></path>
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <div data-upload-filename class="truncate text-sm font-semibold text-slate-900">Activity Proposal</div>
                                                <div data-upload-subtitle class="text-xs text-slate-600">Activity Proposal</div>
                                            </div>
                                        </div>
                                        <div data-upload-check class="flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-emerald-700">
                                            <span>Ready</span>
                                        </div>
                                    </div>
                                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200">
                                        <div data-upload-file-progress class="h-full w-full rounded-full bg-emerald-600"></div>
                                    </div>
                                </div>
                                <div id="activity-proposal-preview" class="mt-3 hidden">
                                    <p class="flex items-center justify-between gap-3 text-sm text-slate-600">Selected file: <span id="activity-proposal-name" class="font-medium"></span><button type="button" data-remove-file="activity_proposal_file" class="shrink-0 text-xs font-semibold text-red-600 hover:text-red-700" aria-label="Remove activity proposal">Remove</button></p>
                                </div>
                                @error('activity_proposal_file')
                                    <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        @elseif ($isExternal)
                            {{-- IGP Receipt for External/Organization --}}
                            <div>
                                <label for="igp_receipt_file" class="mb-3 block text-sm font-semibold text-slate-700">
                                    Receipt document <span class="text-xs font-normal text-slate-500">(optional)</span>
                                </label>
                                <p class="mb-3 text-xs text-slate-500">Do not pay before the Supply Office confirms availability. The IGP will record the official receipt number after availability is confirmed. You may add a receipt document later if requested.</p>
                                <div data-upload-box class="rounded-[32px] border-2 border-dashed border-slate-300 bg-slate-50 px-8 pb-8 pt-8 transition duration-200 hover:border-emerald-400">
                                    <div class="space-y-3 text-center">
                                        <svg class="mx-auto h-14 w-14 text-slate-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex flex-col items-center gap-1 text-sm text-slate-600 sm:flex-row sm:justify-center">
                                            <label for="igp_receipt_file" class="relative cursor-pointer rounded-full bg-white px-4 py-2 text-sm font-semibold text-emerald-600 shadow-sm hover:text-emerald-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-emerald-500">
                                                <span data-upload-action-label>Upload a file</span>
                                                <input id="igp_receipt_file" name="igp_receipt_file" type="file" accept=".pdf,.jpeg,.jpg,.png" class="sr-only">
                                            </label>
                                            <span>or drag and drop</span>
                                        </div>
                                        <p class="text-xs text-slate-500">PDF, JPEG, PNG up to 10MB</p>
                                        <div data-upload-progress class="mt-3 hidden">
                                            <div class="mb-2 flex items-center justify-between gap-4 text-xs text-slate-600">
                                                <span data-upload-file-name class="truncate max-w-[220px] text-left">igp-receipt.pdf</span>
                                                <span data-upload-percent>0%</span>
                                            </div>
                                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-200">
                                                <div data-upload-bar class="h-full w-0 rounded-full bg-gradient-to-r from-cyan-400 to-emerald-400 transition-all duration-300"></div>
                                            </div>
                                        </div>
                                        <p data-upload-status class="hidden text-xs font-medium text-cyan-400">Uploading...</p>
                                        <div data-upload-error class="mt-3 hidden flex items-center justify-between gap-3 rounded-2xl border border-red-200 bg-red-50 px-3 py-2 text-left text-sm text-red-700">
                                            <div>
                                                <div class="font-semibold">Upload failed</div>
                                                <div class="text-xs text-red-600">Connection lost</div>
                                            </div>
                                            <button type="button" data-retry-upload class="rounded-full border border-red-200 bg-white px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100">Retry</button>
                                        </div>
                                    </div>
                                </div>
                                <div data-upload-file-card class="mt-3 hidden rounded-2xl border border-slate-200 bg-slate-900/20 p-3 shadow-inner shadow-slate-950/10">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-500/30 bg-slate-800/80 text-slate-200">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"></path>
                                                    <path d="M14 2v6h6"></path>
                                                    <path d="M9 13h6M9 17h6"></path>
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <div data-upload-filename class="truncate text-sm font-semibold text-slate-100">IGP Receipt</div>
                                                <div data-upload-subtitle class="text-xs text-slate-400">IGP Receipt</div>
                                            </div>
                                        </div>
                                        <div data-upload-check class="flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-emerald-300">
                                            <span>Ready</span>
                                        </div>
                                    </div>
                                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-700">
                                        <div data-upload-file-progress class="h-full w-full rounded-full bg-gradient-to-r from-cyan-400 to-emerald-400"></div>
                                    </div>
                                </div>
                                <div id="igp-receipt-preview" class="mt-3 hidden">
                                    <p class="flex items-center justify-between gap-3 text-sm text-slate-600">Selected file: <span id="igp-receipt-name" class="font-medium"></span><button type="button" data-remove-file="igp_receipt_file" class="shrink-0 text-xs font-semibold text-red-600 hover:text-red-700" aria-label="Remove IGP receipt">Remove</button></p>
                                </div>
                                @error('igp_receipt_file')
                                    <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        {{-- E-Signature (Required for all) --}}
                        <div class="rounded-[24px] border border-emerald-200 bg-emerald-50 p-4">
                            <div class="mb-4 border-b border-emerald-200 pb-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-emerald-700">Section V. E-Signature</p>
                                <p class="mt-2 text-sm font-semibold text-emerald-800">Saved E-Signature Status <span class="required-asterisk">*</span></p>
                                <p class="mt-1 text-xs text-emerald-700">Your e-signature is taken from your Account Settings and will appear on the official request form for administrative records.</p>
                            </div>

                            <div class="rounded-2xl border border-emerald-200 bg-white p-4">
                                @if($currentUser?->e_signature_file)
                                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="flex items-center gap-3">
                                            <img src="{{ route('user.signature', ['user' => $currentUser->id]) }}" alt="Saved e-signature" class="h-14 max-w-[220px] rounded-lg border border-slate-200 bg-white object-contain p-2 shadow-sm">
                                            <div>
                                                <p class="text-sm font-semibold text-emerald-800">Using your saved signature</p>
                                                <p class="text-xs text-emerald-700">This will be applied automatically to the request form and printout.</p>
                                            </div>
                                        </div>
                                        <a href="{{ route('requestor.settings') }}" data-preserve-request-draft class="inline-flex items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">
                                            Update in Account Settings
                                        </a>
                                    </div>
                                @else
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-amber-700">No saved signature found</p>
                                            <p class="text-xs text-slate-600">Please upload your signature in Account Settings before submitting or printing this request.</p>
                                        </div>
                                        <a href="{{ route('requestor.settings') }}" data-preserve-request-draft class="inline-flex items-center justify-center rounded-full border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 transition hover:bg-amber-100">
                                            Go to Account Settings
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-[32px] border border-slate-200 bg-white p-7 shadow-sm">
                    <div class="mb-5 border-b border-slate-200 pb-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Submission checklist</p>
                        <p class="mt-1 text-sm text-slate-500">Please review the following before submitting your request.</p>
                    </div>
                    <div id="submission-checklist-alert" role="alert" aria-live="assertive" class="mb-4 hidden rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"></div>
                    <div class="space-y-3">
                        <div class="flex items-start gap-3">
                            <input id="checklist-required-fields" type="checkbox" disabled class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600">
                            <button type="button" data-checklist-target="checklist-required-fields" class="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-left text-sm text-slate-700 transition hover:border-slate-300 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                                All required fields are completed
                            </button>
                        </div>
                        <div class="flex items-start gap-3">
                            <input id="checklist-venue-availability" type="checkbox" disabled class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600">
                            <button type="button" data-checklist-target="checklist-venue-availability" class="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-left text-sm text-slate-700 transition hover:border-slate-300 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                                Venue and equipment availability confirmed
                            </button>
                        </div>
                        <div class="flex items-start gap-3">
                            <input id="checklist-document-upload" type="checkbox" disabled class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600">
                            <button type="button" data-checklist-target="checklist-document-upload" class="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-left text-sm text-slate-700 transition hover:border-slate-300 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                                <span id="checklist-document-label">Supporting documents uploaded</span>
                            </button>
                        </div>
                        <div class="flex items-start gap-3">
                            <input id="checklist-e-signature" type="checkbox" disabled class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600">
                            <button type="button" data-checklist-target="checklist-e-signature" class="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-left text-sm text-slate-700 transition hover:border-slate-300 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                                E-signature uploaded
                            </button>
                        </div>
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
<dialog id="venue-calendar-modal" style="width:min(96vw,1100px);max-width:none;height:min(92vh,900px);max-height:92vh;margin:auto" class="overflow-hidden rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/60">
    <div class="flex h-full flex-col bg-white">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3 sm:px-6">
            <div>
                <h2 id="venue-calendar-modal-title" class="text-lg font-semibold text-slate-900">Venue calendar</h2>
                <p id="venue-calendar-selection-status" class="mt-1 text-sm text-slate-600" aria-live="polite">Select a start date, then an end date. The end date is inclusive.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" data-close-venue-calendar class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" aria-label="Close venue calendar">Cancel</button>
                <button type="button" data-apply-venue-calendar-dates disabled class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300">Use dates</button>
            </div>
        </div>
        <iframe id="venue-calendar-modal-frame" title="Choose reservation date range" class="min-h-0 w-full flex-1 bg-slate-50" src="about:blank"></iframe>
    </div>
</dialog>
<script>
    window.syncVenueScheduleDuration = (group) => {
        const duration = group.querySelector('input[type="radio"][name$="[reservation_duration]"]:checked')?.value || 'specific_time';
        const startTime = group.querySelector('input[name$="[start_time]"]');
        const endTime = group.querySelector('input[name$="[end_time]"]');
        if (!startTime || !endTime) return;

        const wholeDay = duration === 'whole_day';
        startTime.readOnly = wholeDay;
        endTime.readOnly = wholeDay;
        if (!wholeDay) return;

        [['08:00', startTime], ['17:00', endTime]].forEach(([value, field]) => {
            if (field.value === value) return;
            field.value = value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
    };

    document.addEventListener('change', (event) => {
        const radio = event.target;
        if (!(radio instanceof HTMLInputElement) || radio.type !== 'radio' || !radio.name.endsWith('[reservation_duration]')) return;
        const group = radio.closest('[data-venue-schedule]');
        if (group) window.syncVenueScheduleDuration(group);
    });

    document.querySelectorAll('[data-venue-schedule]').forEach((group) => {
        window.syncVenueScheduleDuration(group);
    });

    const initializeVenueCapacityWarning = () => {
        const form = document.getElementById('request-form');
        const participantCountInput = form?.querySelector('[name="expected_participants"]');
        const capacityWarningBanner = document.getElementById('capacity-warning-banner');
        if (!form || !participantCountInput || !capacityWarningBanner) return;

        const venueCapacities = JSON.parse(form.dataset.venueCapacities || '{}');
        const updateVenueCapacityWarning = () => {
            const participants = Number(participantCountInput.value);
            const selectedVenues = [...form.querySelectorAll('[name="venue[]"]:checked')];
            const exceededVenues = selectedVenues
                .map((venueInput) => ({
                    name: venueInput.value,
                    capacity: Number(venueCapacities[venueInput.value]),
                }))
                .filter(({ capacity }) => Number.isFinite(capacity) && capacity > 0 && participants > capacity);

            if (!Number.isInteger(participants) || participants < 1 || exceededVenues.length === 0) {
                capacityWarningBanner.textContent = '';
                capacityWarningBanner.classList.add('hidden');
                return;
            }

            const venueDetails = exceededVenues
                .map(({ name, capacity }) => `${name} (maximum ${capacity})`)
                .join(', ');
            capacityWarningBanner.textContent = `The expected ${participants} participants exceed the capacity of: ${venueDetails}. You may still submit the request, but the Supply Office will need to review it.`;
            capacityWarningBanner.classList.remove('hidden');
        };

        participantCountInput.addEventListener('input', updateVenueCapacityWarning);
        form.querySelectorAll('[name="venue[]"]').forEach((venueInput) => {
            venueInput.addEventListener('change', updateVenueCapacityWarning);
        });
        updateVenueCapacityWarning();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeVenueCapacityWarning, { once: true });
    } else {
        initializeVenueCapacityWarning();
    }

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('request-form');
        if (!form) return;
        const requestContext = form.querySelector('[name="request_context"]');
        const organizationSelect = form.querySelector('[name="student_organization_id"]');
        const organizationGroup = document.getElementById('student-organization-group');
        const studentPersonalGuidance = document.getElementById('student-personal-guidance');
        const syncOrganizationVisibility = () => {
            if (organizationGroup && requestContext) {
                organizationGroup.classList.toggle('hidden', requestContext.value !== 'student_organization');
                if (requestContext.value !== 'student_organization' && organizationSelect) organizationSelect.value = '';
            }
            if (studentPersonalGuidance && requestContext) {
                studentPersonalGuidance.classList.toggle('hidden', requestContext.value === 'student_organization');
            }
        };
        requestContext?.addEventListener('change', syncOrganizationVisibility);
        organizationSelect?.addEventListener('change', function () {
            if (requestContext) requestContext.value = this.value ? 'student_organization' : 'personal';
            syncOrganizationVisibility();
        });
        syncOrganizationVisibility();

        const positionSelect = document.getElementById('requested_by_position');
        const otherPositionInput = document.getElementById('requested_by_position_other');
        if (positionSelect && otherPositionInput) {
            const updateOtherPosition = () => {
                const isOther = positionSelect.value === 'Other';
                otherPositionInput.classList.toggle('hidden', !isOther);
                otherPositionInput.disabled = !isOther;
                otherPositionInput.required = isOther;
            };

            positionSelect.addEventListener('change', updateOtherPosition);
            updateOtherPosition();
        }

        const collegeSelect = document.getElementById('college_id');
        const departmentSelect = document.getElementById('department_id');
        if (collegeSelect && departmentSelect) {
            const departmentOptions = Array.from(departmentSelect.options).map((option) => ({
                value: option.value,
                label: option.textContent,
                collegeId: option.dataset.collegeId || '',
            }));
            const selectedDepartment = departmentSelect.dataset.selected || departmentSelect.value;

            const filterDepartments = () => {
                const collegeId = collegeSelect.value;
                departmentSelect.innerHTML = '';
                departmentSelect.add(new Option('Select department', ''));
                departmentOptions
                    .filter((option) => option.value === '' || option.collegeId === collegeId)
                    .forEach((option) => departmentSelect.add(new Option(option.label, option.value)));

                const matchingOption = Array.from(departmentSelect.options).find((option) => option.value === selectedDepartment);
                departmentSelect.value = matchingOption ? selectedDepartment : '';
                departmentSelect.disabled = !collegeId || departmentSelect.dataset.profileLocked === '1';
            };

            departmentSelect.dataset.profileLocked = departmentSelect.disabled ? '1' : '0';
            collegeSelect.addEventListener('change', filterDepartments);
            filterDepartments();
        }

        // File input change handlers for preview
        const fileInputs = [
            { id: 'activity_proposal_file', previewId: 'activity-proposal-preview', nameId: 'activity-proposal-name' },
            { id: 'igp_receipt_file', previewId: 'igp-receipt-preview', nameId: 'igp-receipt-name' },
            { id: 'e_signature_file', previewId: 'e-signature-preview', nameId: 'e-signature-name' }
        ];

        const showInlineFileValidation = (input, message) => {
            const uploadBox = input.closest('[data-upload-box]');
            const parent = uploadBox ? uploadBox.parentElement : input.parentElement;
            if (!parent) return;

            const existingError = parent.querySelector('[data-file-validation-error]');
            if (existingError) {
                existingError.remove();
            }

            const error = document.createElement('div');
            error.setAttribute('role', 'alert');
            error.setAttribute('aria-live', 'assertive');
            error.dataset.fileValidationError = 'true';
            error.className = 'mt-3 flex items-start gap-2 rounded-2xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 shadow-sm';
            error.innerHTML = `
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>${message}</span>
            `;

            parent.appendChild(error);
            input.focus();
        };

        fileInputs.forEach(({ id, previewId, nameId }) => {
            const input = document.getElementById(id);
            if (input) {
                input.addEventListener('change', function() {
                    const allowedExtensions = id === 'e_signature_file' ? ['jpg', 'jpeg', 'png'] : ['pdf', 'jpg', 'jpeg', 'png'];
                    const allowedMimeTypes = id === 'e_signature_file' ? ['image/jpeg', 'image/png'] : ['application/pdf', 'image/jpeg', 'image/png'];
                    const maxFileSize = 10 * 1024 * 1024;
                    const file = this.files[0];
                    const extension = file?.name.split('.').pop()?.toLowerCase();

                    const uploadBox = this.closest('[data-upload-box]');
                    const parent = uploadBox ? uploadBox.parentElement : this.parentElement;
                    const existingError = parent ? parent.querySelector('[data-file-validation-error]') : null;
                    if (existingError) {
                        existingError.remove();
                    }

                    if (file && file.size > maxFileSize) {
                        this.value = '';
                        showInlineFileValidation(this, 'File is too large. Please upload a file smaller than 10MB.');
                        updateChecklistState();
                        return;
                    }

                    if (file && (!allowedExtensions.includes(extension) || (file.type && !allowedMimeTypes.includes(file.type)))) {
                        this.value = '';
                        showInlineFileValidation(this, `Invalid file type. Please upload ${allowedExtensions.map((ext) => ext.toUpperCase()).join(', ')}.`);
                        updateChecklistState();
                        return;
                    }

                    const preview = document.getElementById(previewId);
                    const name = document.getElementById(nameId);
                    if (this.files.length > 0) {
                        if (name) name.textContent = this.files[0].name;
                        if (preview) preview.classList.remove('hidden');
                    } else {
                        if (preview) preview.classList.add('hidden');
                    }
                });
            }
        });

        form.querySelectorAll('[data-remove-file]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.removeFile);
                const preview = input ? document.getElementById(fileInputs.find((item) => item.id === input.id)?.previewId) : null;
                const uploadBox = input ? input.closest('[data-upload-box]') : null;
                const parent = uploadBox ? uploadBox.parentElement : null;
                if (parent) {
                    const existingError = parent.querySelector('[data-file-validation-error]');
                    if (existingError) {
                        existingError.remove();
                    }
                }
                if (input) {
                    input.value = '';
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (preview) preview.classList.add('hidden');
            });
        });

    });

    const initializeRequestFormDraftUi = () => {
        const form = document.getElementById('request-form');
        if (!form || form.dataset.requestDraftUiInitialized === '1') return;
        form.dataset.requestDraftUiInitialized = '1';
        const venueCalendarModal = document.getElementById('venue-calendar-modal');
        const venueCalendarFrame = document.getElementById('venue-calendar-modal-frame');
        const venueCalendarTitle = document.getElementById('venue-calendar-modal-title');
        const venueCalendarSelectionStatus = document.getElementById('venue-calendar-selection-status');
        const applyVenueCalendarDates = venueCalendarModal?.querySelector('[data-apply-venue-calendar-dates]');
        let selectedCalendarRange = null;
        const closeVenueCalendarModal = () => {
            if (!venueCalendarModal?.open) return;
            venueCalendarModal.close();
            if (venueCalendarFrame) venueCalendarFrame.src = 'about:blank';
        };

        form.querySelectorAll('[data-open-venue-calendar]').forEach((button) => {
            button.addEventListener('click', () => {
                if (!venueCalendarModal || !venueCalendarFrame) return;
                const venueName = button.dataset.venueName || 'Venue';
                venueCalendarModal.dataset.venueName = venueName;
                selectedCalendarRange = null;
                if (applyVenueCalendarDates) applyVenueCalendarDates.disabled = true;
                if (venueCalendarSelectionStatus) {
                    venueCalendarSelectionStatus.textContent = 'Select a start date, then an end date. The end date is inclusive.';
                }
                if (venueCalendarTitle) venueCalendarTitle.textContent = `${venueName} calendar`;
                venueCalendarFrame.src = button.dataset.calendarUrl || 'about:blank';
                venueCalendarModal.showModal();
            });
        });
        venueCalendarModal?.querySelector('[data-close-venue-calendar]')?.addEventListener('click', closeVenueCalendarModal);
        venueCalendarModal?.addEventListener('click', (event) => {
            if (event.target === venueCalendarModal) closeVenueCalendarModal();
        });
        venueCalendarModal?.addEventListener('close', () => {
            if (venueCalendarFrame) venueCalendarFrame.src = 'about:blank';
        });
        window.addEventListener('message', (event) => {
            if (
                event.origin !== window.location.origin
                || event.source !== venueCalendarFrame?.contentWindow
                || event.data?.type !== 'venue-calendar-date-selected'
                || event.data.venue !== venueCalendarModal?.dataset.venueName
                || typeof event.data.startDate !== 'string'
                || typeof event.data.endDate !== 'string'
                || !/^\d{4}-\d{2}-\d{2}$/.test(event.data.startDate)
                || (event.data.endDate !== '' && !/^\d{4}-\d{2}-\d{2}$/.test(event.data.endDate))
                || (event.data.endDate !== '' && event.data.endDate < event.data.startDate)
            ) {
                return;
            }

            selectedCalendarRange = event.data.endDate
                ? { startDate: event.data.startDate, endDate: event.data.endDate }
                : null;
            if (venueCalendarSelectionStatus) {
                venueCalendarSelectionStatus.textContent = event.data.endDate
                    ? `${event.data.startDate} – ${event.data.endDate} (inclusive). Choose “Use dates” to apply.`
                    : `Start date: ${event.data.startDate}. Now select the end date (inclusive).`;
            }
            if (applyVenueCalendarDates) applyVenueCalendarDates.disabled = !selectedCalendarRange;
        });
        applyVenueCalendarDates?.addEventListener('click', () => {
            if (!selectedCalendarRange || !venueCalendarModal?.dataset.venueName) return;

            const venueSchedule = [...form.querySelectorAll('[data-venue-schedule]')]
                .find((group) => group.dataset.venueName === venueCalendarModal.dataset.venueName);
            if (!venueSchedule) return;

            [
                ['start_date', selectedCalendarRange.startDate],
                ['end_date', selectedCalendarRange.endDate],
            ].forEach(([fieldName, date]) => {
                const field = venueSchedule.querySelector(`[name$="[${fieldName}]"]`);
                if (!field) return;
                field.value = date;
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true }));
            });
            closeVenueCalendarModal();
        });

        const draftStatus = document.getElementById('draft-status');
        let formIsDirty = false;

        const updateDraftStatus = (dirty) => {
            if (!draftStatus) return;

            const indicator = draftStatus.querySelector('[data-draft-status-indicator]');
            const label = draftStatus.querySelector('[data-draft-status-label]');

            if (indicator) {
                indicator.className = dirty
                    ? 'h-2.5 w-2.5 rounded-full bg-amber-500 animate-pulse'
                    : 'h-2.5 w-2.5 rounded-full bg-emerald-500';
            }

            if (draftStatus) {
                draftStatus.className = dirty
                    ? 'inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-900 shadow-sm ring-1 ring-amber-200'
                    : 'inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 shadow-sm ring-1 ring-emerald-200';
            }

            if (label) {
                label.textContent = dirty ? 'Unsaved changes' : 'Draft autosave';
            }
        };

        const markFormDirty = () => {
            if (!formIsDirty) {
                formIsDirty = true;
                updateDraftStatus(true);
            }
        };

        form.querySelectorAll('input, textarea, select').forEach((field) => {
            field.addEventListener('input', markFormDirty);
            field.addEventListener('change', markFormDirty);
        });

        form.addEventListener('submit', (event) => {
            const hasBothMicrophoneTypes = [...form.querySelectorAll('[data-venue-equipment-group]:not(.hidden)')].some((group) => (
                group.querySelector('.equipment-checkbox:checked[value="Wireless Microphones"]')
                && group.querySelector('.equipment-checkbox:checked[value="Non-Wireless Microphones"]')
            ));
            const microphoneError = document.getElementById('microphone-selection-error');
            if (hasBothMicrophoneTypes) {
                event.preventDefault();
                microphoneError?.classList.remove('hidden');
                microphoneError?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            formIsDirty = false;
            updateDraftStatus(false);
        });

        window.addEventListener('beforeunload', (event) => {
            if (formIsDirty && !window.requestFormDraftNavigation) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
        window.addEventListener('pageshow', () => {
            window.requestFormDraftNavigation = false;
        });

        updateDraftStatus(false);

        const venueScheduleGroups = form.querySelectorAll('[data-venue-schedule]');

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
            const selectedVenues = [...form.querySelectorAll('input[name="venue[]"]:checked')].map((input) => input.value);
            const venueText = selectedVenues.length ? selectedVenues.join(', ') : 'Not selected';
            const scheduleSummary = [...venueScheduleGroups]
                .filter((group) => group.closest('[data-venue-option]')?.querySelector('input[name="venue[]"]')?.checked)
                .map((group) => {
                    const fields = Object.fromEntries(['start_date', 'end_date', 'start_time', 'end_time']
                        .map((field) => [field, group.querySelector(`[name$="[${field}]"]`)?.value || '']));
                    const duration = group.querySelector('input[type="radio"]:checked')?.value;
                    const date = fields.start_date && fields.end_date
                        ? `${formatDisplayDate(fields.start_date)}${fields.start_date === fields.end_date ? '' : ` – ${formatDisplayDate(fields.end_date)}`}`
                        : 'Dates not selected';
                    const time = duration === 'whole_day'
                        ? 'Whole day (8:00 AM–5:00 PM)'
                        : fields.start_time && fields.end_time
                            ? `${formatDisplayTime(fields.start_time)} – ${formatDisplayTime(fields.end_time)}`
                            : 'Times not selected';
                    return `${group.dataset.venueName}: ${date}, ${time}`;
                });

            if (summaryDetails.activity) summaryDetails.activity.textContent = activityInput?.value.trim() || 'Not specified';
            if (summaryDetails.venue) summaryDetails.venue.textContent = venueText;
            if (summaryDetails.date) summaryDetails.date.textContent = scheduleSummary.length ? scheduleSummary.join(' | ') : 'Not selected';
            if (summaryDetails.time) summaryDetails.time.textContent = 'Schedules are specified separately for each selected venue.';

            const selectedEquipment = [...form.querySelectorAll('.equipment-row')].filter((row) => {
                const checkbox = row.querySelector('.equipment-checkbox');
                return checkbox && checkbox.checked;
            }).map((row) => {
                const name = row.dataset.name || 'Equipment';
                const venue = row.dataset.venue || 'Venue';
                const qtyInput = row.querySelector('input[type="number"]');
                const qty = qtyInput?.value || 'Enter quantity';
                return `${venue}: ${name} × ${qty}`;
            });

            if (summaryDetails.equipment) {
                if (selectedEquipment.length) {
                    summaryDetails.equipment.innerHTML = selectedEquipment.map((item) => `<li class="text-sm text-slate-700">${item}</li>`).join('');
                } else {
                    summaryDetails.equipment.innerHTML = '<li class="text-sm text-slate-500">No equipment selected</li>';
                }
            }

            // Update checklist state
            updateChecklistState();
        }

        const venueInputs = form.querySelectorAll('input[name="venue[]"]');
        const venueDefaultEquipment = JSON.parse(form.dataset.venueDefaultEquipment || '{}');
        const venueAvailabilityContainer = document.getElementById('venue-conflict-alert-wrap');
        let venueAvailabilityState = null;
        let venueAvailabilitySequence = 0;
        let venueAvailabilityTimer = null;
        const renderVenueAvailability = (state, message, results = []) => {
            venueAvailabilityState = state;
            if (!venueAvailabilityContainer) return;
            venueAvailabilityContainer.replaceChildren();

            const notice = document.createElement('div');
            notice.className = state === true
                ? 'rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800'
                : state === false
                    ? 'rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900'
                    : 'rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600';
            notice.setAttribute('role', 'status');
            notice.setAttribute('aria-live', 'polite');
            notice.textContent = message;
            venueAvailabilityContainer.appendChild(notice);

            if (results.length) {
                const list = document.createElement('ul');
                list.className = 'mt-2 space-y-1';
                results.forEach((result) => {
                    const item = document.createElement('li');
                    item.textContent = `${result.venue}: ${result.available ? 'available' : (result.message || 'unavailable')}`;
                    list.appendChild(item);
                });
                notice.appendChild(list);
            }
        };

        const checkVenueAvailability = () => {
            window.clearTimeout(venueAvailabilityTimer);
            const selectedGroups = [...venueScheduleGroups].filter((group) => {
                return group.closest('[data-venue-option]')?.querySelector('input[name="venue[]"]')?.checked;
            });
            const preparedSchedules = selectedGroups.map((group) => ({
                group,
                venue: group.dataset.venueName,
                startDate: group.querySelector('[name$="[start_date]"]')?.value,
                endDate: group.querySelector('[name$="[end_date]"]')?.value,
                startTime: group.querySelector('[name$="[start_time]"]')?.value,
                endTime: group.querySelector('[name$="[end_time]"]')?.value,
                duration: group.querySelector('input[type="radio"]:checked')?.value || 'specific_time',
            }));

            if (!preparedSchedules.length) {
                venueAvailabilitySequence += 1;
                venueAvailabilityState = null;
                renderVenueAvailability(null, 'Select a venue and enter its schedule to check availability.');
                updateChecklistState();
                return;
            }

            const sequence = ++venueAvailabilitySequence;
            const incompleteSchedules = preparedSchedules.filter((schedule) => {
                return !schedule.startDate || !schedule.endDate || !schedule.startTime || !schedule.endTime;
            });
            if (incompleteSchedules.length) {
                venueAvailabilityState = null;
                incompleteSchedules.forEach(({ group }) => {
                    const status = group.querySelector('.venue-availability');
                    if (status) {
                        status.textContent = 'Select dates and times to check availability.';
                        status.className = 'venue-availability mt-3 text-xs text-slate-500';
                    }
                });
                renderVenueAvailability(null, 'Enter dates and times for each selected venue.');
                updateChecklistState();
                return;
            }

            preparedSchedules.forEach(({ group }) => {
                const status = group.querySelector('.venue-availability');
                if (status) {
                    status.textContent = 'Checking availability…';
                    status.className = 'venue-availability mt-3 text-xs text-slate-500';
                }
            });
            renderVenueAvailability(null, 'Checking selected venue schedules…');
            updateChecklistState();
            venueAvailabilityTimer = window.setTimeout(async () => {
                try {
                    const results = await Promise.all(preparedSchedules.map(async (schedule) => {
                        const query = new URLSearchParams({
                            start_date: schedule.startDate,
                            end_date: schedule.endDate,
                            start_time: schedule.startTime,
                            end_time: schedule.endTime,
                            reservation_duration: schedule.duration,
                        });
                        query.append('venues[]', schedule.venue);
                        const response = await fetch(`${form.dataset.venueAvailabilityUrl}?${query.toString()}`, {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (!response.ok) throw new Error('Availability check failed.');
                        const payload = await response.json();
                        return { group: schedule.group, ...(payload.venues?.[0] || { venue: schedule.venue, available: false }) };
                    }));
                    if (sequence !== venueAvailabilitySequence) return;
                    results.forEach((result) => {
                        const status = result.group.querySelector('.venue-availability');
                        if (status) {
                            status.textContent = result.available
                                ? `${result.venue} is available for this schedule. Supply Office confirmation is still required.`
                                : `${result.venue} is unavailable for this schedule. ${result.message || ''}`;
                            status.className = `venue-availability mt-3 text-xs ${result.available ? 'text-emerald-700' : 'text-amber-800'}`;
                        }
                    });
                    const isAvailable = results.every((result) => result.available);
                    renderVenueAvailability(
                        isAvailable,
                        isAvailable
                            ? 'All selected venues are available for their individual schedules. Supply Office confirmation is still required.'
                            : 'One or more selected venues are unavailable for their selected schedule.',
                        results,
                    );
                } catch {
                    if (sequence !== venueAvailabilitySequence) return;
                    preparedSchedules.forEach(({ group }) => {
                        const status = group.querySelector('.venue-availability');
                        if (status) {
                            status.textContent = 'Availability could not be checked. Please try again.';
                            status.className = 'venue-availability mt-3 text-xs text-red-700';
                        }
                    });
                    renderVenueAvailability(null, 'Availability could not be checked. Please try again before submitting.');
                }
                updateChecklistState();
            }, 250);
        };

        const toggleScheduleFields = () => {
            venueScheduleGroups.forEach((group) => {
                const isSelected = group.closest('[data-venue-option]')?.querySelector('input[name="venue[]"]')?.checked === true;
                group.classList.toggle('hidden', !isSelected);
                group.style.display = isSelected ? 'block' : 'none';
                group.querySelectorAll('.venue-schedule-input').forEach((field) => {
                    field.required = isSelected;
                });
            });
        };

        const applyVenueDuration = (group) => {
            window.syncVenueScheduleDuration(group);
        };

        const updateVenueEquipmentDefaults = () => {
            const selectedVenues = [...venueInputs].filter((input) => input.checked).map((input) => input.value);
            form.querySelectorAll('[data-venue-equipment-group]').forEach((group) => {
                const venue = group.dataset.venueName || '';
                const venueIsSelected = selectedVenues.includes(venue);
                group.classList.toggle('hidden', !venueIsSelected);
                group.querySelectorAll('.equipment-row').forEach((row) => {
                    const checkbox = row.querySelector('.equipment-checkbox');
                    const quantityWrap = row.querySelector('.quantity-input-wrap');
                    const quantity = quantityWrap?.querySelector('input[type="number"]');
                    const includedBadge = row.querySelector('[data-equipment-included-badge]');
                    if (!checkbox) return;

                    const isVenueRequired = (venueDefaultEquipment[venue] || []).includes(row.dataset.name);
                    checkbox.dataset.venueRequired = isVenueRequired ? '1' : '0';
                    if (includedBadge) {
                        includedBadge.classList.toggle('hidden', !isVenueRequired);
                        includedBadge.classList.toggle('inline-flex', isVenueRequired);
                    }

                    if (!venueIsSelected) {
                        checkbox.checked = false;
                    } else if (isVenueRequired && !checkbox.disabled) {
                        checkbox.checked = true;
                    }

                    const isSelected = venueIsSelected && checkbox.checked;
                    quantityWrap?.classList.toggle('hidden', !isSelected);
                    if (quantity) {
                        quantity.disabled = !isSelected;
                        quantity.required = isSelected;
                        if (isSelected && !quantity.value) quantity.value = '1';
                    }
                    row.classList.toggle('border-emerald-300', isSelected);
                    row.classList.toggle('bg-emerald-50/60', isSelected);
                });
            });
            updateSummary();
        };

        form.querySelectorAll('.equipment-checkbox').forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                if (checkbox.dataset.venueRequired === '1' && !checkbox.checked) {
                    checkbox.checked = true;
                }
                if (checkbox.checked && ['Wireless Microphones', 'Non-Wireless Microphones'].includes(checkbox.value)) {
                    const otherMicrophone = checkbox.value === 'Wireless Microphones' ? 'Non-Wireless Microphones' : 'Wireless Microphones';
                    const otherCheckbox = checkbox.closest('[data-venue-equipment-group]')?.querySelector(`.equipment-checkbox[value="${otherMicrophone}"]`);
                    if (otherCheckbox) otherCheckbox.checked = false;
                    document.getElementById('microphone-selection-error')?.classList.add('hidden');
                }
                updateVenueEquipmentDefaults();
            });
        });

        venueScheduleGroups.forEach((group) => {
            group.querySelectorAll('input[type="radio"][name$="[reservation_duration]"]').forEach((radio) => {
                radio.addEventListener('change', () => {
                    applyVenueDuration(group);
                    checkVenueAvailability();
                    updateSummary();
                });
            });
            group.querySelectorAll('.venue-schedule-input').forEach((field) => {
                field.addEventListener('input', () => {
                    if (field.name.endsWith('[start_date]')) {
                        const endDate = group.querySelector('[name$="[end_date]"]');
                        if (endDate) endDate.min = field.value || '{{ now()->toDateString() }}';
                        group.querySelectorAll('[data-calendar-url]').forEach((button) => {
                            const calendarUrl = new URL(button.dataset.calendarUrl, window.location.origin);
                            calendarUrl.searchParams.set('date', field.value || '{{ now()->toDateString() }}');
                            button.dataset.calendarUrl = calendarUrl.toString();
                        });
                    }
                    checkVenueAvailability();
                    updateSummary();
                });
                field.addEventListener('change', () => {
                    checkVenueAvailability();
                    updateSummary();
                });
            });
            applyVenueDuration(group);
        });

        venueInputs.forEach((input) => {
            input.addEventListener('mousedown', () => {
                input.dataset.wasChecked = input.checked ? 'true' : 'false';
            });
            input.addEventListener('click', (event) => {
                if (input.dataset.wasChecked === 'true' && input.checked) {
                    event.preventDefault();
                    input.checked = false;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            input.addEventListener('change', function () {
                toggleScheduleFields();
                venueSelectionState();
                updateVenueEquipmentDefaults();
                venueScheduleGroups.forEach((group) => {
                    const isSelected = group.closest('[data-venue-option]')?.querySelector('input[name="venue[]"]')?.checked === true;
                    if (!isSelected) return;

                    group.querySelectorAll('.venue-schedule-input').forEach((field) => {
                        field.required = true;
                    });
                    applyVenueDuration(group);
                });
                checkVenueAvailability();
                updateSummary();
            });
        });

        form.querySelectorAll('input, textarea, select').forEach((field) => {
            field.addEventListener('input', updateSummary);
            field.addEventListener('change', updateSummary);
        });

        const checklistRequiredFields = document.getElementById('checklist-required-fields');
        const checklistVenueAvailability = document.getElementById('checklist-venue-availability');
        const checklistDocumentUpload = document.getElementById('checklist-document-upload');
        const checklistESignature = document.getElementById('checklist-e-signature');

        const isChecklistFieldComplete = (field) => {
            if (!field) return true;
            if (field.type === 'radio' || field.type === 'checkbox') return field.checked;
            if (field.type === 'file') return Boolean(field.files && field.files.length);
            return String(field.value || '').trim() !== '';
        };

        const getChecklistTarget = (checklistId) => {
            const requestorType = form.dataset.requestorType || '';
            const positionSelect = form.querySelector('[name="requested_by_position"]');
            const otherPositionInput = form.querySelector('[name="requested_by_position_other"]');

            if (checklistId === 'checklist-required-fields') {
                const requiredFields = [
                    requestorType === 'outsider' ? form.querySelector('[name="organization_name"]') : form.querySelector('[name="college_id"]'),
                    requestorType === 'outsider' ? null : form.querySelector('[name="department_id"]'),
                    form.querySelector('[name="name_of_activity"]'),
                    form.querySelector('[name="purpose"]'),
                    form.querySelector('[name="expected_participants"]'),
                    form.querySelector('[name="requested_by_position"]'),
                    positionSelect?.value === 'Other' ? otherPositionInput : null,
                ];

                const missingField = requiredFields.find((field) => !isChecklistFieldComplete(field));
                if (missingField) return missingField;
                const selectedGroup = [...venueScheduleGroups].find((group) => {
                    return group.closest('[data-venue-option]')?.querySelector('input[name="venue[]"]')?.checked;
                });
                if (!selectedGroup) return form.querySelector('[name="venue[]"]');
                return [...selectedGroup.querySelectorAll('.venue-schedule-input')].find((field) => !field.value)
                    || selectedGroup.querySelector('input[type="radio"]:checked');
            }

            if (checklistId === 'checklist-venue-availability') {
                const selectedVenues = [...form.querySelectorAll('[name="venue[]"]:checked')];
                if (!selectedVenues.length) return form.querySelector('[name="venue[]"]') || null;

                const hasEquipmentSelection = form.querySelectorAll('.equipment-row .equipment-checkbox:checked').length > 0;
                const hasAvailableEquipmentSelected = [...form.querySelectorAll('.equipment-row')].filter((row) => {
                    return row.querySelector('.equipment-checkbox')?.checked;
                }).every((row) => {
                    const checkbox = row.querySelector('.equipment-checkbox');
                    const quantity = row.querySelector('.quantity-input-wrap input[type="number"]');
                    const requested = Number(quantity?.value);
                    return checkbox?.checked && Number.isInteger(requested) && requested >= 1 && requested <= Number(row.dataset.available || 0);
                });

                if (hasEquipmentSelection && !hasAvailableEquipmentSelected) {
                    return form.querySelector('.equipment-row .equipment-checkbox, .equipment-row input[type="number"]') || selectedVenues[0];
                }

                return null;
            }

            if (checklistId === 'checklist-document-upload') {
                const requestContext = form.querySelector('[name="request_context"]')?.value;
                const isStudent = form.dataset.isStudent === '1';
                const supportInput = isStudent && requestContext === 'student_organization'
                    ? form.querySelector('[name="activity_proposal_file"], [name="proposal_file"]')
                    : form.querySelector('[name="igp_receipt_file"], [name="activity_proposal_file"], [name="proposal_file"]');

                if (supportInput && !supportInput.files?.length) {
                    return supportInput;
                }

                return null;
            }

            if (checklistId === 'checklist-e-signature') {
                const eSignatureInput = form.querySelector('[name="e_signature_file"]');
                if (eSignatureInput && !(eSignatureInput.files?.length || form.dataset.hasSavedSignature === '1')) {
                    return eSignatureInput;
                }
                return null;
            }

            return null;
        };

        document.querySelectorAll('[data-checklist-target]').forEach((button) => {
            button.addEventListener('click', () => {
                const checklistId = button.dataset.checklistTarget;
                if (!checklistId || document.getElementById(checklistId)?.checked === true) {
                    return;
                }

                const target = getChecklistTarget(checklistId);
                if (!target) {
                    return;
                }

                target.scrollIntoView({ block: 'center', behavior: 'smooth' });
                target.focus({ preventScroll: true });
            });
        });

        const updateChecklistState = () => {
            const requestorType = form.dataset.requestorType;
            const positionSelect = form.querySelector('[name="requested_by_position"]');
            const otherPositionInput = form.querySelector('[name="requested_by_position_other"]');
            const requiredFields = [
                requestorType === 'outsider' ? form.querySelector('[name="organization_name"]') : form.querySelector('[name="college_id"]'),
                requestorType === 'outsider' ? null : form.querySelector('[name="department_id"]'),
                form.querySelector('[name="name_of_activity"]'),
                form.querySelector('[name="purpose"]'),
                form.querySelector('[name="expected_participants"]'),
                form.querySelector('[name="requested_by_position"]'),
                positionSelect?.value === 'Other' ? otherPositionInput : null,
            ];

            const selectedScheduleGroups = [...venueScheduleGroups].filter((group) => {
                return group.closest('[data-venue-option]')?.querySelector('input[name="venue[]"]')?.checked;
            });
            const schedulesComplete = selectedScheduleGroups.length > 0 && selectedScheduleGroups.every((group) => {
                return [...group.querySelectorAll('.venue-schedule-input')].every(isChecklistFieldComplete)
                    && Boolean(group.querySelector('input[type="radio"]:checked'));
            });
            const allRequiredComplete = requiredFields.every(isChecklistFieldComplete) && schedulesComplete;
            const selectedVenue = form.querySelector('[name="venue[]"]:checked');
            const hasEquipmentSelection = form.querySelectorAll('.equipment-row .equipment-checkbox:checked').length > 0;
            const hasAvailableEquipmentSelected = [...form.querySelectorAll('.equipment-row')].filter((row) => {
                return row.querySelector('.equipment-checkbox')?.checked;
            }).every((row) => {
                const quantity = row.querySelector('.quantity-input-wrap input[type="number"]');
                const requested = Number(quantity?.value);
                return Number.isInteger(requested) && requested >= 1 && requested <= Number(row.dataset.available || 0);
            });
            const isStudent = form.dataset.isStudent === '1';
            const requestContext = form.querySelector('[name="request_context"]')?.value;
            const activityProposalFile = form.querySelector('[name="activity_proposal_file"]')?.files?.length > 0;
            const proposalFile = form.querySelector('[name="proposal_file"]')?.files?.length > 0;
            const igpReceiptFile = form.querySelector('[name="igp_receipt_file"]')?.files?.length > 0;
            const eSignatureFile = form.querySelector('[name="e_signature_file"]')?.files?.length > 0 || form.dataset.hasSavedSignature === '1';
            const hasSupportingDocument = isStudent && requestContext === 'student_organization'
                ? (activityProposalFile || proposalFile)
                : (isStudent || requestorType === 'faculty') && !requestContext
                    ? (activityProposalFile || proposalFile)
                    : requestorType === 'outsider'
                        ? (!form.querySelector('[name="is_emergency"]')?.checked || activityProposalFile || proposalFile)
                        : igpReceiptFile;

            if (checklistRequiredFields) {
                checklistRequiredFields.checked = allRequiredComplete;
            }

            if (checklistVenueAvailability) {
                checklistVenueAvailability.checked = Boolean(selectedVenue)
                    && schedulesComplete
                    && venueAvailabilityState === true
                    && (!hasEquipmentSelection || hasAvailableEquipmentSelected);
            }

            if (checklistDocumentUpload) {
                checklistDocumentUpload.checked = hasSupportingDocument;
            }

            if (checklistESignature) {
                checklistESignature.checked = eSignatureFile;
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

        toggleScheduleFields();
        venueSelectionState();
        updateVenueEquipmentDefaults();
        updateSummary();
        updateChecklistState();
        checkVenueAvailability();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeRequestFormDraftUi, { once: true });
    } else {
        initializeRequestFormDraftUi();
    }
</script>
