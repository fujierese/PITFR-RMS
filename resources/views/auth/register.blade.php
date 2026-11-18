<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('images/PIT-LOGO.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/PIT-LOGO.png') }}">
    <title>Register - PIT Facility Request Portal</title>
    @if (app()->runningUnitTests())
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="h-screen overflow-hidden bg-slate-100 text-slate-900">
    <div class="relative h-screen w-full lg:grid lg:h-screen lg:grid-cols-[1.12fr_0.88fr]">
        <section class="relative hidden min-h-screen bg-white lg:flex lg:min-h-screen lg:min-h-0 lg:flex-col lg:justify-center lg:pt-10 lg:pb-3 lg:pl-5 lg:pr-8 xl:pl-8 xl:pr-12">
            <div class="relative z-10 ml-0 w-full max-w-[860px] pt-2">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white shadow-sm ring-1 ring-slate-100">
                        <img src="{{ asset('images/PIT-LOGO.png') }}" alt="PIT Logo" class="h-12 w-12 rounded-full object-cover">
                    </div>
                    <div class="leading-tight">
                        <p class="text-[10px] uppercase tracking-[0.42em] text-slate-600">PITFR-RMS</p>
                        <p class="mt-2 text-sm font-semibold text-slate-700">Palompon Institute of Technology</p>
                    </div>
                </div>
                <div class="mt-4 flex justify-center">
                    <div class="relative mx-auto w-full max-w-[90%] ml-8 overflow-hidden rounded-[22px] border border-slate-200 bg-slate-100 shadow-[0_18px_50px_rgba(15,23,42,0.12)]">
                        <img src="{{ asset('images/loginpage2.jpg') }}" alt="PIT Facility Request Portal" class="block h-[260px] w-full object-cover sm:h-[320px] lg:h-[360px]">
                    </div>
                </div>
                <div class="mt-10 mb-6 max-w-[330px] pb-4 lg:max-w-[360px]">
                    <h1 class="text-[2.1rem] font-black leading-[0.9] tracking-[-0.04em] text-slate-900 xl:text-[2.8rem]">
                        Book the<br>
                        spaces<br>
                        you <span class="text-emerald-600">need.</span>
                    </h1>
                </div>
            </div>
        </section>

        <section class="login-panel-reveal flex h-screen items-center justify-center overflow-y-auto bg-slate-100 px-6 py-6 text-slate-900 sm:px-10 lg:px-16 xl:px-24" style="animation-delay: 80ms;">
            <div class="w-full max-w-[460px]">
                <div class="mb-8 max-w-full">
                    <p class="break-words text-xs font-semibold uppercase tracking-[0.25em] text-emerald-600 sm:text-[11px]">Create your PITFR account</p>
                </div>

                @if($errors->any())
                    <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
                @endif

                <p class="mb-3 text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Requestor registration</p>
                <div class="mb-6 border-l-4 border-emerald-500 bg-emerald-50 p-4 text-sm text-emerald-800">
                    Students, faculty, staff and guests (Outsiders) can create an account here. Custodian and Supply Office accounts are created by the Supply Office.
                </div>

                    <p class="mb-4 text-xs text-slate-500">Fields marked <span class="text-red-500">*</span> are required.</p>
                <form method="POST" action="{{ route('register.post') }}" enctype="multipart/form-data" class="space-y-5">
                        @csrf
                        @if(! ($verificationEnabled ?? false))
                            <input type="hidden" name="requestor_type" value="outsider">
                        @endif

                        @if($googleProfile)
                            <p class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700">Google account connected: {{ $googleProfile['email'] }}.</p>
                        @endif

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="first_name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">First name <span class="text-red-500" aria-hidden="true">*</span></label>
                                <input id="first_name" type="text" name="first_name" value="{{ old('first_name', $googleProfile['first_name'] ?? '') }}" required autocomplete="given-name" placeholder="First name" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                            </div>
                            <div>
                                <label for="middle_name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Middle name <span class="font-normal normal-case tracking-normal text-slate-400">(optional)</span></label>
                                <input id="middle_name" type="text" name="middle_name" value="{{ old('middle_name') }}" autocomplete="additional-name" placeholder="Optional" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                            </div>
                            <div>
                                <label for="surname" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Surname <span class="text-red-500" aria-hidden="true">*</span></label>
                                <input id="surname" type="text" name="surname" value="{{ old('surname', $googleProfile['last_name'] ?? '') }}" required autocomplete="family-name" placeholder="Surname" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="username" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Email address <span class="text-red-500" aria-hidden="true">*</span></label>
                                <input id="username" type="email" name="username" value="{{ old('username', $googleProfile['email'] ?? '') }}" required @disabled($googleProfile) autocomplete="email" placeholder="you@example.com" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                                @if($googleProfile)<input type="hidden" name="username" value="{{ $googleProfile['email'] }}">@endif
                            </div>
                            <div>
                                <label for="contact_number" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Contact number <span class="font-normal normal-case tracking-normal text-slate-400">(optional)</span></label>
                                <input id="contact_number" type="text" name="contact_number" value="{{ old('contact_number') }}" autocomplete="tel" placeholder="09171234567" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                            </div>
                        </div>

                        @if($verificationEnabled ?? false)
                            @php
                                $selectedType = old('requestor_type', 'outsider');
                                $typeOptions = [
                                    'student' => ['Student', 'Enrolled PIT student'],
                                    'faculty' => ['Faculty', 'PIT teaching personnel'],
                                    'staff' => ['Staff', 'PIT non-teaching personnel'],
                                    'outsider' => ['Outsider / Guest', 'Not affiliated with PIT'],
                                ];
                            @endphp
                            <fieldset>
                                <legend class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">I am registering as <span class="text-red-500" aria-hidden="true">*</span></legend>
                                <div class="grid gap-2 sm:grid-cols-4">
                                    @foreach($typeOptions as $value => $option)
                                        <label class="flex cursor-pointer flex-col rounded-xl border border-slate-300 p-3 text-sm transition has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50 has-[:checked]:ring-2 has-[:checked]:ring-emerald-100">
                                            <span class="flex items-center gap-2 font-semibold text-slate-800">
                                                <input type="radio" name="requestor_type" value="{{ $value }}" @checked($selectedType === $value) class="accent-emerald-600" data-requestor-type>
                                                {{ $option[0] }}
                                            </span>
                                            <span class="mt-1 text-xs text-slate-500">{{ $option[1] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>

                            {{-- A.2: PIT members choose their College and Department from the directory. --}}
                            <div id="college_department_section" class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="college_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">College <span class="text-red-500" aria-hidden="true">*</span></label>
                                    <select id="college_id" name="college_id" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                                        <option value="">Choose your college</option>
                                        @foreach(($colleges ?? collect()) as $college)
                                            <option value="{{ $college->id }}" @selected((string) old('college_id') === (string) $college->id)>{{ $college->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="department_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Department <span class="text-red-500" aria-hidden="true">*</span></label>
                                    <select id="department_id" name="department_id" data-selected="{{ old('department_id') }}" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                                        <option value="">Choose your college first</option>
                                        @foreach(($colleges ?? collect()) as $college)
                                            @foreach($college->departments as $department)
                                                <option value="{{ $department->id }}" data-college-id="{{ $college->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->name }}</option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <script>
                                (function () {
                                    var section = document.getElementById('college_department_section');
                                    var college = document.getElementById('college_id');
                                    var department = document.getElementById('department_id');
                                    if (!section || !college || !department) return;
                                    var allDepartments = Array.prototype.slice.call(department.options).filter(function (o) { return o.value; })
                                        .map(function (o) { return { value: o.value, label: o.textContent, college: o.dataset.collegeId }; });
                                    function fillDepartments() {
                                        var keep = department.value || department.dataset.selected || '';
                                        department.innerHTML = '';
                                        department.add(new Option(college.value ? 'Choose your department' : 'Choose your college first', ''));
                                        allDepartments.filter(function (d) { return d.college === college.value; })
                                            .forEach(function (d) { department.add(new Option(d.label, d.value, false, d.value === keep)); });
                                        department.disabled = !college.value || section.hidden;
                                    }
                                    function toggle() {
                                        var checked = document.querySelector('[data-requestor-type]:checked');
                                        var member = !!checked && checked.value !== 'outsider';
                                        var orgSection = document.getElementById('organization_section');
                                        section.hidden = !member;
                                        college.disabled = !member;
                                        college.required = member;
                                        department.required = member;
                                        if (orgSection) {
                                            orgSection.hidden = member;
                                            orgSection.querySelectorAll('input').forEach(function (i) { i.disabled = member; });
                                        }
                                        fillDepartments();
                                    }
                                    college.addEventListener('change', function () { department.dataset.selected = ''; fillDepartments(); });
                                    document.querySelectorAll('[data-requestor-type]').forEach(function (r) { r.addEventListener('change', toggle); });
                                    document.addEventListener('DOMContentLoaded', toggle);
                                    toggle();
                                })();
                            </script>

                            <div id="verification_section" class="rounded-2xl border border-slate-200 bg-slate-50 p-4" data-institutional-domains="{{ implode(',', $institutionalDomains ?? []) }}">
                                <p class="text-sm font-semibold text-slate-800">Identity verification</p>
                                <p id="verification_auto" class="mt-2 hidden rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">
                                    Your PIT email address will verify your account automatically after you enter the code we send. No ID upload is needed.
                                </p>
                                <div id="verification_upload">
                                    <p id="verification_hint_member" class="mt-1 hidden text-xs text-slate-600">
                                        Upload your PIT ID, or register with your PIT email address{{ !empty($institutionalDomains) ? ' (@' . implode(', @', $institutionalDomains) . ')' : '' }} to be verified automatically. Until the Supply Office confirms your ID, Outsider rules apply to your requests.
                                    </p>
                                    <p id="verification_hint_outsider" class="mt-1 text-xs text-slate-600">
                                        Upload any valid government ID. The Supply Office checks it before your requests can receive final approval.
                                    </p>
                                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label for="verification_id_type" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">ID type</label>
                                            <select id="verification_id_type" name="verification_id_type" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                                                <option value="">Choose an ID</option>
                                                @foreach($idTypes as $value => $label)
                                                    <option value="{{ $value }}" @selected(old('verification_id_type') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label for="verification_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">ID photo or scan</label>
                                            <input id="verification_id" type="file" name="verification_id" accept=".jpg,.jpeg,.png,.pdf" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-emerald-700">
                                        </div>
                                    </div>
                                    <p class="mt-2 text-xs text-slate-500">You can also upload it later in Account Settings, but your requests cannot receive final approval until your ID is reviewed. JPG, PNG or PDF, up to 5 MB. Front side only. You may cover your ID number; we only need your name and photo. The image is deleted once the Supply Office reviews it.</p>
                                </div>
                            </div>
                            <script>
                                (function () {
                                    var section = document.getElementById('verification_section');
                                    if (!section) return;
                                    var domains = (section.dataset.institutionalDomains || '').split(',').map(function (d) { return d.trim().toLowerCase(); }).filter(Boolean);
                                    var email = document.getElementById('username');
                                    var radios = document.querySelectorAll('[data-requestor-type]');
                                    function update() {
                                        var checked = document.querySelector('[data-requestor-type]:checked');
                                        var type = checked ? checked.value : 'outsider';
                                        var domain = ((email && email.value) || '').toLowerCase().split('@')[1] || '';
                                        var auto = type !== 'outsider' && domains.indexOf(domain) !== -1;
                                        document.getElementById('verification_auto').classList.toggle('hidden', !auto);
                                        document.getElementById('verification_upload').classList.toggle('hidden', auto);
                                        document.getElementById('verification_hint_member').classList.toggle('hidden', type === 'outsider');
                                        document.getElementById('verification_hint_outsider').classList.toggle('hidden', type !== 'outsider');
                                        var idType = document.getElementById('verification_id_type');
                                        if (type !== 'outsider' && !idType.value) idType.value = 'pit_id';
                                    }
                                    radios.forEach(function (r) { r.addEventListener('change', update); });
                                    if (email) email.addEventListener('input', update);
                                    update();
                                })();
                            </script>
                        @endif

                        <div id="organization_section" class="space-y-4">
                        <div>
                            <label for="office_or_organization" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Office, department or organization <span class="font-normal normal-case tracking-normal text-slate-400">(optional)</span></label>
                            <input id="office_or_organization" type="text" name="office_or_organization" value="{{ old('office_or_organization') }}" maxlength="191" list="organization_suggestions" autocomplete="organization" placeholder="Start typing, or enter your company or group" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                            <datalist id="organization_suggestions">
                                @foreach(($organizationSuggestions ?? []) as $suggestion)
                                    <option value="{{ $suggestion }}"></option>
                                @endforeach
                            </datalist>
                            <p class="mt-2 text-xs text-slate-400">Shown on your request form. It does not change which rules apply to you; that comes from the category you register as. Leave blank if you are requesting as an individual.</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="organization_acronym" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Organization acronym <span class="font-normal normal-case tracking-normal text-slate-400">(optional)</span></label>
                                <input id="organization_acronym" type="text" name="organization_acronym" value="{{ old('organization_acronym') }}" maxlength="50" placeholder="Example: ABC" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                            </div>
                            <div>
                                <label for="organization_type" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Organization type <span class="font-normal normal-case tracking-normal text-slate-400">(optional)</span></label>
                                <input id="organization_type" type="text" name="organization_type" value="{{ old('organization_type') }}" maxlength="100" placeholder="Example: Company, NGO, School" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                            </div>
                        </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="register_password" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Password <span class="text-red-500" aria-hidden="true">*</span></label>
                                <div class="pitfr-password-wrapper">
                                    <input id="register_password" type="password" name="password" @required(!$googleProfile) autocomplete="new-password" placeholder="At least 12 characters" aria-describedby="password_strength password_rules" class="pitfr-password-input w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                                    <button type="button" data-password-toggle-target="#register_password" aria-label="Show password" class="password-toggle pitfr-password-toggle"></button>
                                </div>
                                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-200" aria-hidden="true">
                                    <div id="password_strength_bar" class="h-full w-0 rounded-full transition-all"></div>
                                </div>
                                <p id="password_strength" class="mt-1 text-xs text-slate-500" aria-live="polite"></p>
                                <p id="password_rules" class="mt-1 text-xs text-slate-400">Use at least 12 characters with upper-case and lower-case letters, a number and a symbol.</p>
                                @error('password')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="register_password_confirmation" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Confirm password <span class="text-red-500" aria-hidden="true">*</span></label>
                                <div class="pitfr-password-wrapper">
                                    <input id="register_password_confirmation" type="password" name="password_confirmation" @required(!$googleProfile) autocomplete="new-password" placeholder="Re-enter password" class="pitfr-password-input w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                                    <button type="button" data-password-toggle-target="#register_password_confirmation" aria-label="Show password" class="password-toggle pitfr-password-toggle"></button>
                                </div>
                            </div>
                        </div>

                        @if($privacyConsentEnabled ?? false)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                                <label class="flex items-start gap-3">
                                    <input type="checkbox" name="privacy_accepted" value="1" required @checked(old('privacy_accepted')) class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600">
                                    <span>
                                        I have read the <a href="{{ route('privacy.data-protection') }}" target="_blank" rel="noopener" class="font-semibold text-emerald-700 underline">Privacy Notice</a> and agree that PIT may process my personal information for facility reservations, as described there and under the
                                        <a href="{{ route('privacy.data-protection') }}#data-privacy-rights" target="_blank" rel="noopener" class="font-semibold text-emerald-700 underline">Data Privacy Act of 2012 (RA 10173)</a>. <span class="text-red-500" aria-hidden="true">*</span>
                                    </span>
                                </label>
                                @error('privacy_accepted')<p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif

                        <div class="flex flex-col gap-4 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-slate-500">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-emerald-600 hover:text-emerald-700">Sign in</a></p>
                            <button type="submit" class="w-full rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-600/20 transition hover:-translate-y-0.5 hover:bg-emerald-700 sm:w-auto">Create account</button>
                        </div>
                    </form>
                <p class="mt-8 text-center text-[10px] uppercase tracking-[0.3em] text-slate-400">© {{ date('Y') }} Palompon Institute of Technology</p>
            </div>
        </section>
    </div>
    <script>
        (() => {
            const input = document.getElementById('register_password');
            const bar = document.getElementById('password_strength_bar');
            const label = document.getElementById('password_strength');
            if (!input || !bar || !label) return;
            const levels = [
                { text: '', width: '0%', color: '' },
                { text: 'Strength: weak', width: '33%', color: 'bg-red-500' },
                { text: 'Strength: moderate', width: '66%', color: 'bg-amber-500' },
                { text: 'Strength: strong', width: '100%', color: 'bg-emerald-600' },
            ];
            input.addEventListener('input', () => {
                const v = input.value;
                const checks = [v.length >= 12, /[a-z]/.test(v) && /[A-Z]/.test(v), /\d/.test(v), /[^A-Za-z0-9]/.test(v)];
                const passed = checks.filter(Boolean).length;
                const level = v === '' ? 0 : (passed === 4 ? 3 : (passed >= 2 ? 2 : 1));
                bar.className = 'h-full rounded-full transition-all ' + levels[level].color;
                bar.style.width = levels[level].width;
                label.textContent = levels[level].text;
            });
        })();
    </script>
</body>
</html>
