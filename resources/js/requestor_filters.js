document.addEventListener('DOMContentLoaded', function () {
    const filterForm = document.getElementById('request-filters-form');
    if (!filterForm) {
        return;
    }

    const searchInput = document.getElementById('search');
    const statusSelect = document.getElementById('status');
    const venueSelect = document.getElementById('venue');
    const sortSelect = document.getElementById('sort');
    const dateFromInput = document.getElementById('date_from');
    const dateToInput = document.getElementById('date_to');
    const toggleButton = document.getElementById('filters-toggle-button');
    const toggleIcon = document.getElementById('filters-toggle-icon');
    const advancedPanel = document.getElementById('advanced-filters-panel');
    const clearButton = document.getElementById('clear-filters-button');
    const countInfo = document.getElementById('request-count-info');
    const resultsPanel = document.getElementById('request-results-panel');
    const xhrToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const originalUrl = new URL(filterForm.action, window.location.origin);

    let debounceTimer;

    const buildQueryParams = () => {
        const params = new URLSearchParams();
        params.set('tab', 'requests');

        if (searchInput?.value.trim() !== '') {
            params.set('search', searchInput.value.trim());
        }

        if (statusSelect?.value !== '') {
            params.set('status', statusSelect.value);
        }

        if (venueSelect?.value !== '') {
            params.set('venue', venueSelect.value);
        }

        if (sortSelect?.value !== 'latest') {
            params.set('sort', sortSelect.value);
        }

        if (dateFromInput?.value) {
            params.set('date_from', dateFromInput.value);
        }

        if (dateToInput?.value) {
            params.set('date_to', dateToInput.value);
        }

        return params;
    };

    const updateUrlState = (params) => {
        const newUrl = `${originalUrl.pathname}?${params.toString()}`;
        window.history.replaceState({}, '', newUrl);
    };

    const refreshRequestList = () => {
        const params = buildQueryParams();
        const url = `${originalUrl.pathname}?${params.toString()}`;

        if (resultsPanel) {
            resultsPanel.classList.add('opacity-75');
            const existingLoading = document.getElementById('request-results-loading');
            if (!existingLoading) {
                const loadingRow = document.createElement('div');
                loadingRow.id = 'request-results-loading';
                loadingRow.className = 'mb-3 flex items-center gap-2 text-sm text-slate-500';
                loadingRow.innerHTML = `
                    <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Refreshing results...</span>
                `;
                resultsPanel.insertAdjacentElement('afterbegin', loadingRow);
            }
        }

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': xhrToken,
                'Accept': 'text/html',
            },
            credentials: 'same-origin',
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then((html) => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newResultsPanel = doc.getElementById('request-results-panel');
                const newCountInfo = doc.getElementById('request-count-info');
                const newSearchValue = doc.getElementById('search');
                const newStatusValue = doc.getElementById('status');
                const newVenueValue = doc.getElementById('venue');
                const newSortValue = doc.getElementById('sort');
                const newDateFromValue = doc.getElementById('date_from');
                const newDateToValue = doc.getElementById('date_to');

                if (newResultsPanel && resultsPanel) {
                    resultsPanel.innerHTML = newResultsPanel.innerHTML;
                }

                if (newCountInfo && countInfo) {
                    countInfo.textContent = newCountInfo.textContent || countInfo.textContent;
                }

                if (newSearchValue && searchInput) {
                    searchInput.value = newSearchValue.value;
                }
                if (newStatusValue && statusSelect) {
                    statusSelect.value = newStatusValue.value;
                }
                if (newVenueValue && venueSelect) {
                    venueSelect.value = newVenueValue.value;
                }
                if (newSortValue && sortSelect) {
                    sortSelect.value = newSortValue.value;
                }
                if (newDateFromValue && dateFromInput) {
                    dateFromInput.value = newDateFromValue.value;
                }
                if (newDateToValue && dateToInput) {
                    dateToInput.value = newDateToValue.value;
                }

                updateUrlState(params);
            })
            .catch((error) => {
                console.error('Request list refresh failed:', error);
            })
            .finally(() => {
                if (resultsPanel) {
                    resultsPanel.classList.remove('opacity-75');
                    document.getElementById('request-results-loading')?.remove();
                }
            });
    };

    const scheduleRefresh = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(refreshRequestList, 300);
    };

    const onInputChange = () => {
        if (advancedPanel && advancedPanel.classList.contains('hidden')) {
            advancedPanel.classList.remove('hidden');
            toggleIcon?.classList.add('rotate-180');
            toggleButton?.setAttribute('aria-expanded', 'true');
        }

        scheduleRefresh();
    };

    if (searchInput) {
        searchInput.addEventListener('input', scheduleRefresh);
    }

    [statusSelect, venueSelect, sortSelect, dateFromInput, dateToInput].forEach((field) => {
        if (field) {
            field.addEventListener('change', scheduleRefresh);
        }
    });

    if (toggleButton && advancedPanel) {
        toggleButton.addEventListener('click', function () {
            advancedPanel.classList.toggle('hidden');
            const expanded = advancedPanel.classList.contains('hidden') ? 'false' : 'true';
            toggleButton.setAttribute('aria-expanded', expanded);
            if (toggleIcon) {
                toggleIcon.classList.toggle('rotate-180');
            }
        });
    }

    if (filterForm) {
        filterForm.addEventListener('submit', function (event) {
            event.preventDefault();
            scheduleRefresh();
        });
    }

    if (clearButton) {
        clearButton.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (statusSelect) statusSelect.value = '';
            if (venueSelect) venueSelect.value = '';
            if (sortSelect) sortSelect.value = 'latest';
            if (dateFromInput) dateFromInput.value = '';
            if (dateToInput) dateToInput.value = '';
            scheduleRefresh();
        });
    }
});
