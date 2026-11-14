const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('resources/js/quick-sales.js', 'utf8').replaceAll('export function', 'function');
const context = {};
vm.runInNewContext(source, context);

test('totals use integer cents and independent extra quantities', () => {
    const catalog = [{type: 'product', id: 1, price_cents: 1250}, {type: 'extra', id: 1, price_cents: 350}];
    assert.equal(context.saleSubtotal([{type: 'product', id: 1, quantity: 2}, {type: 'extra', id: 1, quantity: 2}], catalog), 3200);
    assert.equal(context.saleSubtotal([{type: 'extra', id: 1, quantity: 3}], catalog), 1050);
});

test('adding existing selection increments quantity without merging product and extra ids', () => {
    const lines = [];
    context.addSaleItem(lines, {type: 'product', id: 1});
    context.addSaleItem(lines, {type: 'product', id: 1});
    context.addSaleItem(lines, {type: 'extra', id: 1});
    assert.equal(lines.length, 2);
    assert.equal(lines[0].quantity, 2);
    assert.equal(lines[1].quantity, 1);
});

test('quantity and number of lines are bounded', () => {
    const lines = Array.from({length: 50}, (_, i) => ({type: 'product', id: i, quantity: 99}));
    context.addSaleItem(lines, {type: 'product', id: 0});
    context.addSaleItem(lines, {type: 'extra', id: 99});
    assert.equal(lines[0].quantity, 99);
    assert.equal(lines.length, 50);
});

test('combo quantities use combo price and remain separate from products with the same id', () => {
    const lines = [];
    context.addSaleItem(lines, {type: 'product', id: 1});
    context.addSaleItem(lines, {type: 'combo', id: 1});
    context.addSaleItem(lines, {type: 'combo', id: 1});
    assert.equal(lines.length, 2);
    assert.equal(lines[1].quantity, 2);
    assert.equal(context.saleSubtotal(lines, [{type: 'product', id: 1, price_cents: 1250}, {type: 'combo', id: 1, price_cents: 2299}]), 5848);
});

function interfaceSetup() {
    class Node {
        constructor() { this.children = []; this.listeners = {}; this.attributes = {}; this.dataset = {}; this.value = ''; }
        append(...nodes) { this.children.push(...nodes); }
        replaceChildren(...nodes) { this.children = nodes; }
        setAttribute(name, value) { this.attributes[name] = value; }
        addEventListener(name, fn) { this.listeners[name] = fn; }
        focus() {}
    }
    const nodes = Object.fromEntries(['catalog', 'initial', 'lines', 'choice', 'total', 'save', 'message', 'subtotal', 'empty', 'add', 'reset'].map(name => [`[data-sale-${name}]`, new Node()]));
    nodes['[data-sale-catalog]'].textContent = JSON.stringify([{type: 'product', id: 1, name: '<script>name</script>', price_cents: 1250}, {type: 'extra', id: 1, name: 'Gaseosa', price_cents: 350}]);
    nodes['[data-sale-initial]'].textContent = '[]';
    const form = new Node();
    form.querySelector = selector => nodes[selector];
    const document = { readyState: 'loading', addEventListener() {}, createElement: () => new Node(), querySelectorAll: () => [form] };
    const window = { addEventListener(name, callback) { this[name] = callback; } };
    const navigator = { onLine: true };
    const runtime = {document, window, navigator};
    vm.runInNewContext(source, runtime);
    runtime.initializeQuickSales(document);
    const add = value => { nodes['[data-sale-choice]'].value = value; nodes['[data-sale-add]'].listeners.click(); };
    const submit = () => { let prevented = false; form.listeners.submit({preventDefault() { prevented = true; }}); return prevented; };
    return { nodes, add, submit, navigator, window };
}

test('manual total survives changes and can be restored to calculated total', () => {
    const x = interfaceSetup();
    x.add('product:1');
    assert.equal(x.nodes['[data-sale-total]'].value, '12.50');
    x.nodes['[data-sale-total]'].value = '10.00';
    x.nodes['[data-sale-total]'].listeners.input();
    x.add('extra:1');
    assert.equal(x.nodes['[data-sale-total]'].value, '10.00');
    x.nodes['[data-sale-reset]'].listeners.click();
    assert.equal(x.nodes['[data-sale-total]'].value, '16.00');
    assert.equal(x.nodes['[data-sale-lines]'].children[0].children[0].textContent, '<script>name</script>');
});

test('offline keeps form available and online retry submits only once', () => {
    const x = interfaceSetup(); x.add('product:1');
    x.navigator.onLine = false;
    assert.equal(x.submit(), true);
    assert.equal(x.nodes['[data-sale-save]'].disabled, false);
    assert.match(x.nodes['[data-sale-message]'].textContent, /Sin conexión/);
    x.navigator.onLine = true;
    assert.equal(x.submit(), false);
    assert.equal(x.nodes['[data-sale-save]'].disabled, true);
    assert.equal(x.submit(), true);
    x.window.pageshow();
    assert.equal(x.nodes['[data-sale-save]'].disabled, false);
});

test('empty sale cannot submit', () => {
    const x = interfaceSetup();
    assert.equal(x.nodes['[data-sale-save]'].disabled, true);
    assert.equal(x.submit(), true);
});
