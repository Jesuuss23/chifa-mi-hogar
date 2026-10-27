const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const blade = fs.readFileSync(path.join(
    __dirname, '../../resources/views/storefront/order-tracking.blade.php'
), 'utf8');
const flush = () => new Promise(resolve => setImmediate(resolve));
const ok = data => ({ ok: true, json: async () => data });
const status = (value, payment = 'pending') => ok({ status: value, payment_status: payment });
const noGps = () => ok({ gps_fresh: false, gps_active: false, driver: null });

function deferred() {
    let resolve;
    const promise = new Promise(done => { resolve = done; });
    return { promise, resolve };
}

function page({ initial = 'pending', hidden = false, fetcher = async () => status('pending') } = {}) {
    let script = blade.match(/<script>([\s\S]*?)<\/script>/)[1];
    script = script
        .replace("@json(route('orders.tracking.status', ['token' => $trackingToken]))", '"/status"')
        .replace(/@json\(\s*route\('orders.tracking.route', \['token' => \$trackingToken\]\)\s*\)/, '"/route"')
        .replace('@json($order->status)', JSON.stringify(initial))
        .replace('@json($order->payment_status)', '"pending"');
    assert.equal(script.includes('@json'), false, 'all Blade expressions must be substituted');

    const listeners = {};
    const elements = Object.fromEntries([
        'tracking-status', 'tracking-cancelled', 'payment-status',
        'delivery-map-placeholder', 'delivery-route-map', 'delivery-route-info',
    ].map(id => [id, { textContent: '', hidden: false, className: '' }]));
    const document = {
        hidden,
        addEventListener: (event, callback) => { listeners[event] = callback; },
        getElementById: id => elements[id],
        querySelectorAll: () => [],
    };
    const intervals = new Map();
    const timeouts = new Map();
    const requests = [];
    let nextId = 1;
    const context = {
        document,
        window: { L: {} },
        AbortController,
        console: { warn() {} },
        setInterval: (callback, delay) => {
            const id = nextId++;
            intervals.set(id, { callback, delay });
            return id;
        },
        clearInterval: id => intervals.delete(id),
        setTimeout: (callback, delay) => {
            const id = nextId++;
            timeouts.set(id, { callback, delay });
            return id;
        },
        clearTimeout: id => timeouts.delete(id),
        fetch: (url, options) => {
            requests.push({ url, options });
            return fetcher(url, options);
        },
    };
    vm.runInNewContext(script, context);
    listeners.DOMContentLoaded();

    return {
        elements, document, intervals, timeouts, requests,
        tick() { for (const timer of [...intervals.values()]) timer.callback(); },
        visibility(value) {
            document.hidden = value;
            listeners.visibilitychange?.();
        },
        expireRequest() {
            for (const [id, timer] of [...timeouts]) {
                if (timer.delay === 10000) {
                    timeouts.delete(id);
                    timer.callback();
                }
            }
        },
    };
}

test('polls status and payment every 15 seconds, not only the map', async () => {
    const responses = [status('pending'), status('confirmed', 'verified'), status('preparing', 'verified')];
    const p = page({ fetcher: async () => responses.shift() });
    await flush();
    assert.equal([...p.intervals.values()][0].delay, 15000);
    p.tick();
    await flush();
    assert.equal(p.elements['tracking-status'].textContent, 'Pedido confirmado');
    assert.equal(p.elements['payment-status'].textContent, 'Verificado');
    p.tick();
    await flush();
    assert.equal(p.elements['tracking-status'].textContent, 'Preparando tu comida');
    assert.deepEqual(p.requests.map(r => r.url), ['/status', '/status', '/status']);
});

test('starts the map when status becomes on_the_way and keeps refreshing both endpoints', async () => {
    const responses = [status('preparing'), status('on_the_way'), status('on_the_way')];
    const p = page({ fetcher: async url => url === '/route' ? noGps() : responses.shift() });
    await flush();
    assert.equal(p.elements['delivery-map-placeholder'].hidden, true);
    p.tick();
    await flush();
    assert.equal(p.elements['delivery-map-placeholder'].hidden, false);
    p.tick();
    await flush();
    assert.equal(p.requests.filter(r => r.url === '/route').length, 2);
    assert.equal(p.requests.filter(r => r.url === '/status').length, 3);
});

for (const terminal of ['delivered', 'cancelled']) {
    test('stops all polling and hides the map at ' + terminal, async () => {
        const responses = [status('on_the_way'), status(terminal, 'verified')];
        const p = page({ fetcher: async url => url === '/route' ? noGps() : responses.shift() });
        await flush();
        p.tick();
        await flush();
        assert.equal(p.intervals.size, 0);
        assert.equal(p.elements['delivery-map-placeholder'].hidden, true);
        assert.equal(p.elements['tracking-cancelled'].hidden, terminal !== 'cancelled');
        const count = p.requests.length;
        p.tick();
        p.visibility(false);
        await flush();
        assert.equal(p.requests.length, count);
    });

    test('does not start polling an initially ' + terminal + ' order', async () => {
        const p = page({ initial: terminal });
        await flush();
        p.tick();
        p.visibility(false);
        assert.equal(p.intervals.size, 0);
        assert.equal(p.requests.length, 0);
    });
}

test('skips hidden tabs and refreshes immediately when visible', async () => {
    const p = page({ hidden: true, fetcher: async () => status('preparing') });
    p.tick();
    await flush();
    assert.equal(p.requests.length, 0);
    p.visibility(false);
    await flush();
    assert.equal(p.requests.length, 1);
    assert.equal(p.elements['tracking-status'].textContent, 'Preparando tu comida');
    p.visibility(true);
    p.tick();
    await flush();
    assert.equal(p.requests.length, 1);
});

test('does not overlap status requests from timer and visibility events', async () => {
    const pending = deferred();
    const p = page({ fetcher: () => pending.promise });
    p.tick();
    p.visibility(false);
    assert.equal(p.requests.length, 1);
    pending.resolve(status('confirmed'));
    await flush();
    p.tick();
    await flush();
    assert.equal(p.requests.length, 2);
});

test('recovers after HTTP and network failures and ignores invalid states', async () => {
    let attempt = 0;
    const p = page({ fetcher: async () => {
        attempt++;
        if (attempt === 1) return { ok: false };
        if (attempt === 2) throw new Error('offline');
        if (attempt === 3) return status('unknown');
        if (attempt === 4) return status('confirmed', 'unknown');
        return status('confirmed', 'verified');
    } });
    await flush();
    for (let i = 0; i < 3; i++) {
        p.tick();
        await flush();
        assert.equal(p.elements['tracking-status'].textContent, 'Pedido recibido');
    }
    p.tick();
    await flush();
    assert.equal(p.elements['tracking-status'].textContent, 'Pedido confirmado');
    assert.equal(p.elements['payment-status'].textContent, 'Verificado');
    assert.equal(p.timeouts.size, 0);
});

test('aborts a stuck status request and allows the next poll', async () => {
    let attempts = 0;
    const p = page({ fetcher: (url, options) => {
        if (++attempts > 1) return Promise.resolve(status('confirmed'));
        return new Promise((resolve, reject) => {
            options.signal.addEventListener('abort', () => reject(new Error('aborted')));
        });
    } });
    p.expireRequest();
    await flush();
    assert.equal(p.requests[0].options.signal.aborted, true);
    p.tick();
    await flush();
    assert.equal(p.elements['tracking-status'].textContent, 'Pedido confirmado');
    assert.equal(p.requests.length, 2);
});

test('ignores a late GPS response after delivery', async () => {
    const route = deferred();
    const responses = [status('on_the_way'), status('delivered', 'verified')];
    const p = page({ fetcher: url => url === '/route'
        ? route.promise : Promise.resolve(responses.shift()) });
    await flush();
    p.tick();
    await flush();
    assert.equal(p.elements['delivery-map-placeholder'].hidden, true);
    route.resolve(noGps());
    await flush();
    assert.equal(p.elements['delivery-route-info'].textContent, '');
    assert.equal(p.intervals.size, 0);
});
