const orderInput = document.querySelector('[data-order-check]');
if (orderInput) {
    const status = document.getElementById('product-order-status');
    const suggestion = orderInput.form.querySelector('[data-order-suggestion]');
    let timer, pending, revision = 0, suggested = null;
    const check = async (version, value) => {
        const request = new AbortController();
        pending = request;
        const timeout = setTimeout(() => request.abort(), 8000);
        try {
            const url = new URL(orderInput.dataset.orderCheck, window.location.origin);
            url.searchParams.set('sort_order', value);
            if (orderInput.dataset.productId) url.searchParams.set('product_id', orderInput.dataset.productId);
            const response = await fetch(url, { headers: { Accept: 'application/json' },
                credentials: 'same-origin', cache: 'no-store', signal: request.signal });
            if (!response.ok || response.redirected) throw new Error('Availability unavailable');
            const result = await response.json();
            if (revision !== version) return;
            if (result.order !== Number(value) || typeof result.available !== 'boolean'
                || (!result.available && typeof result.occupied_by !== 'string')) throw new Error('Invalid response');
            if (result.available) {
                status.textContent = result.current ? 'Este es el orden actual del plato.' : 'Número libre en este momento. Se comprobará de nuevo al guardar.';
                orderInput.removeAttribute('aria-invalid');
            } else {
                const message = 'Número ocupado por el plato: ' + result.occupied_by + '. Elige otro número.';
                status.textContent = message;
                orderInput.setCustomValidity(message);
                orderInput.setAttribute('aria-invalid', 'true');
                suggested = result.suggested_order;
                if (Number.isInteger(suggested) && suggested >= 0 && suggested <= 2147483647) {
                    suggestion.textContent = 'Usar número libre: ' + suggested;
                    suggestion.hidden = false;
                }
            }
        } catch {
            if (revision === version) {
                orderInput.setCustomValidity('');
                orderInput.removeAttribute('aria-invalid');
                status.textContent = 'No pudimos comprobar el número. Se validará al guardar.';
            }
        } finally {
            clearTimeout(timeout);
            if (revision === version) pending = null;
        }
    };
    const schedule = () => {
        clearTimeout(timer);
        pending?.abort();
        pending = null;
        const version = ++revision;
        orderInput.setCustomValidity('');
        orderInput.removeAttribute('aria-invalid');
        suggestion.hidden = true;
        suggested = null;
        const value = orderInput.value.trim();
        if (!/^\d+$/.test(value) || !orderInput.validity.valid) {
            status.textContent = 'Ingresa un número entero entre 0 y 2147483647.';
            return;
        }
        status.textContent = 'Comprobando número…';
        timer = setTimeout(() => check(version, value), 300);
    };
    orderInput.addEventListener('input', schedule);
    orderInput.addEventListener('change', schedule);
    suggestion.addEventListener('click', () => {
        if (suggested === null) return;
        orderInput.value = String(suggested);
        orderInput.focus();
        schedule();
    });
    orderInput.form.addEventListener('reset', event => queueMicrotask(() => {
        if (!event.defaultPrevented) schedule();
    }));
    window.addEventListener('pageshow', event => { if (event.persisted) schedule(); });
    schedule();
}
