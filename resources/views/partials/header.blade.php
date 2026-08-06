<header class="sticky top-0 z-50 w-full bg-slate-900 border-b border-slate-800 shadow-xl">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
        <a href="{{ Auth::check() ? Auth::user()->getDashboardRoute() : route('home') }}" class="flex items-center gap-3">
            <img src="{{ asset('images/PIT-LOGO.jpg') }}" alt="PIT Logo"
                 class="h-11 w-11 rounded-full object-cover border border-white/10 shadow-sm">
            <div class="min-w-0">
                <p class="text-base font-semibold text-white leading-tight">PIT Facility Request System</p>
                <p class="text-xs text-slate-300">Palompon Institute of Technology</p>
            </div>
        </a>

        <div class="hidden md:flex items-center gap-3">
            <a href="{{ route('home') }}" class="rounded-full border border-slate-700 bg-slate-800/90 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-slate-700 hover:text-white transition">
                Guest Access
            </a>

            <a href="{{ route('notifications.index') }}" class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-slate-800 text-slate-200 ring-1 ring-slate-700 hover:bg-slate-700 hover:text-white transition" aria-label="Notifications">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
            </a>

            @auth
            <form method="POST" action="{{ route('logout') }}" class="flex-shrink-0">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-full bg-rose-500 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-rose-500/20 hover:bg-rose-400 transition duration-200">
                    Logout
                </button>
            </form>
            @else
            <a href="{{ route('login') }}" class="inline-flex items-center gap-2 rounded-full bg-emerald-500 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-emerald-500/20 hover:bg-emerald-400 transition duration-200">
                Login
            </a>
            @endauth
        </div>

        <button id="mobile-menu-btn" class="md:hidden flex items-center justify-center w-10 h-10 rounded-lg bg-slate-800 text-slate-200 hover:bg-slate-700 transition duration-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" id="hamburger-icon">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            <svg class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" id="close-icon">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div id="mobile-menu" class="hidden md:hidden bg-slate-950 border-t border-slate-800 max-h-96 overflow-y-auto">
        <div class="px-4 py-4 space-y-3">
            <div class="flex items-center gap-3 rounded-lg bg-slate-900 p-3 shadow-sm border border-slate-700">
                <a href="#" class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-slate-800 text-slate-200 hover:bg-slate-700 transition duration-200 flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </a>
                <div class="text-left flex-1 min-w-0">
                    @auth
                        <p class="text-sm font-semibold text-white truncate">{{ Auth::user()->name }}</p>
                        <span class="text-xs text-slate-400">{{ Auth::user()->role_label }}</span>
                    @else
                        <p class="text-sm font-semibold text-white">Guest</p>
                        <span class="text-xs text-slate-400">Guest Access</span>
                    @endauth
                </div>
            </div>

            <div class="grid grid-cols-1 gap-2">
                @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-rose-500 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-400 transition duration-200 shadow-sm">
                        Logout
                    </button>
                </form>
                @else
                <a href="{{ route('login') }}" class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-400 transition duration-200 shadow-sm">
                    Login
                </a>
                @endauth
            </div>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    const hamburgerIcon = document.getElementById('hamburger-icon');
    const closeIcon = document.getElementById('close-icon');

    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
            hamburgerIcon.classList.toggle('hidden');
            closeIcon.classList.toggle('hidden');
        });
    }
});
</script>