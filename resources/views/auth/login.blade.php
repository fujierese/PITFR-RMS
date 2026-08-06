<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — PIT Facility Request Portal</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen flex items-center justify-center overflow-x-hidden bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 p-2 text-slate-100 sm:p-4">

<div class="mx-auto w-full max-w-none overflow-hidden rounded-[24px] border border-white/10 bg-slate-950/80 shadow-[0_60px_120px_rgba(15,23,42,0.55)] backdrop-blur-xl sm:rounded-[40px] md:max-w-5xl">
    <div class="grid gap-0 md:grid-cols-[0.95fr_1.05fr]">
        <div class="hidden md:flex flex-col justify-between bg-gradient-to-br from-emerald-900 to-slate-900 p-12 relative overflow-hidden text-white">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_rgba(255,255,255,0.12),_transparent_30%)]"></div>
            <div class="relative z-10 space-y-8">
                <div class="flex items-center gap-4">
                    <img src="{{ asset('images/PIT-LOGO.jpg') }}" alt="PIT Logo" class="h-14 w-14 rounded-full border border-white/20 object-cover shadow-lg">
                    <div>
                        <p class="text-base font-bold tracking-[0.16em]">Palompon Institute</p>
                        <p class="text-base font-bold tracking-[0.16em]">of Technology</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <span class="inline-flex rounded-full bg-white/10 px-4 py-2 text-xs uppercase tracking-[0.32em] text-slate-200">Facility Request System</span>
                    <h2 class="text-4xl font-semibold leading-tight">Secure access for students, faculty, and staff.</h2>
                    <p class="max-w-md text-sm leading-7 text-slate-200/90">Sign in securely to submit requests, view approvals, and manage your facility and equipment reservations in one modern portal.</p>
                </div>
            </div>

            <div class="relative z-10 mt-8 rounded-[32px] border border-white/10 bg-white/5 p-6 shadow-inner">
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-emerald-200">Why use PITFR?</p>
                <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-200/85">
                    <li>• Fast request submission with clear status updates</li>
                    <li>• Mobile-friendly portal for easy access</li>
                    <li>• Dedicated approval tracking and notifications</li>
                </ul>
            </div>
        </div>

        <div class="bg-white p-4 sm:p-6 lg:p-10">
            <div class="mx-auto max-w-md">
                <div class="mb-8 space-y-3">
                    <p class="text-xs uppercase tracking-[0.32em] text-emerald-600 font-semibold">Sign in</p>
                    <h1 class="text-3xl font-semibold text-slate-950">Welcome back!</h1>
                    <p class="text-sm text-slate-500">Sign in to access your dashboard and manage requests.</p>
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

                <form method="POST" action="{{ route('login') }}" class="space-y-6" id="loginForm">
                    @csrf

                    <div class="space-y-4">
                        <label class="block text-sm font-semibold text-slate-700">Username</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-slate-400">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </span>
                            <input type="text" name="username" required
                                   value="{{ old('username') }}"
                                   placeholder="Enter your username"
                                   class="w-full rounded-[28px] border border-slate-200 bg-slate-50 px-4 py-3 pl-12 text-sm text-slate-900 shadow-sm outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100">
                        </div>
                    </div>

                    <div class="space-y-4">
                        <label class="block text-sm font-semibold text-slate-700">Password</label>
                        <div class="relative password-toggle-wrapper">
                            <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-slate-400">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </span>
                            <input type="password" name="password" id="password" required
                                   placeholder="Enter your password"
                                   class="w-full rounded-[28px] border border-slate-200 bg-slate-50 px-4 py-3 pl-12 pr-12 text-sm text-slate-900 shadow-sm outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100">
                            <button type="button" data-password-toggle-target="#password" aria-label="Show password" class="password-toggle absolute inset-y-0 right-4 inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-500 transition hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-slate-50">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" id="remember" name="remember" value="1"
                                   class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            Keep me signed in
                        </label>
                        <a href="{{ route('home') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700 transition">Continue as Guest</a>
                    </div>

                    <button type="submit"
                            class="w-full rounded-[28px] bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-xl shadow-emerald-600/20 transition hover:bg-emerald-700 hover:-translate-y-0.5">
                        Sign In
                    </button>
                </form>

                <div class="mt-6 text-center text-sm text-slate-500">
                    Don’t have an account? <a href="{{ route('register') }}" class="font-semibold text-emerald-600 hover:text-emerald-700 transition">Create requestor account</a>
                </div>

                <p class="mt-10 text-center text-xs uppercase tracking-[0.3em] text-slate-400">© {{ date('Y') }} Palompon Institute of Technology</p>
            </div>
        </div>
    </div>
</div>

</body>
</html>