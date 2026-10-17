(() => {
    function init() {
        const modal = document.getElementById('infrastructureManualModal');
        if (!modal) return;
        const form = modal.querySelector('[data-infrastructure-manual-form]');
        const submit = form.querySelector('[data-manual-submit]');
        const resetSubmit = () => { submit.disabled = false; submit.textContent = 'Simpan Site'; };
        form.addEventListener('submit', event => {
            if (submit.disabled) { event.preventDefault(); return; }
            submit.disabled = true;
            submit.textContent = 'Menyimpan…';
        });
        window.addEventListener('pageshow', resetSubmit);
        modal.addEventListener('shown.bs.modal', () => {
            (modal.querySelector('[data-manual-errors]') || form.querySelector('[name="site_code"]'))?.focus();
        });
        if (modal.dataset.reopen === 'true') bootstrap.Modal.getOrCreateInstance(modal).show();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
