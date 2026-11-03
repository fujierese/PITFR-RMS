<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('images/PIT-LOGO.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/PIT-LOGO.png') }}">
    <title>Sign In — PIT Facility Request Portal</title>
    <style>
        @keyframes login-panel-reveal {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-panel-reveal {
            animation: login-panel-reveal 700ms ease-out both;
        }

        .login-brand-image {
            width: min(90%, 653px);
            aspect-ratio: 1.42;
        }

        .login-brand-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        @media (min-width: 1024px) and (max-height: 820px) {
            .login-brand-image {
                width: min(90%, 653px, max(280px, calc((100svh - 340px) * 1.42)));
            }

            .login-brand-copy {
                margin-top: 1rem !important;
                margin-bottom: 0 !important;
                padding-bottom: 0 !important;
            }

            .login-brand-panel {
                padding-top: 1rem !important;
                padding-bottom: 1rem !important;
            }
        }

        @media (min-width: 1024px) and (max-height: 760px) {
            .login-brand-copy h1 {
                font-size: 2rem !important;
            }

            .login-form-panel {
                padding-top: 1.5rem !important;
                padding-bottom: 1.5rem !important;
            }
        }

        @media (max-height: 700px) {
            .login-form-panel {
                padding-top: 1rem !important;
                padding-bottom: 1rem !important;
            }

            .login-form-brand {
                margin-bottom: 1rem !important;
            }

            .login-form-heading,
            .login-form-links {
                margin-bottom: 1rem !important;
                margin-top: 1rem !important;
            }

            .login-form-panel #loginForm > :not([hidden]) ~ :not([hidden]) {
                margin-top: 1rem;
            }

            .login-form-footer {
                margin-top: 1.25rem !important;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .login-panel-reveal { animation: none !important; }
        }
    </style>
    @if (app()->runningUnitTests())
        {{-- Skip Vite asset loading in tests when the manifest may not exist. --}}
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>

<body class="min-h-screen overflow-x-hidden overflow-y-auto bg-slate-100 text-slate-900">

    <div class="relative min-h-screen w-full xl:grid xl:min-h-screen xl:grid-cols-[1.12fr_0.88fr]">

        <!-- =========================
             LEFT SIDE — IMAGES / BRAND
        ========================== -->
        <section class="login-panel-reveal login-brand-panel relative hidden min-h-screen bg-white xl:flex xl:min-h-screen xl:min-h-0 xl:flex-col xl:justify-center xl:pt-10 xl:pb-3 xl:pl-5 xl:pr-8 xl:pl-8 xl:pr-12">

            <div class="relative z-10 ml-0 w-full max-w-[860px] pt-2">

                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white shadow-sm ring-1 ring-slate-100 xl:h-20 xl:w-20">
                        <img
                            src="{{ asset('images/PIT-LOGO.png') }}"
                            alt="PIT Logo"
                            class="h-12 w-12 rounded-full object-cover xl:h-16 xl:w-16"
                        >
                    </div>

                    <div class="leading-tight">
                        <p class="text-[10px] uppercase tracking-[0.42em] text-slate-600 xl:text-[11px]">
                            PITFR-RMS
                        </p>

                        <p class="mt-2 text-sm font-semibold text-slate-700">
                            Palompon Institute of Technology
                        </p>
                    </div>
                </div>

                <div class="mt-4 flex justify-center">
                    <div class="login-brand-image relative mx-auto w-full max-w-[90%] overflow-hidden rounded-[22px] border border-slate-200 bg-slate-100 shadow-[0_18px_50px_rgba(15,23,42,0.12)]">
                        <img
                            src="{{ asset('images/loginpage2.jpg') }}"
                            alt="PIT Facility Request Portal"
                            class="block w-full object-cover"
                        >
                    </div>
                </div>

                <div class="login-brand-copy mt-10 mb-6 pb-4 max-w-[330px] lg:max-w-[360px]">
                    <h1 class="text-[2.1rem] font-black leading-[0.9] tracking-[-0.04em] text-slate-900 xl:text-[2.8rem]">
                        Book the
                        <br>
                        spaces
                        <br>
                        you
                        <span class="text-emerald-600">need.</span>
                    </h1>
                </div>

            </div>

        </section>


        <!-- =========================
             RIGHT SIDE — LOGIN
        ========================== -->

        <section class="login-panel-reveal login-form-panel flex min-h-screen h-auto items-start justify-center overflow-visible bg-slate-100 px-6 py-8 sm:px-10 sm:py-10 xl:items-center xl:overflow-y-auto xl:px-16 2xl:px-24" style="animation-delay: 80ms;">

            <div class="w-full max-w-[460px]">

                <!-- Mobile Logo -->
                <div class="login-form-brand mb-10 flex items-center gap-3 xl:hidden">

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
                <div class="login-form-heading mb-8 max-w-full">
                    <p class="break-words text-xs font-semibold uppercase tracking-[0.25em] text-emerald-600 sm:text-[11px]">
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

                @if(session('status'))
                    <div class="mb-6 border-l-4 border-emerald-500 bg-emerald-50 p-4 text-sm text-emerald-800">
                        {{ session('status') }}
                    </div>
                @endif


                <!-- Login Form -->
                <form
                    method="POST"
                    action="{{ route('login') }}"
                    class="space-y-5"
                    id="loginForm"
                >

                    @csrf

                    <!-- Email -->
                    <div>

                        <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">
                            Email Address
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
                                type="text"
                                id="email"
                                name="email"
                                autocomplete="username"
                                required
                                value="{{ old('email', old('username')) }}"
                                placeholder="Enter your email address"
                                class="w-full border-0 border-b-2 border-slate-300 bg-transparent px-4 py-3 pl-12 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-0"
                            >

                        </div>

                    </div>


                    <!-- Password -->
                    <div>

                        <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">
                            Password
                        </label>

                        <div class="pitfr-password-wrapper">

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
                                autocomplete="current-password"
                                required
                                placeholder="Enter your password"
                                class="pitfr-password-input w-full border-0 border-b-2 border-slate-300 bg-transparent px-4 py-3 pl-12 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-0"
                            >

                            <button
                                type="button"
                                data-password-toggle-target="#password"
                                aria-label="Show password"
                                class="password-toggle pitfr-password-toggle"
                            ></button>

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
                <div class="login-form-links mt-7 space-y-3 text-center text-sm text-slate-500">

                    <div>
                        Don't have an account?

                        <a
                            href="{{ route('register') }}"
                            class="font-semibold text-emerald-600 transition hover:text-emerald-700"
                        >
                            Create Requestor Account
                        </a>
                    </div>

                    <div>
                        <a
                            href="{{ route('password.request') }}"
                            class="font-semibold text-emerald-600 transition hover:text-emerald-700"
                        >
                            Forgot Password?
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
                <p class="login-form-footer mt-12 text-center text-[10px] uppercase tracking-[0.3em] text-slate-400">
                    © {{ date('Y') }} Palompon Institute of Technology
                </p>

            </div>

        </section>

    </div>


</body>
</html>
