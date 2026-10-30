<dialog
    id="staff-venue-calendar-modal"
    aria-labelledby="staff-venue-calendar-modal-title"
    style="width: min(96vw, 1100px); height: min(92vh, 900px); max-width: none; max-height: 92vh; margin: auto; padding: 0; border: 0;"
    class="overflow-hidden rounded-2xl bg-white shadow-2xl backdrop:bg-slate-950/60 backdrop:backdrop-blur-sm"
>
    <div class="flex h-full min-h-0 flex-col">
        <header class="flex shrink-0 items-center justify-between gap-4 border-b border-slate-200 px-4 py-3 sm:px-5">
            <div class="min-w-0">
                <h2 id="staff-venue-calendar-modal-title" class="truncate text-base font-semibold text-slate-900">Venue availability calendar</h2>
                <p class="mt-0.5 text-xs text-slate-500">Your requested schedule is highlighted. Review approved bookings and tentative requests before confirming availability.</p>
            </div>
            <button type="button" data-venue-calendar-close aria-label="Close venue calendar" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-2xl leading-none text-slate-500 transition hover:bg-slate-100 hover:text-slate-900">
                &times;
            </button>
        </header>
        <iframe id="staff-venue-calendar-modal-frame" title="Venue availability calendar" src="about:blank" class="min-h-0 w-full flex-1 border-0 bg-slate-50"></iframe>
    </div>
</dialog>

<script>
    (() => {
        const modal = document.getElementById('staff-venue-calendar-modal');
        const frame = document.getElementById('staff-venue-calendar-modal-frame');
        const title = document.getElementById('staff-venue-calendar-modal-title');
        if (!modal || !frame || !title) return;

        let lastFocusedElement = null;
        let pendingFrameLoadHandler = null;
        let activeAvailabilityTrigger = null;

        const getGroupButtons = (group) => Array.from(document.querySelectorAll('[data-availability-group]'))
            .filter((button) => button.dataset.availabilityGroup === group);

        const updateAvailabilityGroup = (group) => {
            if (!group) return;

            const buttons = getGroupButtons(group);
            const complete = buttons.length > 0 && buttons.every((button) =>
                ['available', 'unavailable'].includes(button.dataset.availabilityState)
            );
            const hasConflict = buttons.some((button) => button.dataset.availabilityState === 'unavailable');

            document.querySelectorAll('[data-availability-actions]').forEach((element) => {
                if (element.dataset.availabilityActions === group) {
                    element.classList.toggle('hidden', !complete);
                }
            });
            document.querySelectorAll('[data-availability-confirm]').forEach((element) => {
                if (element.dataset.availabilityConfirm === group) {
                    element.classList.toggle('hidden', !complete || hasConflict);
                }
            });
            document.querySelectorAll('[data-availability-summary]').forEach((element) => {
                if (element.dataset.availabilitySummary !== group) return;

                element.textContent = !complete
                    ? 'Check the calendar for each requested venue to validate its schedule.'
                    : (hasConflict
                        ? 'A conflict was found in the requested schedules or resources. Review the calendar and record the reason before marking the request unavailable.'
                        : 'All requested venue schedules were checked. The server will recheck availability when you submit.');
                element.classList.remove('hidden', 'text-amber-800', 'text-emerald-800');
                if (complete) element.classList.add(hasConflict ? 'text-amber-800' : 'text-emerald-800');
            });
        };

        const updateVenueStatus = (trigger, state, message) => {
            const group = trigger.dataset.availabilityGroup;
            const status = Array.from(document.querySelectorAll('[data-availability-venue-status]'))
                .find((element) => element.dataset.availabilityVenueStatus === `${group}:${trigger.dataset.availabilityVenue}`);
            if (status) {
                status.textContent = message;
                status.classList.toggle('text-emerald-700', state === 'available');
                status.classList.toggle('text-amber-800', state === 'unavailable' || state === 'error');
                status.classList.toggle('text-slate-500', state === 'checking');
            }
            trigger.dataset.availabilityState = state;
            updateAvailabilityGroup(group);
        };

        const validateAvailability = async (trigger) => {
            const group = trigger.dataset.availabilityGroup;
            const summary = Array.from(document.querySelectorAll('[data-availability-summary]'))
                .find((element) => element.dataset.availabilitySummary === group);
            if (summary) {
                summary.textContent = 'Checking the requested schedules and resources against current reservations and closures...';
                summary.classList.remove('hidden', 'text-amber-800', 'text-emerald-800');
                summary.classList.add('text-sky-800');
            }

            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            try {
                const response = await fetch(trigger.dataset.availabilityCheckUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token || '',
                    },
                    body: JSON.stringify({ venue: trigger.dataset.availabilityVenue }),
                });
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.message || 'The schedule could not be validated. Please try again.');
                }

                updateVenueStatus(
                    trigger,
                    result.available ? 'available' : 'unavailable',
                    result.message
                );
            } catch (error) {
                updateVenueStatus(trigger, 'error', error.message || 'The schedule could not be validated. Please try again.');
                if (summary) {
                    summary.textContent = 'Validation failed. Actions remain hidden until the schedule check succeeds.';
                    summary.classList.remove('text-sky-800');
                    summary.classList.add('text-amber-800');
                }
            }
        };

        window.addEventListener('message', (event) => {
            const trigger = activeAvailabilityTrigger;
            if (
                !modal.open
                || !trigger
                || event.source !== frame.contentWindow
                || event.origin !== window.location.origin
                || event.data?.type !== 'pitfr-requested-schedule-highlighted'
            ) {
                return;
            }

            const calendarUrl = new URL(trigger.dataset.venueCalendarUrl, window.location.origin);
            const params = calendarUrl.searchParams;
            const expectedEndDate = params.get('end_date') || params.get('start_date');
            if (
                event.data.venue !== params.get('venue')
                || event.data.startDate !== params.get('start_date')
                || event.data.endDate !== expectedEndDate
            ) {
                return;
            }

            validateAvailability(trigger);
        });

        const closeModal = () => {
            if (modal.open) modal.close();
        };

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-venue-calendar-open]');
            if (trigger) {
                lastFocusedElement = trigger;
                title.textContent = trigger.dataset.venueCalendarTitle || 'Venue availability calendar';
                frame.title = title.textContent;
                if (pendingFrameLoadHandler) {
                    frame.removeEventListener('load', pendingFrameLoadHandler);
                    pendingFrameLoadHandler = null;
                }
                activeAvailabilityTrigger = trigger;
                if (trigger.dataset.availabilityCheckUrl) {
                    updateVenueStatus(trigger, 'checking', 'Waiting for the highlighted calendar to load...');
                    pendingFrameLoadHandler = () => {
                        pendingFrameLoadHandler = null;
                        if (modal.open && activeAvailabilityTrigger === trigger) {
                            updateVenueStatus(trigger, 'checking', 'Calendar loaded; waiting for the requested schedule highlight...');
                        }
                    };
                    frame.addEventListener('load', pendingFrameLoadHandler, { once: true });
                }
                frame.src = trigger.dataset.venueCalendarUrl;
                modal.showModal();
                modal.querySelector('[data-venue-calendar-close]')?.focus();
                return;
            }

            if (event.target.closest('[data-venue-calendar-close]')) {
                closeModal();
                return;
            }

            if (event.target === modal) {
                closeModal();
            }
        });

        modal.addEventListener('close', () => {
            if (pendingFrameLoadHandler) {
                frame.removeEventListener('load', pendingFrameLoadHandler);
                pendingFrameLoadHandler = null;
            }
            activeAvailabilityTrigger = null;
            frame.src = 'about:blank';
            lastFocusedElement?.focus();
        });
    })();
</script>
