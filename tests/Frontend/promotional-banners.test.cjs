const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('resources/js/promotional-banners.js', 'utf8').replace('export function', 'function');
function setup({ count = 3, reduced = false } = {}) {
    const node = () => ({ dataset: {}, attributes: {}, listeners: {},
        addEventListener(type, fn) { this.listeners[type] = fn; },
        setAttribute(k, v) { this.attributes[k] = v; }, removeAttribute(k) { delete this.attributes[k]; } });
    const track = { ...node(), clientWidth: 320, scrollLeft: 0,
        scrollTo(args) { this.lastScroll = args; this.scrollLeft = args.left; this.listeners.scroll?.(); } };
    const slides = Array.from({ length: count }, node), dots = Array.from({ length: count }, node);
    const previous = node(), next = node(), controls = { hidden: true }, status = node();
    const single = { '[data-promo-track]': track, '[data-promo-controls]': count > 1 ? controls : null,
        '[data-promo-prev]': previous, '[data-promo-next]': next, '[data-promo-status]': status };
    const carousel = { ...node(), querySelector(s) { return single[s]; },
        querySelectorAll(s) { return s === '[data-promo-slide]' ? slides : dots; } };
    const doc = { readyState: 'complete', querySelectorAll(s) { return s === '[data-promo-carousel]' ? [carousel] : []; } };
    const win = { matchMedia: () => ({ matches: reduced }), addEventListener(type, fn) { this[type] = fn; } };
    vm.runInNewContext(source, { document: doc, window: win });
    return { track, slides, dots, previous, next, controls, status, win };
}
test('manual navigation updates active dot, announcement and boundary buttons', () => {
    const x = setup(); assert.equal(x.controls.hidden, false); assert.equal(x.previous.disabled, true);
    x.next.listeners.click(); assert.equal(x.track.lastScroll.left, 320);
    assert.equal(x.dots[1].attributes['aria-current'], 'true'); assert.equal(x.status.textContent, 'Promoción 2 de 3');
    x.dots[2].listeners.click(); assert.equal(x.next.disabled, true);
    x.previous.listeners.click(); assert.equal(x.next.disabled, false);
});
test('swipe position and resize preserve the current promotion', () => {
    const x = setup(); x.track.scrollLeft = 640; x.track.listeners.scroll();
    assert.equal(x.dots[2].attributes['aria-current'], 'true');
    x.track.clientWidth = 900; x.win.resize(); assert.equal(x.track.lastScroll.left, 1800);
});
test('keyboard respects reduced motion and does not intercept link keys', () => {
    const x = setup({ reduced: true }); let prevented = false;
    x.track.listeners.keydown({ target: x.track, key: 'ArrowRight', preventDefault() { prevented = true; } });
    assert.equal(prevented, true); assert.equal(x.track.lastScroll.behavior, 'auto');
    x.track.listeners.keydown({ target: x.slides[0], key: 'ArrowRight', preventDefault() { throw Error('intercepted link'); } });
    x.slides[2].listeners.focus(); assert.equal(x.track.lastScroll.left, 640);
});
test('single banner needs no carousel controls', () => {
    const x = setup({ count: 1 }); assert.equal(x.controls.hidden, true); assert.equal(x.track.lastScroll, undefined);
});
