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
<body class="h-screen overflow-hidden bg-slate-100 text-slate-100">
    <div class="relative h-screen w-full lg:grid lg:h-screen lg:grid-cols-[1.12fr_0.88fr]">
        <section class="relative hidden min-h-screen bg-slate-950 lg:flex lg:min-h-screen lg:min-h-0 lg:flex-col lg:justify-center lg:pt-10 lg:pb-3 lg:pl-5 lg:pr-8 xl:pl-8 xl:pr-12">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_35%)]"></div>
            <div class="absolute -left-32 top-1/4 h-96 w-96 rounded-full bg-emerald-500/10 blur-3xl"></div>
            <div class="absolute -bottom-32 right-0 h-96 w-96 rounded-full bg-emerald-400/10 blur-3xl"></div>
            <div class="relative z-10 ml-0 w-full max-w-[860px] pt-2">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full border border-white/20 bg-white/10 shadow-lg ring-1 ring-white/10">
                        <img src="{{ asset('images/PIT-LOGO.png') }}" alt="PIT Logo" class="h-12 w-12 rounded-full object-cover">
                    </div>
                    <div class="leading-tight">
                        <p class="text-[10px] uppercase tracking-[0.42em] text-slate-100">PITFR-RMS</p>
                        <p class="mt-2 text-sm font-semibold text-slate-200">Palompon Institute of Technology</p>
                    </div>
                </div>
                <div class="mt-4 flex justify-center">
                    <div class="relative mx-auto w-full max-w-[90%] ml-8 overflow-hidden rounded-[22px] border border-white/10 bg-slate-900 shadow-[0_18px_50px_rgba(15,23,42,0.32)]">
                        <img src="{{ asset('images/loginpage2.jpg') }}" alt="PIT Facility Request Portal" class="block h-[260px] w-full object-cover sm:h-[320px] lg:h-[360px]">
                    </div>
                </div>
                <div class="mt-10 mb-6 max-w-[330px] pb-4 lg:max-w-[360px]">
                    <h1 class="text-[2.1rem] font-black leading-[0.9] tracking-[-0.04em] text-white xl:text-[2.8rem]">
                        Book the<br>
                        spaces<br>
                        you <span class="text-emerald-400">need.</span>
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

                <p class="mb-3 text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Outsider registration</p>
                <div class="mb-6 border-l-4 border-emerald-500 bg-emerald-50 p-4 text-sm text-emerald-800">
                    Students and Faculty accounts are created by the authorized administrator. Outsiders verify their email with a one-time password after registration.
                </div>

                    <form method="POST" action="{{ route('register.post') }}" class="space-y-5">
                        @csrf
                        <input type="hidden" name="requestor_type" value="outsider">

                        @if($googleProfile)
                            <p class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700">Google account connected: {{ $googleProfile['email'] }}.</p>
                        @endif

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="first_name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">First name</label>
                                <input id="first_name" type="text" name="first_name" value="{{ old('first_name', $googleProfile['first_name'] ?? '') }}" required autocomplete="given-name" placeholder="First name" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                            </div>
                            <div>
                                <label for="middle_name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Middle name</label>
                                <input id="middle_name" type="text" name="middle_name" value="{{ old('middle_name') }}" autocomplete="additional-name" placeholder="Optional" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                            </div>
                            <div>
                                <label for="surname" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Surname</label>
                                <input id="surname" type="text" name="surname" value="{{ old('surname', $googleProfile['last_name'] ?? '') }}" required autocomplete="family-name" placeholder="Surname" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="username" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Email address</label>
                                <input id="username" type="email" name="username" value="{{ old('username', $googleProfile['email'] ?? '') }}" required @disabled($googleProfile) autocomplete="email" placeholder="you@example.com" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                                @if($googleProfile)<input type="hidden" name="username" value="{{ $googleProfile['email'] }}">@endif
                            </div>
                            <div>
                                <label for="contact_number" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Contact number</label>
                                <input id="contact_number" type="text" name="contact_number" value="{{ old('contact_number') }}" autocomplete="tel" placeholder="09171234567" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                            </div>
                        </div>

                        <div>
                            <label for="office_or_organization" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Organization name / affiliation</label>
                            <input id="office_or_organization" type="text" name="office_or_organization" value="{{ old('office_or_organization') }}" required placeholder="Your organization, office, company, or Individual / Personal" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                            <p class="mt-2 text-xs text-slate-400">Enter your own organization, office, company, or Individual / Personal.</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="organization_acronym" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Organization acronym <span class="font-normal normal-case tracking-normal text-slate-400">(optional)</span></label>
                                <input id="organization_acronym" type="text" name="organization_acronym" value="{{ old('organization_acronym') }}" maxlength="50" placeholder="Example: ABC" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                            </div>
                            <div>
                                <label for="organization_type" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Organization type</label>
                                <input id="organization_type" type="text" name="organization_type" value="{{ old('organization_type') }}" required maxlength="100" placeholder="Example: Company, NGO, School" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="register_password" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Password</label>
                                <div class="pitfr-password-wrapper">
                                    <input id="register_password" type="password" name="password" @required(!$googleProfile) autocomplete="new-password" placeholder="At least 6 characters" class="pitfr-password-input w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                                    <button type="button" data-password-toggle-target="#register_password" aria-label="Show password" class="password-toggle pitfr-password-toggle"></button>
                                </div>
                            </div>
                            <div>
                                <label for="register_password_confirmation" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Confirm password</label>
                                <div class="pitfr-password-wrapper">
                                    <input id="register_password_confirmation" type="password" name="password_confirmation" @required(!$googleProfile) autocomplete="new-password" placeholder="Re-enter password" class="pitfr-password-input w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                                    <button type="button" data-password-toggle-target="#register_password_confirmation" aria-label="Show password" class="password-toggle pitfr-password-toggle"></button>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-4 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-slate-500">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-emerald-600 hover:text-emerald-700">Sign in</a></p>
                            <button type="submit" class="w-full rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-600/20 transition hover:-translate-y-0.5 hover:bg-emerald-700 sm:w-auto">Create account</button>
                        </div>
                    </form>
                <p class="mt-8 text-center text-[10px] uppercase tracking-[0.3em] text-slate-400">© {{ date('Y') }} Palompon Institute of Technology</p>
            </div>
        </section>
    </div>
</body>
</html>
