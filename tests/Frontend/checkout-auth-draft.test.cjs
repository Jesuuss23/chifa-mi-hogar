const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../resources/views/storefront/checkout.blade.php'), 'utf8');
const body = source.match(/form\.addEventListener\('submit', event => \{([\s\S]*?)\n    \}\);/)[1];

function submit({ auth = false, location = true, change = false, busy = false } = {}) {
    let prevented = false;
    const button = { dataset: auth ? { checkoutAuth: 'true' } : {}, disabled: false, id: 'confirm-button' };
    const context = {
        submitting: busy, confirmedLocation: location,
        currentPayment: () => 'cash',
        cashType: { value: change ? 'change' : 'exact' },
        cashAmount: { value: '', focus() {} },
        locationStatus: { scrollIntoView() {} },
        event: { submitter: button, preventDefault() { prevented = true; } },
    };
    vm.runInNewContext('(function(event) {' + body + '})(event)', context);
    return { prevented, button, submitting: context.submitting };
}

test('draft allows incomplete location/payment and retains native submitter', () => {
    const result = submit({ auth: true, location: false, change: true });
    assert.equal(result.prevented, false);
    assert.equal(result.submitting, true);
    assert.equal(result.button.disabled, false);
});
test('normal submission still requires location', () => {
    assert.equal(submit({ location: false }).prevented, true);
});
test('normal cash change still requires amount', () => {
    assert.equal(submit({ change: true }).prevented, true);
});
test('valid order disables the submit button', () => {
    const result = submit();
    assert.equal(result.prevented, false);
    assert.equal(result.button.disabled, true);
});
test('duplicate draft submission is blocked', () => {
    assert.equal(submit({ auth: true, busy: true }).prevented, true);
});
