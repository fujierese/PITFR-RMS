document.addEventListener('DOMContentLoaded', function () {
    function getEyeIcon(isVisible) {
        if (isVisible) {
            return `
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
            `;
        }

        return `
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.269-2.943-9.543-7a10.05 10.05 0 012.293-3.926m2.946-2.947A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18" />
            </svg>
        `;
    }

    document.querySelectorAll('.password-toggle').forEach(function (button) {
        const targetSelector = button.dataset.passwordToggleTarget;
        if (!targetSelector) {
            return;
        }

        const targetInput = document.querySelector(targetSelector);
        if (!targetInput || (targetInput.type !== 'password' && targetInput.type !== 'text')) {
            return;
        }

        const syncIcon = function () {
            const isVisible = targetInput.type === 'text';
            button.innerHTML = getEyeIcon(isVisible);
            button.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
            button.setAttribute('title', isVisible ? 'Hide password' : 'Show password');
        };

        syncIcon();

        button.addEventListener('click', function () {
            const shouldShow = targetInput.type === 'password';
            targetInput.type = shouldShow ? 'text' : 'password';
            syncIcon();
        });
    });
});
