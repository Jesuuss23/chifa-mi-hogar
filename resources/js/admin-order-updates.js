export function ordersChanged(before, after) {
    if (!Array.isArray(after)) return false;
    const states = new Map(after.map(order => [String(order.id), order]));
    return before.length !== after.length || before.some(order => {
        const current = states.get(String(order.id));
        return !current || current.status !== order.status || current.payment_status !== order.payment_status;
    });
}

export function startOrderUpdates(root, env) {
    const { document, window, fetch, setTimeout, clearTimeout, AbortController } = env;
    let initial;
    try { initial = JSON.parse(root.dataset.orders); } catch { return; }
    if (!Array.isArray(initial) || !initial.length) return;
    const url = new URL(root.dataset.url, window.location.href);
    initial.forEach(order => url.searchParams.append('ids[]', order.id));
    const notice = root.querySelector('[data-order-update-notice]');
    let dirty = false;
    let stopped = false;
    let pending = false;
    let timer;
    let controller;
    const markDirty = event => { if (event.target.closest('form')) dirty = true; };
    document.addEventListener('input', markDirty);
    document.addEventListener('change', markDirty);
    document.addEventListener('submit', () => { stopped = true; clearTimeout(timer); controller?.abort(); });
    window.addEventListener('pagehide', () => { stopped = true; clearTimeout(timer); controller?.abort(); });
    window.addEventListener('pageshow', event => {
        if (event.persisted) { stopped = false; schedule(); }
    });
    function schedule() {
        clearTimeout(timer);
        if (!stopped) timer = setTimeout(check, 10000);
    }
    async function check() {
        if (stopped || pending) return;
        if (document.hidden) { schedule(); return; }
        pending = true;
        controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 8000);
        try {
            const response = await fetch(url.toString(), {
                credentials: 'same-origin', cache: 'no-store',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal,
            });
            if (response.redirected || response.status === 401 || response.status === 403) {
                stopped = true;
                return;
            }
            if (!response.ok) return;
            const result = await response.json();
            if (stopped || !ordersChanged(initial, result.orders)) return;
            if (dirty || document.activeElement?.closest('form')) {
                notice.hidden = false;
            } else {
                stopped = true;
                window.location.reload();
            }
        } catch {
            // Transient network failures retry on the next interval.
        } finally {
            clearTimeout(timeout);
            pending = false;
            schedule();
        }
    }
    schedule();
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-order-updates]').forEach(root => startOrderUpdates(root, {
        document, window, fetch: window.fetch.bind(window),
        setTimeout: window.setTimeout.bind(window), clearTimeout: window.clearTimeout.bind(window),
        AbortController: window.AbortController,
    }));
}
