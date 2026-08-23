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
                    <div>
                        <label class="text-sm font-medium text-slate-700">Full Name</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2" required>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Contact Number</label>
                        <input type="text" name="contact_number" value="{{ old('contact_number', $user->contact_number) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2">
                    </div>
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
                    @if($showOrganization && $user->isOutsider())
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
                        <img src="{{ url('storage/documents/e_signature/users/' . $user->e_signature_file) }}" alt="Current e-signature" class="mt-4 h-16 max-w-full object-contain">
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
                    <input type="password" name="current_password" placeholder="Current password" required class="w-full rounded-2xl border border-slate-200 px-3 py-2">
                    <input type="password" name="password" placeholder="New password" required class="w-full rounded-2xl border border-slate-200 px-3 py-2">
                    <input type="password" name="password_confirmation" placeholder="Confirm new password" required class="w-full rounded-2xl border border-slate-200 px-3 py-2">
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
