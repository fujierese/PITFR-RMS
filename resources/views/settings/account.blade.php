@php
    $settingsRoute = $settingsRoute ?? 'requestor.settings';
    $showSignature = $showSignature ?? false;
    $showOrganization = $showOrganization ?? false;
    $isAdmin = $isAdmin ?? false;
    $preferences = $user->notification_preferences ?? ['request_updates' => true, 'security_alerts' => true];
@endphp

<div class="mx-auto w-full max-w-none px-3 py-4 sm:px-4 sm:py-6 lg:max-w-5xl lg:px-6 lg:py-8">
    <div class="overflow-hidden rounded-3xl bg-white shadow-xl ring-1 ring-slate-200/60">
        <div class="border-b border-slate-200 bg-slate-50 px-4 py-4 sm:px-6 sm:py-5">
            <h1 class="text-2xl font-semibold text-slate-900">Account Settings</h1>
            <p class="mt-2 text-sm text-slate-600">Manage the account details and notifications available to your role.</p>
        </div>

        <div class="grid gap-4 p-4 sm:gap-6 sm:p-6 lg:grid-cols-2">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Profile Information</h2>
                <form method="POST" action="{{ route($settingsRoute . '.profile') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label class="text-sm font-medium text-slate-700">Email</label>
                        <input type="email" value="{{ $user->username }}" class="mt-1 w-full rounded-2xl border border-slate-200 bg-slate-100 px-3 py-2 text-slate-600" readonly aria-describedby="email-help">
                        <p id="email-help" class="mt-1 text-xs text-slate-500">Email changes require account verification and are not available here.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-slate-700">Surname</label>
                            <input type="text" name="surname" value="{{ old('surname', $user->surname) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2" placeholder="Andales">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">First Name</label>
                            <input type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2" placeholder="Nick">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Middle Name</label>
                            <input type="text" name="middle_name" value="{{ old('middle_name', $user->middle_name) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2" placeholder="Vincent">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Suffix</label>
                            <input type="text" name="suffix" value="{{ old('suffix', $user->suffix) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2" placeholder="Jr.">
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Contact Number</label>
                        <input type="text" name="contact_number" value="{{ old('contact_number', $user->contact_number) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2">
                    </div>
                    @if($user->isStudent())
                        <div>
                            <label class="text-sm font-medium text-slate-700">Student ID</label>
                            <input type="text" name="school_id_number" value="{{ old('school_id_number', $user->school_id_number) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">College</label>
                            <select name="college_id" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2">
                                <option value="">Select college</option>
                                @foreach(($colleges ?? collect()) as $college)
                                    <option value="{{ $college->id }}" @selected(old('college_id', $user->college_id) == $college->id)>{{ $college->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Department</label>
                            <select name="department_id" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2">
                                <option value="">Select department</option>
                                @foreach(($colleges ?? collect())->flatMap->departments as $department)
                                    <option value="{{ $department->id }}" @selected(old('department_id', $user->department_id) == $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif($user->isFaculty())
                        <div>
                            <label class="text-sm font-medium text-slate-700">Faculty ID</label>
                            <input type="text" name="faculty_id" value="{{ old('faculty_id', $user->faculty_id) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Position</label>
                            <input type="text" name="position" value="{{ old('position', $user->position) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">College</label>
                            <select name="college_id" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2">
                                <option value="">Select college</option>
                                @foreach(($colleges ?? collect()) as $college)
                                    <option value="{{ $college->id }}" @selected(old('college_id', $user->college_id) == $college->id)>{{ $college->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Department</label>
                            <select name="department_id" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2">
                                <option value="">Select department</option>
                                @foreach(($colleges ?? collect())->flatMap->departments as $department)
                                    <option value="{{ $department->id }}" @selected(old('department_id', $user->department_id) == $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @if($showOrganization)
                        <div>
                            <label class="text-sm font-medium text-slate-700">Registered College</label>
                            <input type="text" value="{{ $user->college?->name ?? 'Not assigned' }}" class="mt-1 w-full rounded-2xl border border-slate-200 bg-slate-100 px-3 py-2 text-slate-600" readonly>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Registered Department</label>
                            <input type="text" value="{{ $user->departmentRecord?->name ?? $user->department ?? 'Not assigned' }}" class="mt-1 w-full rounded-2xl border border-slate-200 bg-slate-100 px-3 py-2 text-slate-600" readonly>
                    @elseif($user->isCustodian())
                        <div>
                            <label class="text-sm font-medium text-slate-700">Department</label>
                            <input type="text" name="department" value="{{ old('department', $user->department) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2">
                        </div>
                    @endif
                    @if($showOrganization && ($user->isOutsider() || $user->isStudentOrganization()))
                        <div>
                            <label class="text-sm font-medium text-slate-700">Organization / Office</label>
                            <input type="text" name="office_or_organization" value="{{ old('office_or_organization', $user->office_or_organization) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2">
                        </div>
                    @endif
                    <button type="submit" class="w-full rounded-2xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white sm:w-auto">Save Profile</button>
                </form>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Email Notifications</h2>
                <form method="POST" action="{{ route($settingsRoute . '.notifications') }}" class="mt-4 space-y-4">
                    @csrf
                    <label class="flex items-center gap-3 text-sm text-slate-700"><input type="checkbox" name="request_updates" value="1" @checked($preferences['request_updates'] ?? true)> Request status updates</label>
                    <label class="flex items-center gap-3 text-sm text-slate-700"><input type="checkbox" name="security_alerts" value="1" @checked($preferences['security_alerts'] ?? true)> Security alerts</label>
                    <button type="submit" class="w-full rounded-2xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white sm:w-auto">Save Preferences</button>
                </form>
            </section>

            @if($showSignature)
                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">E-signature Management</h2>
                    <p class="mt-2 text-sm text-slate-600">Upload the signature image used for printable facility documents.</p>
                    @if($user->e_signature_file)
                        <img src="{{ route('user.signature', ['user' => $user->id]) }}" alt="Current e-signature" class="mt-4 h-16 max-w-full object-contain">
                    @endif
                    <form method="POST" action="{{ route($settingsRoute . '.signature') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                        @csrf
                        <input type="file" name="e_signature_file" accept="image/jpeg,image/png" required class="block w-full text-sm text-slate-600">
                        <button type="submit" class="w-full rounded-2xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white sm:w-auto">Save E-signature</button>
                    </form>
                </section>
            @endif

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Change Password</h2>
                <form method="POST" action="{{ route($settingsRoute . '.password') }}" class="mt-4 space-y-4">
                    @csrf
                    <div class="pitfr-password-wrapper">
                        <input type="password" name="current_password" id="current_password" placeholder="Current password" required class="pitfr-password-input w-full rounded-2xl border border-slate-200 px-3 py-2">
                        <button type="button" data-password-toggle-target="#current_password" aria-label="Show password" class="password-toggle pitfr-password-toggle"></button>
                    </div>
                    <div class="pitfr-password-wrapper">
                        <input type="password" name="password" id="account_new_password" placeholder="New password" required class="pitfr-password-input w-full rounded-2xl border border-slate-200 px-3 py-2">
                        <button type="button" data-password-toggle-target="#account_new_password" aria-label="Show password" class="password-toggle pitfr-password-toggle"></button>
                    </div>
                    <div class="pitfr-password-wrapper">
                        <input type="password" name="password_confirmation" id="account_password_confirmation" placeholder="Confirm new password" required class="pitfr-password-input w-full rounded-2xl border border-slate-200 px-3 py-2">
                        <button type="button" data-password-toggle-target="#account_password_confirmation" aria-label="Show password" class="password-toggle pitfr-password-toggle"></button>
                    </div>
                    <button type="submit" class="w-full rounded-2xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white sm:w-auto">Update Password</button>
                </form>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Account Security</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Email verification</dt><dd class="font-medium text-slate-800">{{ $user->email_verified_at ? 'Verified' : 'Pending' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Account role</dt><dd class="font-medium text-slate-800">{{ $user->roleLabel ?? ucfirst($user->role) }}</dd></div>
                </dl>
                @if($isAdmin)
                    <p class="mt-4 text-sm text-slate-600">Administrative account security is limited to verified email status and password controls.</p>
                @endif
            </section>
        </div>
    </div>
</div>
