const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../resources/js/combo-carousel.js'), 'utf8');

function setup({ width = 300, total = 900, reduced = false, loading = false, empty = false, observer = true } = {}) {
    const viewport = {
        clientWidth: width, scrollWidth: total, scrollLeft: 0, listeners: {}, moves: [],
        addEventListener(name, callback) { this.listeners[name] = callback; },
        scrollBy(options) { this.moves.push(options); },
    };
    const button = () => ({
        disabled: false, listeners: {},
        addEventListener(name, callback) { this.listeners[name] = callback; },
    });
    const previous = button();
    const next = button();
    const controls = { hidden: true };
    const elements = {
        '[data-combo-viewport]': viewport,
        '[data-combo-prev]': previous,
        '[data-combo-next]': next,
        '[data-combo-controls]': controls,
    };
    const document = {
        readyState: loading ? 'loading' : 'complete', listeners: {},
        querySelectorAll() { return empty ? [] : [{ querySelector: selector => elements[selector] }]; },
        addEventListener(name, callback) { this.listeners[name] = callback; },
    };
    let resize;
    const window = {
        matchMedia: () => ({ matches: reduced }),
        addEventListener(name, callback) { if (name === 'resize') resize = callback; },
    };
    if (observer) {
        window.ResizeObserver = class {
            constructor(callback) { resize = callback; }
            observe() {}
        };
    }
    vm.runInNewContext(source, { document, window });
    return { viewport, previous, next, controls, document, resize: () => resize() };
}

test('overflow enables controls and scroll position disables the correct arrow', () => {
    const h = setup();
    assert.equal(h.controls.hidden, false);
    assert.equal(h.previous.disabled, true);
    assert.equal(h.next.disabled, false);
    h.viewport.scrollLeft = 600;
    h.viewport.listeners.scroll();
    assert.equal(h.previous.disabled, false);
    assert.equal(h.next.disabled, true);
});

test('arrows move manually and respect reduced motion', () => {
    const h = setup({ reduced: true });
    assert.equal(h.viewport.moves.length, 0);
    h.next.listeners.click();
    h.previous.listeners.click();
    assert.equal(h.viewport.moves[0].left, 270);
    assert.equal(h.viewport.moves[0].behavior, 'auto');
    assert.equal(h.viewport.moves[1].left, -270);
    const normal = setup();
    normal.next.listeners.click();
    assert.equal(normal.viewport.moves[0].behavior, 'smooth');
});

test('controls disappear when all cards fit and respond to resize', () => {
    const h = setup({ width: 900 });
    assert.equal(h.controls.hidden, true);
    h.viewport.clientWidth = 300;
    h.resize();
    assert.equal(h.controls.hidden, false);
});

test('keyboard arrows work on track but do not intercept focused card links', () => {
    const h = setup();
    let prevented = 0;
    h.viewport.listeners.keydown({ target: {}, key: 'ArrowRight', preventDefault() { prevented++; } });
    assert.equal(h.viewport.moves.length, 0);
    h.viewport.listeners.keydown({ target: h.viewport, key: 'ArrowRight', preventDefault() { prevented++; } });
    assert.equal(h.viewport.moves[0].left, 270);
    assert.equal(prevented, 1);
});

test('initializes after DOM readiness and supports resize fallback', () => {
    const h = setup({ loading: true, observer: false });
    assert.equal(h.controls.hidden, true);
    h.document.listeners.DOMContentLoaded();
    assert.equal(h.controls.hidden, false);
    h.viewport.clientWidth = 900;
    h.resize();
    assert.equal(h.controls.hidden, true);
});

test('pages without a carousel need no controls or observers', () => {
    const h = setup({ empty: true });
    assert.equal(h.controls.hidden, true);
    assert.equal(h.viewport.moves.length, 0);
});
