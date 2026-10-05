@php
    $settingsRoute = $settingsRoute ?? 'requestor.settings';
    $showSignature = $showSignature ?? false;
    $showOrganization = $showOrganization ?? false;
    $isAdmin = $isAdmin ?? false;
    $preferences = $user->notification_preferences ?? ['request_updates' => true, 'security_alerts' => true];
    $roleLabel = $user->role_label ?? ucfirst(str_replace('_', ' ', $user->role));
    $displayName = trim((string) $user->name) !== '' ? $user->name : 'Account holder';
    $custodianResources = $user->isCustodian() ? $user->assignedCustodianResourceLabel() : '';
    $initials = collect(preg_split('/\s+/', $displayName))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<div class="mx-auto w-full max-w-6xl px-3 py-4 sm:px-4 sm:py-6 lg:px-6 lg:py-8">
    <header class="overflow-hidden rounded-3xl text-white shadow-xl" style="background-color: #0f172a; background-image: linear-gradient(135deg, #0f172a 0%, #1e293b 55%, #064e3b 100%);">
        <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:p-7">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-xl font-bold ring-1 ring-white/20" aria-hidden="true">{{ $initials }}</div>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-200">Account settings</p>
                <h1 class="mt-1 text-2xl font-bold sm:text-3xl">{{ $displayName }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-200">
                    <span>{{ $roleLabel }}</span>
                    <span class="text-slate-500" aria-hidden="true">·</span>
                    <span>{{ $user->username }}</span>
                </div>
                @if($custodianResources !== '')
                    <p class="mt-2 text-sm text-slate-300">Assigned resources: {{ $custodianResources }}</p>
                @endif
            </div>
            <span class="inline-flex w-fit items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold {{ $user->email_verified_at ? 'bg-emerald-400/15 text-emerald-100 ring-1 ring-emerald-300/30' : 'bg-amber-300/15 text-amber-100 ring-1 ring-amber-200/30' }}">
                <span class="h-2 w-2 rounded-full {{ $user->email_verified_at ? 'bg-emerald-300' : 'bg-amber-300' }}" aria-hidden="true"></span>
                Email {{ $user->email_verified_at ? 'verified' : 'verification pending' }}
            </span>
        </div>
    </header>

    <nav class="sticky top-2 z-20 my-5 overflow-x-auto rounded-2xl border border-slate-200 bg-white/95 p-2 shadow-sm backdrop-blur" aria-label="Account settings sections">
        <div class="flex min-w-max gap-2">
            <a href="#profile" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500">Profile</a>
            <a href="#notifications" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500">Notifications</a>
            @if($showSignature)
                <a href="#signature" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500">E-signature</a>
            @endif
            <a href="#security" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500">Security</a>
        </div>
    </nav>

    <div class="space-y-6">
            <section id="profile" class="scroll-mt-24 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="mb-5 border-b border-slate-100 pb-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Your information</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-950">Profile</h2>
                    <p class="mt-1 text-sm text-slate-600">Keep your contact and account details up to date.</p>
                </div>
                <form method="POST" action="{{ route($settingsRoute . '.profile') }}" class="mt-4 space-y-4">
                    @csrf
                    <div class="max-w-2xl">
                        <label for="settings_email" class="text-sm font-medium text-slate-700">Email</label>
                        <input id="settings_email" type="email" value="{{ $user->username }}" class="mt-1 w-full rounded-2xl border border-slate-200 bg-slate-100 px-3 py-2 text-slate-600" readonly aria-describedby="email-help">
                        <p id="email-help" class="mt-1 text-xs text-slate-500">Email changes require account verification and are not available here.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="settings_surname" class="text-sm font-medium text-slate-700">Surname</label>
                            <input id="settings_surname" type="text" name="surname" value="{{ old('surname', $user->surname) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2 @error('surname') border-red-400 bg-red-50 @enderror" autocomplete="family-name" aria-invalid="{{ $errors->has('surname') ? 'true' : 'false' }}" @if($errors->has('surname')) aria-describedby="settings_surname_error" @endif placeholder="Andales">
                            @error('surname')<p id="settings_surname_error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="settings_first_name" class="text-sm font-medium text-slate-700">First Name</label>
                            <input id="settings_first_name" type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2 @error('first_name') border-red-400 bg-red-50 @enderror" autocomplete="given-name" aria-invalid="{{ $errors->has('first_name') ? 'true' : 'false' }}" @if($errors->has('first_name')) aria-describedby="settings_first_name_error" @endif placeholder="Nick">
                            @error('first_name')<p id="settings_first_name_error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="settings_middle_name" class="text-sm font-medium text-slate-700">Middle Name</label>
                            <input id="settings_middle_name" type="text" name="middle_name" value="{{ old('middle_name', $user->middle_name) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2 @error('middle_name') border-red-400 bg-red-50 @enderror" autocomplete="additional-name" aria-invalid="{{ $errors->has('middle_name') ? 'true' : 'false' }}" @if($errors->has('middle_name')) aria-describedby="settings_middle_name_error" @endif placeholder="Vincent">
                            @error('middle_name')<p id="settings_middle_name_error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="settings_suffix" class="text-sm font-medium text-slate-700">Suffix</label>
                            <select id="settings_suffix" name="suffix" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 @error('suffix') border-red-400 bg-red-50 @enderror" aria-invalid="{{ $errors->has('suffix') ? 'true' : 'false' }}" @if($errors->has('suffix')) aria-describedby="settings_suffix_error" @endif>
                                @foreach(['' => 'None', 'Jr.' => 'Jr.', 'Sr.' => 'Sr.', 'II' => 'II', 'III' => 'III', 'IV' => 'IV', 'V' => 'V'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('suffix', $user->suffix) === $value || ($value === '' && in_array(strtolower((string) old('suffix', $user->suffix)), ['n/a', 'na', 'none'], true)))>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('suffix')<p id="settings_suffix_error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label for="settings_contact_number" class="text-sm font-medium text-slate-700">Contact Number</label>
                        <input id="settings_contact_number" type="tel" name="contact_number" value="{{ old('contact_number', $user->contact_number) }}" class="mt-1 w-full max-w-xl rounded-2xl border border-slate-200 px-3 py-2 @error('contact_number') border-red-400 bg-red-50 @enderror" autocomplete="tel" aria-invalid="{{ $errors->has('contact_number') ? 'true' : 'false' }}" @if($errors->has('contact_number')) aria-describedby="settings_contact_number_error" @endif>
                        @error('contact_number')<p id="settings_contact_number_error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                    </div>
                    @if($isAdmin)
                        <div>
                            <label for="settings_office_or_organization" class="text-sm font-medium text-slate-700">Office / Organization</label>
                            <input id="settings_office_or_organization" type="text" name="office_or_organization" value="{{ old('office_or_organization', $user->office_or_organization) }}" class="mt-1 w-full max-w-xl rounded-2xl border border-slate-200 px-3 py-2 @error('office_or_organization') border-red-400 bg-red-50 @enderror" aria-invalid="{{ $errors->has('office_or_organization') ? 'true' : 'false' }}" @if($errors->has('office_or_organization')) aria-describedby="settings_office_or_organization_error" @endif>
                            @error('office_or_organization')<p id="settings_office_or_organization_error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                    @endif
                    @if($showOrganization && $user->isStudent())
                        <div>
                            <label for="settings_school_id_number" class="text-sm font-medium text-slate-700">Student ID</label>
                            <input id="settings_school_id_number" type="text" name="school_id_number" value="{{ old('school_id_number', $user->school_id_number) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2 @error('school_id_number') border-red-400 bg-red-50 @enderror" aria-invalid="{{ $errors->has('school_id_number') ? 'true' : 'false' }}" @if($errors->has('school_id_number')) aria-describedby="settings_school_id_number_error" @endif>
                            @error('school_id_number')<p id="settings_school_id_number_error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="settings_college_id" class="text-sm font-medium text-slate-700">Registered College</label>
                            <select id="settings_college_id" name="college_id" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2 @error('college_id') border-red-400 bg-red-50 @enderror" aria-invalid="{{ $errors->has('college_id') ? 'true' : 'false' }}" @if($errors->has('college_id')) aria-describedby="settings_college_id_error" @endif>
                                <option value="">Select college</option>
                                @foreach(($colleges ?? collect()) as $college)
                                    <option value="{{ $college->id }}" @selected(old('college_id', $user->college_id) == $college->id)>{{ $college->name }}</option>
                                @endforeach
                            </select>
                            @error('college_id')<p id="settings_college_id_error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="settings_department_id" class="text-sm font-medium text-slate-700">Registered Department</label>
                            <select id="settings_department_id" name="department_id" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2 @error('department_id') border-red-400 bg-red-50 @enderror" aria-invalid="{{ $errors->has('department_id') ? 'true' : 'false' }}" @if($errors->has('department_id')) aria-describedby="settings_department_id_error" @endif>
                                <option value="">Select department</option>
                                @foreach(($colleges ?? collect())->flatMap->departments as $department)
                                    <option value="{{ $department->id }}" @selected(old('department_id', $user->department_id) == $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </select>
                            @error('department_id')<p id="settings_department_id_error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                    @elseif($showOrganization && $user->isFaculty())
                        <div>
                            <label for="settings_faculty_id" class="text-sm font-medium text-slate-700">Faculty ID</label>
                            <input id="settings_faculty_id" type="text" name="faculty_id" value="{{ old('faculty_id', $user->faculty_id) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2 @error('faculty_id') border-red-400 bg-red-50 @enderror" aria-invalid="{{ $errors->has('faculty_id') ? 'true' : 'false' }}" @if($errors->has('faculty_id')) aria-describedby="settings_faculty_id_error" @endif>
                            @error('faculty_id')<p id="settings_faculty_id_error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="settings_position" class="text-sm font-medium text-slate-700">Position</label>
                            <input id="settings_position" type="text" name="position" value="{{ old('position', $user->position) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2 @error('position') border-red-400 bg-red-50 @enderror" aria-invalid="{{ $errors->has('position') ? 'true' : 'false' }}" @if($errors->has('position')) aria-describedby="settings_position_error" @endif>
                            @error('position')<p id="settings_position_error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="settings_college_id" class="text-sm font-medium text-slate-700">Registered College</label>
                            <select id="settings_college_id" name="college_id" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2 @error('college_id') border-red-400 bg-red-50 @enderror" aria-invalid="{{ $errors->has('college_id') ? 'true' : 'false' }}" @if($errors->has('college_id')) aria-describedby="settings_college_id_error" @endif>
                                <option value="">Select college</option>
                                @foreach(($colleges ?? collect()) as $college)
                                    <option value="{{ $college->id }}" @selected(old('college_id', $user->college_id) == $college->id)>{{ $college->name }}</option>
                                @endforeach
                            </select>
                            @error('college_id')<p id="settings_college_id_error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="settings_department_id" class="text-sm font-medium text-slate-700">Registered Department</label>
                            <select id="settings_department_id" name="department_id" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2 @error('department_id') border-red-400 bg-red-50 @enderror" aria-invalid="{{ $errors->has('department_id') ? 'true' : 'false' }}" @if($errors->has('department_id')) aria-describedby="settings_department_id_error" @endif>
                                <option value="">Select department</option>
                                @foreach(($colleges ?? collect())->flatMap->departments as $department)
                                    <option value="{{ $department->id }}" @selected(old('department_id', $user->department_id) == $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </select>
                            @error('department_id')<p id="settings_department_id_error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                    @endif
                    @if($user->isCustodian())
                        <div>
                            <label for="settings_department" class="text-sm font-medium text-slate-700">Department</label>
                            <input id="settings_department" type="text" name="department" value="{{ old('department', $user->department) }}" class="mt-1 w-full max-w-xl rounded-2xl border border-slate-200 px-3 py-2 @error('department') border-red-400 bg-red-50 @enderror" aria-invalid="{{ $errors->has('department') ? 'true' : 'false' }}" @if($errors->has('department')) aria-describedby="settings_department_error" @endif>
                            @error('department')<p id="settings_department_error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                    @endif
                    @if($showOrganization && $user->isRequestor() && !$user->isStudent() && !$user->isFaculty())
                        <div>
                            <label for="settings_registered_college" class="text-sm font-medium text-slate-700">Registered College</label>
                            <input id="settings_registered_college" type="text" value="{{ $user->college?->name ?? 'Not registered' }}" class="mt-1 w-full rounded-2xl border border-slate-200 bg-slate-100 px-3 py-2 text-slate-600" readonly>
                        </div>
                        <div>
                            <label for="settings_registered_department" class="text-sm font-medium text-slate-700">Registered Department</label>
                            <input id="settings_registered_department" type="text" value="{{ $user->department ?? 'Not registered' }}" class="mt-1 w-full rounded-2xl border border-slate-200 bg-slate-100 px-3 py-2 text-slate-600" readonly>
                        </div>
                    @endif
                    @if($showOrganization && ($user->isOutsider() || $user->isStudentOrganization()))
                        <div>
                            <label for="settings_office_or_organization" class="text-sm font-medium text-slate-700">Organization / Office</label>
                            <input id="settings_office_or_organization" type="text" name="office_or_organization" value="{{ old('office_or_organization', $user->office_or_organization) }}" class="mt-1 w-full max-w-xl rounded-2xl border border-slate-200 px-3 py-2 @error('office_or_organization') border-red-400 bg-red-50 @enderror" aria-invalid="{{ $errors->has('office_or_organization') ? 'true' : 'false' }}" @if($errors->has('office_or_organization')) aria-describedby="settings_office_or_organization_error" @endif>
                            @error('office_or_organization')<p id="settings_office_or_organization_error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                    @endif
                    <div class="border-t border-slate-100 pt-4">
                        <button type="submit" style="background-color: #047857; color: #ffffff;" class="w-full rounded-xl px-5 py-3 text-sm font-semibold shadow-sm transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 sm:w-auto">Save Profile</button>
                    </div>
                </form>
            </section>

            <section id="notifications" class="scroll-mt-24 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="mb-5 border-b border-slate-100 pb-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Stay informed</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-950">Notifications</h2>
                    <p class="mt-1 text-sm text-slate-600">Choose which optional account and reservation notifications are delivered. Password reset emails are always sent when requested.</p>
                </div>
                <form method="POST" action="{{ route($settingsRoute . '.notifications') }}" class="mt-4 space-y-4">
                    @csrf
                    <label for="request_updates" class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 p-4 text-sm text-slate-800 transition hover:border-emerald-300 hover:bg-emerald-50/40">
                        <input id="request_updates" type="checkbox" name="request_updates" value="1" @checked($preferences['request_updates'] ?? true) class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600">
                        <span><strong class="block font-semibold">Request status updates</strong><span class="mt-1 block text-slate-600">Reservation submissions, status changes, schedule reminders, resource availability, and equipment returns.</span></span>
                    </label>
                    <label for="security_alerts" class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 p-4 text-sm text-slate-800 transition hover:border-emerald-300 hover:bg-emerald-50/40">
                        <input id="security_alerts" type="checkbox" name="security_alerts" value="1" @checked($preferences['security_alerts'] ?? true) class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600">
                        <span><strong class="block font-semibold">Security alerts</strong><span class="mt-1 block text-slate-600">Get an in-app and email alert when your password changes.</span></span>
                    </label>
                    <button type="submit" style="background-color: #047857; color: #ffffff;" class="w-full rounded-xl px-5 py-3 text-sm font-semibold shadow-sm transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 sm:w-auto">Save Preferences</button>
                </form>
            </section>

            @if($showSignature)
                <section id="signature" class="scroll-mt-24 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                    <div class="mb-5 border-b border-slate-100 pb-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Documents</p>
                        <h2 class="mt-1 text-xl font-bold text-slate-950">E-signature Management</h2>
                        <p class="mt-1 text-sm text-slate-600">Manage the signature image used for printable facility documents.</p>
                    </div>

                    @if($user->e_signature_file)
                        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Current e-signature</p>
                            <img src="{{ route('user.signature', ['user' => $user->id]) }}" alt="Current e-signature" class="h-20 max-w-full rounded-lg border border-slate-200 bg-white object-contain p-2">
                        </div>
                    @else
                        <div class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-3 text-sm text-slate-500">
                            No e-signature uploaded yet.
                        </div>
                    @endif

                    <form method="POST" action="{{ route($settingsRoute . '.signature') }}" enctype="multipart/form-data" class="mt-4 space-y-4" data-signature-form>
                        @csrf
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                            <label for="e_signature_file" class="mb-2 block text-sm font-medium text-slate-700">Choose a PNG signature image</label>
                            <p id="signature-file-help" class="mb-3 text-xs leading-5 text-slate-600">PNG only, transparent background preferred, maximum 500 KB. Use a landscape image between 200x50 and 2400x1200 pixels.</p>
                            <input id="e_signature_file" type="file" name="e_signature_file" accept="image/png" required data-signature-input aria-describedby="signature-file-help signature-client-error @error('e_signature_file') signature-server-error @enderror" aria-invalid="{{ $errors->has('e_signature_file') ? 'true' : 'false' }}" class="block w-full rounded-xl border border-slate-300 bg-white p-3 text-sm text-slate-700 file:mr-3 file:rounded-full file:border-0 file:bg-emerald-700 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white @error('e_signature_file') border-red-400 bg-red-50 @enderror">
                            @error('e_signature_file')<p id="signature-server-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                            <p id="signature-client-error" class="mt-2 hidden text-sm text-red-700" role="alert" aria-live="polite"></p>
                            <img data-signature-preview class="mt-3 hidden max-h-28 w-full rounded-lg border border-slate-200 bg-white object-contain p-2" alt="Selected signature preview">
                        </div>
                        <label for="e_signature_confirmation" class="flex cursor-pointer items-start gap-3 text-sm text-slate-700"><input id="e_signature_confirmation" type="checkbox" name="e_signature_confirmation" value="1" required class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600"> <span>I confirm this image is my signature and may be used on approved request documents.</span></label>
                        @error('e_signature_confirmation')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                        <button type="submit" style="background-color: #047857; color: #ffffff;" class="w-full rounded-xl px-5 py-3 text-sm font-semibold shadow-sm transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 sm:w-auto">Save E-signature</button>
                    </form>
                </section>
            @endif

            <section id="security" class="scroll-mt-24 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="mb-5 border-b border-slate-100 pb-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Protect your account</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-950">Security</h2>
                    <p class="mt-1 text-sm text-slate-600">Use a strong password and keep your account information secure.</p>
                </div>
                @if(!$isAdmin)
                    <div class="mb-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <h3 class="font-semibold text-slate-900">Account Security</h3>
                        <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                            <div><dt class="text-slate-500">Email verification</dt><dd class="mt-1 font-medium text-slate-800">{{ $user->email_verified_at ? 'Verified' : 'Pending' }}</dd></div>
                            <div><dt class="text-slate-500">Account role</dt><dd class="mt-1 font-medium text-slate-800">{{ $roleLabel }}</dd></div>
                        </dl>
                    </div>
                @endif
                <h3 class="font-semibold text-slate-900">Change Password</h3>
                <form method="POST" action="{{ route($settingsRoute . '.password') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label for="current_password" class="mb-1 block text-sm font-medium text-slate-700">Current password</label>
                        <div class="pitfr-password-wrapper">
                        <input type="password" name="current_password" id="current_password" autocomplete="current-password" required aria-invalid="{{ $errors->has('current_password') ? 'true' : 'false' }}" @if($errors->has('current_password')) aria-describedby="current_password_error" @endif class="pitfr-password-input w-full rounded-2xl border border-slate-200 px-3 py-2 @error('current_password') border-red-400 bg-red-50 @enderror">
                        <button type="button" data-password-toggle-target="#current_password" aria-label="Show password" class="password-toggle pitfr-password-toggle"></button>
                        </div>
                        @error('current_password')<p id="current_password_error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="account_new_password" class="mb-1 block text-sm font-medium text-slate-700">New password</label>
                        <div class="pitfr-password-wrapper">
                        <input type="password" name="password" id="account_new_password" autocomplete="new-password" required aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @if($errors->has('password')) aria-describedby="account_new_password_error" @endif class="pitfr-password-input w-full rounded-2xl border border-slate-200 px-3 py-2 @error('password') border-red-400 bg-red-50 @enderror">
                        <button type="button" data-password-toggle-target="#account_new_password" aria-label="Show password" class="password-toggle pitfr-password-toggle"></button>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Use at least 12 characters.</p>
                        @error('password')<p id="account_new_password_error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="account_password_confirmation" class="mb-1 block text-sm font-medium text-slate-700">Confirm new password</label>
                        <div class="pitfr-password-wrapper">
                        <input type="password" name="password_confirmation" id="account_password_confirmation" autocomplete="new-password" required class="pitfr-password-input w-full rounded-2xl border border-slate-200 px-3 py-2">
                        <button type="button" data-password-toggle-target="#account_password_confirmation" aria-label="Show password" class="password-toggle pitfr-password-toggle"></button>
                        </div>
                    </div>
                    <button type="submit" style="background-color: #0f172a; color: #ffffff;" class="w-full rounded-xl px-5 py-3 text-sm font-semibold shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 sm:w-auto">Update Password</button>
                </form>
            </section>

    </div>
</div>

<script>
document.querySelectorAll('[data-signature-form]').forEach(function (form) {
    const input = form.querySelector('[data-signature-input]');
    const preview = form.querySelector('[data-signature-preview]');
    const error = form.querySelector('[data-signature-client-error]');
    if (!input || !preview || !error) return;
    let previewUrl = null;

    input.addEventListener('change', function () {
        const file = input.files?.[0];
        error.textContent = '';
        error.classList.add('hidden');
        input.setAttribute('aria-invalid', 'false');
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }
        if (!file) {
            preview.removeAttribute('src');
            preview.classList.add('hidden');
            return;
        }

        if (file.type !== 'image/png' || file.size > 512000) {
            input.value = '';
            preview.removeAttribute('src');
            preview.classList.add('hidden');
            error.textContent = file.type !== 'image/png'
                ? 'Please choose a PNG image.'
                : 'The image must be no larger than 500 KB.';
            error.classList.remove('hidden');
            input.setAttribute('aria-invalid', 'true');
            return;
        }

        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        preview.classList.remove('hidden');
    });
});
</script>
