<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — PIT Facility Request Portal</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen overflow-x-hidden bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 p-2 text-slate-100 sm:p-4">

<div class="mx-auto w-full max-w-none overflow-hidden rounded-[24px] border border-white/10 bg-slate-950/80 shadow-[0_60px_120px_rgba(15,23,42,0.55)] backdrop-blur-xl sm:rounded-[40px] md:max-w-6xl">
    <div class="space-y-0">
        <section class="bg-gradient-to-br from-emerald-900 via-slate-900 to-slate-950 p-4 text-white relative overflow-hidden sm:p-6 lg:p-10">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_rgba(255,255,255,0.12),_transparent_30%)]"></div>
            <div class="relative z-10 mx-auto w-full max-w-none space-y-6 md:max-w-5xl md:space-y-10">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <img src="{{ asset('images/PIT-LOGO.jpg') }}" alt="PIT Logo" class="h-14 w-14 rounded-full border border-white/20 object-cover shadow-lg">
                        <div>
                            <p class="text-base font-semibold uppercase tracking-[0.25em] text-white">Palompon Institute of Technology</p>
                            <p class="text-xs uppercase tracking-[0.32em] text-slate-200/75">Facility Request System</p>
                        </div>
                    </div>
                    <div class="rounded-full border border-white/15 bg-white/10 px-4 py-2 text-[11px] uppercase tracking-[0.32em] text-slate-200">Modern request workflows</div>
                </div>

                <div class="max-w-3xl space-y-4">
                    <p class="text-sm uppercase tracking-[0.3em] text-emerald-200 font-semibold">Welcome to PITFR</p>
                    <h1 class="text-4xl font-semibold leading-tight">Register for secure facility and equipment booking.</h1>
                    <p class="text-sm leading-7 text-slate-200/90">Create an account to submit requests, track approvals, and manage your next reservation with a clean and responsive portal.</p>
                </div>

                <div class="rounded-[32px] border border-white/10 bg-white/5 p-6 sm:p-8 shadow-inner">
                    <p class="text-sm font-semibold uppercase tracking-[0.24em] text-emerald-200">Why PITFR works</p>
                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-3xl bg-slate-950/30 p-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-600 text-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                                </div>
                                <p class="text-sm font-semibold text-white">Fast submissions</p>
                            </div>
                            <p class="mt-3 text-xs leading-5 text-slate-200/80">Submit requests quickly with clear approval guidance.</p>
                        </div>
                        <div class="rounded-3xl bg-slate-950/30 p-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-600 text-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/></svg>
                                </div>
                                <p class="text-sm font-semibold text-white">Device-ready</p>
                            </div>
                            <p class="mt-3 text-xs leading-5 text-slate-200/80">Use the portal from desktops, tablets, or mobile devices.</p>
                        </div>
                        <div class="rounded-3xl bg-slate-950/30 p-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-600 text-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </div>
                                <p class="text-sm font-semibold text-white">Approval tracking</p>
                            </div>
                            <p class="mt-3 text-xs leading-5 text-slate-200/80">Keep visibility on every request and status update.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-white p-4 sm:p-6 lg:p-10">
            <div class="mx-auto w-full max-w-none md:max-w-5xl">
                <div class="mb-6 space-y-4 md:mb-10">
                    <p class="text-xs uppercase tracking-[0.32em] text-emerald-600 font-semibold">Create account</p>
                    <h2 class="text-3xl font-semibold text-slate-950">Create your PITFR account</h2>
                    <p class="text-sm text-slate-500">Register to begin submitting requests and tracking your facility reservations.</p>
                </div>

                @if($errors->any())
                    <div class="mb-6 rounded-3xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 shadow-sm">
                        <div class="flex items-start gap-3">
                            <svg class="mt-1 h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p>{{ $errors->first() }}</p>
                        </div>
                    </div>
                @endif

                <div class="rounded-[24px] border border-slate-200 bg-slate-50 p-4 shadow-xl shadow-slate-950/5 sm:p-6 md:rounded-[28px] md:p-8 lg:p-10">
                    <div class="space-y-10">
                        <div class="space-y-6">
                            <p class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">I am a…</p>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <button type="button" data-type="student" class="requestor-type-button rounded-[18px] border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-900 transition hover:border-slate-400">🎓 Student</button>
                                <button type="button" data-type="faculty" class="requestor-type-button rounded-[18px] border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-900 transition hover:border-slate-400">👨‍🏫 Faculty</button>
                                <button type="button" data-type="outsider" class="requestor-type-button rounded-[18px] border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-900 transition hover:border-slate-400">🏢 External / Org</button>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('register.post') }}" class="space-y-8">
                            @csrf
                            <input type="hidden" id="requestor_type" name="requestor_type" value="{{ old('requestor_type', 'student') }}" />

                            <div class="grid gap-6 sm:grid-cols-2">
                                <div class="space-y-3">
                                    <label class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Full name</label>
                                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="ex: Daniel Zrael C. Barro" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition duration-200 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100" />
                                    @error('name')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                                </div>
                                <div class="space-y-3">
                                    <label class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Phone number</label>
                                    <input type="text" name="contact_number" value="{{ old('contact_number') }}" placeholder="09171234567" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition duration-200 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100" />
                                    @error('contact_number')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div class="space-y-3">
                                <label class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Email address</label>
                                <input type="email" name="username" value="{{ old('username') }}" required placeholder="you@example.com" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition duration-200 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100" />
                                @error('username')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                            </div>

                            <div class="grid gap-6 sm:grid-cols-2">
                                <div class="space-y-4" id="department-group">
                                    <label class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">College / department</label>
                                    <input type="text" id="departmentInput" name="department" value="{{ old('department') }}" placeholder="College of Engineering" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition duration-200 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100" />
                                    <p class="text-xs text-slate-400">Complete College Name.</p>
                                </div>
                                <div class="space-y-4" id="school-id-group">
                                    <label class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Student ID</label>
                                    <input type="text" name="school_id_number" value="{{ old('school_id_number') }}" placeholder="23-0098-635" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition duration-200 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100" />
                                    @error('school_id_number')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div class="grid gap-6 sm:grid-cols-2">
                                <div class="space-y-4">
                                    <label class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Password</label>
                                    <div class="relative password-toggle-wrapper">
                                        <input type="password" name="password" id="register_password" required placeholder="At least 6 characters" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 pr-12 text-sm text-slate-900 shadow-sm outline-none transition duration-200 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100" />
                                        <button type="button" data-password-toggle-target="#register_password" aria-label="Show password" class="password-toggle absolute inset-y-0 right-4 inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-500 transition hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-slate-50">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                    </div>
                                    @error('password')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                                </div>
                                <div class="space-y-4">
                                    <label class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Confirm password</label>
                                    <div class="relative password-toggle-wrapper">
                                        <input type="password" name="password_confirmation" id="register_password_confirmation" required placeholder="Re-enter password" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 pr-12 text-sm text-slate-900 shadow-sm outline-none transition duration-200 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100" />
                                        <button type="button" data-password-toggle-target="#register_password_confirmation" aria-label="Show password" class="password-toggle absolute inset-y-0 right-4 inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-500 transition hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-slate-50">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-4 hidden" id="office-org-group">
                                <label id="officeOrgLabel" class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Office / organization</label>
                                <input type="text" id="officeOrgInput" name="office_or_organization" value="{{ old('office_or_organization') }}" placeholder="External partner or individual" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition duration-200 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100" />
                                @error('office_or_organization')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                                <p class="text-xs text-slate-400">Leave blank or type “Individual / Personal” if booking as an external guest.</p>
                            </div>

                            <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-sm text-slate-600">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-emerald-600 hover:text-emerald-700 transition">Sign in</a></p>
                                <button type="submit" class="w-full rounded-[28px] bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-xl shadow-emerald-600/20 transition hover:bg-emerald-700 hover:-translate-y-0.5 sm:w-auto">Create account</button>
                            </div>
                        </form>
                    </div>
                    <p class="mt-10 text-center text-xs uppercase tracking-[0.3em] text-slate-400">© {{ date('Y') }} Palompon Institute of Technology</p>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
    const typeField = document.getElementById('requestor_type');
    const schoolIdGroup = document.getElementById('school-id-group');
    const officeOrgGroup = document.getElementById('office-org-group');
    const officeOrgLabel = document.getElementById('officeOrgLabel');
    const officeOrgInput = document.getElementById('officeOrgInput');
    const departmentGroup = document.getElementById('department-group');
    const departmentInput = document.getElementById('departmentInput');

    function updateRequestorTypeFields() {
        const selected = typeField.value;
        schoolIdGroup.classList.toggle('hidden', selected !== 'student');
        const showOffice = selected === 'outsider';
        officeOrgGroup.classList.toggle('hidden', !showOffice);

        if (showOffice) {
            officeOrgLabel.textContent = 'Organization / Purpose of use (e.g. external partner)';
            officeOrgInput.placeholder = "Enter organization name, or type 'Individual / Personal' if booking as an external guest";
        } else {
            officeOrgLabel.textContent = 'Office / organization';
            officeOrgInput.placeholder = 'External partner or individual';
        }

        const showDept = selected === 'student' || selected === 'faculty';
        departmentGroup.classList.toggle('hidden', !showDept);
        departmentInput.disabled = !showDept;
        if (!showDept) {
            departmentInput.value = '';
        }
    }

    function selectRequestorType(type) {
        typeField.value = type;
        document.querySelectorAll('.requestor-type-button').forEach(button => {
            const isActive = button.dataset.type === type;
            button.classList.toggle('bg-emerald-50', isActive);
            button.classList.toggle('border-emerald-600', isActive);
            button.classList.toggle('text-emerald-700', isActive);
            button.classList.toggle('bg-white', !isActive);
            button.classList.toggle('text-slate-900', !isActive);
        });
        updateRequestorTypeFields();
    }

    document.querySelectorAll('.requestor-type-button').forEach(button => {
        button.addEventListener('click', () => selectRequestorType(button.dataset.type));
    });

    selectRequestorType(typeField.value || 'student');
</script>
