<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Reset password - PITFR</title>@if (!app()->runningUnitTests()) @vite(['resources/css/app.css', 'resources/js/app.js']) @endif</head>
<body class="min-h-screen bg-slate-950 p-6 text-slate-900"><main class="mx-auto mt-16 max-w-md rounded-3xl bg-white p-8 shadow-2xl">
    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-600">Account recovery</p><h1 class="mt-3 text-3xl font-semibold">Choose a new password</h1>
    @if($errors->any())<p class="mt-4 text-sm text-red-600">{{ $errors->first() }}</p>@endif
    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">@csrf<input type="hidden" name="token" value="{{ $token }}">
        <input type="email" name="email" value="{{ old('email', $email) }}" required placeholder="you@example.com" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
        <div class="pitfr-password-wrapper">
            <input type="password" name="password" id="reset_password" required placeholder="New password" class="pitfr-password-input w-full rounded-2xl border border-slate-300 px-4 py-3">
            <button type="button" data-password-toggle-target="#reset_password" aria-label="Show password" class="password-toggle pitfr-password-toggle"></button>
        </div>
        <div class="pitfr-password-wrapper">
            <input type="password" name="password_confirmation" id="reset_password_confirmation" required placeholder="Confirm new password" class="pitfr-password-input w-full rounded-2xl border border-slate-300 px-4 py-3">
            <button type="button" data-password-toggle-target="#reset_password_confirmation" aria-label="Show password" class="password-toggle pitfr-password-toggle"></button>
        </div>
        <button class="w-full rounded-2xl bg-emerald-600 px-4 py-3 font-semibold text-white">Reset password</button>
    </form>
</main></body></html>