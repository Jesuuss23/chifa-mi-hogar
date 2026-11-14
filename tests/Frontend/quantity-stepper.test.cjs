const test = require('node:test');
const assert = require('node:assert/strict');
const load = () => import('../../resources/js/quantity-stepper.js');
function setup(value = '1') {
    const events = [];
    const input = { value, min: '1', max: '20', dispatchEvent(e) { events.push(e.type); } };
    const output = {}, minus = {}, plus = {};
    const nodes = { '[data-quantity-input]': input, output, '[data-quantity-step="-1"]': minus, '[data-quantity-step="1"]': plus };
    return { input, output, minus, plus, events, root: { querySelector(s) { return nodes[s]; } } };
}
test('increments and emits events for existing totals and form payload', async () => {
    const { stepQuantity } = await load(); const x = setup(); stepQuantity(x.root, 1);
    assert.equal(x.input.value, '2'); assert.equal(x.output.textContent, '2');
    assert.deepEqual(x.events, ['input', 'change']); assert.equal(x.minus.disabled, false);
});
test('minimum and maximum disable their respective controls', async () => {
    const { stepQuantity } = await load(); const x = setup(); stepQuantity(x.root, -1);
    assert.equal(x.input.value, '1'); assert.equal(x.minus.disabled, true);
    x.input.value = '20'; stepQuantity(x.root, 1);
    assert.equal(x.input.value, '20'); assert.equal(x.plus.disabled, true);
    stepQuantity(x.root, -1); assert.equal(x.input.value, '19'); assert.equal(x.plus.disabled, false);
});
test('non-finite and fractional values cannot leak into quantity', async () => {
    const { stepQuantity } = await load(); const x = setup('NaN'); stepQuantity(x.root, 1);
    assert.equal(x.input.value, '2'); x.input.value = '2.7'; stepQuantity(x.root, 1);
    assert.equal(x.input.value, '3');
});
