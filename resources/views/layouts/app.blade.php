<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('images/PIT-LOGO.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/PIT-LOGO.png') }}">
    <title>@yield('title', 'PIT – Facility Request System')</title>
    @if (app()->runningUnitTests())
        {{-- Skip Vite asset loading in tests when the manifest may not exist. --}}
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <meta name="user-id" content="{{ Auth::id() }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- SweetAlert2 CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Popper.js & Tippy.js for Tooltips -->
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script src="https://unpkg.com/tippy.js@6"></script>
    <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/themes/light.css">
</head>
<body class="@yield('body-class', 'overflow-x-hidden bg-slate-100 min-h-screen text-slate-900')" data-draft-owner="{{ auth()->user()?->id ?? '' }}" @if(session('request_draft_submitted')) data-request-draft-submitted="1" @endif>

<div class="min-h-screen bg-slate-100">
    @auth
        <aside id="dashboard-sidebar" class="fixed inset-y-0 left-0 z-50 h-screen w-72 -translate-x-full transform overflow-hidden border-r border-slate-200 bg-white shadow-[24px_0_60px_rgba(15,23,42,0.12)] transition-transform duration-300 sm:w-80 lg:fixed lg:inset-y-0 lg:left-0 lg:h-screen lg:w-80 lg:translate-x-0 lg:overflow-hidden">
            @include('components.dashboard-sidebar')
        </aside>
        <div id="sidebar-backdrop" class="fixed inset-0 z-40 hidden bg-slate-950/40 backdrop-blur-sm lg:hidden"></div>
        <button id="sidebar-toggle" type="button" aria-controls="dashboard-sidebar" aria-expanded="false" aria-label="Open navigation menu" title="Open navigation menu" class="fixed left-4 top-4 z-[60] inline-flex items-center rounded-2xl bg-emerald-600 px-3 py-2 text-white shadow-lg shadow-emerald-600/20 ring-1 ring-white/10 transition hover:bg-emerald-500 lg:hidden">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    @endauth

    <main class="min-h-screen overflow-x-hidden px-3 py-3 {{ auth()->check() ? 'pt-16 lg:ml-80' : 'pt-6' }} sm:px-4 sm:py-4 sm:pt-6 md:px-6 lg:min-h-screen lg:overflow-y-auto lg:px-8 lg:py-6 lg:pt-8">
        <div class="mx-auto w-full max-w-none lg:max-w-7xl">
            {{-- Messages appear as toasts (SweetAlert2, already on every page). Screen readers
                 hear them from the live region; the banners below show only if toasts cannot. --}}
            @php
                $flashToasts = array_values(array_filter([
                    session('success') ? ['icon' => 'success', 'text' => (string) session('success')] : null,
                    session('info') ? ['icon' => 'info', 'text' => (string) session('info')] : null,
                    session('warning') ? ['icon' => 'warning', 'text' => (string) session('warning')] : null,
                    session('error') ? ['icon' => 'error', 'text' => (string) session('error')] : null,
                    isset($errors) && $errors->any() ? ['icon' => 'error', 'text' => (string) $errors->first()] : null,
                ]));
            @endphp
            @if($flashToasts !== [])
                <div class="sr-only" role="status" aria-live="polite" data-flash-messages>@foreach($flashToasts as $flashToast){{ $flashToast['text'] }} @endforeach</div>
                <script>
                    (function () {
                    const showFlashToasts = async function () {
                        const messages = @json($flashToasts);
                        if (!window.Swal || typeof window.Swal.mixin !== 'function') {
                            document.querySelectorAll('[data-flash-fallback]').forEach(function (banner) { banner.hidden = false; });
                            return;
                        }
                        const Toast = window.Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            showCloseButton: true,
                            timerProgressBar: true,
                            customClass: { popup: 'pitfr-toast' },
                            didOpen: function (toast) {
                                toast.addEventListener('mouseenter', window.Swal.stopTimer);
                                toast.addEventListener('mouseleave', window.Swal.resumeTimer);
                            },
                        });
                        for (const message of messages) {
                            await Toast.fire({ icon: message.icon, title: message.text, timer: message.icon === 'success' || message.icon === 'info' ? 4000 : 8000 });
                        }
                    };
                    // Show right away: SweetAlert2 is loaded in <head> and the page body exists
                    // here, so the message does not wait for the rest of the page to load.
                    showFlashToasts();
                    })();
                </script>
                <style>
                    .pitfr-toast { max-width: min(28rem, calc(100vw - 2rem)); }
                    .pitfr-toast .swal2-title { font-size: 0.9rem !important; font-weight: 500 !important; line-height: 1.4 !important; text-align: left !important; }
                </style>
            @endif
            @if(session('success'))
                <div data-flash-fallback hidden class="mb-6 rounded-3xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900 shadow-sm">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif
            @if(session('warning'))
                <div data-flash-fallback hidden class="mb-6 rounded-3xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900 shadow-sm">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ session('warning') }}</span>
                    </div>
                </div>
            @endif
            @if(isset($errors) && $errors->any())
                <div data-flash-fallback hidden class="mb-6 rounded-3xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 shadow-sm">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <span>{{ $errors->first() }}</span>
                    </div>
                </div>
            @endif
                    @yield('content')
        </div>
    </main>
    <footer class="border-t border-slate-200 bg-white px-4 py-5 text-center text-sm text-slate-600 {{ auth()->check() ? 'lg:ml-80' : '' }}">
        <nav class="flex flex-wrap justify-center gap-x-5 gap-y-2" aria-label="Help and legal information">
            <button type="button" class="font-medium text-emerald-800 underline-offset-4 hover:underline" data-open-faq-modal aria-haspopup="dialog" aria-controls="faq-modal">FAQ</button>
            @if(config('app.privacy_page'))
            <a href="{{ route('privacy.data-protection') }}" class="font-medium text-emerald-800 underline-offset-4 hover:underline">Privacy &amp; Data Protection</a>
            @endif
        </nav>
        @if(config('app.privacy_page'))
        <p class="mt-2 text-xs text-slate-500">PITFR handles personal information in accordance with RA 10173, the Data Privacy Act of 2012.</p>
        @endif
    </footer>
    @include('components.venue-calendar-modal')
    <script>
        // Additional Enhancements, search: every list search box gets a clear button and
        // searches as you type (server-side, so all records are searched, not one page).
        (() => {
            const boxes = document.querySelectorAll('form[method=GET] input[name=search], form[method=get] input[name=search]');
            boxes.forEach((input) => {
                const form = input.form;
                if (!form || input.dataset.searchEnhanced) return;
                input.dataset.searchEnhanced = '1';
                input.setAttribute('autocomplete', 'off');
                if (!input.getAttribute('maxlength')) input.setAttribute('maxlength', '200');

                const wrap = document.createElement('span');
                wrap.className = 'relative block w-full';
                input.parentNode.insertBefore(wrap, input);
                wrap.appendChild(input);
                input.style.paddingRight = '2.5rem';
                const clear = document.createElement('button');
                clear.type = 'button';
                clear.className = 'absolute right-2 top-1/2 -translate-y-1/2 rounded-full p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500';
                clear.setAttribute('aria-label', 'Clear search');
                clear.innerHTML = '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>';
                wrap.appendChild(clear);
                const sync = () => { clear.hidden = input.value === ''; };
                sync();

                const submit = () => {
                    try { sessionStorage.setItem('pitfr-search-focus', input.name + '|' + location.pathname); } catch (e) {}
                    const page = form.querySelector('input[name="page"]');
                    if (page) page.remove();
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                };
                let timer = null;
                let last = input.value.trim();
                input.addEventListener('input', () => {
                    sync();
                    clearTimeout(timer);
                    const value = input.value.trim();
                    if (value === last || (value.length > 0 && value.length < 2)) return;
                    timer = setTimeout(() => { last = value; submit(); }, 600);
                });
                input.addEventListener('search', () => { if (input.value === '' && last !== '') { last = ''; submit(); } });
                clear.addEventListener('click', () => {
                    input.value = '';
                    sync();
                    input.focus();
                    if (last !== '') { last = ''; submit(); }
                });

                // After a search-as-you-type reload, keep typing where you were.
                try {
                    if (sessionStorage.getItem('pitfr-search-focus') === input.name + '|' + location.pathname) {
                        sessionStorage.removeItem('pitfr-search-focus');
                        input.focus();
                        const end = input.value.length;
                        input.setSelectionRange && input.type !== 'search' ? input.setSelectionRange(end, end) : null;
                    }
                } catch (e) {}
            });
        })();
    </script>
    @include('components.faq-modal')
</div>

@yield('scripts')

{{-- WebSocket Event Listeners --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // WebSocket event listeners for real-time updates
    if (window.PitfrWebSocket) {
        window.PitfrWebSocket.on('connected', function() {
            console.log('WebSocket connected - real-time updates enabled');
            // Show connection status if needed
        });

        window.PitfrWebSocket.on('disconnected', function() {
            console.log('WebSocket disconnected - real-time updates disabled');
            // Show disconnection status if needed
        });

        window.PitfrWebSocket.on('request_created', function(data) {
            console.log('New request created:', data);
            // Refresh request lists, show notification, etc.
            showRealtimeNotification('New Request', `Request ${data.control_number} submitted by ${data.user_name}`, 'info');
            refreshRequestLists();
        });

        window.PitfrWebSocket.on('request_approved', function(data) {
            console.log('Request approved:', data);
            showRealtimeNotification('Request Approved', `Request ${data.control_number} ${data.approval_type} approved by ${data.user_name}`, 'success');
            refreshRequestLists();
        });

        window.PitfrWebSocket.on('request_rejected', function(data) {
            console.log('Request rejected:', data);
            showRealtimeNotification('Request Rejected', `Request ${data.control_number} ${data.rejection_type} rejected by ${data.user_name}`, 'error');
            refreshRequestLists();
        });

        window.PitfrWebSocket.on('request_cancelled', function(data) {
            console.log('Request cancelled:', data);
            showRealtimeNotification('Request Cancelled', `Request ${data.control_number} cancelled by ${data.user_name}`, 'warning');
            refreshRequestLists();
        });

        window.PitfrWebSocket.on('equipment_returned', function(data) {
            console.log('Equipment returned:', data);
            showRealtimeNotification('Equipment Returned', `Equipment for request ${data.control_number} returned by ${data.user_name}`, 'success');
            refreshRequestLists();
            refreshEquipmentAvailability();
        });
    }

    function showRealtimeNotification(title, message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `fixed top-20 right-6 z-50 max-w-sm p-4 rounded-xl shadow-lg transform transition-all duration-300 translate-x-full`;

        // Set colors based on type
        const colors = {
            success: 'bg-green-50 border-green-200 text-green-800',
            error: 'bg-red-50 border-red-200 text-red-800',
            warning: 'bg-yellow-50 border-yellow-200 text-yellow-800',
            info: 'bg-blue-50 border-blue-200 text-blue-800'
        };

        notification.classList.add(...colors[type].split(' '));

        // Build notification DOM using safe text insertion for dynamic values
        const inner = document.createElement('div');
        inner.className = 'flex items-start gap-3';

        const iconWrapper = document.createElement('div');
        iconWrapper.className = 'flex-shrink-0';
        // getIcon returns static SVG markup; safe to set as innerHTML
        iconWrapper.innerHTML = getIcon(type);

        const content = document.createElement('div');
        content.className = 'flex-1';

        const titleEl = document.createElement('p');
        titleEl.className = 'text-sm font-semibold';
        titleEl.textContent = title ?? '';

        const messageEl = document.createElement('p');
        messageEl.className = 'text-sm opacity-90';
        messageEl.textContent = message ?? '';

        content.appendChild(titleEl);
        content.appendChild(messageEl);

        const closeBtn = document.createElement('button');
        closeBtn.className = 'text-gray-400 hover:text-gray-600';
        closeBtn.setAttribute('aria-label', 'Close notification');
        closeBtn.addEventListener('click', function () {
            if (notification.parentElement) notification.parentElement.removeChild(notification);
        });
        // Static close icon
        closeBtn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';

        inner.appendChild(iconWrapper);
        inner.appendChild(content);
        inner.appendChild(closeBtn);

        notification.appendChild(inner);

        document.body.appendChild(notification);

        // Animate in
        setTimeout(() => {
            notification.classList.remove('translate-x-full');
        }, 100);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.classList.add('translate-x-full');
                setTimeout(() => notification.remove(), 300);
            }
        }, 5000);
    }

    function getIcon(type) {
        const icons = {
            success: '<svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
            error: '<svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>',
            warning: '<svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>',
            info: '<svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
        };
        return icons[type] || icons.info;
    }

    function refreshRequestLists() {
        // Refresh any request tables/lists on the page
        const requestTables = document.querySelectorAll('[data-refresh="requests"]');
        requestTables.forEach(table => {
            if (typeof table.refresh === 'function') {
                table.refresh();
            } else {
                // Fallback: reload the page or specific section
                location.reload();
            }
        });
    }

    function refreshEquipmentAvailability() {
        // Refresh equipment availability displays
        const availabilityElements = document.querySelectorAll('[data-refresh="equipment"]');
        availabilityElements.forEach(element => {
            if (typeof element.refresh === 'function') {
                element.refresh();
            }
        });
    }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const setLoadingButtonState = function (button, loadingText) {
        if (!button) {
            return;
        }

        button.dataset.originalHtml = button.dataset.originalHtml || button.innerHTML;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.setAttribute('aria-label', loadingText || 'Loading...');
        button.classList.add('opacity-80', 'cursor-not-allowed');
        button.innerHTML = `
            <span class="inline-flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>${loadingText}</span>
            </span>
        `;
    };

    document.querySelectorAll('[data-swal-confirm]').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            // Let the form's own checks run first; a page script cancels this event
            // when the form is incomplete, so the dialog never submits an invalid form.
            const readyCheck = new CustomEvent('swal-before-confirm', { cancelable: true });
            if (!form.dispatchEvent(readyCheck)) {
                return;
            }

            const result = await Swal.fire({
                title: form.dataset.swalTitle || 'Are you sure?',
                text: form.dataset.swalText || 'This action cannot be undone.',
                icon: form.dataset.swalIcon || 'warning',
                showCancelButton: true,
                reverseButtons: true,
                focusCancel: true,
                confirmButtonText: form.dataset.swalConfirmText || 'Yes, continue',
                cancelButtonText: 'Keep it',
                confirmButtonColor: form.dataset.swalConfirmColor || '#059669',
                cancelButtonColor: '#9CA3AF',
                allowOutsideClick: false,
                allowEscapeKey: true,
            });

            if (result.isConfirmed) {
                const submitPreparations = [];
                form.dispatchEvent(new CustomEvent('confirmed-submit', {
                    detail: {
                        waitUntil: function (promise) {
                            submitPreparations.push(Promise.resolve(promise));
                        }
                    }
                }));

                try {
                    await Promise.all(submitPreparations);
                } catch (error) {
                    console.error('Confirmed form submission preparation failed', error);
                    await Swal.fire('Unable to continue', 'The form could not be prepared for submission. Please try again.', 'error');
                    return;
                }

                const submitButton = form.querySelector('button[type="submit"]');
                const buttonText = (submitButton?.textContent || '').toLowerCase();
                let loadingText = form.dataset.swalLoadingText || 'Submitting...';

                if (!form.dataset.swalLoadingText && (buttonText.includes('confirm') || buttonText.includes('availability'))) {
                    loadingText = 'Confirming availability...';
                } else if (!form.dataset.swalLoadingText && buttonText.includes('approve')) {
                    loadingText = 'Approving...';
                } else if (!form.dataset.swalLoadingText && (buttonText.includes('verify') || buttonText.includes('endorse'))) {
                    loadingText = 'Verifying...';
                } else if (!form.dataset.swalLoadingText && buttonText.includes('reject')) {
                    loadingText = 'Rejecting...';
                } else if (!form.dataset.swalLoadingText && (buttonText.includes('return') || buttonText.includes('revision'))) {
                    loadingText = 'Returning...';
                } else if (!form.dataset.swalLoadingText && buttonText.includes('save')) {
                    loadingText = 'Saving...';
                } else if (!form.dataset.swalLoadingText && buttonText.includes('delete')) {
                    loadingText = 'Deleting...';
                } else if (!form.dataset.swalLoadingText && buttonText.includes('submit')) {
                    loadingText = 'Submitting...';
                }

                setLoadingButtonState(submitButton, loadingText);
                form.submit();
            }
        });
    });

    document.querySelectorAll('form[data-show-loading="true"]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton && !form.dataset.swalConfirm) {
                const buttonText = (submitButton?.textContent || '').toLowerCase();
                let loadingText = 'Submitting...';

                if (buttonText.includes('approve')) {
                    loadingText = 'Approving...';
                } else if (buttonText.includes('reject')) {
                    loadingText = 'Rejecting...';
                } else if (buttonText.includes('return')) {
                    loadingText = 'Returning...';
                } else if (buttonText.includes('save') || buttonText.includes('update')) {
                    loadingText = 'Saving...';
                } else if (buttonText.includes('delete')) {
                    loadingText = 'Deleting...';
                }

                setLoadingButtonState(submitButton, loadingText);
            }
        });
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const cards = Array.from(document.querySelectorAll('.dashboard-stat-cards .stat-card[data-filter]'));
    const rows = Array.from(document.querySelectorAll('[data-request-row]'));
    const clearFilterButton = document.querySelector('[data-action="clear-request-filter"]');
    const tableBody = rows.length ? rows[0].closest('tbody') : null;
    let activeFilter = null;

    if (!cards.length || !tableBody) {
        return;
    }

    function buildNoResultsRow() {
        const row = document.createElement('tr');
        row.id = 'dashboard-filter-no-results';
        row.className = 'bg-yellow-50';
        row.innerHTML = `
            <td colspan="4" class="px-4 py-6 text-center text-sm text-yellow-900">
                No requests found for this category.
            </td>
        `;
        return row;
    }

    function clearExistingMessage() {
        const existing = document.getElementById('dashboard-filter-no-results');
        if (existing && existing.parentElement) {
            existing.parentElement.removeChild(existing);
        }
    }

    function updateCardStyles() {
        cards.forEach(function(card) {
            const isActive = card.dataset.filter === activeFilter;
            card.classList.toggle('border-slate-900', isActive);
            card.classList.toggle('bg-slate-50', isActive);
            card.classList.toggle('shadow-xl', isActive);
            card.classList.toggle('shadow-sm', !isActive);
        });
    }

    function updateFilter(filter) {
        if (activeFilter === filter) {
            filter = null; // toggle off the active filter
        }

        activeFilter = filter;
        updateCardStyles();

        const serverPlaceholders = Array.from(tableBody.querySelectorAll('tr:not([data-request-row])'));
        serverPlaceholders.forEach(function(placeholder) {
            placeholder.style.display = filter ? 'none' : '';
        });

        let visibleCount = 0;

        rows.forEach(function(row) {
            const status = (row.dataset.status || '').toLowerCase();
            const returned = (row.dataset.returned || '').toLowerCase();
            const upcoming = row.dataset.upcoming === 'true';
            let visible = true;

            if (!filter) {
                visible = true;
            } else if (filter === 'upcoming') {
                visible = status === 'approved' && upcoming;
            } else if (filter === 'pending') {
                visible = status === 'pending';
            } else if (filter === 'completed') {
                visible = status === 'approved' && returned === 'returned';
            }

            row.style.display = visible ? '' : 'none';
            if (visible) {
                visibleCount += 1;
            }
        });

        clearExistingMessage();

        if (filter && visibleCount === 0) {
            tableBody.appendChild(buildNoResultsRow());
        }
    }

    cards.forEach(function(card) {
        card.addEventListener('click', function() {
            updateFilter(card.dataset.filter);
        });
    });

    if (clearFilterButton) {
        clearFilterButton.addEventListener('click', function() {
            updateFilter(null);
        });
    }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var sidebar = document.getElementById('dashboard-sidebar');
    var toggle = document.getElementById('sidebar-toggle');
    var backdrop = document.getElementById('sidebar-backdrop');
    var closeButton = document.getElementById('sidebar-close');
    var closeTriggers = Array.from(document.querySelectorAll('[data-sidebar-close]'));

    function openSidebar() {
        sidebar.classList.remove('-translate-x-full');
        backdrop.classList.remove('hidden');
        toggle.setAttribute('aria-expanded', 'true');
        document.body.classList.add('overflow-hidden');
    }

    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('overflow-hidden');
    }

    if (toggle && sidebar && backdrop) {
        toggle.addEventListener('click', function() {
            if (sidebar.classList.contains('-translate-x-full')) {
                openSidebar();
            } else {
                closeSidebar();
            }
        });

        backdrop.addEventListener('click', closeSidebar);
        if (closeButton) {
            closeButton.addEventListener('click', closeSidebar);
        }

        closeTriggers.forEach(function(trigger) {
            trigger.addEventListener('click', function() {
                if (window.innerWidth < 1024) {
                    closeSidebar();
                }
            });
        });

        window.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && !sidebar.classList.contains('-translate-x-full')) {
                closeSidebar();
            }
        });
    }
});
</script>
</body>
</html>