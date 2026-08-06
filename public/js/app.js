// ============================================================
// PIT Facility Request System — JS
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

    // Toggle "Others (specify)" text inputs
    document.querySelectorAll('input[type="checkbox"][data-other]').forEach(function (cb) {
        var targetId  = cb.getAttribute('data-other');
        var wrapId    = targetId + '-wrap';
        var wrap      = document.getElementById(wrapId);
        var input     = document.getElementById(targetId);

        function toggle() {
            if (wrap) wrap.style.display = cb.checked ? 'block' : 'none';
            if (input && !cb.checked) input.value = '';
        }

        cb.addEventListener('change', toggle);
        toggle(); // run on load in case of pre-fill
    });

    // Auto-open modal if present
    var modal = document.getElementById('review-modal');
    if (modal) {
        // Modal is always visible when rendered — just ensure scroll to it
        modal.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // Auto-dismiss alerts after 4s
    document.querySelectorAll('.alert-success').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity .5s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 500);
        }, 4000);
    });
});

// Show/hide quantity input when equipment checkbox is checked
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.equipment-checkbox').forEach(function (checkbox, index) {
        checkbox.addEventListener('change', function () {
            const wrap = document.getElementById('qty-wrap-' + index);
            if (wrap) {
                wrap.style.display = this.checked ? 'block' : 'none';
                if (!this.checked) {
                    wrap.querySelector('input').value = 1;
                }
            }
        });
    });

    // Others specify toggle
    document.querySelectorAll('[data-other]').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            const targetId = this.dataset.other + '-wrap';
            const wrap = document.getElementById(targetId);
            if (wrap) wrap.style.display = this.checked ? 'block' : 'none';
        });
    });
});