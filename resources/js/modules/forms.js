/* forms.js — form serialization + AJAX submission. serialize() turns a <form>
   into a plain object; initAjax() wires up any <form data-ajax> to submit via
   http, show a toast, and redirect on success — no per-view boilerplate. */

import { http } from './http.js';
import { toast } from './toast.js';
import { loading } from './loading.js';

export const forms = {
    serialize(form) {
        const data = {};
        new FormData(form).forEach((value, key) => {
            if (key in data) {
                data[key] = [].concat(data[key], value);
            } else {
                data[key] = value;
            }
        });
        // Unchecked checkboxes report nothing; normalize known ones to booleans.
        form.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
            data[cb.name] = cb.checked;
        });
        return data;
    },

    initAjax(root = document) {
        root.querySelectorAll('form[data-ajax]').forEach((form) => {
            if (form.dataset.ajaxBound) return;
            form.dataset.ajaxBound = '1';

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const method = (form.dataset.method || form.method || 'POST').toUpperCase();
                const url = form.getAttribute('action');
                const submitBtn = form.querySelector('[type="submit"]');
                clearErrors(form);

                await loading.button(submitBtn, async () => {
                    try {
                        const body = this.serialize(form);
                        const fn = method === 'PUT' ? http.put : method === 'DELETE' ? http.del : http.post;
                        const res = await fn(url, body);
                        toast.success(form.dataset.success || 'Guardado.');
                        if (res && res.redirect) window.location.href = res.redirect;
                        else if (form.dataset.redirect) window.location.href = form.dataset.redirect;
                    } catch (err) {
                        if (err.status === 422 && err.data && err.data.errors) {
                            showErrors(form, err.data.errors);
                        } else {
                            toast.error(err.message || 'Ocurrió un error.');
                        }
                    }
                });
            });
        });
    },
};

function clearErrors(form) {
    form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    form.querySelectorAll('.field-error').forEach((el) => el.remove());
}

function showErrors(form, errors) {
    Object.entries(errors).forEach(([field, messages]) => {
        const input = form.querySelector(`[name="${field}"]`);
        if (!input) return;
        input.classList.add('is-invalid');
        const note = document.createElement('div');
        note.className = 'field-error form-hint';
        note.style.color = 'var(--danger)';
        note.textContent = Array.isArray(messages) ? messages[0] : messages;
        input.insertAdjacentElement('afterend', note);
    });
}
