const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../resources/views/storefront/checkout.blade.php'), 'utf8');
const block = source.split('// BEGIN checkout feedback')[1].split('// END checkout feedback')[0];

function setup(withQuote = true) {
    const listeners = {}, links = [], nodes = {};
    const node = () => ({
        dataset: {}, hidden: false, textContent: '', focused: false,
        focus() { this.focused = true; }, scrollIntoView() {}, closest() { return null; },
    });
    nodes['checkout-errors'] = { ...node(), querySelectorAll() { return links; } };
    for (const id of ['checkout-quote-summary', 'checkout-quote-amounts', 'checkout-quote-title',
        'checkout-quote-notice', 'payment-section', 'customer_phone']) nodes[id] = node();
    if (!withQuote) delete nodes['checkout-quote-summary'];
    const link = {
        dataset: { checkoutErrorTarget: 'customer_phone' },
        addEventListener(type, handler) { this.click = handler; },
    };
    links.push(link);
    const context = {
        document: { getElementById: id => nodes[id] ?? null },
        form: { addEventListener(type, handler) { listeners[type] = handler; } },
    };
    vm.runInNewContext(block, context);
    return { nodes, listeners, link, context };
}

test('focuses error summary and linked field', () => {
    const x = setup();
    assert.equal(x.nodes['checkout-errors'].focused, true);
    x.link.click({ preventDefault() {} });
    assert.equal(x.nodes.customer_phone.focused, true);
});
test('contact edits do not invalidate quote', () => {
    const x = setup();
    x.listeners.input({ target: { name: 'customer_phone' } });
    assert.equal(x.nodes['checkout-quote-amounts'].hidden, false);
});
test('delivery edit hides previous amounts', () => {
    const x = setup();
    x.listeners.input({ target: { name: 'delivery_address' } });
    assert.equal(x.nodes['checkout-quote-amounts'].hidden, true);
    assert.equal(x.nodes['checkout-quote-summary'].dataset.stale, 'true');
});
test('payment selection invalidates quote', () => {
    const x = setup();
    x.listeners.change({ target: { name: 'payment_method' } });
    assert.equal(x.nodes['checkout-quote-amounts'].hidden, true);
});
test('editing without quote is harmless', () => {
    assert.doesNotThrow(() => setup(false).listeners.input({ target: { name: 'delivery_address' } }));
});
test('disabled payment error target falls back to payment section', () => {
    const x = setup();
    x.nodes.customer_phone.disabled = true;
    x.link.click({ preventDefault() {} });
    assert.equal(x.nodes['payment-section'].focused, true);
});
test('map invalidation is idempotent', () => {
    const x = setup();
    vm.runInNewContext('invalidateDisplayedQuote(); invalidateDisplayedQuote();', x.context);
    assert.equal(x.nodes['checkout-quote-amounts'].hidden, true);
    assert.match(x.nodes['checkout-quote-notice'].textContent, /Calcula nuevamente/);
    assert.match(source, /if \(!restoringLocation\) \{\s*markDeliveryEdited\(\);\s*invalidateDisplayedQuote\(\);/);
});
