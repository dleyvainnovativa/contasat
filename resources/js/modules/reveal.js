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
        }, { threshold: 0.08 });

        items.forEach((el) => io.observe(el));
    },
};
