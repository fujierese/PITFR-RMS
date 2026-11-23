<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('images/PIT-LOGO.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/PIT-LOGO.png') }}">
    <title>Create an account - PIT Facility Request Portal</title>
    @if (app()->runningUnitTests())
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        .reg-label { display: block; margin-bottom: .375rem; font-size: .8125rem; font-weight: 600; color: rgb(51 65 85); }
        .reg-input { width: 100%; border-radius: .75rem; border: 1px solid rgb(203 213 225); background: #fff; padding: .625rem .75rem; font-size: .875rem; outline: none; transition: border-color .15s, box-shadow .15s; }
        .reg-input:focus { border-color: rgb(5 150 105); box-shadow: 0 0 0 3px rgb(209 250 229); }
        .reg-section { border-top: 1px solid rgb(226 232 240); padding-top: 1.25rem; }
        .reg-section-title { margin-bottom: .875rem; font-size: .9375rem; font-weight: 700; color: rgb(15 23 42); }
        .reg-hint { margin-top: .375rem; font-size: .75rem; color: rgb(100 116 139); }
        .reg-optional { font-weight: 400; color: rgb(148 163 184); }
        .reg-required { color: rgb(220 38 38); }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 lg:h-screen lg:overflow-hidden">
    <div class="min-h-screen w-full lg:grid lg:h-screen lg:grid-cols-[0.8fr_1.2fr]">
        {{-- Brand panel (large screens only) --}}
        <section class="relative hidden bg-white lg:flex lg:h-screen lg:flex-col lg:justify-center lg:overflow-hidden lg:px-10 xl:px-14">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/PIT-LOGO.png') }}" alt="PIT Logo" class="h-12 w-12 rounded-full border border-slate-200 object-cover">
                <div class="leading-tight">
                    <p class="text-[10px] uppercase tracking-[0.42em] text-slate-500">PITFR-RMS</p>
                    <p class="mt-1 text-sm font-semibold text-slate-700">Palompon Institute of Technology</p>
                </div>
            </div>
            <img src="{{ asset('images/loginpage2.jpg') }}" alt="Palompon Institute of Technology campus" class="mt-8 h-[300px] w-full rounded-[22px] border border-slate-200 object-cover shadow-[0_18px_50px_rgba(15,23,42,0.12)] xl:h-[340px]">
            <h2 class="mt-8 text-[2.2rem] font-black leading-[0.95] tracking-[-0.04em] text-slate-900 xl:text-[2.6rem]">Book the spaces<br>you <span class="text-emerald-600">need.</span></h2>
        </section>

        {{-- Sign-up form --}}
        <section class="flex justify-center px-4 py-8 sm:px-8 lg:h-screen lg:items-start lg:overflow-y-auto lg:overscroll-contain lg:py-12">
            <div class="w-full max-w-[640px] rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
                <div class="mb-6 flex items-center gap-3 lg:hidden">
                    <img src="{{ asset('images/PIT-LOGO.png') }}" alt="PIT Logo" class="h-10 w-10 rounded-full border border-slate-200 object-cover">
                    <p class="text-sm font-semibold text-slate-700">Palompon Institute of Technology</p>
                </div>
                <h1 class="text-2xl font-bold text-slate-900">Create an account</h1>
                <p class="mt-1 text-sm text-slate-600">For PIT students, faculty, staff and guests. Fields marked <span class="reg-required">*</span> are required.</p>

                @if($errors->any())
                    <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('register.post') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
                    @csrf
                    @if(! ($verificationEnabled ?? false))
                        <input type="hidden" name="requestor_type" value="outsider">
                    @endif

                    @if($googleProfile)
                        <p class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700">Google account connected: {{ $googleProfile['email'] }}.</p>
                    @endif

                    {{-- About you --}}
                    <div>
                        <p class="reg-section-title">About you</p>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="first_name" class="reg-label">First name <span class="reg-required" aria-hidden="true">*</span></label>
                                <input id="first_name" type="text" name="first_name" value="{{ old('first_name', $googleProfile['first_name'] ?? '') }}" required autocomplete="given-name" class="reg-input">
                            </div>
                            <div>
                                <label for="middle_name" class="reg-label">Middle name <span class="reg-optional">(optional)</span></label>
                                <input id="middle_name" type="text" name="middle_name" value="{{ old('middle_name') }}" autocomplete="additional-name" class="reg-input">
                            </div>
                            <div>
                                <label for="surname" class="reg-label">Surname <span class="reg-required" aria-hidden="true">*</span></label>
                                <input id="surname" type="text" name="surname" value="{{ old('surname', $googleProfile['last_name'] ?? '') }}" required autocomplete="family-name" class="reg-input">
                            </div>
                        </div>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="username" class="reg-label">Email address <span class="reg-required" aria-hidden="true">*</span></label>
                                <input id="username" type="email" name="username" value="{{ old('username', $googleProfile['email'] ?? '') }}" required @disabled($googleProfile) autocomplete="email" placeholder="you@example.com" class="reg-input">
                                @if($googleProfile)<input type="hidden" name="username" value="{{ $googleProfile['email'] }}">@endif
                            </div>
                            <div>
                                <label for="contact_number" class="reg-label">Contact number <span class="reg-optional">(optional)</span></label>
                                <input id="contact_number" type="text" name="contact_number" value="{{ old('contact_number') }}" autocomplete="tel" placeholder="09171234567" class="reg-input">
                            </div>
                        </div>
                    </div>

                    @if($verificationEnabled ?? false)
                        @php
                            $selectedType = old('requestor_type', 'outsider');
                            $typeOptions = [
                                'student' => ['Student', 'PIT student'],
                                'faculty' => ['Faculty', 'Teaching staff'],
                                'staff' => ['Staff', 'Non-teaching staff'],
                                'outsider' => ['Guest', 'Not from PIT'],
                            ];
                        @endphp
                        {{-- Account type --}}
                        <fieldset class="reg-section">
                            <legend class="reg-section-title">I am registering as <span class="reg-required" aria-hidden="true">*</span></legend>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                @foreach($typeOptions as $value => $option)
                                    <label class="flex cursor-pointer flex-col rounded-xl border border-slate-300 px-3 py-2.5 text-sm transition hover:border-emerald-400 has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50 has-[:checked]:ring-2 has-[:checked]:ring-emerald-100">
                                        <span class="flex items-center gap-2 font-semibold text-slate-800">
                                            <input type="radio" name="requestor_type" value="{{ $value }}" @checked($selectedType === $value) class="accent-emerald-600" data-requestor-type>
                                            {{ $option[0] }}
                                        </span>
                                        <span class="mt-0.5 pl-6 text-xs text-slate-500">{{ $option[1] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        {{-- A.2: PIT members choose their College and Department from the directory. --}}
                        <div id="college_department_section" class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="college_id" class="reg-label">College <span class="reg-required" aria-hidden="true">*</span></label>
                                <select id="college_id" name="college_id" class="reg-input">
                                    <option value="">Choose your college</option>
                                    @foreach(($colleges ?? collect()) as $college)
                                        <option value="{{ $college->id }}" @selected((string) old('college_id') === (string) $college->id)>{{ $college->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="department_id" class="reg-label">Department <span class="reg-required" aria-hidden="true">*</span></label>
                                <select id="department_id" name="department_id" data-selected="{{ old('department_id') }}" class="reg-input">
                                    <option value="">Choose your college first</option>
                                    @foreach(($colleges ?? collect()) as $college)
                                        @foreach($college->departments as $department)
                                            <option value="{{ $department->id }}" data-college-id="{{ $college->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->name }}</option>
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif

                    {{-- QA-10: guests give their office, department or organization and its type. --}}
                    <div id="organization_section" class="space-y-4">
                        <div>
                            <label for="office_or_organization" class="reg-label">Office, department or organization <span class="reg-required" aria-hidden="true" data-organization-required>*</span></label>
                            <input id="office_or_organization" type="text" name="office_or_organization" value="{{ old('office_or_organization') }}" maxlength="191" list="organization_suggestions" autocomplete="organization" placeholder="Company, agency, school or group" class="reg-input">
                            <datalist id="organization_suggestions">
                                @foreach(($organizationSuggestions ?? []) as $suggestion)
                                    <option value="{{ $suggestion }}"></option>
                                @endforeach
                            </datalist>
                            <p class="reg-hint">Shown on your request forms. If you are booking for yourself, enter your own name.</p>
                        </div>
                        @php
                            $organizationTypes = \App\Models\User::ORGANIZATION_TYPES;
                            $savedType = old('organization_type');
                        @endphp
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="organization_type" class="reg-label">Organization type <span class="reg-required" aria-hidden="true" data-organization-required>*</span></label>
                                <select id="organization_type" name="organization_type" class="reg-input" data-organization-type>
                                    <option value="">Choose a type</option>
                                    @foreach($organizationTypes as $type)
                                        <option value="{{ $type }}" @selected($savedType === $type)>{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div data-organization-type-other @class(['hidden' => $savedType !== 'Other'])>
                                <label for="organization_type_other" class="reg-label">Please specify <span class="reg-required" aria-hidden="true">*</span></label>
                                <input id="organization_type_other" type="text" name="organization_type_other" value="{{ old('organization_type_other') }}" maxlength="80" class="reg-input">
                            </div>
                        </div>
                    </div>

                    @if($verificationEnabled ?? false)
                        {{-- ID verification --}}
                        <div id="verification_section" class="reg-section" data-institutional-domains="{{ implode(',', $institutionalDomains ?? []) }}">
                            <p class="reg-section-title">Identity verification</p>
                            <p id="verification_auto" class="hidden rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">
                                Your PIT email verifies your account automatically after you enter the code we send. No ID upload is needed.
                            </p>
                            <div id="verification_upload">
                                <p id="verification_hint_member" class="hidden text-sm text-slate-600">Upload your PIT ID{{ !empty($institutionalDomains) ? ', or sign up with your @' . implode(' / @', $institutionalDomains) . ' email to be verified automatically' : '' }}.</p>
                                <p id="verification_hint_outsider" class="text-sm text-slate-600">Upload a valid government ID.</p>
                                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="verification_id_type" class="reg-label">ID type</label>
                                        <select id="verification_id_type" name="verification_id_type" class="reg-input">
                                            <option value="">Choose an ID</option>
                                            @foreach($idTypes as $value => $label)
                                                <option value="{{ $value }}" @selected(old('verification_id_type') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="verification_id" class="reg-label">ID photo or scan</label>
                                        <input id="verification_id" type="file" name="verification_id" accept=".jpg,.jpeg,.png,.pdf" class="reg-input py-2 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1 file:text-emerald-700">
                                    </div>
                                </div>
                                <p class="reg-hint">JPG, PNG or PDF, up to 5 MB. You can also add it later in Account Settings.</p>
                                <details class="mt-2 text-xs text-slate-600">
                                    <summary class="cursor-pointer font-medium text-emerald-700">Why we ask for an ID</summary>
                                    <p class="mt-2 leading-relaxed">Requests get final approval only after your ID is checked; until then, PIT members follow the same rules as guests. Upload the front side only. You may cover the ID number. The image is deleted after the Supply Office reviews it.</p>
                                </details>
                            </div>
                        </div>
                    @endif

                    {{-- Password --}}
                    <div class="reg-section">
                        <p class="reg-section-title">Password</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="register_password" class="reg-label">Password <span class="reg-required" aria-hidden="true">*</span></label>
                                <div class="pitfr-password-wrapper">
                                    <input id="register_password" type="password" name="password" @required(!$googleProfile) autocomplete="new-password" aria-describedby="password_strength password_rules" class="pitfr-password-input reg-input">
                                    <button type="button" data-password-toggle-target="#register_password" aria-label="Show password" class="password-toggle pitfr-password-toggle"></button>
                                </div>
                                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-200" aria-hidden="true">
                                    <div id="password_strength_bar" class="h-full w-0 rounded-full transition-all"></div>
                                </div>
                                <p id="password_strength" class="mt-1 text-xs text-slate-500" aria-live="polite"></p>
                                @error('password')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="register_password_confirmation" class="reg-label">Confirm password <span class="reg-required" aria-hidden="true">*</span></label>
                                <div class="pitfr-password-wrapper">
                                    <input id="register_password_confirmation" type="password" name="password_confirmation" @required(!$googleProfile) autocomplete="new-password" class="pitfr-password-input reg-input">
                                    <button type="button" data-password-toggle-target="#register_password_confirmation" aria-label="Show password" class="password-toggle pitfr-password-toggle"></button>
                                </div>
                            </div>
                        </div>
                        <p id="password_rules" class="reg-hint">At least 12 characters, with upper- and lower-case letters, a number and a symbol.</p>
                    </div>

                    @if($privacyConsentEnabled ?? false)
                        <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                            <input type="checkbox" name="privacy_accepted" value="1" required @checked(old('privacy_accepted')) class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600">
                            <span>
                                I have read the <a href="{{ route('privacy.data-protection') }}" target="_blank" rel="noopener" class="font-semibold text-emerald-700 underline">Privacy Notice</a> and agree to the processing of my personal information under the
                                <a href="{{ route('privacy.data-protection') }}#data-privacy-rights" target="_blank" rel="noopener" class="font-semibold text-emerald-700 underline">Data Privacy Act (RA 10173)</a>. <span class="reg-required" aria-hidden="true">*</span>
                            </span>
                        </label>
                        @error('privacy_accepted')<p class="-mt-4 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    @endif

                    <div class="flex flex-col-reverse gap-4 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-slate-500">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-emerald-600 hover:text-emerald-700">Sign in</a></p>
                        <button type="submit" class="w-full rounded-xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-600/20 transition hover:bg-emerald-700 sm:w-auto">Create account</button>
                    </div>
                </form>
                <p class="mt-6 text-center text-xs text-slate-400">Custodian and Supply Office accounts are created by the Supply Office. &copy; {{ date('Y') }} Palompon Institute of Technology</p>
            </div>
        </section>
    </div>

    <script>
        (function () {
            var section = document.getElementById('college_department_section');
            var college = document.getElementById('college_id');
            var department = document.getElementById('department_id');
            var orgSection = document.getElementById('organization_section');
            var orgName = document.getElementById('office_or_organization');
            var orgType = document.querySelector('[data-organization-type]');
            var otherWrap = document.querySelector('[data-organization-type-other]');
            var otherInput = document.getElementById('organization_type_other');
            var verificationOn = document.querySelectorAll('[data-requestor-type]').length > 0;
            var allDepartments = department ? Array.prototype.slice.call(department.options).filter(function (o) { return o.value; })
                .map(function (o) { return { value: o.value, label: o.textContent, college: o.dataset.collegeId }; }) : [];

            function fillDepartments() {
                if (!department || !college) return;
                var keep = department.value || department.dataset.selected || '';
                department.innerHTML = '';
                department.add(new Option(college.value ? 'Choose your department' : 'Choose your college first', ''));
                allDepartments.filter(function (d) { return d.college === college.value; })
                    .forEach(function (d) { department.add(new Option(d.label, d.value, false, d.value === keep)); });
                department.disabled = !college.value || (section && section.hidden);
            }
            function syncOther() {
                var isOther = orgType && orgType.value === 'Other' && !orgType.disabled;
                if (otherWrap) otherWrap.classList.toggle('hidden', !isOther);
                if (otherInput) { otherInput.required = isOther; otherInput.disabled = !isOther; }
            }
            function toggle() {
                var checked = document.querySelector('[data-requestor-type]:checked');
                var member = !!checked && checked.value !== 'outsider';
                if (section) {
                    section.hidden = !member;
                    college.disabled = !member;
                    college.required = member;
                    department.required = member;
                }
                if (orgSection) {
                    orgSection.hidden = member;
                    orgSection.querySelectorAll('input, select').forEach(function (i) { i.disabled = member; });
                    // Guests must say who they represent when account types are in use.
                    var required = verificationOn && !member;
                    orgName.required = required;
                    orgType.required = required;
                    orgSection.querySelectorAll('[data-organization-required]').forEach(function (s) { s.hidden = !required; });
                }
                fillDepartments();
                syncOther();
            }
            if (college) college.addEventListener('change', function () { department.dataset.selected = ''; fillDepartments(); });
            if (orgType) orgType.addEventListener('change', syncOther);
            document.querySelectorAll('[data-requestor-type]').forEach(function (r) { r.addEventListener('change', toggle); });
            toggle();
        })();

        (function () {
            var section = document.getElementById('verification_section');
            if (!section) return;
            var domains = (section.dataset.institutionalDomains || '').split(',').map(function (d) { return d.trim().toLowerCase(); }).filter(Boolean);
            var email = document.getElementById('username');
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
                if (type !== 'outsider' && !idType.value) { idType.value = 'pit_id'; idType.dataset.autoSelected = '1'; }
                if (type === 'outsider' && idType.dataset.autoSelected === '1') { idType.value = ''; delete idType.dataset.autoSelected; }
            }
            document.querySelectorAll('[data-requestor-type]').forEach(function (r) { r.addEventListener('change', update); });
            if (email) email.addEventListener('input', update);
            update();
        })();

        (function () {
            var input = document.getElementById('register_password');
            var bar = document.getElementById('password_strength_bar');
            var label = document.getElementById('password_strength');
            if (!input || !bar || !label) return;
            var levels = [
                { text: '', width: '0%', color: '' },
                { text: 'Strength: weak', width: '33%', color: 'bg-red-500' },
                { text: 'Strength: moderate', width: '66%', color: 'bg-amber-500' },
                { text: 'Strength: strong', width: '100%', color: 'bg-emerald-600' },
            ];
            input.addEventListener('input', function () {
                var v = input.value;
                var checks = [v.length >= 12, /[a-z]/.test(v) && /[A-Z]/.test(v), /\d/.test(v), /[^A-Za-z0-9]/.test(v)];
                var passed = checks.filter(Boolean).length;
                var level = v === '' ? 0 : (passed === 4 ? 3 : (passed >= 2 ? 2 : 1));
                bar.className = 'h-full rounded-full transition-all ' + levels[level].color;
                bar.style.width = levels[level].width;
                label.textContent = levels[level].text;
            });
        })();
    </script>
</body>
</html>
