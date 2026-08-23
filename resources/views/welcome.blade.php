<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PIT – Facility & Equipment Request System</title>
    @if (app()->runningUnitTests())
        {{-- Skip Vite asset loading in tests when the manifest may not exist. --}}
    @else
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="bg-emerald-900 min-h-screen flex flex-col pt-14 sm:pt-16">

<header class="fixed inset-x-0 top-0 z-40 border-b border-white/10 bg-slate-950/95 text-white shadow-lg backdrop-blur">
    <nav class="mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6" aria-label="Guest navigation">
        <a href="#home" class="shrink-0 text-sm font-bold tracking-wide text-emerald-300">PITFR</a>
        <div class="flex min-w-0 flex-1 items-center justify-end gap-1 overflow-x-auto text-xs font-semibold sm:gap-2 sm:text-sm">
            <a href="#home" class="whitespace-nowrap rounded-lg px-3 py-2 transition hover:bg-white/10">Home</a>
            <a href="#calendar-section" class="whitespace-nowrap rounded-lg px-3 py-2 transition hover:bg-white/10">View Facility Calendar</a>
            <a href="#features" class="whitespace-nowrap rounded-lg px-3 py-2 transition hover:bg-white/10">System Features</a>
            <a href="#how-it-works" class="whitespace-nowrap rounded-lg px-3 py-2 transition hover:bg-white/10">How It Works</a>
            <a href="#about-us" class="whitespace-nowrap rounded-lg px-3 py-2 transition hover:bg-white/10">About Us</a>
            <a href="#contact-us" class="whitespace-nowrap rounded-lg px-3 py-2 transition hover:bg-white/10">Contact Us</a>
            <a href="{{ route('login') }}" class="whitespace-nowrap rounded-lg bg-emerald-500 px-3 py-2 text-white transition hover:bg-emerald-600">Login to Request</a>
        </div>
    </nav>
</header>

{{-- Hero --}}
<section id="home" class="relative isolate overflow-hidden bg-cover bg-top px-4 py-14 text-center text-white sm:px-6 sm:py-16 lg:py-20 min-h-[clamp(620px,calc(100vh-64px),820px)]"
        style="background-image: url('{{ asset('images/GPD-BG.jpg') }}');">
    <div class="absolute inset-0 bg-slate-950/20"></div>
    <div class="relative z-10 mx-auto max-w-4xl">
        <div class="inline-flex items-center justify-center w-52 h-52 rounded-full mx-auto mb-8 bg-white/10 backdrop-blur-sm border border-white/20 shadow-lg sm:h-64 sm:w-64 lg:h-72 lg:w-72">
            <img src="{{ asset('images/PIT-LOGO.png') }}" alt="PIT Logo"
                 class="h-44 w-44 rounded-full object-cover border-4 border-white/60 shadow-lg sm:h-56 sm:w-56 lg:h-64 lg:w-64">
        </div>
        <h1 class="mb-4 text-3xl font-bold leading-tight text-white drop-shadow-lg sm:text-4xl md:text-5xl">
            PIT Facility & Equipment<br>Request System
        </h1>
        <p class="text-lg font-semibold text-emerald-200 mb-2">
            Palompon Institute of Technology
        </p>
        <p class="mx-auto mb-8 max-w-2xl text-base text-slate-200 sm:text-lg">
            Preview public facility availability, explore venue schedules, and sign in to submit reservation requests for PIT venues and equipment.
        </p>
    </div>
</section>

{{-- Availability & Calendar --}}
<section id="calendar-section" class="mx-auto w-full max-w-none px-3 py-8 sm:px-6 sm:py-12 lg:max-w-7xl lg:py-16">
    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 shadow-sm sm:p-6 lg:p-8">
        @include('calendar._calendar', [
            'hideHeader' => true,
            'showHowToRequest' => false,
            'showLoginCTA' => false,
            'showAvailabilityPanel' => false,
        ])
    </div>
</section>

{{-- About Us --}}
<section id="about-us" class="mx-auto w-full max-w-none px-3 py-8 sm:px-6 sm:py-12 lg:max-w-7xl lg:py-16">
    <div class="rounded-3xl bg-gradient-to-br from-slate-50 to-emerald-50 p-5 sm:p-8 lg:p-10">
        <div class="max-w-3xl">
            <p class="mb-2 text-sm font-semibold uppercase tracking-wide text-emerald-700">About Us</p>
            <h2 class="mb-4 text-2xl font-bold text-gray-800 sm:text-3xl">A clearer way to coordinate PIT facilities</h2>
            <p class="text-gray-600">
                PIT Facility &amp; Equipment Request System (PITFR) is Palompon Institute of Technology’s reservation and scheduling portal. It helps the institution coordinate facility availability, review requests, and keep approved activities organized in one place.
            </p>
        </div>
        <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-emerald-100 bg-white/80 p-5">
                <h3 class="mb-2 font-bold text-gray-800">What it covers</h3>
                <p class="text-sm text-gray-600">PIT venues such as the Conference Hall, Gymnasium, and Oval Grounds, along with available activity equipment.</p>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-white/80 p-5">
                <h3 class="mb-2 font-bold text-gray-800">Why it exists</h3>
                <p class="text-sm text-gray-600">To make reservation requests, availability checks, scheduling, and approval coordination more consistent.</p>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-white/80 p-5">
                <h3 class="mb-2 font-bold text-gray-800">Who it serves</h3>
                <p class="text-sm text-gray-600">PIT students, faculty, staff, custodians, and administrators who plan or manage institutional activities.</p>
            </div>
        </div>

        <div class="mt-12">
            <div class="mb-5">
                <p class="mb-1 text-sm font-semibold uppercase tracking-wide text-emerald-700">Explore the spaces</p>
                <h3 class="text-2xl font-bold text-gray-800">PIT Venues</h3>
            </div>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['file' => 'chic-inside1.png', 'name' => 'CHIC Conference Hall', 'description' => 'Interior view of the Conference Hall & Interaction Center.'],
                    ['file' => 'chic-inside2.png', 'name' => 'CHIC Conference Hall', 'description' => 'Additional interior view of the Conference Hall & Interaction Center.'],
                    ['file' => 'chic-maindoor.png', 'name' => 'CHIC Conference Hall', 'description' => 'Main entrance of the Conference Hall & Interaction Center.'],
                    ['file' => 'pit-gym-outside.png', 'name' => 'PIT Multi-Purpose Gymnasium', 'description' => 'PIT Gymnasium exterior.'],
                    ['file' => 'balay-alumni.png', 'name' => 'Balay Alumni Function Hall', 'description' => 'Balay Alumni Function Hall.'],
                    ['file' => 'balay-alumni-outside.png', 'name' => 'Balay Alumni Function Hall', 'description' => 'Balay Alumni Function Hall exterior.'],
                    ['file' => 'ovalgrounds-stage.png', 'name' => 'PIT Oval Ground', 'description' => 'PIT Oval Grounds activity area.'],
                    ['file' => 'basketball-court.png', 'name' => 'Covered Court', 'description' => 'Basketball court activity area.'],
                    ['file' => 'volleyball-court.png', 'name' => 'Volleyball Court', 'description' => 'Volleyball Court activity area.'],
                ] as $venue)
                    <article class="group overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-sm transition duration-300 hover:shadow-md">
                        <div class="aspect-[4/3] overflow-hidden">
                            <img src="{{ asset('images/venue/' . $venue['file']) }}" alt="{{ $venue['name'] }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                        </div>
                        <div class="p-4">
                            <h4 class="font-bold text-gray-800">{{ $venue['name'] }}</h4>
                            <p class="mt-1 text-sm text-gray-600">{{ $venue['description'] }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>

        <div class="mt-12">
            <div class="mb-5">
                <p class="mb-1 text-sm font-semibold uppercase tracking-wide text-emerald-700">Available resources</p>
                <h3 class="text-2xl font-bold text-gray-800">Activity Equipment</h3>
            </div>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ([
                    ['file' => 'soundsystem.png', 'name' => 'Sound System'],
                    ['file' => 'microphone.png', 'name' => 'Microphones'],
                    ['file' => 'canopies.png', 'name' => 'Canopies'],
                    ['file' => 'industrial-fan.png', 'name' => 'Industrial Fans'],
                    ['file' => 'cooler-fan.png', 'name' => 'Iwata Cooler Fans'],
                    ['file' => 'table.png', 'name' => 'Tables'],
                    ['file' => 'monobloc chairs.png', 'name' => 'Monobloc chairs'],
                ] as $equipment)
                    <article class="group overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-sm transition duration-300 hover:shadow-md">
                        <div class="aspect-square overflow-hidden">
                            <img src="{{ asset('images/equipment/' . $equipment['file']) }}" alt="{{ $equipment['name'] }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                        </div>
                        <div class="p-3 sm:p-4">
                            <h4 class="font-bold text-gray-800">{{ $equipment['name'] }}</h4>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- Features --}}
<main id="features" class="mx-auto w-full max-w-none flex-1 px-3 py-8 sm:px-6 sm:py-12 lg:max-w-7xl lg:py-16">
    <div class="mb-12 rounded-3xl bg-gradient-to-br from-slate-50 to-blue-50 p-4 sm:p-6 lg:mb-16 lg:p-10">
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
    <div id="how-it-works" class="mb-12 rounded-3xl bg-gradient-to-br from-slate-50 to-blue-50 p-4 sm:p-6 lg:mb-16 lg:p-10">
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
    <div class="rounded-3xl bg-gradient-to-r from-emerald-700 to-emerald-600 p-6 text-center text-white shadow-xl sm:p-8 lg:p-12">
        <h2 class="text-3xl font-bold mb-3">Ready to Get Started?</h2>
        <p class="text-emerald-100 mb-8 max-w-md mx-auto">Sign in to submit your facility and equipment requests. Contact the administration office for account access.</p>
        <a href="{{ route('login') }}"
           class="inline-flex items-center gap-2 bg-white text-emerald-700 hover:bg-slate-100 font-bold px-8 py-3 rounded-xl transition shadow-lg">
            Sign In Now →
        </a>
    </div>

    {{-- Contact Us --}}
    <section id="contact-us" class="mt-12 rounded-3xl bg-gradient-to-br from-slate-50 to-blue-50 p-5 sm:mt-16 sm:p-8 lg:p-10">
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_1.2fr] lg:items-start">
            <div>
                <p class="mb-2 text-sm font-semibold uppercase tracking-wide text-blue-700">Contact Us</p>
                <h2 class="mb-4 text-2xl font-bold text-gray-800 sm:text-3xl">Need help with access or a request?</h2>
                <p class="text-gray-600">For account access and reservation concerns, contact the PITFR administration office using the official details configured for this project.</p>
            </div>
            <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ([
                    'office' => 'Office',
                    'email' => 'Email',
                    'phone' => 'Contact Number',
                    'address' => 'Address',
                ] as $key => $label)
                    <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                        <dd class="mt-1 break-words text-sm font-medium text-slate-800">{{ config("pitfr.contact.$key") ?: 'Not configured' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>
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
@if (app()->runningUnitTests())
    {{-- Skip Vite JS loading in tests when the manifest may not exist. --}}
@else
    @vite(['resources/js/app.js'])
@endif

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

</body>
</html>