// Restore visibility of the server-selected category after full-page navigation.
// Only move the horizontal strip: scrollIntoView would also move the page.
export function revealSelectedCategory(strip) {
    const active = strip.querySelector('[aria-current="page"]');
    if (!active || strip.clientWidth <= 0) return;
    const outer = strip.getBoundingClientRect();
    const selected = active.getBoundingClientRect();
    if (selected.left >= outer.left && selected.right <= outer.left + strip.clientWidth) return;
    const target = strip.scrollLeft + selected.left - outer.left
        - (strip.clientWidth - selected.width) / 2;
    strip.scrollLeft = Math.max(0, Math.min(target, strip.scrollWidth - strip.clientWidth));
}

if (typeof document !== 'undefined') {
    const restore = () => requestAnimationFrame(() => {
        document.querySelectorAll('[data-category-strip]').forEach(revealSelectedCategory);
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', restore, { once: true });
    else restore();
    window.addEventListener('pageshow', restore);
}
