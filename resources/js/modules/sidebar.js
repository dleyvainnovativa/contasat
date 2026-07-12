/* sidebar.js — mobile off-canvas navigation. The sidebar is always visible on
   desktop (CSS); on mobile it slides in over the content and needs a way out.
   Handles: toggle, backdrop tap, Escape, close-on-navigate, and body scroll lock.
   Also keeps aria-expanded in sync for screen readers (WCAG AA). */

const OPEN_CLASS = 'open';

export const sidebar = {
    el: null,
    backdrop: null,
    toggles: [],

    init() {
        this.el = document.getElementById('sidebar');
        if (!this.el) return;

        this.backdrop = document.getElementById('sidebar-backdrop');
        this.toggles = Array.from(document.querySelectorAll('[data-sidebar-toggle]'));

        this.toggles.forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggle();
            });
        });

        document.querySelectorAll('[data-sidebar-close]').forEach((btn) => {
            btn.addEventListener('click', () => this.close());
        });

        this.backdrop?.addEventListener('click', () => this.close());

        // Escape dismisses, matching every other overlay in the app.
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen()) this.close();
        });

        // Navigating away should not leave the sidebar open behind the new page.
        this.el.querySelectorAll('a[href]').forEach((link) => {
            link.addEventListener('click', () => this.close());
        });

        // Returning to desktop width clears any mobile state.
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 992 && this.isOpen()) this.close();
        });
    },

    isOpen() {
        return this.el?.classList.contains(OPEN_CLASS) ?? false;
    },

    open() {
        this.el.classList.add(OPEN_CLASS);
        this.backdrop?.classList.add(OPEN_CLASS);
        document.body.style.overflow = 'hidden'; // stop the page scrolling behind
        this.toggles.forEach((b) => b.setAttribute('aria-expanded', 'true'));
    },

    close() {
        this.el.classList.remove(OPEN_CLASS);
        this.backdrop?.classList.remove(OPEN_CLASS);
        document.body.style.overflow = '';
        this.toggles.forEach((b) => b.setAttribute('aria-expanded', 'false'));
    },

    toggle() {
        this.isOpen() ? this.close() : this.open();
    },
};