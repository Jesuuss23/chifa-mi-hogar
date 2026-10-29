const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../resources/js/customer-profile.js'), 'utf8');

function page({ latitude = '', longitude = '' } = {}) {
    const ids = ['customer-profile-form', 'profile-address', 'profile-reference',
        'profile-latitude', 'profile-longitude', 'profile-location-status',
        'profile-clear-address', 'profile-use-location'];
    const nodes = Object.fromEntries(ids.map(id => [id, {
        value: '', textContent: '', handlers: {},
        addEventListener(event, fn) { this.handlers[event] = fn; },
    }]));
    nodes['profile-latitude'].value = latitude;
    nodes['profile-longitude'].value = longitude;
    nodes['profile-address'].value = 'Dirección guardada';
    let marker = null;
    let gpsCalls = 0;
    const map = {
        handlers: {}, setView() { return this; },
        on(event, fn) { this.handlers[event] = fn; },
        removeLayer() { marker = null; },
    };
    const L = {
        map() { return map; },
        tileLayer() { return { addTo() {} }; },
        marker(point) {
            marker = {
                point, handlers: {},
                addTo() { return this; },
                on(event, fn) { this.handlers[event] = fn; },
                setLatLng(point) { this.point = point; },
                getLatLng() { return {lat: this.point[0], lng: this.point[1]}; },
            };
            return marker;
        },
    };
    let ready;
    vm.runInNewContext(source, {
        document: { getElementById: id => nodes[id], addEventListener: (_, fn) => { ready = fn; } },
        window: { L },
        navigator: { geolocation: { getCurrentPosition() { gpsCalls++; } } },
    });
    ready();
    return { nodes, map, get marker() { return marker; }, get gpsCalls() { return gpsCalls; } };
}

test('saved location is displayed without requesting geolocation', () => {
    const p = page({ latitude: '-12.0000000', longitude: '-77.0000000' });
    assert.equal(p.nodes['profile-latitude'].value, '-12.0000000');
    assert.equal(p.nodes['profile-longitude'].value, '-77.0000000');
    assert.ok(p.marker);
    assert.equal(p.gpsCalls, 0);
});

test('editing address clears both coordinates and marker', () => {
    const p = page({ latitude: '-12', longitude: '-77' });
    p.nodes['profile-address'].handlers.input();
    assert.equal(p.nodes['profile-latitude'].value, '');
    assert.equal(p.nodes['profile-longitude'].value, '');
    assert.equal(p.marker, null);
});

test('map selection accepts zero and writes a coherent coordinate pair', () => {
    const p = page();
    p.map.handlers.click({ latlng: {lat: 0, lng: 0} });
    assert.equal(p.nodes['profile-latitude'].value, '0.0000000');
    assert.equal(p.nodes['profile-longitude'].value, '0.0000000');
});

test('clear address only updates form and GPS is requested only on click', () => {
    const p = page({ latitude: '-12', longitude: '-77' });
    p.nodes['profile-reference'].value = 'Referencia';
    p.nodes['profile-clear-address'].handlers.click();
    assert.equal(p.nodes['profile-address'].value, '');
    assert.equal(p.nodes['profile-reference'].value, '');
    assert.equal(p.nodes['profile-latitude'].value, '');
    assert.match(p.nodes['profile-location-status'].textContent, /al pulsar Guardar/);
    assert.equal(p.gpsCalls, 0);
    p.nodes['profile-use-location'].handlers.click();
    assert.equal(p.gpsCalls, 1);
});
