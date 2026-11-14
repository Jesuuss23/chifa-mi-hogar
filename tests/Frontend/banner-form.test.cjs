const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('resources/js/promotional-banners.js', 'utf8').replace('export function', 'function');

function form(initial) {
    const type = { value: initial, addEventListener(event, callback) { this.change = callback; } };
    const panels = ['product', 'combo'].map(value => ({
        dataset: { bannerDestination: value }, select: {},
        querySelector() { return this.select; },
    }));
    const form = { querySelector() { return type; }, querySelectorAll() { return panels; } };
    const document = { readyState: 'complete', querySelectorAll(selector) { return selector === '[data-banner-form]' ? [form] : []; } };
    vm.runInNewContext(source, { document });
    return { type, panels };
}

test('only the chosen destination is visible, enabled and required, including repeated switches', () => {
    const { type, panels } = form('product');
    for (const value of ['product', 'combo', 'product', 'combo']) {
        type.value = value;
        type.change();
        for (const panel of panels) {
            const active = panel.dataset.bannerDestination === value;
            assert.equal(panel.hidden, !active);
            assert.equal(panel.select.disabled, !active);
            assert.equal(panel.select.required, active);
        }
    }
});

test('editing a combo initializes the matching destination immediately', () => {
    const { panels } = form('combo');
    assert.equal(panels[0].hidden, true);
    assert.equal(panels[1].hidden, false);
    assert.equal(panels[0].select.disabled, true);
});
