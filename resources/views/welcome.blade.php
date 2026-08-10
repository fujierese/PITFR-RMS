<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PIT – Facility & Equipment Request System</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white min-h-screen flex flex-col">

{{-- Hero --}}
<section class="relative overflow-hidden bg-cover bg-center px-4 py-16 text-center text-white sm:px-6 sm:py-12 lg:py-7"
            style="background-image: url('{{ asset('images/GPD-BG.jpg') }}'); background-attachment: fixed;">
    <div class="relative z-10 mx-auto max-w-4xl">
        <div class="inline-flex items-center justify-center w-80 h-80 rounded-full">
            <img src="{{ asset('images/PIT-LOGO.png') }}" alt="PIT Logo"
                 class="w-80 h-80 rounded-full object-cover border-4 border-white/50 shadow-lg">
        </div>
        <h1 class="mb-4 text-3xl font-bold leading-tight sm:text-4xl md:text-5xl">
            PIT Facility & Equipment<br>Request System
        </h1>
        <p class="text-lg font-semibold text-emerald-100 mb-2">
            Palompon Institute of Technology
        </p>
        <p class="mx-auto mb-8 max-w-2xl text-base text-blue-100 sm:text-lg">
            Review public facility availability and submit reservation requests for venues and equipment at Palompon Institute of Technology.
        </p>
        <div class="flex flex-col sm:flex-row justify-center gap-3">
            <a href="{{ route('login') }}"
               class="inline-flex items-center justify-center gap-2 bg-emerald-500 hover:bg-emerald-600 text-white font-bold px-8 py-3 rounded-xl transition shadow-lg text-sm">
                Login to Request →
            </a>
            <a href="#calendar-section"
               class="inline-flex items-center justify-center gap-2 border border-white/50 bg-white/10 hover:bg-white/20 text-white font-bold px-8 py-3 rounded-xl transition shadow-lg text-sm backdrop-blur-sm">
                View Facility Calendar →
            </a>
        </div>
    </div>
</section>

{{-- Standalone Calendar Section --}}
<section id="calendar-section" class="mx-auto w-full max-w-none bg-white px-3 py-8 sm:px-6 sm:py-12 lg:max-w-7xl lg:py-16">
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
        @include('calendar._calendar', [
            'hideHeader' => true,
            'showHowToRequest' => false,
            'showLoginCTA' => false,
            'showAvailabilityPanel' => false,
        ])
    </div>
</section>

{{-- Public Facility Availability Container --}}
<section class="mx-auto w-full max-w-none bg-white px-3 pb-8 sm:px-6 sm:pb-12 lg:max-w-7xl lg:pb-16">
    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 shadow-sm sm:p-6 lg:p-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-2xl">
                <h2 class="text-2xl font-bold text-slate-900">Public Facility Availability</h2>
                <p class="mt-2 text-sm sm:text-base text-slate-600">
                    Check facility availability before submitting a reservation request. The public calendar shows venue schedules and availability for reference without exposing private requestor details.
                </p>
            </div>
            <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700">
                Login to Submit a Request
            </a>
        </div>

        <div class="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @php
                $venueLegend = [
                    ['label' => 'Conference Hall & Interaction Center (CHIC)', 'color' => '#3b82f6'],
                    ['label' => 'Balay Alumni', 'color' => '#ec4899'],
                    ['label' => 'Oval Grounds', 'color' => '#f97316'],
                    ['label' => 'Gymnasium', 'color' => '#10b981'],
                    ['label' => 'Covered Court', 'color' => '#8b5cf6'],
                    ['label' => 'Volleyball Court', 'color' => '#06b6d4'],
                ];
            @endphp
            @foreach ($venueLegend as $legendItem)
                <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <span class="inline-flex h-3 w-3 rounded-full" style="background-color: {{ $legendItem['color'] }}"></span>
                    <span class="text-sm font-medium text-slate-700">{{ $legendItem['label'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            Review the calendar, then sign in to submit a reservation request for the venue and equipment you need.
        </div>

        <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-xl font-semibold text-slate-900">How to Request</h3>
            <p class="mt-2 text-sm text-slate-600">Use the public calendar to review availability first, then sign in to submit a reservation request for the facility or equipment you need.</p>
            <ol class="mt-4 list-decimal list-inside space-y-2 text-sm text-slate-600">
                <li>Sign in with your PIT account.</li>
                <li>Review the calendar for available dates and venue schedules.</li>
                <li>Submit a reservation request with your preferred venue and equipment details.</li>
                <li>Wait for custodian review and administrator final approval.</li>
            </ol>
        </div>
    </div>
</section>

{{-- Features --}}
<main class="mx-auto w-full max-w-none flex-1 px-3 py-8 sm:px-6 sm:py-12 lg:max-w-7xl lg:py-16">
    <h2 class="text-2xl font-bold text-center text-gray-800 mb-3">System Features</h2>
    <p class="text-center text-gray-400 text-sm mb-10">Everything you need to manage facility and equipment requests</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 mb-20">
        <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-2xl p-6 text-center hover:shadow-lg transition">
            <div class="bg-green-500 w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-md">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m7-14v4m0 0v4m0-4h4m-4 0H8"/>
                </svg>
            </div>
            <h3 class="font-bold text-gray-800 mb-2">Venue Reservation Request</h3>
            <p class="text-sm text-gray-500">Request Conference Hall, Gymnasium, Oval Grounds, and more for your activity</p>
        </div>

        <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-2xl p-6 text-center hover:shadow-lg transition">
            <div class="bg-blue-500 w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-md">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
                </svg>
            </div>
            <h3 class="font-bold text-gray-800 mb-2">Equipment Request</h3>
            <p class="text-sm text-gray-500">Request sound systems, chairs, tables, microphones, and more for your activity</p>
        </div>

        <div class="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 rounded-2xl p-6 text-center hover:shadow-lg transition">
            <div class="bg-purple-500 w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-md">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <h3 class="font-bold text-gray-800 mb-2">Smart Scheduling</h3>
            <p class="text-sm text-gray-500">Automated approval workflow with date and time management</p>
        </div>

        <div class="bg-gradient-to-br from-orange-50 to-orange-100 border border-orange-200 rounded-2xl p-6 text-center hover:shadow-lg transition">
            <div class="bg-orange-500 w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-md">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <h3 class="font-bold text-gray-800 mb-2">Role-Based Access</h3>
            <p class="text-sm text-gray-500">Separate dashboards for students, faculty, custodians, and admins</p>
        </div>
    </div>

    {{-- How It Works --}}
    <div class="mb-12 rounded-3xl bg-gradient-to-br from-slate-50 to-blue-50 p-4 sm:p-6 lg:mb-16 lg:p-10">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-10">How It Works</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6">
            <div class="text-center">
                <div class="w-12 h-12 rounded-full bg-blue-600 text-white font-bold text-xl flex items-center justify-center mx-auto mb-4 shadow-lg">1</div>
                <h3 class="font-bold text-gray-800 mb-2">Sign In</h3>
                <p class="text-sm text-gray-500">Log in with your PIT account to access the request system.</p>
            </div>
            <div class="text-center">
                <div class="w-12 h-12 rounded-full bg-green-600 text-white font-bold text-xl flex items-center justify-center mx-auto mb-4 shadow-lg">2</div>
                <h3 class="font-bold text-gray-800 mb-2">Submit Request</h3>
                <p class="text-sm text-gray-500">Provide your reservation details and specify the facilities or equipment needed.</p>
            </div>
            <div class="text-center">
                <div class="w-12 h-12 rounded-full bg-purple-600 text-white font-bold text-xl flex items-center justify-center mx-auto mb-4 shadow-lg">3</div>
                <h3 class="font-bold text-gray-800 mb-2">Custodian Review</h3>
                <p class="text-sm text-gray-500">Custodians review availability and validate the request details.</p>
            </div>
            <div class="text-center">
                <div class="w-12 h-12 rounded-full bg-amber-600 text-white font-bold text-xl flex items-center justify-center mx-auto mb-4 shadow-lg">4</div>
                <h3 class="font-bold text-gray-800 mb-2">Administrator</h3>
                <p class="text-sm text-gray-500">The Administrator gives the final approval decision.</p>
            </div>
            <div class="text-center">
                <div class="w-12 h-12 rounded-full bg-orange-600 text-white font-bold text-xl flex items-center justify-center mx-auto mb-4 shadow-lg">5</div>
                <h3 class="font-bold text-gray-800 mb-2">Confirmation</h3>
                <p class="text-sm text-gray-500">The reservation is confirmed after approval.</p>
            </div>
        </div>
    </div>

    {{-- CTA --}}
    <div class="rounded-3xl bg-gradient-to-r from-emerald-700 to-emerald-500 p-6 text-center text-white shadow-xl sm:p-8 lg:p-12">
        <h2 class="text-3xl font-bold mb-3">Ready to Get Started?</h2>
        <p class="text-emerald-100 mb-8 max-w-md mx-auto">Sign in to submit your facility and equipment requests. Contact the administration office for account access.</p>
        <a href="{{ route('login') }}"
           class="inline-flex items-center gap-2 bg-emerald-100 hover:bg-emerald-200 text-emerald-900 font-bold px-8 py-3 rounded-xl transition shadow-lg">
            Sign In Now →
        </a>
    </div>
</main>

{{-- Footer --}}
<footer class="bg-gray-800 text-gray-400 text-center text-sm py-6 mt-8">
    <p class="font-medium text-gray-300">© {{ date('Y') }} Palompon Institute of Technology</p>
    <p class="text-xs mt-1">Quality Management System Portal — Facility & Equipment Request System</p>
</footer>

<!-- FullCalendar CSS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">

<!-- Tippy.js for Tooltips -->
<script src="https://unpkg.com/tippy.js@6"></script>
<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/themes/light.css">

<!-- App JS -->
@vite(['resources/js/app.js'])

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

</body>
</html>