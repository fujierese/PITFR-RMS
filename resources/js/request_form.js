document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('request-form');
    if (!form) {
        return;
    }

    const config = {
        equipmentAvailabilityUrl: form.dataset.equipmentAvailabilityUrl || '',
        conflictCheckUrl: form.datasetConflictCheckUrl || form.dataset.conflictCheckUrl || '',
        excludeRequestId: form.dataset.excludeRequestId ? parseInt(form.dataset.excludeRequestId, 10) : null,
        isStudent: form.dataset.isStudent === '1',
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
    };

    const fileInput = document.getElementById('proposal_file');
    const filePreview = document.getElementById('file-preview');
    const fileName = document.getElementById('file-name');
    const draftStatus = document.getElementById('draft-status');
    const draftStorageKeys = ['pitfr-request-draft', 'pitfr-request-draft-session', 'pitfr-request-draft-cache'];
    const urgentCheckbox = document.getElementById('urgent-processing-checkbox') || document.getElementById('is-emergency-checkbox');
    const emergencyCheckbox = urgentCheckbox;
    const emergencyJustificationWrap = document.getElementById('urgent-justification-wrap') || document.getElementById('emergency-justification-wrap');
    const emergencyJustificationInput = document.getElementById('emergency-justification');
    const startDateInput = document.querySelector('input[name="start_date"]');
    const startTimeInput = document.querySelector('input[name="start_time"]');
    const endDateInput = document.querySelector('input[name="end_date"]');
    const endTimeInput = document.querySelector('input[name="end_time"]');
    const overnightHint = document.getElementById('overnight-hint');
    const submitButton = form.querySelector('button[type="submit"]');
    const defaultSubmitButtonHtml = submitButton ? submitButton.innerHTML : 'Submit Request';
    let hasConflictBlocker = false;
    const escapeHtml = function (value) {
        const element = document.createElement('div');
        element.textContent = String(value ?? '');
        return element.innerHTML;
    };
    if (submitButton) {
        submitButton.dataset.originalHtml = submitButton.innerHTML;
    }
    const setSubmitButtonLoading = function (button, label) {
        if (!button) {
            return;
        }

        button.dataset.originalHtml = button.dataset.originalHtml || button.innerHTML;
        button.disabled = true;
        button.classList.add('opacity-80', 'cursor-not-allowed');
        button.innerHTML = `
            <span class="inline-flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>${label}</span>
            </span>
        `;
    };

    const resetSubmitButtonLoading = function (button) {
        if (!button) {
            return;
        }

        button.disabled = false;
        button.classList.remove('opacity-80', 'cursor-not-allowed');
        button.innerHTML = button.dataset.originalHtml || defaultSubmitButtonHtml;
    };
    const venueRadioInputs = form.querySelectorAll('input[name="venue"]');
    const venueSelect = form.querySelector('select[name="venue"]');
    const expectedParticipantsInput = form.querySelector('input[name="expected_participants"]');
    const equipmentCheckboxes = form.querySelectorAll('input[name="equipment[]"]');
    const rows = form.querySelectorAll('.equipment-row');
    const capacityWarningBanner = document.getElementById('capacity-warning-banner');
    const conflictAlert = document.createElement('div');
    const conflictAlertWrapper = document.getElementById('venue-conflict-alert-wrap');

    conflictAlert.className = 'mt-4 p-4 rounded-lg border hidden';
    conflictAlert.id = 'conflict-alert';
    if (conflictAlertWrapper) {
        conflictAlertWrapper.appendChild(conflictAlert);
    } else {
        form.parentElement?.insertBefore(conflictAlert, form.parentElement.firstChild);
    }

    const clearDraftStorage = function () {
        draftStorageKeys.forEach(function (key) {
            localStorage.removeItem(key);
            sessionStorage.removeItem(key);
        });
    };

    const clearTransientUiState = function () {
        conflictAlert.classList.add('hidden');
        conflictAlert.innerHTML = '';
        updateSubmitButtonLabel(false);
        resetSubmitButtonLoading(submitButton);

        venueRadioInputs.forEach(function (input) {
            input.disabled = false;
            input.checked = false;
        });

        if (venueSelect) {
            Array.from(venueSelect.options).forEach(function (option) {
                option.disabled = !option.value;
            });
            venueSelect.value = '';
        }

        if (capacityWarningBanner) {
            capacityWarningBanner.classList.add('hidden');
            capacityWarningBanner.innerHTML = '';
            capacityWarningBanner.textContent = '';
        }

        rows.forEach(function (row) {
            const badge = row.querySelector('.availability-badge');
            const utilizationCard = row.querySelector('.equipment-utilization-card');
            if (badge) {
                badge.textContent = '';
                badge.className = 'availability-badge rounded-full px-2.5 py-1 text-xs font-semibold bg-slate-100 text-slate-500';
            }
            if (utilizationCard) {
                utilizationCard.className = 'equipment-utilization-card mt-2 hidden';
                utilizationCard.innerHTML = '';
            }
        });

        form.querySelectorAll('input, select, textarea').forEach(function (field) {
            field.removeAttribute('aria-invalid');
            field.setCustomValidity('');

            const errorElement = form.querySelector(`#${field.name}-error`);
            if (errorElement) {
                errorElement.textContent = '';
                errorElement.classList.add('hidden');
            }
        });
    };

    const saveDraft = function () {
        const formData = new FormData(form);
        const draft = {};

        formData.forEach((value, key) => {
            if (key === 'proposal_file' || key === 'equipment[]' || key === 'venue') {
                return;
            }
            draft[key] = value;
        });

        draft.equipment = Array.from(equipmentCheckboxes)
            .filter(checkbox => checkbox.checked)
            .map(checkbox => checkbox.value);

        draft.venue = Array.from(venueRadioInputs)
            .find(radio => radio.checked)?.value || '';

        try {
            localStorage.setItem('pitfr-request-draft', JSON.stringify(draft));
            sessionStorage.setItem('pitfr-request-draft', JSON.stringify(draft));
            if (draftStatus) {
                draftStatus.textContent = 'Draft saved just now';
            }
        } catch (error) {
            console.warn('Draft autosave failed', error);
        }
    };

    const restoreDraft = function () {
        try {
            clearTransientUiState();
            const cached = localStorage.getItem('pitfr-request-draft');
            if (!cached) {
                updateProposalRequirement();
                toggleEmergencyJustification();
                updateSelectedItemsSummary();
                updateCapacityWarning();
                syncOvernightEndDate();
                refreshAllEquipmentAvailability();
                clearTimeout(conflictCheckTimeout);
                conflictCheckTimeout = setTimeout(checkConflicts, 250);
                return;
            }

            const draft = JSON.parse(cached);
            Object.entries(draft).forEach(([key, value]) => {
                if (key === 'equipment') {
                    equipmentCheckboxes.forEach(checkbox => {
                        checkbox.checked = Array.isArray(value) && value.includes(checkbox.value);
                    });
                    return;
                }

                if (key === 'venue') {
                    venueRadioInputs.forEach(radio => {
                        radio.checked = radio.value === value;
                    });
                    return;
                }

                const field = form.querySelector(`[name="${key}"]`);
                if (!field) {
                    return;
                }

                if (field.type === 'checkbox') {
                    field.checked = value === '1' || value === true;
                } else if (field.type === 'radio') {
                    field.checked = field.value === value;
                } else {
                    field.value = value;
                }
            });

            updateProposalRequirement();
            toggleEmergencyJustification();
            updateSelectedItemsSummary();
            updateCapacityWarning();
            syncOvernightEndDate();
            refreshAllEquipmentAvailability();
            clearTimeout(conflictCheckTimeout);
            conflictCheckTimeout = setTimeout(checkConflicts, 250);

            if (draftStatus) {
                draftStatus.textContent = 'Draft restored';
            }
        } catch (error) {
            console.warn('Draft restore failed', error);
        }
    };

    const updateProposalRequirement = function () {
        if (!fileInput) {
            return;
        }

        if (config.isStudent && !(emergencyCheckbox?.checked)) {
            fileInput.setAttribute('required', 'required');
        } else {
            fileInput.removeAttribute('required');
        }
    };

    const getAvailabilityState = function (available, total) {
        if (available <= 0) {
            return { label: 'Fully Reserved', icon: '🔴', tone: 'red', text: 'No units available.' };
        }

        if (available < total) {
            const lowThreshold = Math.max(1, Math.floor(total * 0.3));
            if (available <= lowThreshold) {
                return { label: 'High Demand', icon: '🟠', tone: 'orange', text: 'Limited quantity remaining.' };
            }

            return { label: 'Already Reserved', icon: '🟡', tone: 'yellow', text: 'Existing reservations detected.' };
        }

        return { label: 'Available', icon: '🟢', tone: 'green', text: 'Available' };
    };

    const getScheduleSummary = function () {
        const startDate = startDateInput?.value;
        const endDate = endDateInput?.value || startDate;
        const startTime = startTimeInput?.value;
        const endTime = endTimeInput?.value;

        if (!startDate || !startTime || !endTime) {
            return 'Select dates and times to view the current schedule.';
        }

        const formatDate = (value) => {
            const date = new Date(`${value}T00:00:00`);
            return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        };

        const formatTime = (value) => {
            const [hours, minutes] = value.split(':').map(Number);
            const date = new Date();
            date.setHours(hours, minutes, 0, 0);
            return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        };

        const summaryDate = endDate && endDate !== startDate ? `${formatDate(startDate)} – ${formatDate(endDate)}` : formatDate(startDate);
        return `${summaryDate}<br>${formatTime(startTime)} – ${formatTime(endTime)}`;
    };

    const updateSelectedItemsSummary = function () {
        const selectedVenue = form.querySelector('input[name="venue"]:checked');
        const venueText = selectedVenue ? selectedVenue.value : (venueSelect?.value || 'No venue selected yet.');
        document.getElementById('summary-venue').textContent = 'Venue: ' + venueText;

        const selectedEquipment = Array.from(form.querySelectorAll('input[name="equipment[]"]:checked'));
        const summaryEquipment = document.getElementById('summary-equipment');

        if (selectedEquipment.length === 0) {
            summaryEquipment.innerHTML = 'Equipment: No equipment selected.';
            return;
        }

        const equipmentLines = selectedEquipment.map(checkbox => {
            const row = checkbox.closest('.equipment-row');
            const qtyInput = row?.querySelector('.quantity-input-wrap input[type="number"]');
            const qty = qtyInput?.value || '1';
            return `<div>• ${checkbox.value} × ${qty}</div>`;
        });

        summaryEquipment.innerHTML = 'Equipment:<br>' + equipmentLines.join('');
    };

    const updateCapacityWarning = function () {
        if (!capacityWarningBanner || !expectedParticipantsInput) {
            return;
        }

        const participantValue = expectedParticipantsInput.value;
        const participants = Number.parseInt(participantValue, 10);
        const selectedVenue = form.querySelector('input[name="venue"]:checked');
        const selectedVenueValue = selectedVenue ? selectedVenue.value : (venueSelect?.value || null);

        const capacityMap = {
            'Conference Hall & Interaction Center (CHIC)': 150,
            'Gymnasium': 500,
            'Balay Alumni': 200,
            'Covered Court': 300,
            'Oval Grounds': 1000,
            'Volleyball Court': 100,
        };

        if (!selectedVenueValue || !participantValue || Number.isNaN(participants) || !capacityMap[selectedVenueValue]) {
            capacityWarningBanner.classList.add('hidden');
            capacityWarningBanner.textContent = '';
            capacityWarningBanner.innerHTML = '';
            return;
        }

        const capacity = capacityMap[selectedVenueValue];
        if (participants <= capacity) {
            capacityWarningBanner.classList.add('hidden');
            capacityWarningBanner.textContent = '';
            capacityWarningBanner.innerHTML = '';
            return;
        }

        const overage = participants - capacity;
        capacityWarningBanner.className = 'mt-4 rounded-lg border border-yellow-300 bg-yellow-50 px-3 py-2 text-sm text-yellow-800';
        capacityWarningBanner.innerHTML = `
            <div class="flex items-start gap-2">
                <span class="mt-0.5 text-lg">⚠️</span>
                <div>
                    <div class="font-semibold">Capacity: ${capacity}</div>
                    <div class="text-xs text-yellow-800/80">Expected Participants: ${participants}</div>
                    <div class="mt-1 text-xs text-yellow-800/90">${overage} participant${overage === 1 ? '' : 's'} exceed this venue's capacity. You may continue or choose a larger venue.</div>
                </div>
            </div>
        `;
        capacityWarningBanner.classList.remove('hidden');
    };

    const updateSubmitButtonLabel = function (hasConflict) {
        if (!submitButton) {
            return;
        }

        if (hasConflict) {
            submitButton.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Submit for Priority Review';
        } else {
            submitButton.innerHTML = defaultSubmitButtonHtml;
        }
    };

    const toggleEmergencyJustification = function () {
        if (!emergencyCheckbox || !emergencyJustificationWrap || !emergencyJustificationInput) {
            return;
        }

        if (emergencyCheckbox.checked) {
            emergencyJustificationWrap.style.display = 'block';
            emergencyJustificationInput.removeAttribute('disabled');
            emergencyJustificationInput.setAttribute('required', 'required');
            emergencyJustificationInput.focus();
        } else {
            emergencyJustificationWrap.style.display = 'none';
            emergencyJustificationInput.removeAttribute('required');
            emergencyJustificationInput.value = '';
            emergencyJustificationInput.setAttribute('disabled', 'disabled');
        }
    };

    const setInputValidationState = function (field, valid, message = '') {
        if (!field) {
            return;
        }

        const errorId = `${field.name}-error`;
        let errorElement = form.querySelector(`#${errorId}`);

        field.setAttribute('aria-invalid', valid ? 'false' : 'true');
        field.setCustomValidity(valid ? '' : message);

        if (valid || !message.trim()) {
            if (errorElement) {
                errorElement.remove();
            }
            return;
        }

        if (!errorElement) {
            errorElement = document.createElement('p');
            errorElement.id = errorId;
            errorElement.className = 'text-xs text-red-600 mt-1';
            field.insertAdjacentElement('afterend', errorElement);
        }

        errorElement.textContent = message;
        errorElement.classList.remove('hidden');
    };

    const validateDateTimeRange = function () {
        if (!startDateInput || !endDateInput || !startTimeInput || !endTimeInput) {
            return true;
        }

        const startDate = startDateInput.value;
        const endDate = endDateInput.value;
        const startTime = startTimeInput.value;
        const endTime = endTimeInput.value;

        if (!startDate || !endDate || !startTime || !endTime) {
            return true;
        }

        const start = new Date(`${startDate}T${startTime}`);
        const end = new Date(`${endDate}T${endTime}`);

        if (end < start) {
            setInputValidationState(endDateInput, false, 'End date/time must be after the start date/time.');
            return false;
        }

        setInputValidationState(endDateInput, true);
        return true;
    };

    const validateRequiredFields = function () {
        let valid = true;
        const requiredFields = Array.from(form.querySelectorAll('[required]'));

        requiredFields.forEach(field => {
            if (!field.value) {
                setInputValidationState(field, false, 'This field is required.');
                valid = false;
            } else {
                setInputValidationState(field, true);
            }
        });

        return valid;
    };

    const validateQuantities = function () {
        let valid = true;

        rows.forEach(row => {
            const checkbox = row.querySelector('.equipment-checkbox');
            const qtyInput = row.querySelector('.quantity-input-wrap input[type="number"]');
            const maxAllowed = parseInt(row.dataset.maxQuantity || row.dataset.available, 10) || 0;
            const qty = parseInt(qtyInput.value, 10) || 0;

            if (checkbox.checked && (qty < 1 || qty > maxAllowed)) {
                qtyInput.classList.add('border-red-500');
                valid = false;
            } else {
                qtyInput.classList.remove('border-red-500');
            }
        });

        return valid;
    };

    const updateEquipmentRowVisuals = function (row, available, total, overlappingRequests = []) {
        const badge = row.querySelector('.availability-badge');
        const qtyInput = row.querySelector('.quantity-input-wrap input[type="number"]');
        const checkbox = row.querySelector('.equipment-checkbox');
        const utilizationCard = row.querySelector('.equipment-utilization-card');
        const normalizedAvailable = Math.max(0, parseInt(available, 10) || 0);
        const normalizedTotal = Math.max(0, parseInt(total, 10) || 0);
        const state = getAvailabilityState(normalizedAvailable, normalizedTotal);

        if (badge) {
            const badgeText = normalizedAvailable <= 0
                ? `${state.icon} ${state.text}`
                : state.tone === 'orange'
                    ? `${state.icon} ${state.label} · ${state.text}`
                    : `${state.icon} ${state.label} · ${normalizedAvailable} / ${normalizedTotal}`;
            badge.textContent = badgeText;
            badge.className = `availability-badge rounded-full px-2.5 py-1 text-xs font-semibold ${state.tone === 'green' ? 'bg-green-100 text-green-700' : state.tone === 'yellow' ? 'bg-yellow-100 text-yellow-700' : state.tone === 'orange' ? 'bg-orange-100 text-orange-700' : 'bg-red-100 text-red-700'}`;
        }

        if (utilizationCard) {
            if (overlappingRequests.length > 0) {
                const firstConflict = overlappingRequests[0];
                const reservedQuantity = Math.max(0, (firstConflict?.quantity || Math.max(0, total - available)));
                const remainingQuantity = Math.max(0, available);
                utilizationCard.className = 'equipment-utilization-card mt-2 w-full rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-left text-[11px] text-slate-600 sm:min-w-[220px]';
                utilizationCard.innerHTML = `
                    <div class="font-semibold text-slate-800">⚠ Existing Reservation</div>
                    <div class="mt-1 space-y-1">
                        <div>Activity: ${firstConflict?.activity || 'Existing reservation'}</div>
                        <div>Reserved Quantity: ${reservedQuantity}</div>
                        <div>Remaining Quantity: ${remainingQuantity}</div>
                    </div>
                    <div class="mt-2 border-t border-amber-200 pt-2 text-[11px] text-slate-500">
                        <div class="font-medium">Reservation Schedule</div>
                        <div>${getScheduleSummary()}</div>
                    </div>
                `;
            } else {
                utilizationCard.className = 'equipment-utilization-card mt-2 hidden';
                utilizationCard.innerHTML = '';
            }
        }

        if (checkbox && qtyInput) {
            const maxAllowed = Math.max(0, parseInt(row.dataset.maxQuantity || normalizedAvailable, 10) || normalizedAvailable);
            qtyInput.max = maxAllowed;
            if (parseInt(qtyInput.value, 10) > maxAllowed) {
                qtyInput.value = maxAllowed;
            }

            if (normalizedAvailable <= 0) {
                checkbox.disabled = true;
                checkbox.checked = false;
                qtyInput.value = 0;
                qtyInput.disabled = true;
                qtyInput.closest('.quantity-input-wrap').style.display = 'none';
            } else {
                checkbox.disabled = false;
                qtyInput.disabled = !checkbox.checked;
                if (checkbox.checked) {
                    qtyInput.closest('.quantity-input-wrap').style.display = 'block';
                } else {
                    qtyInput.closest('.quantity-input-wrap').style.display = 'none';
                }
            }
        }
    };

    const fetchAvailabilityAndUpdate = function (row) {
        const name = row.dataset.name;
        if (!name || !config.equipmentAvailabilityUrl) {
            return;
        }

        const scheduleParams = new URLSearchParams({
            name,
            start_date: startDateInput?.value || '',
            end_date: endDateInput?.value || startDateInput?.value || '',
            start_time: startTimeInput?.value || '',
            end_time: endTimeInput?.value || ''
        });

        fetch(`${config.equipmentAvailabilityUrl}?${scheduleParams.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            if (!data || typeof data.available === 'undefined') {
                return;
            }

            row.dataset.available = data.available;
            row.dataset.total = data.total;
            updateEquipmentRowVisuals(row, data.available, data.total, data.overlapping_requests || []);
        })
        .catch(() => {});
    };

    const refreshAllEquipmentAvailability = function () {
        rows.forEach(fetchAvailabilityAndUpdate);
    };

    const checkConflicts = function () {
        const selectedVenueInput = form.querySelector('input[name="venue"]:checked');
        const selectedVenueValue = selectedVenueInput ? selectedVenueInput.value : (venueSelect?.value || null);
        const venues = venueSelect
            ? Array.from(venueSelect.options).map(option => option.value).filter(value => value)
            : Array.from(venueRadioInputs).map(input => input.value);
        const startDate = startDateInput?.value;
        const endDate = endDateInput?.value;
        const startTime = startTimeInput?.value;
        const endTime = endTimeInput?.value;

        hasConflictBlocker = false;

        if (!startDate || !startTime || !endTime || !config.conflictCheckUrl) {
            conflictAlert.classList.add('hidden');
            venueRadioInputs.forEach(input => input.disabled = false);
            if (venueSelect) {
                Array.from(venueSelect.options).forEach(option => option.disabled = option.value === '');
            }
            updateSubmitButtonLabel(false);
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
            }
            return;
        }

        fetch(config.conflictCheckUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': config.csrfToken
            },
            body: JSON.stringify({
                venues,
                start_date: startDate,
                start_time: startTime,
                end_date: endDate || startDate,
                end_time: endTime,
                exclude_request_id: config.excludeRequestId
            })
        })
        .then(response => response.json())
        .then(data => {
            const conflicts = data.conflicts || {};
            const selectedHasConflict = selectedVenueValue && Object.prototype.hasOwnProperty.call(conflicts, selectedVenueValue);
            const isUrgent = Boolean(urgentCheckbox?.checked);

            venueRadioInputs.forEach(input => {
                const inputHasConflict = Object.prototype.hasOwnProperty.call(conflicts, input.value);
                input.disabled = inputHasConflict && !isUrgent;
                if (inputHasConflict && input.checked && !isUrgent) {
                    input.checked = false;
                }
            });
            if (venueSelect) {
                Array.from(venueSelect.options).forEach(option => {
                    if (!option.value) {
                        return;
                    }
                    const optionHasConflict = Object.prototype.hasOwnProperty.call(conflicts, option.value);
                    option.disabled = optionHasConflict && !isUrgent;
                });
                if (selectedVenueValue && Object.prototype.hasOwnProperty.call(conflicts, selectedVenueValue) && !isUrgent) {
                    venueSelect.value = '';
                }
            }

            if (selectedHasConflict) {
                const conflictEntries = conflicts[selectedVenueValue] || [];
                conflictAlert.className = isUrgent
                    ? 'mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900'
                    : 'mt-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-red-800';
                conflictAlert.classList.remove('hidden');

                let conflictHtml = isUrgent
                    ? '<div class="font-semibold mb-2">⚠️ Institute Urgent Request</div>'
                    : '<div class="font-semibold mb-2">⚠️ Scheduling conflict detected for this venue:</div>';
                conflictHtml += '<div class="text-sm space-y-2">';
                if (isUrgent) {
                    conflictHtml += '<div class="text-sm">This venue already has an approved reservation. Because this request is marked as an Institute Urgent Activity, the reservation may proceed for administrative review.</div>';
                }
                conflictEntries.forEach(conflict => {
                    const priorityLabel = (conflict.priority || 'regular').toString().toUpperCase();
                    const priorityTone = priorityLabel === 'URGENT' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700';
                    conflictHtml += `<div class="rounded-xl border border-white/70 bg-white/70 p-3 text-xs">
                        <div class="font-semibold text-slate-800">Current Reservation</div>
                        <div class="mt-1">Request No: ${escapeHtml(conflict.control_number)}</div>
                        <div>Activity: ${escapeHtml(conflict.activity)}</div>
                        <div>Requester: ${escapeHtml(conflict.requestor)}</div>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="font-medium text-slate-500">Priority:</span>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-semibold ring-1 ring-inset ${priorityTone}">${escapeHtml(priorityLabel)}</span>
                        </div>
                        <div>Schedule: ${escapeHtml(conflict.start_date)}${conflict.end_date ? ' to ' + escapeHtml(conflict.end_date) : ''} at ${escapeHtml(conflict.time)}</div>
                        <div>Status: ${escapeHtml(conflict.status)}</div>
                    </div>`;
                });
                conflictHtml += '</div>';
                conflictHtml += isUrgent
                    ? '<div class="mt-2 text-xs text-amber-800">Final approval will determine whether the existing reservation must be rescheduled.</div>'
                    : '<div class="mt-2 text-xs text-red-600">Please choose another venue or schedule.</div>';
                conflictAlert.innerHTML = conflictHtml;

                if (isUrgent) {
                    updateSubmitButtonLabel(false);
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
                    }
                    hasConflictBlocker = false;
                } else {
                    updateSubmitButtonLabel(true);
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.classList.add('opacity-50', 'cursor-not-allowed');
                    }
                    hasConflictBlocker = true;
                }
            } else {
                conflictAlert.classList.add('hidden');
                updateSubmitButtonLabel(false);
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
                }
                hasConflictBlocker = false;
            }
        })
        .catch(error => {
            console.error('Conflict check failed:', error);
            hasConflictBlocker = false;
        });
    };

    const syncOvernightEndDate = function () {
        if (!startDateInput?.value || !startTimeInput?.value || !endTimeInput?.value) {
            overnightHint?.classList.add('hidden');
            return;
        }

        const startDate = new Date(startDateInput.value);
        const endDateValue = endDateInput.value || startDateInput.value;
        const currentEndDate = new Date(endDateValue);
        const nextDay = new Date(startDate);
        nextDay.setDate(nextDay.getDate() + 1);
        const nextDayString = nextDay.toISOString().slice(0, 10);

        if (endTimeInput.value <= startTimeInput.value) {
            if (endDateInput.value !== nextDayString) {
                endDateInput.value = nextDayString;
            }
            if (overnightHint) {
                overnightHint.textContent = 'Overnight booking detected: end date auto-updated to the next day.';
                overnightHint.classList.remove('hidden');
            }
        } else {
            if (endDateInput.value === nextDayString && currentEndDate <= startDate) {
                endDateInput.value = startDateInput.value;
            }
            overnightHint?.classList.add('hidden');
        }
    };

    const createValidationListeners = function () {
        const fields = Array.from(form.querySelectorAll('input, select, textarea'));
        fields.forEach(field => {
            field.addEventListener('input', function () {
                if (field.validity.valid) {
                    setInputValidationState(field, true);
                }
                saveDraft();
            });
            field.addEventListener('change', function () {
                if (field.validity.valid) {
                    setInputValidationState(field, true);
                }
                saveDraft();
            });
        });
    };

    const attachSelectionListeners = function () {
        venueRadioInputs.forEach(radio => {
            radio.addEventListener('change', function () {
                updateSelectedItemsSummary();
                updateCapacityWarning();
                clearTimeout(conflictCheckTimeout);
                conflictCheckTimeout = setTimeout(checkConflicts, 300);
                refreshAllEquipmentAvailability();
                saveDraft();
            });
        });

        if (venueSelect) {
            venueSelect.addEventListener('change', function () {
                updateSelectedItemsSummary();
                updateCapacityWarning();
                clearTimeout(conflictCheckTimeout);
                conflictCheckTimeout = setTimeout(checkConflicts, 300);
                refreshAllEquipmentAvailability();
                saveDraft();
            });
        }

        equipmentCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function () {
                updateSelectedItemsSummary();
                refreshAllEquipmentAvailability();
                saveDraft();
            });
        });

        form.querySelectorAll('.quantity-input-wrap input[type="number"]').forEach(input => {
            input.addEventListener('input', function () {
                updateSelectedItemsSummary();
                refreshAllEquipmentAvailability();
                saveDraft();
            });
        });
    };

    let conflictCheckTimeout;
    let equipmentAvailabilityRefreshTimeout;

    if (fileInput) {
        fileInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                fileName.textContent = file.name;
                filePreview.classList.remove('hidden');
            } else {
                filePreview.classList.add('hidden');
            }
            saveDraft();
        });
    }

    updateProposalRequirement();
    if (emergencyCheckbox) {
        emergencyCheckbox.addEventListener('change', function () {
            updateProposalRequirement();
            toggleEmergencyJustification();
            saveDraft();
            clearTimeout(conflictCheckTimeout);
            conflictCheckTimeout = setTimeout(checkConflicts, 300);
        });
    }

    form.addEventListener('submit', function (event) {
        const clickedButton = document.activeElement && document.activeElement.matches('button[type="submit"]') ? document.activeElement : form.querySelector('button[type="submit"]');
        const isValid = form.checkValidity();

        if (!isValid) {
            resetSubmitButtonLoading(clickedButton);
            return;
        }

        if (clickedButton?.dataset.loading === '1') {
            event.preventDefault();
            return;
        }

        const loadingLabel = clickedButton?.textContent?.toLowerCase().includes('save') ? 'Saving...' : 'Submitting...';
        clickedButton?.setAttribute('data-loading', '1');
        setSubmitButtonLoading(clickedButton, loadingLabel);
    });

    createValidationListeners();
    attachSelectionListeners();
    restoreDraft();
    updateSelectedItemsSummary();

    document.getElementById('print-summary-btn')?.addEventListener('click', function () {
        window.print();
    });

    rows.forEach(function (row) {
        const checkbox = row.querySelector('.equipment-checkbox');
        const qtyInput = row.querySelector('.quantity-input-wrap input[type="number"]');
        const qtyWrap = row.querySelector('.quantity-input-wrap');
        let errorMessage = row.querySelector('.quantity-error-message');

        if (!errorMessage) {
            errorMessage = document.createElement('div');
            errorMessage.className = 'quantity-error-message mt-1 text-xs text-red-600 hidden';
            qtyWrap.appendChild(errorMessage);
        }

        const updateAvailability = function () {
            const available = Math.max(0, parseInt(row.dataset.available, 10) || 0);
            const total = Math.max(0, parseInt(row.dataset.total, 10) || 0);
            const maxAllowed = Math.max(0, parseInt(row.dataset.maxQuantity || available, 10) || available);
            let qty = parseInt(qtyInput.value, 10);
            const isChecked = checkbox.checked;

            qtyInput.max = maxAllowed;
            qtyInput.disabled = !isChecked || available <= 0;

            if (!qty || qty < 1) {
                qty = 1;
            }

            if (isChecked) {
                qtyWrap.style.display = 'block';
            } else {
                qtyWrap.style.display = 'none';
                qtyInput.value = 0;
                qty = 0;
            }

            let invalid = false;
            if (isChecked && qty > maxAllowed) {
                invalid = true;
                qtyInput.setCustomValidity('Quantity requested cannot exceed the allowed amount.');
                errorMessage.textContent = 'Please request no more than ' + maxAllowed + ' item' + (maxAllowed === 1 ? '' : 's') + '.';
            } else {
                qtyInput.setCustomValidity('');
                errorMessage.textContent = '';
            }

            if (invalid) {
                qtyInput.classList.add('border-red-500');
                errorMessage.classList.remove('hidden');
            } else {
                qtyInput.classList.remove('border-red-500');
                errorMessage.classList.add('hidden');
            }

            if (badge) {
                const state = getAvailabilityState(available, total);
                badge.textContent = available <= 0 ? `${state.icon} ${state.text}` : `${state.icon} ${state.label} · ${available} / ${total}`;
                badge.className = 'availability-badge rounded-full px-2.5 py-1 text-xs font-semibold ' + (state.tone === 'green' ? 'bg-green-100 text-green-700' : state.tone === 'yellow' ? 'bg-yellow-100 text-yellow-700' : state.tone === 'orange' ? 'bg-orange-100 text-orange-700' : 'bg-red-100 text-red-700');
            }

            if (available <= 0) {
                checkbox.disabled = true;
                checkbox.checked = false;
                qtyInput.value = 0;
                qtyInput.disabled = true;
                qtyWrap.style.display = 'none';
            }

            if (qty > maxAllowed) {
                qtyInput.value = maxAllowed;
            }
        };

        const badge = row.querySelector('.availability-badge');
        checkbox.addEventListener('change', updateAvailability);
        qtyInput.addEventListener('input', updateAvailability);

        updateAvailability();
        fetchAvailabilityAndUpdate(row);
    });

    startDateInput?.addEventListener('change', function () {
        syncOvernightEndDate();
        clearTimeout(conflictCheckTimeout);
        conflictCheckTimeout = setTimeout(checkConflicts, 500);
        refreshAllEquipmentAvailability();
        saveDraft();
    });

    endDateInput?.addEventListener('change', function () {
        syncOvernightEndDate();
        clearTimeout(conflictCheckTimeout);
        conflictCheckTimeout = setTimeout(checkConflicts, 500);
        refreshAllEquipmentAvailability();
        saveDraft();
    });

    startTimeInput?.addEventListener('change', function () {
        syncOvernightEndDate();
        clearTimeout(conflictCheckTimeout);
        conflictCheckTimeout = setTimeout(checkConflicts, 500);
        refreshAllEquipmentAvailability();
        saveDraft();
    });

    endTimeInput?.addEventListener('change', function () {
        syncOvernightEndDate();
        clearTimeout(conflictCheckTimeout);
        conflictCheckTimeout = setTimeout(checkConflicts, 500);
        refreshAllEquipmentAvailability();
        saveDraft();
    });

    expectedParticipantsInput?.addEventListener('input', function () {
        updateCapacityWarning();
    });

    syncOvernightEndDate();

    form.addEventListener('submit', function (e) {
        const requiredOk = validateRequiredFields();
        const dateTimeOk = validateDateTimeRange();
        const qtyOk = validateQuantities();
        const hasConflict = hasConflictBlocker;

        if (!requiredOk || !dateTimeOk || !qtyOk || hasConflict) {
            e.preventDefault();
            if (!requiredOk || !dateTimeOk) {
                const firstInvalid = form.querySelector(':invalid');
                firstInvalid?.focus();
            }
            if (!qtyOk) {
                alert('Please correct equipment quantities so each requested amount is within the available stock.');
            }
            if (hasConflict) {
                alert('Please resolve scheduling conflicts before submitting your request.');
            }
            return false;
        }

        clearDraftStorage();
        return true;
    });
});
