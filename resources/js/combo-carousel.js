function initializeComboCarousels() {
    document.querySelectorAll('[data-combo-carousel]').forEach(carousel => {
        const viewport = carousel.querySelector('[data-combo-viewport]');
        const controls = carousel.querySelector('[data-combo-controls]');
        const previous = carousel.querySelector('[data-combo-prev]');
        const next = carousel.querySelector('[data-combo-next]');
        if (!viewport || !controls || !previous || !next) return;

        const updateControls = () => {
            const maximum = Math.max(0, viewport.scrollWidth - viewport.clientWidth);
            controls.hidden = maximum <= 2;
            previous.disabled = viewport.scrollLeft <= 2;
            next.disabled = viewport.scrollLeft >= maximum - 2;
        };
        const move = direction => {
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            viewport.scrollBy({
                left: direction * viewport.clientWidth * 0.9,
                behavior: reducedMotion ? 'auto' : 'smooth',
            });
        };

        previous.addEventListener('click', () => move(-1));
        next.addEventListener('click', () => move(1));
        viewport.addEventListener('scroll', updateControls, { passive: true });
        // Links retain their normal keyboard behavior; arrows act only on the track.
        viewport.addEventListener('keydown', event => {
            if (event.target !== viewport || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            event.preventDefault();
            move(event.key === 'ArrowRight' ? 1 : -1);
        });

        if ('ResizeObserver' in window) {
            const observer = new window.ResizeObserver(updateControls);
            observer.observe(viewport);
        } else {
            window.addEventListener('resize', updateControls);
        }
        updateControls();
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeComboCarousels, { once: true });
} else {
    initializeComboCarousels();
}
