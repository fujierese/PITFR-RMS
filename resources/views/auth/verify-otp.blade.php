<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify email - PITFR</title>
    @if (!app()->runningUnitTests())
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="min-h-screen bg-slate-950 p-6 text-slate-900">
    <main class="mx-auto mt-16 max-w-md rounded-3xl bg-white p-8 shadow-2xl">
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-600">Email verification</p>
        <h1 class="mt-3 text-3xl font-semibold">Check your inbox</h1>
        <p class="mt-3 text-sm text-slate-500">Enter the six-digit code sent to {{ $email }}.</p>
        @if(session('status'))<p class="mt-4 text-sm text-emerald-700">{{ session('status') }}</p>@endif
        @if($errors->any())<p class="mt-4 text-sm text-red-600">{{ $errors->first() }}</p>@endif
        <form method="POST" action="{{ route('register.verify.post') }}" class="mt-6 space-y-4">
            @csrf
            <input name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-center text-2xl tracking-[0.5em]" aria-label="Verification code">
            <button class="w-full rounded-2xl bg-emerald-600 px-4 py-3 font-semibold text-white">Verify email</button>
        </form>
        <form method="POST" action="{{ route('register.verify.resend') }}" class="mt-3 text-center">
            @csrf
            <button class="text-sm font-semibold text-emerald-700">Send a new code</button>
        </form>
    </main>
</body>
</html>