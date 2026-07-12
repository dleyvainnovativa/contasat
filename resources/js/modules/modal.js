/* modal.js — thin wrapper over Bootstrap's Modal so views don't each re-implement
   show/hide/confirm. Requires Bootstrap's JS bundle (loaded in the layout). */

export const modal = {
    show(id) {
        const el = document.getElementById(id);
        if (el && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(el).show();
    },
    hide(id) {
        const el = document.getElementById(id);
        if (el && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(el)?.hide();
    },

    // Promise-based confirm dialog backed by a shared #confirm-modal in the layout.
    confirm({ title = 'Confirmar', message = '', confirmText = 'Confirmar', danger = false } = {}) {
        return new Promise((resolve) => {
            const el = document.getElementById('confirm-modal');
            if (!el) { resolve(window.confirm(message)); return; }

            el.querySelector('[data-confirm-title]').textContent = title;
            el.querySelector('[data-confirm-message]').textContent = message;
            const okBtn = el.querySelector('[data-confirm-ok]');
            okBtn.textContent = confirmText;
            okBtn.className = 'btn ' + (danger ? 'btn-danger' : 'btn-brand');

            const instance = window.bootstrap.Modal.getOrCreateInstance(el);
            const onOk = () => { cleanup(); resolve(true); instance.hide(); };
            const onHide = () => { cleanup(); resolve(false); };
            function cleanup() {
                okBtn.removeEventListener('click', onOk);
                el.removeEventListener('hidden.bs.modal', onHide);
            }
            okBtn.addEventListener('click', onOk);
            el.addEventListener('hidden.bs.modal', onHide, { once: true });
            instance.show();
        });
    },
};
