const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../resources/js/category-strip.js'), 'utf8')
    .replace('export function', 'function');

function strip({ left = 800, width = 140, scroll = 0, total = 1000, viewport = 320, active = true } = {}) {
    return {
        scrollLeft: scroll, scrollWidth: total, clientWidth: viewport,
        getBoundingClientRect: () => ({ left: 10 }),
        querySelector: () => active ? { getBoundingClientRect: () => ({ left: 10 + left, right: 10 + left + width, width }) } : null,
    };
}

test('late mobile category becomes visible without scrolling the document', () => {
    const context = {};
    vm.runInNewContext(source, context);
    const element = strip();
    context.revealSelectedCategory(element);
    assert.equal(element.scrollLeft, 680);
});

test('already visible category keeps the existing horizontal position', () => {
    const context = {};
    vm.runInNewContext(source, context);
    const element = strip({ left: 50, scroll: 400 });
    context.revealSelectedCategory(element);
    assert.equal(element.scrollLeft, 400);
});

test('first category, missing active link and desktop do not overscroll', () => {
    const context = {};
    vm.runInNewContext(source, context);
    for (const options of [{ left: -500, scroll: 500 }, { active: false }, { left: 800, viewport: 1200 }]) {
        const element = strip(options);
        context.revealSelectedCategory(element);
        assert.equal(element.scrollLeft, 0);
    }
});

test('page restoration schedules the same horizontal-only adjustment', () => {
    const element = strip();
    const events = {};
    const frames = [];
    vm.runInNewContext(source, {
        document: { readyState: 'complete', querySelectorAll: () => [element] },
        window: { addEventListener: (name, callback) => { events[name] = callback; } },
        requestAnimationFrame: callback => frames.push(callback),
    });
    assert.equal(frames.length, 1);
    frames.pop()();
    assert.equal(element.scrollLeft, 680);
    element.scrollLeft = 0;
    events.pageshow();
    frames.pop()();
    assert.equal(element.scrollLeft, 680);
});
