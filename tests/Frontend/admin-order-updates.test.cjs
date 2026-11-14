const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../resources/js/admin-order-updates.js'), 'utf8')
    .replaceAll('export function', 'function');
const context = { URL };
vm.runInNewContext(source, context);

function setup({ changed = true, status = 200, hidden = false, networkError = false, focused = false } = {}) {
    const documentEvents = {};
    const windowEvents = {};
    const timers = new Map();
    let id = 0;
    const state = { reloads: 0, requests: 0, aborted: false };
    const initial = [{ id: 1, status: 'on_the_way', payment_status: 'pending' }];
    const notice = { hidden: true };
    const root = {
        dataset: { url: '/admin/orders/updates', orders: JSON.stringify(initial) },
        querySelector: () => notice,
    };
    const env = {
        document: {
            hidden, activeElement: { closest: () => focused ? {} : null },
            addEventListener: (name, fn) => { documentEvents[name] = fn; },
        },
        window: {
            location: { href: 'https://example.test/admin/orders', reload: () => state.reloads++ },
            addEventListener: (name, fn) => { windowEvents[name] = fn; },
        },
        fetch: async (url, options) => {
            state.options = options;
            state.requests++;
            if (networkError) throw new Error('offline');
            return { ok: status === 200, status, redirected: false,
                json: async () => ({ orders: changed ? [{ id: 1, status: 'delivered', payment_status: 'verified' }] : initial }) };
        },
        setTimeout: (fn, ms) => { timers.set(++id, { fn, ms }); return id; },
        clearTimeout: timer => timers.delete(timer),
        AbortController: class { signal = {}; abort() { state.aborted = true; } },
    };
    context.startOrderUpdates(root, env);
    return {
        state, notice, documentEvents, windowEvents, timers, env,
        async tick() {
            const next = [...timers].find(([, task]) => task.ms === 10000);
            assert.ok(next);
            timers.delete(next[0]);
            await next[1].fn();
        },
    };
}

test('changed delivery reloads an idle admin page', async () => {
    const h = setup();
    await h.tick();
    assert.equal(h.state.reloads, 1);
    assert.equal(h.timers.size, 0);
});

test('unchanged orders never reload', async () => {
    const h = setup({ changed: false });
    await h.tick();
    assert.equal(h.state.reloads, 0);
    assert.equal(h.timers.size, 1);
});

test('poll is marked as AJAX so it does not replace the form return URL', async () => {
    const h = setup({ changed: false });
    await h.tick();
    assert.equal(h.state.options.headers['X-Requested-With'], 'XMLHttpRequest');
    assert.equal(h.state.options.headers.Accept, 'application/json');
});

test('dirty or focused forms keep values and show a refresh notice', async () => {
    for (const dirty of [true, false]) {
        const h = setup({ focused: !dirty });
        if (dirty) h.documentEvents.input({ target: { closest: () => ({}) } });
        await h.tick();
        assert.equal(h.state.reloads, 0);
        assert.equal(h.notice.hidden, false);
    }
});

test('background tabs do not send requests', async () => {
    const h = setup({ hidden: true });
    await h.tick();
    assert.equal(h.state.requests, 0);
    assert.equal(h.timers.size, 1);
});

test('temporary network failure retries without reload', async () => {
    const h = setup({ networkError: true });
    await h.tick();
    assert.equal(h.state.reloads, 0);
    assert.equal(h.timers.size, 1);
});

test('expired authorization stops polling', async () => {
    for (const status of [401, 403]) {
        const h = setup({ status });
        await h.tick();
        assert.equal(h.timers.size, 0);
        assert.equal(h.state.reloads, 0);
    }
});

test('submit and pagehide stop pending timers; bfcache resumes polling', () => {
    const h = setup();
    h.documentEvents.submit();
    assert.equal(h.timers.size, 0);
    h.windowEvents.pageshow({ persisted: true });
    assert.equal(h.timers.size, 1);
    h.windowEvents.pagehide();
    assert.equal(h.timers.size, 0);
});

test('order comparison ignores GPS changes and response ordering', () => {
    const before = [{ id: 1, status: 'on_the_way', payment_status: 'verified' }, { id: 2, status: 'pending', payment_status: 'pending' }];
    assert.equal(context.ordersChanged(before, [...before].reverse()), false);
    assert.equal(context.ordersChanged(before, before.map(order => ({ ...order, driver_latitude: -12 }))), false);
    assert.equal(context.ordersChanged(before, []), true);
    assert.equal(context.ordersChanged(before, undefined), false);
});
