<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Forgot password - PITFR</title>@if (!app()->runningUnitTests()) @vite(['resources/css/app.css']) @endif</head>
<body class="min-h-screen bg-slate-950 p-6 text-slate-900"><main class="mx-auto mt-16 max-w-md rounded-3xl bg-white p-8 shadow-2xl">
    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-600">Account recovery</p><h1 class="mt-3 text-3xl font-semibold">Reset your password</h1>
    <p class="mt-3 text-sm text-slate-500">Enter your account email and we will send a password reset link.</p>
    @if(session('status'))<p class="mt-4 text-sm text-emerald-700">{{ session('status') }}</p>@endif
    @if($errors->any())<p class="mt-4 text-sm text-red-600">{{ $errors->first() }}</p>@endif
    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">@csrf
        <input type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="you@example.com" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
        <button class="w-full rounded-2xl bg-emerald-600 px-4 py-3 font-semibold text-white">Send reset link</button>
    </form><a href="{{ route('login') }}" class="mt-5 block text-center text-sm font-semibold text-emerald-700">Back to login</a>
</main></body></html>