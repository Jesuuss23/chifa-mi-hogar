const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/storefront/partials/tracking-gps-guard.blade.php'), 'utf8');
const create = new Function(source + '\nreturn createTrackingGpsGuard;')();

function setup() {
    let clock = Date.parse('2026-11-05T12:00:00Z'), cleared = 0, message = '', next = 0;
    const timers = new Map();
    const guard = create({
        clear() { cleared++; }, notify(text) { message = text; },
        now: () => clock,
        setTimeout(fn, ms) { timers.set(++next, { fn, ms }); return next; },
        clearTimeout(id) { timers.delete(id); },
    });
    const valid = () => ({
        gps_active: true, gps_fresh: true,
        driver: { latitude: -12, longitude: -77 },
        destination: { latitude: -12.1, longitude: -77.1 },
        location_updated_at: new Date(clock - 10000).toISOString(),
    });
    return { guard, timers, valid, cleared: () => cleared, message: () => message, advance: ms => clock += ms };
}
test('fresh location expires using remaining GPS lifetime', () => {
    const x = setup();
    assert.equal(x.guard.accept(x.valid(), x.guard.ticket()), true);
    const timer = [...x.timers.values()][0];
    assert.equal(timer.ms, 50000);
    timer.fn();
    assert.equal(x.cleared(), 1);
    assert.match(x.message(), /dejó de estar actualizada/);
});
test('expired GPS removes marker and route', () => {
    const x = setup(), data = x.valid();
    x.advance(50000);
    assert.equal(x.guard.accept(data, x.guard.ticket()), false);
    assert.equal(x.cleared(), 1);
});
test('missing or malformed timestamp is rejected', () => {
    for (const timestamp of [undefined, 'not-a-date']) {
        const x = setup(), data = x.valid();
        data.location_updated_at = timestamp;
        assert.equal(x.guard.accept(data, x.guard.ticket()), false);
    }
});
test('future timestamp cannot extend marker lifetime', () => {
    const x = setup(), data = x.valid();
    data.location_updated_at = '2026-11-05T12:00:01Z';
    assert.equal(x.guard.accept(data, x.guard.ticket()), false);
});
test('invalid points cannot reach Leaflet', () => {
    for (const driver of [null, { latitude: 91, longitude: 0 }, { latitude: '12', longitude: 0 }]) {
        const x = setup(), data = x.valid();
        data.driver = driver;
        assert.equal(x.guard.accept(data, x.guard.ticket()), false);
    }
});
test('inactive GPS removes old position', () => {
    const x = setup(), data = x.valid();
    data.gps_active = false;
    assert.equal(x.guard.accept(data, x.guard.ticket()), false);
    assert.equal(x.cleared(), 1);
});
test('late request after disconnect or cancellation is ignored', () => {
    const x = setup(), ticket = x.guard.ticket();
    x.guard.invalidate('Sin conexión');
    assert.equal(x.guard.accept(x.valid(), ticket), false);
    assert.equal(x.timers.size, 0);
    assert.equal(x.cleared(), 1);
});
test('fresh recovery replaces timer instead of accumulating timers', () => {
    const x = setup();
    x.guard.accept(x.valid(), x.guard.ticket());
    x.advance(1000);
    x.guard.accept(x.valid(), x.guard.ticket());
    assert.equal(x.timers.size, 1);
    assert.equal([...x.timers.values()][0].ms, 50000);
});
