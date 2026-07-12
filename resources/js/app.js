/* ==========================================================================
   ContaSAT — app.js
   Central JS entry. Assembles the window.App namespace from focused modules
   (http, toast, loading, modal, forms, reveal, theme, sidebar). Vanilla JS only.
   Prefer these helpers over inline scripts across all views.
   ========================================================================== */

import { http } from './modules/http.js';
import { toast } from './modules/toast.js';
import { loading } from './modules/loading.js';
import { modal } from './modules/modal.js';
import { forms } from './modules/forms.js';
import { reveal } from './modules/reveal.js';
import { theme } from './modules/theme.js';
import { sidebar } from './modules/sidebar.js';

const App = { http, toast, loading, modal, forms, reveal, theme, sidebar };

// Expose globally so Blade views and inline handlers can call App.*
window.App = App;

document.addEventListener('DOMContentLoaded', () => {
    theme.init();      // toggle wiring (initial theme is set by the inline head script)
    sidebar.init();    // mobile off-canvas navigation
    reveal.init();     // progressive reveal for [data-reveal] elements
    forms.initAjax();  // wire up [data-ajax] forms
    flushServerToasts(); // show any toast queued server-side (session flash)
});

// Server-side flashed toast: <meta name="toast" data-type=".." content="..">
function flushServerToasts() {
    document.querySelectorAll('meta[name="toast"]').forEach((m) => {
        toast.show(m.getAttribute('content'), m.dataset.type || 'info');
    });
}

export default App;