export function initializePromotionalBanners(root = document) {
    root.querySelectorAll('[data-promo-carousel]').forEach(carousel => {
        const track = carousel.querySelector('[data-promo-track]');
        const slides = [...carousel.querySelectorAll('[data-promo-slide]')];
        const controls = carousel.querySelector('[data-promo-controls]');
        if (!track || slides.length < 2 || !controls || carousel.dataset.ready) return;
        carousel.dataset.ready = 'true';
        const previous = carousel.querySelector('[data-promo-prev]');
        const next = carousel.querySelector('[data-promo-next]');
        const dots = [...carousel.querySelectorAll('[data-promo-index]')];
        const status = carousel.querySelector('[data-promo-status]');
        let index = 0;
        const move = target => {
            index = Math.max(0, Math.min(slides.length - 1, target));
            track.scrollTo({ left: index * track.clientWidth,
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
        };
        const refresh = () => {
            index = Math.max(0, Math.min(slides.length - 1, Math.round(track.scrollLeft / (track.clientWidth || 1))));
            previous.disabled = index === 0;
            next.disabled = index === slides.length - 1;
            dots.forEach((dot, position) => {
                if (position === index) dot.setAttribute('aria-current', 'true');
                else dot.removeAttribute('aria-current');
            });
            status.textContent = `Promoción ${index + 1} de ${slides.length}`;
        };
        previous.addEventListener('click', () => move(index - 1));
        next.addEventListener('click', () => move(index + 1));
        dots.forEach((dot, position) => dot.addEventListener('click', () => move(position)));
        track.addEventListener('scroll', refresh, { passive: true });
        track.addEventListener('keydown', event => {
            if (event.target !== track || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            event.preventDefault();
            move(index + (event.key === 'ArrowRight' ? 1 : -1));
        });
        // Tab navigation to an off-screen promotion must reveal the focused link.
        slides.forEach((slide, position) => slide.addEventListener('focus', () => move(position)));
        const resize = () => { track.scrollTo({ left: index * track.clientWidth, behavior: 'instant' }); refresh(); };
        if ('ResizeObserver' in window) new ResizeObserver(resize).observe(track);
        else window.addEventListener('resize', resize);
        controls.hidden = false;
        refresh();
    });
}

function initializeBannerForms() {
    document.querySelectorAll('[data-banner-form]').forEach(form => {
        const type = form.querySelector('[data-banner-target]');
        const refresh = () => form.querySelectorAll('[data-banner-destination]').forEach(panel => {
            const selected = panel.dataset.bannerDestination === type.value;
            panel.hidden = !selected;
            panel.querySelector('select').disabled = !selected;
            panel.querySelector('select').required = selected;
        });
        type.addEventListener('change', refresh);
        refresh();
    });
}

const initialize = () => { initializePromotionalBanners(); initializeBannerForms(); };
if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
}
