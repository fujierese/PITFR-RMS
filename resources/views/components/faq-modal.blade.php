<dialog id="faq-modal" aria-labelledby="info-modal-title" class="m-auto max-h-[90vh] w-[calc(100%-2rem)] max-w-3xl rounded-3xl p-0 shadow-2xl backdrop:bg-slate-950/70">
    <div class="flex max-h-[90vh] flex-col overflow-hidden bg-white">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-7">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">PIT information</p>
                <h2 id="info-modal-title" class="mt-1 text-xl font-bold text-slate-900 sm:text-2xl">Frequently Asked Questions</h2>
            </div>
            <button type="button" data-close-info-modal class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xl font-semibold text-slate-700 hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500" aria-label="Close information dialog">×</button>
        </div>
        <div class="overflow-y-auto bg-slate-50 p-4 sm:p-6">
            <section data-info-modal-panel="faq">
                @include('legal.faq-content')
            </section>
            <section data-info-modal-panel="privacy" class="hidden">
                @include('legal.privacy-content')
            </section>
            <section data-info-modal-panel="data-privacy" class="hidden">
                @include('legal.data-privacy-content')
            </section>
        </div>
    </div>
</dialog>

<script>
    (() => {
        const dialog = document.getElementById('faq-modal');
        if (!dialog) return;

        const title = dialog.querySelector('#info-modal-title');
        const panels = dialog.querySelectorAll('[data-info-modal-panel]');
        const titles = {
            faq: 'Frequently Asked Questions',
            privacy: 'Privacy Policy (Draft)',
            'data-privacy': 'Data Privacy Act of 2012',
        };

        document.querySelectorAll('[data-open-faq-modal], [data-open-info-modal]').forEach((trigger) => {
            trigger.addEventListener('click', (event) => {
                event.preventDefault();
                const panelName = trigger.dataset.openInfoModal || 'faq';
                if (!titles[panelName]) return;

                title.textContent = titles[panelName];
                panels.forEach((panel) => {
                    panel.classList.toggle('hidden', panel.dataset.infoModalPanel !== panelName);
                });
                if (!dialog.open) dialog.showModal();
            });
        });

        dialog.querySelectorAll('[data-close-info-modal]').forEach((button) => {
            button.addEventListener('click', () => dialog.close());
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    })();
</script>
