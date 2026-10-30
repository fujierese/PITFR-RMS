const initializeReservationDurationToggles = function () {
    let previousDuration = document.querySelector('input[name="reservation_duration"]:checked')?.value || null;

    document.querySelectorAll('.reservation-duration-option').forEach(function (option) {
        const input = option.querySelector('input[name="reservation_duration"]');
        if (!input) {
            return;
        }

        const updateState = function () {
            const isSelected = input.checked;
            const parent = option.closest('.reservation-duration-group');
            const scopedTimeFields = parent ? parent.querySelectorAll('input[name="start_time"], input[name="end_time"]') : [];
            const timeFields = scopedTimeFields.length
                ? scopedTimeFields
                : document.querySelectorAll('input[name="start_time"], input[name="end_time"]');

            option.classList.toggle('border-emerald-500', isSelected);
            option.classList.toggle('bg-emerald-50', isSelected);
            option.classList.toggle('shadow-sm', isSelected);
            option.classList.toggle('border-slate-200', !isSelected);
            option.classList.toggle('bg-white', !isSelected);
            option.classList.toggle('text-slate-900', isSelected);
            option.classList.toggle('text-slate-600', !isSelected);

            timeFields.forEach(function (field) {
                if (!field) {
                    return;
                }
                const isWholeDay = input.checked && input.value === 'whole_day';
                field.readOnly = isWholeDay;
                field.classList.toggle('opacity-60', isWholeDay);
                field.classList.toggle('cursor-not-allowed', isWholeDay);
                if (isWholeDay) {
                    field.value = field.name === 'start_time' ? '08:00' : '23:59';
                } else if (previousDuration === 'whole_day' || (!document.querySelector('input[name="reservation_duration"]:checked') && previousDuration)) {
                    field.value = '';
                }
            });
        };

        input.addEventListener('mousedown', function () {
            input.dataset.wasChecked = input.checked ? 'true' : 'false';
        });
        input.addEventListener('keydown', function (event) {
            if (event.key === ' ' && input.checked) {
                event.preventDefault();
                input.checked = false;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
        input.addEventListener('click', function (event) {
            if (input.dataset.wasChecked === 'true') {
                event.preventDefault();
                input.checked = false;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
        input.addEventListener('change', function () {
            updateState();
            previousDuration = document.querySelector('input[name="reservation_duration"]:checked')?.value || null;
        });
        updateState();
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeReservationDurationToggles, { once: true });
} else {
    initializeReservationDurationToggles();
}

const initializeRequestForm = function () {
    const form = document.getElementById('request-form');
    if (!form) {
        return;
    }

    if (form.dataset.requestFormInitialized === '1') {
        return;
    }

    form.dataset.requestFormInitialized = '1';
    initializeReservationDurationToggles();

    const config = {
        equipmentAvailabilityUrl: form.dataset.equipmentAvailabilityUrl || '',
        conflictCheckUrl: form.datasetConflictCheckUrl || form.dataset.conflictCheckUrl || '',
        excludeRequestId: form.dataset.excludeRequestId ? parseInt(form.dataset.excludeRequestId, 10) : null,
        isStudent: form.dataset.isStudent === '1',
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
    };

    const ensureCsrfToken = function () {
        if (!config.csrfToken) {
            return;
        }

        let tokenInput = form.querySelector('input[name="_token"]');
        if (!tokenInput) {
            tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = '_token';
            form.appendChild(tokenInput);
        }

        tokenInput.value = config.csrfToken;
    };

    const fileInput = document.getElementById('proposal_file');
    const filePreview = document.getElementById('file-preview');
    const fileName = document.getElementById('file-name');
    const draftStatus = document.getElementById('draft-status');
    const draftStorageKeys = ['pitfr-request-draft', 'pitfr-request-draft-session', 'pitfr-request-draft-cache'];
    let draftDatabasePromise;
    let draftSaveQueue = Promise.resolve();
    const urgentCheckbox = document.getElementById('urgent-processing-checkbox') || document.getElementById('is-emergency-checkbox');
    const emergencyCheckbox = urgentCheckbox;
    const emergencyJustificationWrap = document.getElementById('urgent-justification-wrap') || document.getElementById('emergency-justification-wrap');
    const emergencyJustificationInput = document.getElementById('emergency-justification');
    const venueScheduleGroups = Array.from(form.querySelectorAll('[data-venue-schedule]'));
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
    const otherVenueWrap = document.getElementById('venue-other-wrap');
    const otherVenueInput = document.getElementById('venue-other');
    const equipmentSelectionPanel = document.getElementById('equipment-selection-panel');
    const venueDefaultEquipmentPanel = document.getElementById('venue-default-equipment-panel');
    const venueDefaultEquipmentList = document.getElementById('venue-default-equipment-list');
    const balayChairNote = document.getElementById('balay-chair-note');
    const expectedParticipantsInput = form.querySelector('input[name="expected_participants"]');
    const equipmentCheckboxes = form.querySelectorAll('input[name="equipment[]"]');
    const rows = form.querySelectorAll('.equipment-row');
    const capacityWarningBanner = document.getElementById('capacity-warning-banner');
    const checklistWarning = document.getElementById('submission-checklist-alert');
    const conflictAlert = document.createElement('div');
    const conflictAlertWrapper = document.getElementById('venue-conflict-alert-wrap');
    const summaryDetails = {
        activity: document.getElementById('summary-activity'),
        venue: document.getElementById('summary-venue'),
        date: document.getElementById('summary-date'),
        time: document.getElementById('summary-time'),
        equipment: document.getElementById('summary-equipment')
    };

    const isChecklistFieldComplete = function (field) {
        if (!field) {
            return true;
        }

        if (field.type === 'radio' || field.type === 'checkbox') {
            return field.checked;
        }

        if (field.type === 'file') {
            return Boolean(field.files && field.files.length);
        }

        return String(field.value || '').trim() !== '';
    };

    const getChecklistTarget = function (checklistId) {
        const requestorType = form.dataset.requestorType || '';
        const positionSelect = form.querySelector('[name="requested_by_position"]');
        const otherPositionInput = form.querySelector('[name="requested_by_position_other"]');

        if (checklistId === 'checklist-required-fields') {
            const requiredFields = [
                requestorType === 'outsider' ? form.querySelector('[name="organization_name"]') : form.querySelector('[name="college_id"]'),
                requestorType === 'outsider' ? null : form.querySelector('[name="department_id"]'),
                form.querySelector('[name="name_of_activity"]'),
                form.querySelector('[name="purpose"]'),
                form.querySelector('[name="expected_participants"]'),
                form.querySelector('[name="requested_by_position"]'),
                positionSelect?.value === 'Other' ? otherPositionInput : null,
            ];

            const missingField = requiredFields.find(function (field) {
                return !isChecklistFieldComplete(field);
            }) || null;
            if (missingField) return missingField;

            const selectedGroup = Array.from(form.querySelectorAll('[data-venue-schedule]')).find(function (group) {
                return group.closest('[data-venue-option]')?.querySelector('[name="venue[]"]')?.checked;
            });
            if (!selectedGroup) return form.querySelector('[name="venue[]"]');
            return Array.from(selectedGroup.querySelectorAll('.venue-schedule-input')).find(function (field) {
                return !isChecklistFieldComplete(field);
            }) || selectedGroup.querySelector('input[type="radio"]:checked');
        }

        if (checklistId === 'checklist-venue-availability') {
            const selectedVenues = Array.from(form.querySelectorAll('[name="venue[]"]:checked'));
            if (!selectedVenues.length) return form.querySelector('[name="venue[]"]') || null;

            const selectedEquipmentRows = Array.from(form.querySelectorAll('.equipment-row')).filter(function (row) {
                return row.querySelector('.equipment-checkbox')?.checked;
            });
            const equipmentSelected = selectedEquipmentRows.length > 0;
            const hasAvailableEquipmentSelected = selectedEquipmentRows.every(function (row) {
                const quantity = row.querySelector('.quantity-input-wrap input[type="number"]');
                const requested = Number(quantity?.value);
                return Number.isInteger(requested) && requested >= 1 && requested <= Number(row.dataset.available || 0);
            });

            if (equipmentSelected && !hasAvailableEquipmentSelected) {
                return form.querySelector('.equipment-row .equipment-checkbox, .equipment-row input[type="number"]') || selectedVenues[0];
            }

            const selectedGroup = Array.from(form.querySelectorAll('[data-venue-schedule]')).find(function (group) {
                return group.closest('[data-venue-option]')?.querySelector('[name="venue[]"]')?.checked;
            });
            return selectedGroup
                ? Array.from(selectedGroup.querySelectorAll('.venue-schedule-input')).find(function (field) {
                    return !isChecklistFieldComplete(field);
                }) || null
                : selectedVenues[0];
        }

        if (checklistId === 'checklist-document-upload') {
            const requestContext = form.querySelector('[name="request_context"]')?.value;
            const isStudent = form.dataset.isStudent === '1';
            const requestorType = form.dataset.requestorType || '';
            const proposalInput = form.querySelector('[name="activity_proposal_file"], [name="proposal_file"]');
            const receiptInput = form.querySelector('[name="igp_receipt_file"]');
            const hasProposal = Boolean(proposalInput?.files?.length);
            const hasReceipt = Boolean(receiptInput?.files?.length);
            const isEmergency = Boolean(form.querySelector('[name="is_emergency"]')?.checked);
            const isRequired = isStudent && requestContext === 'student_organization'
                ? !hasProposal
                : (isStudent || requestorType === 'faculty') && !requestContext
                    ? !hasProposal
                    : requestorType === 'outsider'
                        ? isEmergency && !hasProposal
                        : !hasReceipt;

            return isRequired ? proposalInput || receiptInput : null;
        }

        if (checklistId === 'checklist-e-signature') {
            const eSignatureInput = form.querySelector('[name="e_signature_file"]');
            return eSignatureInput && !(eSignatureInput.files?.length || form.dataset.hasSavedSignature === '1') ? eSignatureInput : null;
        }

        return null;
    };

    document.querySelectorAll('[data-checklist-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            const checklistId = button.dataset.checklistTarget;
            if (!checklistId || document.getElementById(checklistId)?.checked === true) {
                return;
            }

            const target = getChecklistTarget(checklistId);
            if (!target) {
                return;
            }

            target.scrollIntoView({ block: 'center', behavior: 'smooth' });
            if (typeof target.focus === 'function') {
                target.focus({ preventScroll: true });
            }
        });
    });

    const formatDisplayDate = function (value) {
        if (!value) {
            return 'Not selected';
        }

        const date = new Date(`${value}T00:00:00`);
        return Number.isNaN(date.getTime())
            ? value
            : new Intl.DateTimeFormat('en-US', { month: 'long', day: 'numeric', year: 'numeric' }).format(date);
    };

    const formatDisplayTime = function (value) {
        if (!value) {
            return 'Not selected';
        }

        const [hours, minutes] = value.split(':').map(Number);
        const date = new Date();
        date.setHours(hours, minutes, 0, 0);
        return new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit' }).format(date);
    };

    const updateChecklistState = function () {
        const requestorType = form.dataset.requestorType || '';
        const positionSelect = form.querySelector('[name="requested_by_position"]');
        const otherPositionInput = form.querySelector('[name="requested_by_position_other"]');

        const requiredFields = [
            requestorType === 'outsider' ? form.querySelector('[name="organization_name"]') : form.querySelector('[name="college_id"]'),
            requestorType === 'outsider' ? null : form.querySelector('[name="department_id"]'),
            form.querySelector('[name="name_of_activity"]'),
            form.querySelector('[name="purpose"]'),
            form.querySelector('[name="expected_participants"]'),
            form.querySelector('[name="requested_by_position"]'),
            positionSelect?.value === 'Other' ? otherPositionInput : null,
        ];
        const selectedScheduleGroups = Array.from(form.querySelectorAll('[data-venue-schedule]')).filter(function (group) {
            return group.closest('[data-venue-option]')?.querySelector('[name="venue[]"]')?.checked;
        });
        const schedulesComplete = selectedScheduleGroups.length > 0 && selectedScheduleGroups.every(function (group) {
            return Array.from(group.querySelectorAll('.venue-schedule-input')).every(isChecklistFieldComplete)
                && Boolean(group.querySelector('input[type="radio"]:checked'));
        });
        const allRequiredComplete = requiredFields.every(isChecklistFieldComplete) && schedulesComplete;
        const hasEquipmentSelection = form.querySelectorAll('.equipment-row .equipment-checkbox:checked').length > 0;
        const hasAvailableEquipmentSelected = Array.from(form.querySelectorAll('.equipment-row')).filter(function (row) {
            return row.querySelector('.equipment-checkbox')?.checked;
        }).every(function (row) {
            const quantity = row.querySelector('.quantity-input-wrap input[type="number"]');
            const requested = Number(quantity?.value);
            return Number.isInteger(requested) && requested >= 1 && requested <= Number(row.dataset.available || 0);
        });

        const requestContext = form.querySelector('[name="request_context"]')?.value;
        const isStudent = form.dataset.isStudent === '1';
        const hasProposal = Boolean(form.querySelector('[name="activity_proposal_file"]')?.files?.length || form.querySelector('[name="proposal_file"]')?.files?.length);
        const hasReceipt = Boolean(form.querySelector('[name="igp_receipt_file"]')?.files?.length);
        const hasSupportingDocument = isStudent && requestContext === 'student_organization'
            ? hasProposal
            : (isStudent || requestorType === 'faculty') && !requestContext
                ? hasProposal
                : requestorType === 'outsider'
                    ? (!form.querySelector('[name="is_emergency"]')?.checked || hasProposal)
                    : hasReceipt;
        const eSignatureSelected = Boolean(form.querySelector('[name="e_signature_file"]')?.files?.length) || form.dataset.hasSavedSignature === '1';

        const requiredChecklist = document.getElementById('checklist-required-fields');
        const venueChecklist = document.getElementById('checklist-venue-availability');
        const documentChecklist = document.getElementById('checklist-document-upload');
        const signatureChecklist = document.getElementById('checklist-e-signature');
        if (requiredChecklist) requiredChecklist.checked = allRequiredComplete;
        if (venueChecklist) venueChecklist.checked = selectedScheduleGroups.length > 0 && schedulesComplete && (!hasEquipmentSelection || hasAvailableEquipmentSelected);
        if (documentChecklist) documentChecklist.checked = hasSupportingDocument;
        if (signatureChecklist) signatureChecklist.checked = eSignatureSelected;
        if (checklistWarning && [requiredChecklist, venueChecklist, documentChecklist, signatureChecklist].every(function (item) {
            return item?.checked === true;
        })) {
            checklistWarning.classList.add('hidden');
            checklistWarning.textContent = '';
        }
    };

    const updateFormSummary = function () {
        const startDate = startDateInput?.value || '';
        const endDate = endDateInput?.value || '';
        const startTime = startTimeInput?.value || '';
        const endTime = endTimeInput?.value || '';
        const isWholeDay = form.querySelector('[name="reservation_duration"]:checked')?.value === 'whole_day';
        const selectedVenue = form.querySelector('[name="venue"]:checked');
        const selectedEquipment = Array.from(form.querySelectorAll('.equipment-checkbox:checked')).map(function (checkbox) {
            const quantity = checkbox.closest('.equipment-row')?.querySelector('.quantity-input-wrap input[type="number"]')?.value || 'Enter quantity';
            return `${checkbox.value} x ${quantity}`;
        });

        if (summaryDetails.activity) summaryDetails.activity.textContent = form.querySelector('[name="name_of_activity"]')?.value.trim() || 'Not specified';
        if (summaryDetails.venue) summaryDetails.venue.textContent = selectedVenue?.value || 'Not selected';
        if (summaryDetails.date) summaryDetails.date.textContent = startDate && endDate
            ? `${formatDisplayDate(startDate)}${startDate === endDate ? '' : ` - ${formatDisplayDate(endDate)}`}`
            : 'Not selected';
        if (summaryDetails.time) summaryDetails.time.textContent = isWholeDay
            ? 'Whole day (08:00 AM - 11:59 PM)'
            : startTime && endTime ? `${formatDisplayTime(startTime)} - ${formatDisplayTime(endTime)}` : 'Not selected';
        if (summaryDetails.equipment) {
            summaryDetails.equipment.innerHTML = selectedEquipment.length
                ? selectedEquipment.map(item => `<li class="text-sm text-slate-700">${escapeHtml(item)}</li>`).join('')
                : '<li class="text-sm text-slate-500">No additional equipment selected</li>';
        }

        updateChecklistState();
    };

    if (capacityWarningBanner) {
        capacityWarningBanner.setAttribute('role', 'status');
        capacityWarningBanner.setAttribute('aria-live', 'polite');
    }

    conflictAlert.className = 'mt-4 p-4 rounded-lg border hidden';
    conflictAlert.id = 'conflict-alert';
    conflictAlert.setAttribute('role', 'alert');
    conflictAlert.setAttribute('aria-live', 'assertive');
    if (conflictAlertWrapper) {
        conflictAlertWrapper.appendChild(conflictAlert);
    } else {
        form.parentElement?.insertBefore(conflictAlert, form.parentElement.firstChild);
    }

    const showConflictWarning = function (message, tone = 'red') {
        if (!message || String(message).trim() === '') {
            hideConflictWarning();
            return;
        }

        conflictAlert.className = `mt-4 rounded-2xl border p-4 text-sm ${tone === 'amber' ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-red-200 bg-red-50 text-red-800'}`;
        conflictAlert.innerHTML = message;
        conflictAlert.classList.remove('hidden');
        conflictAlert.scrollIntoView({ block: 'center', behavior: 'smooth' });
    };

    const hideConflictWarning = function () {
        conflictAlert.classList.add('hidden');
        conflictAlert.innerHTML = '';
    };

    const showCapacityWarning = function (venueName, capacity, participants) {
        if (!capacityWarningBanner) {
            return;
        }

        capacityWarningBanner.className = 'mt-4 rounded-lg border border-red-300 bg-red-50 px-3 py-3 text-sm text-red-900';
        capacityWarningBanner.innerHTML = `
            <div class="flex items-start gap-3">
                <span class="mt-0.5 text-xl">⚠️</span>
                <div>
                    <div class="font-semibold">Selected venue capacity exceeded.</div>
                    <div class="text-xs text-red-900/80">The chosen venue (<strong>${escapeHtml(venueName)}</strong>) has a capacity of ${capacity} participant${capacity === 1 ? '' : 's'}.</div>
                </div>
            </div>
        `;
        capacityWarningBanner.classList.remove('hidden');
    };

    const hideCapacityWarning = function () {
        if (!capacityWarningBanner) {
            return;
        }
        capacityWarningBanner.classList.add('hidden');
        capacityWarningBanner.innerHTML = '';
    };

    const clearDraftStorage = function () {
        try {
            const draft = JSON.parse(localStorage.getItem('pitfr-request-draft') || '{}');
            (draft.files || []).forEach(function (name) {
                deleteDraftFile(name).catch(function (error) {
                    console.warn('Draft attachment cleanup failed', error);
                });
            });
        } catch (error) {
            console.warn('Draft attachment cleanup could not read saved draft', error);
        }
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

            const errorElement = document.getElementById(`${field.name}-error`);
            if (errorElement) {
                errorElement.textContent = '';
                errorElement.classList.add('hidden');
            }
        });
    };

    const createFileFromDraftData = function (metadata) {
        if (!metadata || !metadata.name || !metadata.data) {
            return null;
        }

        try {
            const [header, encoded] = metadata.data.split(',');
            if (!encoded) {
                return null;
            }
            const binary = atob(encoded);
            const bytes = new Uint8Array(binary.length);
            for (let index = 0; index < binary.length; index += 1) {
                bytes[index] = binary.charCodeAt(index);
            }
            const blob = new Blob([bytes], { type: metadata.type || 'application/octet-stream' });
            return new File([blob], metadata.name, { type: metadata.type || 'application/octet-stream', lastModified: Date.now() });
        } catch (error) {
            console.warn('Draft file restore failed', error);
            return null;
        }
    };

    const openDraftDatabase = function () {
        if (!window.indexedDB) {
            return Promise.reject(new Error('This browser does not support persistent request attachments.'));
        }
        if (!draftDatabasePromise) {
            draftDatabasePromise = new Promise(function (resolve, reject) {
                const request = window.indexedDB.open('pitfr-request-drafts', 1);
                request.onupgradeneeded = function () {
                    if (!request.result.objectStoreNames.contains('files')) {
                        request.result.createObjectStore('files', { keyPath: 'name' });
                    }
                };
                request.onsuccess = function () {
                    resolve(request.result);
                };
                request.onerror = function () {
                    reject(request.error || new Error('Unable to open request draft storage.'));
                };
            });
        }
        form.querySelectorAll('[name="venue[]"], [data-venue-schedule] .venue-schedule-input').forEach(function (field) {
            field.addEventListener('input', updateUrgentScheduleConstraints);
            field.addEventListener('change', updateUrgentScheduleConstraints);
        });
        updateUrgentScheduleConstraints();
        window.setInterval(function () {
            if (emergencyCheckbox?.checked) {
                updateUrgentScheduleConstraints();
            }
        }, 30000);
        return draftDatabasePromise;
    };

    const storeDraftFile = async function (name, file) {
        const database = await openDraftDatabase();
        return new Promise(function (resolve, reject) {
            const transaction = database.transaction('files', 'readwrite');
            transaction.objectStore('files').put({
                name: name,
                file: file,
                fileName: file.name,
                type: file.type,
                lastModified: file.lastModified
            });
            transaction.oncomplete = resolve;
            transaction.onerror = function () {
                reject(transaction.error || new Error('Unable to save request attachment.'));
            };
            transaction.onabort = function () {
                reject(transaction.error || new Error('Request attachment save was interrupted.'));
            };
        });
    };

    const loadDraftFile = async function (name) {
        const database = await openDraftDatabase();
        return new Promise(function (resolve, reject) {
            const transaction = database.transaction('files', 'readonly');
            const request = transaction.objectStore('files').get(name);
            request.onsuccess = function () {
                resolve(request.result || null);
            };
            request.onerror = function () {
                reject(request.error || new Error('Unable to restore request attachment.'));
            };
        });
    };

    const deleteDraftFile = async function (name) {
        const database = await openDraftDatabase();
        return new Promise(function (resolve, reject) {
            const transaction = database.transaction('files', 'readwrite');
            transaction.objectStore('files').delete(name);
            transaction.oncomplete = resolve;
            transaction.onerror = function () {
                reject(transaction.error || new Error('Unable to remove request attachment.'));
            };
            transaction.onabort = function () {
                reject(transaction.error || new Error('Request attachment removal was interrupted.'));
            };
        });
    };

    const displayRestoredFile = function (input, file) {
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        input.files = dataTransfer.files;
        const previewId = input.name === 'activity_proposal_file' ? 'activity-proposal-name'
            : input.name === 'igp_receipt_file' ? 'igp-receipt-name'
            : input.name === 'e_signature_file' ? 'e-signature-name'
            : null;
        const preview = previewId ? document.getElementById(previewId) : null;
        if (preview) {
            preview.textContent = file.name;
            const wrapper = preview.closest('p,div') || preview.parentElement;
            wrapper?.classList.remove('hidden');
        }
        input.dataset.restoredFileName = file.name;
    };

    const saveDraft = function () {
        const persistDraft = async function () {
            const formData = new FormData(form);
            const draft = {};

            formData.forEach((value, key) => {
                const field = form.querySelector(`[name="${key}"]`);
                if (field?.type === 'file' || key === 'proposal_file' || key === 'equipment[]' || key === 'venue') {
                    return;
                }
                draft[key] = value;
            });

            const fileInputs = Array.from(form.querySelectorAll('input[type="file"]'));
            const files = fileInputs.filter(input => input.files?.length).map(input => input.name);
            await Promise.all(fileInputs.map(function (input) {
                return input.files?.length
                    ? storeDraftFile(input.name, input.files[0])
                    : Promise.resolve();
            }));
            draft.files = files;
            draft.equipment = Array.from(equipmentCheckboxes)
                .filter(checkbox => checkbox.checked)
                .map(checkbox => checkbox.value);

            draft.venue = Array.from(venueRadioInputs)
                .find(radio => radio.checked)?.value || '';

            try {
                localStorage.setItem('pitfr-request-draft', JSON.stringify(draft));
                sessionStorage.setItem('pitfr-request-draft', JSON.stringify(draft));
                if (draftStatus) {
                    const label = draftStatus.querySelector('[data-draft-status-label]');
                    if (label) {
                        label.textContent = 'Draft saved just now';
                    }
                }
            } catch (error) {
                throw new Error('Unable to save the request draft in this browser.', { cause: error });
            }
        };

        draftSaveQueue = draftSaveQueue.catch(function () {}).then(persistDraft).then(function () {
            return true;
        }).catch(function (error) {
            console.error('Draft autosave failed', error);
            if (draftStatus) {
                const label = draftStatus.querySelector('[data-draft-status-label]');
                if (label) {
                    label.textContent = 'Draft attachment could not be saved';
                }
            }
            return false;
        });
        return draftSaveQueue;
    };

    const restoreDraft = async function () {
        try {
            clearTransientUiState();
            const cached = localStorage.getItem('pitfr-request-draft') || sessionStorage.getItem('pitfr-request-draft');
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
            for (const [key, value] of Object.entries(draft)) {
                if (key === 'equipment') {
                    equipmentCheckboxes.forEach(checkbox => {
                        checkbox.checked = Array.isArray(value) && value.includes(checkbox.value);
                    });
                    continue;
                }

                if (key === 'venue') {
                    venueRadioInputs.forEach(radio => {
                        radio.checked = radio.value === value;
                    });
                    continue;
                }

                if (key === 'files') {
                    for (const fileName of value || []) {
                        const targetInput = form.querySelector(`[name="${fileName}"]`);
                        if (!targetInput) {
                            continue;
                        }
                        const savedFile = await loadDraftFile(fileName);
                        if (savedFile?.file) {
                            displayRestoredFile(targetInput, new File([savedFile.file], savedFile.fileName, {
                                type: savedFile.type || savedFile.file.type || 'application/octet-stream',
                                lastModified: savedFile.lastModified || Date.now()
                            }));
                        }
                    }
                    continue;
                }

                if (key === 'fileMetadata') {
                    Object.entries(value || {}).forEach(([fileName, metadata]) => {
                        const targetInput = form.querySelector(`[name="${fileName}"]`);
                        const restoredFile = createFileFromDraftData(metadata);
                        if (targetInput && restoredFile) {
                            displayRestoredFile(targetInput, restoredFile);
                        }
                    });
                    continue;
                }

                const field = form.querySelector(`[name="${key}"]`);
                if (!field) {
                    continue;
                }

                if (field.type === 'file') {
                    continue;
                }

                if (field.type === 'checkbox') {
                    field.checked = value === '1' || value === true;
                } else if (field.type === 'radio') {
                    form.querySelectorAll(`input[type="radio"][name="${key}"]`).forEach(function (radio) {
                        radio.checked = radio.value === value;
                    });
                } else {
                    field.value = value;
                }
            }

            updateProposalRequirement();
            toggleEmergencyJustification();
            updateOtherVenueVisibility();
            updateSelectedItemsSummary();
            updateCapacityWarning();
            form.querySelector('[name="reservation_duration"]:checked')?.dispatchEvent(new Event('change', { bubbles: true }));
            form.querySelector('[name="reservation_duration"]:checked')?.dispatchEvent(new Event('change', { bubbles: true }));
            syncOvernightEndDate();
            updateUrgentScheduleConstraints();
            refreshAllEquipmentAvailability();
            clearTimeout(conflictCheckTimeout);
            conflictCheckTimeout = setTimeout(checkConflicts, 250);

            if (draftStatus) {
                draftStatus.textContent = 'Draft restored';
            }
        } catch (error) {
            console.error('Draft restore failed', error);
            if (draftStatus) {
                const label = draftStatus.querySelector('[data-draft-status-label]');
                if (label) {
                    label.textContent = 'Draft attachment could not be restored';
                }
            }
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
            return `${summaryDate}\n${formatTime(startTime)} – ${formatTime(endTime)}`;
    };

    const updateSelectedItemsSummary = function () {
        const selectedVenue = form.querySelector('input[name="venue"]:checked');
        const venueText = selectedVenue ? selectedVenue.value : (venueSelect?.value || 'No venue selected yet.');
        document.getElementById('summary-venue').textContent = 'Venue: ' + venueText;

        const selectedEquipment = Array.from(form.querySelectorAll('input[name="equipment[]"]:checked'));
        const summaryEquipment = document.getElementById('summary-equipment');

        const mergedEquipment = [...selectedEquipment].map(checkbox => checkbox.value);

        if (mergedEquipment.length === 0) {
            summaryEquipment.innerHTML = 'Equipment: No additional equipment selected.';
            updateFormSummary();
            return;
        }

        const equipmentLines = mergedEquipment.map(item => {
            const checkbox = form.querySelector(`input[name="equipment[]"][value="${CSS.escape(item)}"]`);
            const row = checkbox ? checkbox.closest('.equipment-row') : null;
            const qtyInput = row?.querySelector('.quantity-input-wrap input[type="number"]');
            const qty = qtyInput?.value || 'Enter quantity';
            return `<div>• ${escapeHtml(item)} × ${escapeHtml(qty)}</div>`;
        });

        summaryEquipment.innerHTML = 'Equipment:<br>' + equipmentLines.join('');
        updateFormSummary();
    };

    const updateOtherVenueVisibility = function () {
        if (!otherVenueWrap || !otherVenueInput) {
            return;
        }

        const selectedVenue = form.querySelector('input[name="venue"]:checked')?.value || (venueSelect?.value || '');
        const isOther = selectedVenue === 'Others (specify)';

        otherVenueWrap.style.display = isOther ? 'block' : 'none';

        if (isOther) {
            otherVenueInput.disabled = false;
            otherVenueInput.removeAttribute('disabled');
            otherVenueInput.setAttribute('required', 'required');
            otherVenueInput.setAttribute('aria-required', 'true');
        } else {
            otherVenueInput.removeAttribute('required');
            otherVenueInput.setAttribute('aria-required', 'false');
            otherVenueInput.disabled = true;
            otherVenueInput.value = '';
        }
    };

    const renderVenueDefaultEquipmentList = function (venueName) {
        if (!venueDefaultEquipmentList) {
            return;
        }

        venueDefaultEquipmentList.innerHTML = '';
    };

    const decodeHtmlEntities = function (value) {
        if (!value || typeof value !== 'string') {
            return value;
        }

        const textarea = document.createElement('textarea');
        textarea.innerHTML = value;
        return textarea.value;
    };

    const getVenueCapacities = function () {
        try {
            const raw = form.getAttribute('data-venue-capacities') || form.dataset.venueCapacities || '';
            const decoded = decodeHtmlEntities(raw);
            return decoded ? JSON.parse(decoded) : {};
        } catch (error) {
            return {};
        }
    };

    const updateCapacityWarning = function () {
        if (!capacityWarningBanner || !expectedParticipantsInput) {
            return;
        }

        const participantValue = expectedParticipantsInput.value;
        const participants = Number(participantValue);
        const selectedVenue = form.querySelector('input[name="venue"]:checked');
        const selectedVenueValue = selectedVenue ? selectedVenue.value : (venueSelect?.value || null);

        if (!selectedVenueValue || !participantValue || Number.isNaN(participants) || selectedVenueValue === 'Others (specify)') {
            hideCapacityWarning();
            return;
        }

        let capacity = null;
        const capacities = getVenueCapacities();
        if (Object.prototype.hasOwnProperty.call(capacities, selectedVenueValue)) {
            capacity = Number.parseInt(capacities[selectedVenueValue], 10);
        }

        if (capacity === null || Number.isNaN(capacity)) {
            hideCapacityWarning();
            return;
        }

        if (participants <= capacity) {
            hideCapacityWarning();
            return;
        }

        hideCapacityWarning();
        showCapacityWarning(selectedVenueValue, capacity, participantValue);
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

    const getSystemLocalDateTime = function () {
        const parts = new Intl.DateTimeFormat('en-CA', {
            timeZone: form.dataset.urgentTimezone || 'Asia/Manila',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23'
        }).formatToParts(new Date()).reduce(function (values, part) {
            values[part.type] = part.value;
            return values;
        }, {});

        return {
            date: `${parts.year}-${parts.month}-${parts.day}`,
            time: `${parts.hour}:${parts.minute}`
        };
    };

    const updateUrgentScheduleConstraints = function () {
        const isUrgent = Boolean(emergencyCheckbox?.checked);
        const current = getSystemLocalDateTime();

        venueScheduleGroups.forEach(function (group) {
            const venueOption = group.closest('[data-venue-option]');
            const isSelected = venueOption?.querySelector('[name="venue[]"]')?.checked === true;
            const startDate = group.querySelector('input[name$="[start_date]"]');
            const endDate = group.querySelector('input[name$="[end_date]"]');
            const startTime = group.querySelector('input[name$="[start_time]"]');
            const dates = [startDate, endDate].filter(Boolean);

            dates.forEach(function (field) {
                if (isUrgent) {
                    field.max = current.date;
                } else {
                    field.removeAttribute('max');
                }
                if (field.dataset.urgentValidation === '1') {
                    field.setCustomValidity('');
                    delete field.dataset.urgentValidation;
                }
            });

            if (startTime) {
                if (isUrgent && startDate?.value === current.date) {
                    startTime.min = current.time;
                } else {
                    startTime.removeAttribute('min');
                }
                if (startTime.dataset.urgentValidation === '1') {
                    startTime.setCustomValidity('');
                    delete startTime.dataset.urgentValidation;
                }
            }

            if (!isUrgent || !isSelected) {
                return;
            }

            if (startDate?.value && startDate.value !== current.date) {
                startDate.setCustomValidity('Urgent reservations must be scheduled for today.');
                startDate.dataset.urgentValidation = '1';
            }
            if (endDate?.value && endDate.value !== current.date) {
                endDate.setCustomValidity('Urgent reservations must end today.');
                endDate.dataset.urgentValidation = '1';
            }
            if (startDate?.value === current.date && startTime?.value && startTime.value < current.time) {
                const message = 'Urgent reservations must start at the current time or later.';
                const invalidField = startTime.readOnly ? startDate : startTime;
                invalidField?.setCustomValidity(message);
                if (invalidField) {
                    invalidField.dataset.urgentValidation = '1';
                }
            }
        });
    };

    const validateUrgentSchedules = function () {
        updateUrgentScheduleConstraints();
        return !emergencyCheckbox?.checked
            || venueScheduleGroups
                .filter(function (group) {
                    return group.closest('[data-venue-option]')?.querySelector('[name="venue[]"]')?.checked === true;
                })
                .every(function (group) {
                    return Array.from(group.querySelectorAll('input[type="date"], input[type="time"]'))
                        .every(function (field) {
                            return field.validity.valid;
                        });
                });
    };

    const setInputValidationState = function (field, valid, message = '') {
        if (!field) {
            return;
        }

        const errorId = `${field.name}-error`;
        let errorElement = document.getElementById(errorId);

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
        const isSpecificTime = (form.querySelector('[name="reservation_duration"]:checked')?.value || 'specific_time') === 'specific_time';
        const disallowedEnd = isSpecificTime && endTime && endTime <= '05:00';
        endTimeInput.setCustomValidity(disallowedEnd
            ? 'Reservations cannot end between 12:00 AM and 5:00 AM. Choose an end time after 5:00 AM or use the Whole Day option.'
            : '');
        endTimeInput.setAttribute('aria-invalid', disallowedEnd ? 'true' : 'false');
        document.getElementById('reservation-end-time-error')?.classList.toggle('hidden', !disallowedEnd);
        if (disallowedEnd) {
            return false;
        }

        if (!startDate || !endDate || !startTime || !endTime) {
            return true;
        }

        const start = new Date(`${startDate}T${startTime}`);
        const end = new Date(`${endDate}T${endTime}`);

        if (end <= start) {
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

    const formatFileSize = function (bytes) {
        if (!bytes || Number.isNaN(bytes)) {
            return '0 KB';
        }

        const units = ['B', 'KB', 'MB', 'GB'];
        let size = bytes;
        let unitIndex = 0;

        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex += 1;
        }

        return `${size.toFixed(size >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
    };

    const setUploadState = function (box, state, options = {}) {
        if (!box) {
            return;
        }

        const status = box.querySelector('[data-upload-status]');
        const actionLabel = box.querySelector('[data-upload-action-label]');
        const progress = box.querySelector('[data-upload-progress]');
        const fileName = box.querySelector('[data-upload-file-name]');
        const percent = box.querySelector('[data-upload-percent]');
        const bar = box.querySelector('[data-upload-bar]');
        const errorBox = box.querySelector('[data-upload-error]');
        const nextPercent = Number.isFinite(options.percent) ? Math.min(100, Math.max(0, options.percent)) : 0;
        const fileValue = options.fileName || fileName?.textContent || 'file.pdf';

        box.classList.remove('border-slate-300', 'border-emerald-400', 'border-cyan-400', 'border-emerald-500', 'bg-slate-50', 'bg-cyan-500/5', 'bg-slate-900/60', 'bg-emerald-50/40', 'bg-white', 'shadow-[0_0_0_1px_rgba(34,211,238,0.25)]', 'shadow-[0_0_0_1px_rgba(16,185,129,0.18)]', 'shadow-lg');
        box.classList.add(
            state === 'dragging' ? 'border-emerald-500 bg-emerald-50 shadow-lg' :
            state === 'uploading' ? 'border-emerald-500 bg-white shadow-lg' :
            state === 'success' ? 'border-emerald-400 bg-emerald-50/40 shadow-[0_0_0_1px_rgba(16,185,129,0.18)]' :
            state === 'error' ? 'border-red-300 bg-red-50/30 shadow-[0_0_0_1px_rgba(239,68,68,0.12)]' :
            'border-slate-300 bg-slate-50'
        );

        if (fileName) {
            fileName.textContent = fileValue;
        }

        if (percent) {
            percent.textContent = `${Math.round(nextPercent)}%`;
        }

        if (bar) {
            bar.style.width = `${nextPercent}%`;
            bar.classList.remove('bg-gradient-to-r', 'from-cyan-400', 'to-emerald-400', 'from-red-400', 'to-red-300', 'bg-emerald-600', 'bg-red-500');
            bar.classList.add(state === 'error' ? 'bg-red-500' : 'bg-emerald-600');
        }

        if (status) {
            const shouldShowStatus = state === 'uploading' || state === 'success';
            status.classList.toggle('hidden', !shouldShowStatus);
            status.textContent = state === 'success' ? 'Uploaded' : 'Uploading...';
            status.classList.remove('text-emerald-400', 'text-cyan-400');
            status.classList.add('text-slate-700');
        }

        if (progress) {
            progress.classList.toggle('hidden', state === 'idle' || state === 'error');
        }

        if (errorBox) {
            errorBox.classList.toggle('hidden', state !== 'error');
        }

        if (actionLabel) {
            actionLabel.textContent = state === 'success' ? 'Uploaded' : state === 'error' ? 'Retry' : 'Upload a file';
        }
    };

    const initializeUploadSurfaceStates = function () {
        document.querySelectorAll('[data-upload-box]').forEach(function (box) {
            const input = box.querySelector('input[type="file"]');
            if (!input) {
                return;
            }

            const resetToIdle = function () {
                setUploadState(box, 'idle');
            };

            const simulateUpload = function (file) {
                if (!file) {
                    resetToIdle();
                    return;
                }

                const fileNameValue = file.name || 'file.pdf';
                const fileSizeValue = formatFileSize(file.size || 0);
                const progressNode = box.querySelector('[data-upload-file-name]');
                const statusNode = box.querySelector('[data-upload-status]');

                if (progressNode) {
                    progressNode.textContent = `${fileNameValue} • ${fileSizeValue}`;
                }

                if (statusNode) {
                    statusNode.textContent = 'Uploading...';
                }

                setUploadState(box, 'uploading', { percent: 15, fileName: `${fileNameValue} • ${fileSizeValue}` });

                let progress = 15;
                const timer = window.setInterval(function () {
                    progress += 12;
                    if (progress >= 100) {
                        window.clearInterval(timer);
                        setUploadState(box, 'success', { percent: 100, fileName: `${fileNameValue} • ${fileSizeValue}` });
                        return;
                    }

                    setUploadState(box, 'uploading', { percent: progress, fileName: `${fileNameValue} • ${fileSizeValue}` });
                }, 220);

                const retryButton = box.querySelector('[data-retry-upload]');
                if (retryButton) {
                    retryButton.onclick = function () {
                        window.clearInterval(timer);
                        setUploadState(box, 'uploading', { percent: 18, fileName: `${fileNameValue} • ${fileSizeValue}` });
                        let retryProgress = 18;
                        const retryTimer = window.setInterval(function () {
                            retryProgress += 16;
                            if (retryProgress >= 100) {
                                window.clearInterval(retryTimer);
                                setUploadState(box, 'success', { percent: 100, fileName: `${fileNameValue} • ${fileSizeValue}` });
                                return;
                            }
                            setUploadState(box, 'uploading', { percent: retryProgress, fileName: `${fileNameValue} • ${fileSizeValue}` });
                        }, 180);
                    };
                }
            };

            input.addEventListener('change', function () {
                const file = input.files?.[0];
                if (!file) {
                    resetToIdle();
                    return;
                }

                simulateUpload(file);
            });

            box.addEventListener('dragover', function (event) {
                event.preventDefault();
                setUploadState(box, 'dragging');
            });

            box.addEventListener('dragleave', function (event) {
                if (!box.contains(event.relatedTarget)) {
                    if (input.files && input.files.length) {
                        setUploadState(box, 'success', { percent: 100, fileName: input.files[0].name });
                        return;
                    }
                    resetToIdle();
                }
            });

            box.addEventListener('drop', function (event) {
                event.preventDefault();
                const droppedFiles = event.dataTransfer?.files;
                if (droppedFiles && droppedFiles.length) {
                    input.files = droppedFiles;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            const retryButton = box.querySelector('[data-retry-upload]');
            if (retryButton) {
                retryButton.addEventListener('click', function () {
                    const file = input.files?.[0];
                    if (!file) {
                        resetToIdle();
                        return;
                    }
                    simulateUpload(file);
                    setUploadState(box, 'uploading', { percent: 10, fileName: file.name });
                });
            }
        });
    };

    const initializeFileInputs = function () {
        const fileInputs = [
            { name: 'activity_proposal_file', preview: 'activity-proposal-preview', label: 'activity-proposal-name', extensions: ['pdf', 'png', 'jpg', 'jpeg'], displayLabel: 'Activity Proposal' },
            { name: 'igp_receipt_file', preview: 'igp-receipt-preview', label: 'igp-receipt-name', extensions: ['pdf', 'png', 'jpg', 'jpeg'], displayLabel: 'IGP Receipt' },
            { name: 'e_signature_file', preview: 'e-signature-preview', label: 'e-signature-name', extensions: ['png', 'jpg', 'jpeg'], displayLabel: 'E-Signature' },
            { name: 'proposal_file', preview: 'file-preview', label: 'file-name', extensions: ['pdf', 'png', 'jpg', 'jpeg'], displayLabel: 'Supporting Document' }
        ];

        fileInputs.forEach(function (config) {
            const input = form.querySelector(`[name="${config.name}"]`);
            if (!input) {
                return;
            }

            input.addEventListener('change', function () {
                const file = input.files?.[0];
                const extension = file?.name.split('.').pop()?.toLowerCase();
                const preview = document.getElementById(config.preview);
                const label = document.getElementById(config.label);
                const uploadCard = input.closest('[data-upload-box]')?.parentElement?.querySelector('[data-upload-file-card]');
                const uploadFilename = uploadCard?.querySelector('[data-upload-filename]');
                const uploadSubtitle = uploadCard?.querySelector('[data-upload-subtitle]');
                const uploadProgress = uploadCard?.querySelector('[data-upload-file-progress]');
                const uploadCheck = uploadCard?.querySelector('[data-upload-check]');

                if (file && !config.extensions.includes(extension)) {
                    input.value = '';
                    setInputValidationState(input, false, `Invalid file type. Allowed formats: ${config.extensions.join(', ')}`);
                    preview?.classList.add('hidden');
                    uploadCard?.classList.add('hidden');
                    updateFormSummary();
                    return;
                }

                setInputValidationState(input, true);
                if (label) label.textContent = file?.name || '';
                if (preview) preview.classList.toggle('hidden', !file);

                if (uploadCard) {
                    uploadCard.classList.toggle('hidden', !file);
                    if (uploadFilename) {
                        uploadFilename.textContent = file ? file.name : config.displayLabel;
                    }
                    if (uploadSubtitle) {
                        uploadSubtitle.textContent = file ? config.displayLabel : 'No file selected';
                    }
                    if (uploadProgress) {
                        uploadProgress.style.width = file ? '100%' : '0%';
                    }
                    if (uploadCheck) {
                        uploadCheck.innerHTML = file ? '<span>Ready</span>' : '<span>Ready</span>';
                    }
                }

                updateFormSummary();
            });
        });
    };

    const validateQuantities = function () {
        let valid = true;

        rows.forEach(row => {
            const checkbox = row.querySelector('.equipment-checkbox');
            const qtyInput = row.querySelector('.quantity-input-wrap input[type="number"]');
            const errorMessage = row.querySelector('.quantity-error-message');
            if (!checkbox || !qtyInput) return;
            const maxAllowed = parseInt(row.dataset.available, 10) || 0;
            const qty = Number(qtyInput.value);

            if (checkbox.checked && (!Number.isInteger(qty) || qty < 1 || qty > maxAllowed)) {
                qtyInput.classList.add('border-red-500');
                qtyInput.setCustomValidity(maxAllowed > 0
                    ? `Enter a whole-number quantity from 1 to ${maxAllowed}.`
                    : 'No units are available for the selected schedule.');
                if (errorMessage) {
                    errorMessage.textContent = maxAllowed > 0
                        ? 'Enter a whole-number quantity from 1 to ' + maxAllowed + '.'
                        : 'No units are available for the selected schedule.';
                    errorMessage.classList.remove('hidden');
                }
                valid = false;
            } else {
                qtyInput.classList.remove('border-red-500');
                qtyInput.setCustomValidity('');
                if (errorMessage) {
                    errorMessage.textContent = '';
                    errorMessage.classList.add('hidden');
                }
            }
        });

        return valid;
    };

    const updateEquipmentAvailabilityBadge = function (row) {
        const badge = row.querySelector('.availability-badge');
        const checkbox = row.querySelector('.equipment-checkbox');
        const qtyInput = row.querySelector('.quantity-input-wrap input[type="number"]');
        if (!badge) return;

        const available = Math.max(0, parseInt(row.dataset.available, 10) || 0);
        const total = Math.max(0, parseInt(row.dataset.total, 10) || 0);
        const requested = Number(qtyInput?.value);
        const hasQuantity = Boolean(qtyInput?.value);
        const hasValidQuantity = Number.isInteger(requested) && requested >= 1 && requested <= available;
        const remaining = checkbox?.checked && hasQuantity
            ? Math.max(0, available - (hasValidQuantity ? requested : available))
            : available;
        const state = getAvailabilityState(remaining, total);
        const tone = checkbox?.checked && hasQuantity && !hasValidQuantity ? 'red' : state.tone;

        badge.textContent = `${state.icon} ${remaining} / ${total} available`;
        badge.className = `availability-badge rounded-full px-2.5 py-1 text-xs font-semibold ${tone === 'green' ? 'bg-green-100 text-green-700' : tone === 'yellow' ? 'bg-yellow-100 text-yellow-700' : tone === 'orange' ? 'bg-orange-100 text-orange-700' : 'bg-red-100 text-red-700'}`;
    };

    const updateEquipmentRowVisuals = function (row, available, total, overlappingRequests = []) {
        const qtyInput = row.querySelector('.quantity-input-wrap input[type="number"]');
        const checkbox = row.querySelector('.equipment-checkbox');
        const utilizationCard = row.querySelector('.equipment-utilization-card');
        const normalizedAvailable = Math.max(0, parseInt(available, 10) || 0);
        const normalizedTotal = Math.max(0, parseInt(total, 10) || 0);

        if (utilizationCard) {
            if (overlappingRequests.length > 0) {
                const firstConflict = overlappingRequests[0];
                const reservedQuantity = Math.max(0, normalizedTotal - normalizedAvailable);
                const remainingQuantity = normalizedAvailable;
                const conflictPriority = (firstConflict?.priority || 'regular').toString().toUpperCase();
                const priorityTone = conflictPriority === 'URGENT' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700';
                utilizationCard.className = 'equipment-utilization-card mt-2 w-full rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-left text-[11px] text-slate-600 sm:min-w-[220px]';
                // Build DOM safely instead of injecting unescaped server values
                utilizationCard.innerHTML = '';
                const title = document.createElement('div');
                title.className = 'font-semibold text-slate-800';
                title.textContent = '⚠ Existing Reservation' + (overlappingRequests.length > 1 ? `s (${overlappingRequests.length})` : '');
                utilizationCard.appendChild(title);

                const infoWrap = document.createElement('div');
                infoWrap.className = 'mt-1 space-y-1';

                const activityRow = document.createElement('div');
                activityRow.className = 'flex items-center gap-2';
                const activityDiv = document.createElement('div');
                activityDiv.textContent = (overlappingRequests.length > 1 ? 'Example activity: ' : 'Activity: ') + (firstConflict?.activity || 'Existing reservation');
                const priorityDiv = document.createElement('div');
                priorityDiv.className = 'ml-2 inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-semibold ring-1 ring-inset ' + priorityTone;
                priorityDiv.textContent = escapeHtml(conflictPriority);
                activityRow.appendChild(activityDiv);
                activityRow.appendChild(priorityDiv);
                infoWrap.appendChild(activityRow);

                const reservedDiv = document.createElement('div');
                reservedDiv.textContent = 'Reserved Quantity: ' + reservedQuantity;
                infoWrap.appendChild(reservedDiv);

                const remainingDiv = document.createElement('div');
                remainingDiv.textContent = 'Remaining Quantity: ' + remainingQuantity;
                infoWrap.appendChild(remainingDiv);

                utilizationCard.appendChild(infoWrap);

                const scheduleWrap = document.createElement('div');
                scheduleWrap.className = 'mt-2 whitespace-pre-line border-t border-amber-200 pt-2 text-[11px] text-slate-500';
                const scheduleTitle = document.createElement('div');
                scheduleTitle.className = 'font-medium';
                scheduleTitle.textContent = 'Reservation Schedule';
                const scheduleContent = document.createElement('div');
                    scheduleContent.textContent = getScheduleSummary().replace(/<br>/g, '\n');
                scheduleWrap.appendChild(scheduleTitle);
                scheduleWrap.appendChild(scheduleContent);
                utilizationCard.appendChild(scheduleWrap);
            } else {
                utilizationCard.className = 'equipment-utilization-card mt-2 hidden';
                utilizationCard.innerHTML = '';
            }
        }

        if (checkbox && qtyInput) {
            qtyInput.max = normalizedAvailable;
            qtyInput.required = checkbox.checked;

            if (normalizedAvailable <= 0) {
                checkbox.disabled = true;
                checkbox.checked = false;
                qtyInput.value = '';
                qtyInput.disabled = true;
                qtyInput.required = false;
                qtyInput.closest('.quantity-input-wrap').style.display = 'none';
            } else {
                checkbox.disabled = false;
                qtyInput.disabled = !checkbox.checked;
                if (checkbox.checked) {
                    qtyInput.closest('.quantity-input-wrap').style.display = 'block';
                } else {
                    qtyInput.value = '';
                    qtyInput.closest('.quantity-input-wrap').style.display = 'none';
                }
            }

            qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
        } else {
            updateEquipmentAvailabilityBadge(row);
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

            // Ensure availability values are always non-negative numbers
            const avail = Math.max(0, Number(data.available || 0));
            const tot = Math.max(0, Number(data.total || 0));
            row.dataset.available = String(avail);
            row.dataset.total = String(tot);
            updateEquipmentRowVisuals(row, avail, tot, data.overlapping_requests || []);
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
                let conflictHtml = isUrgent
                    ? '<div class="font-semibold mb-2">⚠️ Institute Urgent Request</div>'
                    : '<div class="font-semibold mb-2">⚠️ Scheduling conflict detected for this venue:</div>';
                conflictHtml += '<div class="text-sm space-y-2">';
                if (isUrgent) {
                    conflictHtml += '<div class="text-sm">This venue already has an existing reservation. Because this request is marked as an Institute Urgent Activity, it may proceed for administrative review.</div>';
                }
                conflictEntries.forEach(conflict => {
                    conflictHtml += `<div class="rounded-xl border border-white/70 bg-white/70 p-3 text-xs">
                        <div class="font-semibold text-slate-800">Existing reservation</div>
                        <div>Schedule: ${escapeHtml(conflict.start_date)}${conflict.end_date ? ' to ' + escapeHtml(conflict.end_date) : ''} at ${escapeHtml(conflict.time)}</div>
                    </div>`;
                });
                conflictHtml += '</div>';
                conflictHtml += isUrgent
                    ? '<div class="mt-2 text-xs text-amber-800">Final approval will determine whether the existing reservation must be rescheduled.</div>'
                    : '<div class="mt-2 text-xs text-red-600">Please choose another venue or schedule.</div>';

                showConflictWarning(conflictHtml, isUrgent ? 'amber' : 'red');

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
                hideConflictWarning();
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

        const startDateValue = startDateInput.value;
        const endDateValue = endDateInput.value || startDateValue;
        const startDate = new Date(`${startDateValue}T${startTimeInput.value}`);
        const endDate = new Date(`${endDateValue}T${endTimeInput.value}`);
        const nextDay = new Date(startDate);
        nextDay.setDate(nextDay.getDate() + 1);
        const nextDayString = `${nextDay.getFullYear()}-${String(nextDay.getMonth() + 1).padStart(2, '0')}-${String(nextDay.getDate()).padStart(2, '0')}`;
        const isOvernightTime = endTimeInput.value <= startTimeInput.value;
        const shouldAutoUpdate = isOvernightTime && (!endDateInput.value || endDateInput.value === startDateValue);
        const isOvernightBooking = isOvernightTime && endDateInput.value === nextDayString;

        if (shouldAutoUpdate) {
            if (endDateInput.value !== nextDayString) {
                endDateInput.value = nextDayString;
            }
            autoAdjustedEndDate = true;
            if (overnightHint) {
                overnightHint.textContent = 'Overnight booking detected: end date auto-updated to the next day.';
                overnightHint.classList.remove('hidden');
            }
            return;
        }

        if (isOvernightBooking) {
            if (overnightHint) {
                overnightHint.textContent = 'Overnight booking detected: end date auto-updated to the next day.';
                overnightHint.classList.remove('hidden');
            }
            return;
        }

        if (autoAdjustedEndDate && endDateInput.value === nextDayString) {
            endDateInput.value = startDateValue;
            autoAdjustedEndDate = false;
        }

        if (overnightHint) {
            overnightHint.classList.add('hidden');
        }

        if (endDateInput.value === nextDayString && endDate <= startDate) {
            endDateInput.value = startDateValue;
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
                updateFormSummary();
            });
            field.addEventListener('change', function () {
                if (field.validity.valid) {
                    setInputValidationState(field, true);
                }
                saveDraft();
                updateFormSummary();
            });
        });
    };

    const applyVenueSpecificEquipmentRules = function () {
        const selectedVenues = Array.from(form.querySelectorAll('input[name="venue"]:checked'))
            .map(input => input.value);

        if (equipmentSelectionPanel) {
            equipmentSelectionPanel.style.display = 'block';
        }

        if (venueDefaultEquipmentPanel) {
            venueDefaultEquipmentPanel.style.display = 'none';
        }

        if (balayChairNote) {
            balayChairNote.classList.toggle('hidden', !selectedVenues.includes('Balay Alumni'));
        }

        const balayIncompatibleEquipment = [
            'Canopies',
            'Industrial Fans'
        ];

        const equipmentRows = form.querySelectorAll('.equipment-row');
        equipmentRows.forEach(row => {
            const checkbox = row.querySelector('input[name="equipment[]"]');
            const itemName = checkbox ? checkbox.value : '';
            const isBalaySelected = selectedVenues.includes('Balay Alumni');
            const isIncompatible = isBalaySelected && balayIncompatibleEquipment.includes(itemName);

            checkbox.dataset.venueDefault = 'false';
            if (isIncompatible) {
                row.style.opacity = '0.5';
                row.style.pointerEvents = 'none';
                checkbox.checked = false;
                checkbox.disabled = true;
            } else {
                row.style.opacity = '1';
                row.style.pointerEvents = 'auto';
                checkbox.disabled = false;
            }
        });

        updateSelectedItemsSummary();
    };

    const attachSelectionListeners = function () {
        venueRadioInputs.forEach(radio => {
            radio.addEventListener('mousedown', function () {
                radio.dataset.wasChecked = radio.checked ? 'true' : 'false';
            });

            const handleVenueSelection = function () {
                applyVenueSpecificEquipmentRules();
                updateOtherVenueVisibility();
                updateSelectedItemsSummary();
                updateCapacityWarning();
                clearTimeout(conflictCheckTimeout);
                conflictCheckTimeout = setTimeout(checkConflicts, 300);
                refreshAllEquipmentAvailability();
                saveDraft();
            };

            radio.addEventListener('click', function (event) {
                if (radio.dataset.wasChecked === 'true' && radio.checked) {
                    event.preventDefault();
                    radio.checked = false;
                    radio.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            radio.addEventListener('change', handleVenueSelection);
        });

        if (venueSelect) {
            venueSelect.addEventListener('change', function () {
                applyVenueSpecificEquipmentRules();
                updateOtherVenueVisibility();
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
                saveDraft();
            });
        });
    };

    let conflictCheckTimeout;
    let equipmentAvailabilityRefreshTimeout;
    let autoAdjustedEndDate = false;

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
    updateOtherVenueVisibility();
    if (emergencyCheckbox) {
        emergencyCheckbox.addEventListener('change', function () {
            updateProposalRequirement();
            toggleEmergencyJustification();
            updateUrgentScheduleConstraints();
            updateOtherVenueVisibility();
            saveDraft();
            clearTimeout(conflictCheckTimeout);
            conflictCheckTimeout = setTimeout(checkConflicts, 300);
        });
    }

    createValidationListeners();
    initializeFileInputs();
    attachSelectionListeners();
    restoreDraft();
    form.querySelectorAll('[data-preserve-request-draft]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            link.setAttribute('aria-disabled', 'true');
            saveDraft().then(function (saved) {
                if (!saved) {
                    link.removeAttribute('aria-disabled');
                    link.focus();
                    return;
                }
                window.requestFormDraftNavigation = true;
                window.location.assign(link.href);
            });
        });
    });
    updateOtherVenueVisibility();
    updateSelectedItemsSummary();

    document.getElementById('print-summary-btn')?.addEventListener('click', function () {
        window.print();
    });

    rows.forEach(function (row) {
        const checkbox = row.querySelector('.equipment-checkbox');
        const qtyInput = row.querySelector('.quantity-input-wrap input[type="number"]');
        const qtyWrap = row.querySelector('.quantity-input-wrap');
        if (!checkbox || !qtyInput || !qtyWrap) return;
        let errorMessage = row.querySelector('.quantity-error-message');

        if (!errorMessage) {
            errorMessage = document.createElement('div');
            errorMessage.className = 'quantity-error-message mt-1 text-xs text-red-600 hidden';
            qtyWrap.appendChild(errorMessage);
        }

        const updateAvailability = function () {
            const available = Math.max(0, parseInt(row.dataset.available, 10) || 0);
            const isChecked = checkbox.checked;
            const quantity = Number(qtyInput.value);

            qtyInput.max = available;
            qtyInput.disabled = !isChecked || available <= 0;
            qtyInput.required = isChecked;

            if (isChecked) {
                qtyWrap.style.display = 'block';
            } else {
                qtyWrap.style.display = 'none';
                qtyInput.value = '';
            }

            const invalid = isChecked && (!Number.isInteger(quantity) || quantity < 1 || quantity > available);
            if (invalid) {
                qtyInput.setCustomValidity(available > 0
                    ? 'Enter a whole-number quantity from 1 to ' + available + '.'
                    : 'No units are available for the selected schedule.');
                errorMessage.textContent = available > 0
                    ? 'Enter a whole-number quantity from 1 to ' + available + '.'
                    : 'No units are available for the selected schedule.';
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

            if (available <= 0) {
                checkbox.disabled = true;
                checkbox.checked = false;
                qtyInput.value = '';
                qtyInput.disabled = true;
                qtyInput.required = false;
                qtyWrap.style.display = 'none';
                qtyInput.setCustomValidity('');
                qtyInput.classList.remove('border-red-500');
                errorMessage.textContent = '';
                errorMessage.classList.add('hidden');
            }

            updateEquipmentAvailabilityBadge(row);
            updateFormSummary();
        };

        checkbox.addEventListener('change', updateAvailability);
        qtyInput.addEventListener('input', updateAvailability);
        qtyInput.addEventListener('change', updateAvailability);

        updateAvailability();
        fetchAvailabilityAndUpdate(row);
    });

    startDateInput?.addEventListener('change', function () {
        autoAdjustedEndDate = false;
        syncOvernightEndDate();
        clearTimeout(conflictCheckTimeout);
        conflictCheckTimeout = setTimeout(checkConflicts, 500);
        refreshAllEquipmentAvailability();
        saveDraft();
    });

    endDateInput?.addEventListener('change', function () {
        autoAdjustedEndDate = false;
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
        saveDraft();
    });

    syncOvernightEndDate();

    form.addEventListener('submit', function (e) {
        ensureCsrfToken();
        updateChecklistState();

        const clickedButton = document.activeElement && document.activeElement.matches('button[type="submit"]') ? document.activeElement : form.querySelector('button[type="submit"]');
        const requiredOk = validateRequiredFields();
        const dateTimeOk = validateDateTimeRange();
        const urgentScheduleOk = validateUrgentSchedules();
        const qtyOk = validateQuantities();
        const hasConflict = hasConflictBlocker;
        const checklistComplete = ['checklist-required-fields', 'checklist-venue-availability', 'checklist-document-upload', 'checklist-e-signature']
            .every(id => document.getElementById(id)?.checked === true);

        if (!requiredOk || !dateTimeOk || !urgentScheduleOk || !qtyOk || hasConflict || !checklistComplete) {
            e.preventDefault();
            if (!checklistComplete) {
                if (checklistWarning) {
                    checklistWarning.textContent = 'Please complete all checklist items before submitting this request.';
                    checklistWarning.classList.remove('hidden');
                    checklistWarning.scrollIntoView({ block: 'center', behavior: 'smooth' });
                }
                resetSubmitButtonLoading(clickedButton);
            }
            if (!requiredOk || !dateTimeOk || !urgentScheduleOk) {
                const firstInvalid = form.querySelector(':invalid');
                firstInvalid?.focus();
                firstInvalid?.reportValidity();
            }
            return false;
        }

        if (clickedButton) {
            clickedButton.setAttribute('data-loading', '1');
            const loadingLabel = clickedButton.textContent?.toLowerCase().includes('save') ? 'Saving...' : 'Submitting...';
            setSubmitButtonLoading(clickedButton, loadingLabel);
        }

        clearDraftStorage();
        return true;
    });
};

window.initializeRequestForm = initializeRequestForm;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeRequestForm, { once: true });
} else {
    initializeRequestForm();
}
