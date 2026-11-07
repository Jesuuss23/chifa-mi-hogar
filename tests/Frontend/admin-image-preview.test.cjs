const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../resources/js/admin-image-preview.js'), 'utf8');

function setup(max = 5 * 1024 * 1024, supported = true) {
    const element = () => ({
        hidden: false, textContent: '', children: [], events: {},
        addEventListener(name, fn) { this.events[name] = fn; },
        replaceChildren() { this.children = []; },
        append(child) { this.children.push(child); },
    });
    const panel = element(), holder = element(), name = element(), meta = element(), status = element(), clear = element();
    const fields = { '[data-preview-panel]': panel, '[data-preview-image]': holder, '[data-preview-name]': name,
        '[data-preview-meta]': meta, '[data-preview-status]': status, '[data-preview-clear]': clear };
    const form = element(), input = element();
    Object.assign(input, { type: 'file', files: [], form, focused: false, focus() { this.focused = true; } });
    Object.defineProperty(input, 'value', { set(value) { if (value === '') this.files = []; } });
    const root = { dataset: { inputId: 'photo', maxBytes: String(max) }, querySelector: selector => fields[selector] };
    const document = { querySelectorAll: () => [root], getElementById: () => input, createElement: () => element() };
    const window = element(), revoked = [], made = [], tasks = [];
    const URL = {
        revokeObjectURL(url) { revoked.push(url); },
        ...(supported ? { createObjectURL(file) { made.push(file); return 'blob:' + made.length; } } : {}),
    };
    vm.runInNewContext(source, { document, window, URL, queueMicrotask: fn => tasks.push(fn) });
    return { input, panel, holder, name, meta, status, clear, form, window, made, revoked,
        select(file) { input.files = file ? [file] : []; input.events.change(); },
        flush() { tasks.splice(0).forEach(fn => fn()); } };
}
const jpg = (name = 'foto.jpg') => ({ name, type: 'image/jpeg', size: 1024 });

test('preview remains pending and reports real dimensions without submitting', () => {
    const s = setup();
    assert.equal(s.panel.hidden, true);
    s.select(jpg('<img onerror=bad>.jpg'));
    assert.equal(s.name.textContent, '<img onerror=bad>.jpg');
    assert.equal(s.panel.hidden, false);
    const img = s.holder.children[0];
    img.naturalWidth = 640; img.naturalHeight = 480; img.onload();
    assert.match(s.meta.textContent, /640 × 480/);
    assert.match(s.status.textContent, /todavía no se ha guardado/);
    assert.equal(s.input.files.length, 1);
});
test('replacing a selection revokes old URL and ignores stale image events', () => {
    const s = setup();
    s.select(jpg('first.jpg'));
    const first = s.holder.children[0];
    s.select(jpg('second.jpg'));
    first.onerror();
    assert.equal(s.revoked[0], 'blob:1');
    assert.equal(s.holder.children.length, 1);
    assert.equal(s.name.textContent, 'second.jpg');
    assert.match(s.status.textContent, /Cargando/);
});
test('clear removes only pending file and returns focus', () => {
    const s = setup();
    s.select(jpg());
    s.clear.events.click();
    assert.equal(s.input.files.length, 0);
    assert.equal(s.panel.hidden, true);
    assert.equal(s.holder.children.length, 0);
    assert.equal(s.revoked[0], 'blob:1');
    assert.equal(s.input.focused, true);
});
test('oversize and unsupported formats do not allocate previews or change upload validation', () => {
    const s = setup(2 * 1024 * 1024);
    s.select({ ...jpg(), size: 3 * 1024 * 1024 });
    assert.match(s.status.textContent, /límite de 2 MB/);
    assert.equal(s.made.length, 0);
    s.select({ name: 'bad.svg', type: 'image/svg+xml', size: 100 });
    assert.match(s.status.textContent, /formato/);
    assert.equal(s.made.length, 0);
    assert.equal(s.input.files.length, 1);
});
test('decode errors release URL and allow another selection', () => {
    const s = setup();
    s.select(jpg());
    s.holder.children[0].onerror();
    assert.equal(s.holder.children.length, 0);
    assert.equal(s.revoked[0], 'blob:1');
    assert.match(s.status.textContent, /No pudimos/);
    s.select(jpg('ok.jpg'));
    assert.equal(s.holder.children.length, 1);
});
test('native form reset clears preview after reset and respects cancelled resets', () => {
    const s = setup();
    s.select(jpg());
    s.form.events.reset({ defaultPrevented: true }); s.flush();
    assert.equal(s.panel.hidden, false);
    s.form.events.reset({ defaultPrevented: false });
    s.input.files = [];
    s.flush();
    assert.equal(s.panel.hidden, true);
});
test('pagehide releases URL and bfcache restore regenerates preview', () => {
    const s = setup();
    s.select(jpg());
    s.window.events.pagehide();
    assert.equal(s.revoked[0], 'blob:1');
    s.window.events.pageshow({ persisted: true });
    assert.equal(s.made.length, 2);
    assert.equal(s.holder.children[0].src, 'blob:2');
});
test('missing browser support leaves native file input unchanged', () => {
    const s = setup(5 * 1024 * 1024, false);
    assert.equal(s.input.events.change, undefined);
    assert.equal(s.made.length, 0);
});
test('empty MIME may preview accepted extensions while SVG is still rejected', () => {
    const s = setup();
    s.select({ ...jpg('FOTO.PNG'), type: '' });
    assert.equal(s.made.length, 1);
    s.select({ ...jpg('foto.svg'), type: '' });
    assert.equal(s.made.length, 1);
    assert.equal(s.holder.children.length, 0);
});
