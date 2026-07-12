/* theme.js — light/dark toggle. The initial theme is applied by a blocking
   inline script in <head> (anti-FOUC); this module only handles toggling. */

const KEY = 'contasat-theme';

export const theme = {
    init() {
        // The inline head script already set [data-theme]. Just sync the icon
        // and bind the toggle.
        this.syncIcon(document.documentElement.getAttribute('data-theme'));

        document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
            btn.addEventListener('click', () => this.toggle());
        });
    },

    apply(mode) {
        document.documentElement.setAttribute('data-theme', mode);
        this.syncIcon(mode);
    },

    syncIcon(mode) {
        document.querySelectorAll('[data-theme-toggle] i').forEach((i) => {
            i.className = mode === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        });
    },

    toggle() {
        const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        localStorage.setItem(KEY, next);
        this.apply(next);
    },
};