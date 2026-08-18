<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — PIT Facility Request Portal</title>
    @if (app()->runningUnitTests())
        {{-- Skip Vite asset loading in tests when the manifest may not exist. --}}
    @else
        @vite(['resources/css/app.css'])
    @endif
</head>

<body class="min-h-screen overflow-x-hidden bg-slate-100 text-slate-100 lg:h-screen lg:overflow-hidden">

    <div class="relative min-h-screen w-full lg:grid lg:h-screen lg:grid-cols-2">

        <!-- =========================
             LEFT SIDE — IMAGES / BRAND
        ========================== -->
        <section class="relative hidden h-full overflow-hidden bg-slate-950 lg:flex lg:flex-col lg:justify-start lg:pt-12 lg:pl-6 lg:pr-10 xl:pl-8 xl:pr-16">

            <!-- Background effects -->
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_35%)]"></div>
            <div class="absolute -left-32 top-1/4 h-96 w-96 rounded-full bg-emerald-500/10 blur-3xl"></div>
            <div class="absolute -bottom-32 right-0 h-96 w-96 rounded-full bg-emerald-400/10 blur-3xl"></div>

            <div class="relative z-10 ml-0 w-full max-w-[920px] pt-2">

                <!-- Logo / Header -->
                <div class="flex items-center gap-4">
                    <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full border border-white/20 bg-white/10 shadow-lg ring-1 ring-white/10">
                        <img
                            src="{{ asset('images/PIT-LOGO.png') }}"
                            alt="PIT Logo"
                            class="h-16 w-16 rounded-full object-cover"
                        >
                    </div>

                    <div class="leading-tight">
                        <p class="text-[11px] uppercase tracking-[0.46em] text-slate-100">
                            PITFR-RMS
                        </p>

                        <p class="mt-2 text-sm font-semibold text-slate-200">
                            Palompon Institute of Technology
                        </p>
                    </div>
                </div>

                <div class="mt-8 flex justify-center">
                    <div class="relative mx-auto w-full max-w-[920px] overflow-hidden rounded-[28px] border border-white/10 bg-slate-900 shadow-[0_30px_80px_rgba(15,23,42,0.45)]">
                        <img
                            src="{{ asset('images/loginpage2.jpg') }}"
                            alt="PIT Facility Request Portal"
                            class="block h-[520px] w-full object-cover sm:h-[580px]"
                        >
                    </div>
                </div>

                <div class="mt-3 max-w-[420px]">
                    <h1 class="text-5xl font-black leading-[1.05] tracking-[-0.04em] text-white xl:text-6xl">
                        Book the
                        <br>
                        spaces
                        <br>
                        you
                        <span class="text-emerald-400">need.</span>
                    </h1>
                </div>

            </div>

        </section>


        <!-- =========================
             CENTER DIVIDER
        ========================== -->

        <div class="hidden lg:block absolute left-1/2 top-1/2 z-20 h-[70vh] w-px -translate-x-1/2 -translate-y-1/2 bg-slate-300"></div>


        <!-- =========================
             RIGHT SIDE — LOGIN
        ========================== -->

        <section class="flex min-h-screen items-center justify-center bg-slate-100 px-6 py-12 sm:px-10 lg:px-16 xl:px-24">

            <div class="w-full max-w-[460px]">

                <!-- Mobile Logo -->
                <div class="mb-10 flex items-center gap-3 lg:hidden">

                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-600">
                        <img
                            src="{{ asset('images/PIT-LOGO.png') }}"
                            alt="PIT Logo"
                            class="h-9 w-9 rounded-full object-cover"
                        >
                    </div>

                    <div>
                        <p class="text-xs uppercase tracking-[0.25em] text-emerald-600">
                            PITFR-RMS
                        </p>

                        <p class="text-sm font-semibold text-slate-700">
                            Palompon Institute of Technology
                        </p>
                    </div>

                </div>


                <!-- Login Heading -->
                <div class="relative mb-8 -ml-8">
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-600">
                        Log in to PITFR-RMS
                    </p>
                </div>

                <!-- Error -->
                @if($errors->any())

                    <div class="mb-6 border-l-4 border-red-500 bg-red-50 p-4 text-sm text-red-800">

                        <div class="flex items-start gap-3">

                            <svg
                                class="mt-0.5 h-5 w-5 shrink-0 text-red-500"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>

                            <p>
                                {{ $errors->first() }}
                            </p>

                        </div>

                    </div>

                @endif


                <!-- Login Form -->
                <form
                    method="POST"
                    action="{{ route('login') }}"
                    class="space-y-6"
                    id="loginForm"
                >

                    @csrf


                    <!-- Email -->
                    <div>

                        <label class="mb-2 block text-sm font-semibold text-slate-700">
                            Email
                        </label>

                        <div class="relative">

                            <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-slate-400">

                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                    />
                                </svg>

                            </span>

                            <input
                                type="email"
                                name="email"
                                required
                                value="{{ old('email', old('username')) }}"
                                placeholder="Enter your email address"
                                class="w-full border-0 border-b-2 border-slate-300 bg-transparent px-4 py-3 pl-12 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-0"
                            >

                        </div>

                    </div>


                    <!-- Password -->
                    <div>

                        <label class="mb-2 block text-sm font-semibold text-slate-700">
                            Password
                        </label>

                        <div class="relative password-toggle-wrapper">

                            <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-slate-400">

                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                                    />
                                </svg>

                            </span>

                            <input
                                type="password"
                                name="password"
                                id="password"
                                required
                                placeholder="Enter your password"
                                class="w-full border-0 border-b-2 border-slate-300 bg-transparent px-4 py-3 pl-12 pr-12 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-0"
                            >

                            <button
                                type="button"
                                data-password-toggle-target="#password"
                                aria-label="Show password"
                                class="password-toggle absolute inset-y-0 right-2 inline-flex h-10 w-10 items-center justify-center text-slate-400 transition hover:text-slate-900 focus:outline-none"
                            >

                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                    />

                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                    />

                                </svg>

                            </button>

                        </div>

                    </div>


                    <!-- Options -->
                    <div class="flex items-center justify-between gap-4">

                        <label class="inline-flex items-center gap-2 text-sm text-slate-500">

                            <input
                                type="checkbox"
                                id="remember"
                                name="remember"
                                value="1"
                                class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                            >

                            Keep me signed in

                        </label>


                        <a
                            href="{{ route('home') }}"
                            class="text-sm font-semibold text-emerald-600 transition hover:text-emerald-700"
                        >
                            Forgot Password?
                        </a>

                    </div>


                    <!-- Sign In -->
                    <button
                        type="submit"
                        class="w-full bg-emerald-600 px-5 py-3.5 text-sm font-semibold text-white shadow-lg shadow-emerald-600/20 transition hover:-translate-y-0.5 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                    >
                        Sign In
                    </button>

                </form>


                <!-- Register -->
                <div class="mt-7 space-y-3 text-center text-sm text-slate-500">

                    <div>
                        Don't have an account?

                        <a
                            href="{{ route('register') }}"
                            class="font-semibold text-emerald-600 transition hover:text-emerald-700"
                        >
                            Create requestor account
                        </a>
                    </div>

                    <div>
                        <a
                            href="{{ route('home') }}"
                            class="font-semibold text-emerald-600 transition hover:text-emerald-700"
                        >
                            Return to Home
                        </a>
                    </div>

                </div>


                <!-- Footer -->
                <p class="mt-12 text-center text-[10px] uppercase tracking-[0.3em] text-slate-400">
                    © {{ date('Y') }} Palompon Institute of Technology
                </p>

            </div>

        </section>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function getEyeIcon(isHidden) {
                return isHidden
                    ? '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>'
                    : '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.269-2.943-9.543-7a10.05 10.05 0 012.293-3.926m2.946-2.947A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18"/></svg>';
            }

            document.querySelectorAll('.password-toggle').forEach(function (button) {
                button.addEventListener('click', function () {
                    var targetSelector = button.dataset.passwordToggleTarget;
                    if (!targetSelector) {
                        return;
                    }
                    var input = document.querySelector(targetSelector);
                    if (!input) {
                        return;
                    }

                    var isHidden = input.type === 'password';
                    input.type = isHidden ? 'text' : 'password';
                    button.innerHTML = getEyeIcon(!isHidden);
                    button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
                });
            });
        });
    </script>
</body>
</html>
```
