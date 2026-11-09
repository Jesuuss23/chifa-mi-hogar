const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../resources/js/customer-interface.js'), 'utf8')
    .replaceAll('export function', 'function');
const context = {};
vm.runInNewContext(source, context);

function field(strength = true) {
    const events = {}, inputEvents = {}, formEvents = {}, toggleEvents = {};
    const attributes = { 'aria-label': 'Mostrar contraseña' };
    const input = {
        type: 'password', value: '', form: { addEventListener: (n, cb) => { formEvents[n] = cb; } },
        hasAttribute: () => strength, addEventListener: (n, cb) => { inputEvents[n] = cb; },
    };
    const toggle = {
        hidden: true, textContent: 'Ver',
        getAttribute: n => attributes[n], setAttribute: (n, value) => { attributes[n] = value; },
        addEventListener: (n, cb) => { toggleEvents[n] = cb; },
    };
    const feedback = { hidden: true };
    const meter = { value: 0, setAttribute() {} };
    const label = { textContent: '' };
    const elements = { input, '[data-password-toggle]': toggle, '[data-password-feedback]': feedback,
        '[data-password-meter]': meter, '[data-password-label]': label };
    const root = { querySelector: n => elements[n], contains: target => target === input || target === toggle,
        addEventListener: (n, cb) => { events[n] = cb; } };
    context.initPasswordField(root);
    return { input, toggle, feedback, meter, label, events, inputEvents, formEvents, toggleEvents, attributes };
}

test('empty, too short and repeated passwords have no misleading strong score', () => {
    assert.equal(context.passwordEstimate('').score, 0);
    assert.equal(context.passwordEstimate('Abc123!').score, 0);
    assert.equal(context.passwordEstimate('aaaaaaaaaaaaaaaaaaaaaaaa').score, 1);
    assert.equal(context.passwordEstimate('password1234567890').score, 1);
});

test('long passphrases do not require arbitrary symbol combinations', () => {
    assert.equal(context.passwordEstimate('sol luna mar bosque cielo').score, 4);
    assert.equal(context.passwordEstimate('ochoabcd').score, 1);
});

test('strength appears only after typing and resets when empty', () => {
    const h = field();
    assert.equal(h.feedback.hidden, true);
    h.input.value = 'sol luna mar bosque cielo';
    h.inputEvents.input();
    assert.equal(h.feedback.hidden, false);
    assert.equal(h.meter.value, 4);
    h.input.value = '';
    h.inputEvents.input();
    assert.equal(h.feedback.hidden, true);
    assert.equal(h.meter.value, 0);
});

test('login has reveal control without strength validation', () => {
    const h = field(false);
    assert.equal(h.toggle.hidden, false);
    assert.equal(h.inputEvents.input, undefined);
    assert.equal(h.feedback.hidden, true);
});

test('reveal does not alter password and hides on submit or leaving field', () => {
    const h = field();
    h.input.value = 'keep my exact password';
    h.toggleEvents.click();
    assert.equal(h.input.type, 'text');
    assert.equal(h.attributes['aria-pressed'], 'true');
    assert.equal(h.toggle.textContent, 'Ocultar');
    h.events.focusout({ relatedTarget: h.toggle });
    assert.equal(h.input.type, 'text');
    h.events.focusout({ relatedTarget: null });
    assert.equal(h.input.type, 'password');
    h.toggleEvents.click();
    h.formEvents.submit();
    assert.equal(h.input.type, 'password');
    assert.equal(h.input.value, 'keep my exact password');
    assert.equal(h.attributes['aria-pressed'], 'false');
});

test('menu closes on Escape and returns focus to summary', () => {
    const events = {}, documentEvents = {};
    let focused = false;
    const menu = { open: true, querySelector: () => ({ focus: () => { focused = true; } }),
        querySelectorAll: () => [], contains: () => false,
        addEventListener: (n, cb) => { events[n] = cb; } };
    context.initCustomerMenu(menu, { addEventListener: (n, cb) => { documentEvents[n] = cb; } });
    events.keydown({ key: 'Escape' });
    assert.equal(menu.open, false);
    assert.equal(focused, true);
    menu.open = true;
    documentEvents.click({ target: {} });
    assert.equal(menu.open, false);
});

test('menu keeps internal focus and closes when keyboard focus leaves', () => {
    const events = {};
    const inside = {};
    const menu = { open: true, querySelector: () => ({ focus() {} }), querySelectorAll: () => [],
        contains: target => target === inside, addEventListener: (n, cb) => { events[n] = cb; } };
    context.initCustomerMenu(menu, { addEventListener() {} });
    events.focusout({ relatedTarget: inside });
    assert.equal(menu.open, true);
    events.focusout({ relatedTarget: {} });
    assert.equal(menu.open, false);
});
