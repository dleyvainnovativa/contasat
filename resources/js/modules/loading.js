/* loading.js — loading state helpers. Toggle .is-loading on any element, or
   swap a button's label for a spinner while an async action runs. */

export const loading = {
    on(el)  { if (el) el.classList.add('is-loading'); },
    off(el) { if (el) el.classList.remove('is-loading'); },

    // Wrap a button during an async fn; restores its label afterward.
    async button(btn, fn) {
        if (!btn) return fn();
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-ring"></span>';
        try {
            return await fn();
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    },
};
