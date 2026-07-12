/* toast.js — non-blocking notifications. App.toast.show(msg, type). Types map to
   the .t-* classes in theme.css: success | danger | warning | info. */

const ICONS = {
    success: 'fa-circle-check',
    danger:  'fa-circle-exclamation',
    warning: 'fa-triangle-exclamation',
    info:    'fa-circle-info',
};

function stack() {
    let el = document.querySelector('.toast-stack');
    if (!el) {
        el = document.createElement('div');
        el.className = 'toast-stack';
        document.body.appendChild(el);
    }
    return el;
}

export const toast = {
    show(message, type = 'info', timeout = 4000) {
        const item = document.createElement('div');
        item.className = `toast-item t-${type}`;
        item.setAttribute('role', 'status');
        item.innerHTML = `
            <span class="toast-item__icon"><i class="fa-solid ${ICONS[type] || ICONS.info}"></i></span>
            <span class="toast-item__msg">${escapeHtml(message)}</span>`;
        stack().appendChild(item);

        const remove = () => {
            item.style.opacity = '0';
            item.style.transform = 'translateX(12px)';
            setTimeout(() => item.remove(), 200);
        };
        if (timeout) setTimeout(remove, timeout);
        item.addEventListener('click', remove);
    },
    success(m, t) { this.show(m, 'success', t); },
    error(m, t)   { this.show(m, 'danger', t); },
    warning(m, t) { this.show(m, 'warning', t); },
    info(m, t)    { this.show(m, 'info', t); },
};

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
}
