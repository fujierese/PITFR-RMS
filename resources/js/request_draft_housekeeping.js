// A.4: request drafts belong to one account on one browser. They are cleared after
// a successful submission, on logout, and when a different account signs in here.
const DRAFT_KEY_PREFIX = 'pitfr-request-draft-user-';
const DRAFT_DB_PREFIX = 'pitfr-request-drafts-user-';

const removeDraftFor = function (ownerId) {
    if (!/^\d+$/.test(String(ownerId || ''))) {
        return;
    }
    try {
        localStorage.removeItem(DRAFT_KEY_PREFIX + ownerId);
        sessionStorage.removeItem(DRAFT_KEY_PREFIX + ownerId);
    } catch (error) {
        // Storage can be unavailable (private mode); nothing to clear then.
    }
    try {
        window.indexedDB?.deleteDatabase(DRAFT_DB_PREFIX + ownerId);
    } catch (error) {
        // Ignore: the attachment store is optional.
    }
};

const removeOtherAccountsDrafts = function (ownerId) {
    try {
        Object.keys(localStorage)
            .filter((key) => key.startsWith(DRAFT_KEY_PREFIX) && key !== DRAFT_KEY_PREFIX + ownerId)
            .forEach((key) => removeDraftFor(key.slice(DRAFT_KEY_PREFIX.length)));
    } catch (error) {
        // Storage unavailable.
    }
    if (typeof window.indexedDB?.databases === 'function') {
        window.indexedDB.databases().then((databases) => {
            databases
                .map((database) => database.name || '')
                .filter((name) => name.startsWith(DRAFT_DB_PREFIX) && name !== DRAFT_DB_PREFIX + ownerId)
                .forEach((name) => removeDraftFor(name.slice(DRAFT_DB_PREFIX.length)));
        }).catch(() => {});
    }
};

const runDraftHousekeeping = function () {
    const body = document.body;
    if (!body) {
        return;
    }
    const ownerId = body.dataset.draftOwner || '';

    if (ownerId) {
        removeOtherAccountsDrafts(ownerId);
    }
    if (ownerId && body.dataset.requestDraftSubmitted === '1') {
        removeDraftFor(ownerId);
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (form instanceof HTMLFormElement && /\/logout\/?$/.test(new URL(form.action, window.location.href).pathname)) {
            removeDraftFor(ownerId);
        }
    }, true);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runDraftHousekeeping, { once: true });
} else {
    runDraftHousekeeping();
}
