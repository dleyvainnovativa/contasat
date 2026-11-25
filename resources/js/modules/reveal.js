/* reveal.js — progressive appearance for [data-reveal] elements via
   IntersectionObserver. Respects prefers-reduced-motion (handled in CSS). */

export const reveal = {
    init(root = document) {
        const items = root.querySelectorAll('[data-reveal]');
        if (!items.length) return;

        if (!('IntersectionObserver' in window)) {
            items.forEach((el) => el.classList.add('revealed'));
            return;
        }

        const io = new IntersectionObserver((entries, obs) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const delay = entry.target.dataset.revealDelay || 0;
                    setTimeout(() => entry.target.classList.add('revealed'), delay);
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0 });
        // threshold 0 = reveal as soon as any pixel enters the viewport. A positive
        // threshold breaks for elements taller than the viewport (e.g. a 500-row
        // table): their visible fraction can never reach it, so they'd stay hidden.

        items.forEach((el) => io.observe(el));

        // Safety net: never leave content permanently hidden. If an element still
        // hasn't revealed shortly after load (odd layout/observer edge cases),
        // reveal it anyway.
        setTimeout(() => {
            items.forEach((el) => {
                if (!el.classList.contains('revealed')) {
                    const r = el.getBoundingClientRect();
                    if (r.top < window.innerHeight && r.bottom > 0) {
                        el.classList.add('revealed');
                    }
                }
            });
        }, 1200);
    },
};
