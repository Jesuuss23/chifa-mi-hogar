const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/storefront/partials/confirmation-polling.blade.php'), 'utf8');
const create = new Function(source + '\nreturn createConfirmationPolling;')();

function setup({ initial = 'pending', online = true, hidden = false, response, pending = false } = {}) {
    const timers = new Map(), events = {}, updates = [];
    let next = 0, calls = 0, resolveFetch, rejectFetch;
    const retry = { hidden: false, disabled: false, addEventListener(type, fn) { this.click = fn; } };
    const feedback = { textContent: '' };
    const document = { hidden, addEventListener(type, fn) { events[type] = fn; } };
    const navigator = { onLine: online };
    const defaultResponse = { ok: true, status: 200, json: async () => ({ status: 'preparing', payment_status: 'pending' }) };
    let currentResponse = response ?? defaultResponse;
    const env = {
        document, navigator,
        window: { addEventListener(type, fn) { events[type] = fn; }, location: { reload() {} } },
        fetch() {
            calls++;
            if (pending) return new Promise((resolve, reject) => { resolveFetch = resolve; rejectFetch = reject; });
            if (currentResponse instanceof Error) return Promise.reject(currentResponse);
            return Promise.resolve(currentResponse);
        },
        AbortController: class {
            constructor() { this.signal = {}; }
            abort() { if (rejectFetch) rejectFetch(new Error('aborted')); }
        },
        setTimeout(fn, ms) { timers.set(++next, { fn, ms }); return next; },
        clearTimeout(id) { timers.delete(id); },
    };
    const poll = create({ url: '/private-status', initialStatus: initial, apply: data => updates.push(data), feedback, retry, env });
    return { poll, timers, events, updates, retry, feedback, document, navigator,
        calls: () => calls, setResponse: value => currentResponse = value,
        resolve: value => resolveFetch(value), defaultResponse,
    };
}
async function flush() { for (let i = 0; i < 8; i++) await Promise.resolve(); }

test('success applies state and schedules one poll', async () => {
    const x = setup(); await flush();
    assert.equal(x.updates.length, 1);
    assert.equal(x.calls(), 1);
    assert.equal([...x.timers.values()][0].ms, 15000);
});
test('pending request prevents overlapping calls and times out', async () => {
    const x = setup({ pending: true });
    await x.poll.refresh(); assert.equal(x.calls(), 1);
    [...x.timers.values()][0].fn(); await flush();
    assert.equal(x.updates.length, 0);
    assert.equal([...x.timers.values()][0].ms, 30000);
    assert.equal(x.retry.disabled, false);
});
test('network error preserves state and recovers manually', async () => {
    const x = setup({ response: new Error('network') }); await flush();
    assert.equal(x.updates.length, 0);
    assert.match(x.feedback.textContent, /último estado/);
    x.setResponse(x.defaultResponse); await x.poll.refresh();
    assert.equal(x.updates.length, 1);
    assert.equal([...x.timers.values()][0].ms, 15000);
});
test('malformed data does not replace valid state', async () => {
    const x = setup({ response: { ok: true, status: 200, json: async () => ({ status: 'fake', payment_status: 'verified' }) } });
    await flush(); assert.equal(x.updates.length, 0);
    assert.equal([...x.timers.values()][0].ms, 30000);
});
test('delivered stops polling', async () => {
    const x = setup({ response: { ok: true, status: 200, json: async () => ({ status: 'delivered', payment_status: 'verified' }) } });
    await flush(); await x.poll.refresh();
    assert.equal(x.calls(), 1); assert.equal(x.timers.size, 0); assert.equal(x.retry.hidden, true);
});
test('initial cancelled order makes no request', async () => {
    const x = setup({ initial: 'cancelled' }); await flush();
    assert.equal(x.calls(), 0); assert.equal(x.timers.size, 0);
});
test('missing private link stops retries', async () => {
    const x = setup({ response: { ok: false, status: 404 } }); await flush();
    await x.poll.refresh(); assert.equal(x.calls(), 1); assert.equal(x.retry.hidden, true);
});
test('offline pauses then online resumes', async () => {
    const x = setup({ online: false }); assert.equal(x.calls(), 0);
    x.navigator.onLine = true; x.events.online(); await flush();
    assert.equal(x.updates.length, 1);
});
test('hidden page pauses then visible resumes', async () => {
    const x = setup({ hidden: true }); assert.equal(x.calls(), 0);
    x.document.hidden = false; x.events.visibilitychange(); await flush();
    assert.equal(x.calls(), 1);
    x.document.hidden = true; x.events.visibilitychange();
    assert.equal(x.timers.size, 0);
});
test('late response after navigation cannot update page', async () => {
    const x = setup({ pending: true }); x.poll.stop();
    x.resolve(x.defaultResponse); await flush();
    assert.equal(x.updates.length, 0); assert.equal(x.timers.size, 0);
});
test('rate limiting backs off to sixty seconds without losing state', async () => {
    const x = setup({ response: { ok: false, status: 429 } }); await flush();
    assert.equal([...x.timers.values()][0].ms, 30000);
    await x.poll.refresh();
    assert.equal([...x.timers.values()][0].ms, 60000);
    assert.equal(x.updates.length, 0);
});
