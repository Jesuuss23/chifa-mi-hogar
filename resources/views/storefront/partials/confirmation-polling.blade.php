function createConfirmationPolling({ url, initialStatus, apply, feedback, retry, env }) {
    const { document, window, navigator, fetch, AbortController, setTimeout, clearTimeout } = env;
    const statuses = ['pending', 'confirmed', 'preparing', 'on_the_way', 'delivered', 'cancelled'];
    let status = initialStatus;
    let stopped = ['delivered', 'cancelled'].includes(status);
    let busy = false;
    let timer = null;
    let failures = 0;
    let request = null;

    function message(text) {
        feedback.textContent = text;
    }
    function schedule(delay) {
        clearTimeout(timer);
        timer = null;
        if (!stopped && !document.hidden && navigator.onLine !== false) {
            timer = setTimeout(refresh, delay);
        }
    }
    function stop() {
        stopped = true;
        clearTimeout(timer);
        timer = null;
        request?.abort();
        retry.hidden = true;
    }
    async function refresh() {
        if (stopped || busy || document.hidden) return;
        clearTimeout(timer);
        timer = null;
        if (navigator.onLine === false) {
            message('Sin conexión. Conservamos el último estado; actualizaremos cuando vuelvas a conectarte.');
            return;
        }
        busy = true;
        retry.disabled = true;
        request = new AbortController();
        const timeout = setTimeout(() => request.abort(), 10000);
        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin', cache: 'no-store', signal: request.signal,
            });
            if (stopped) return;
            if ([403, 404, 410].includes(response.status)) {
                message('El seguimiento ya no está disponible. Conservamos el último estado recibido.');
                stop();
                return;
            }
            if (!response.ok) throw new Error('Unavailable');
            const data = await response.json();
            if (stopped) return;
            if (!data || !statuses.includes(data.status)
                || !['pending', 'verified', 'rejected'].includes(data.payment_status)) {
                throw new Error('Invalid response');
            }
            status = data.status;
            apply(data);
            failures = 0;
            if (['delivered', 'cancelled'].includes(status)) {
                message('Seguimiento finalizado. El pedido está ' + (status === 'delivered' ? 'entregado.' : 'cancelado.'));
                stop();
            } else {
                message('Estado actualizado. Seguiremos consultando automáticamente.');
            }
        } catch (error) {
            if (!stopped) {
                failures = Math.min(failures + 1, 3);
                message('No pudimos actualizar. Conservamos el último estado y volveremos a intentarlo.');
            }
        } finally {
            clearTimeout(timeout);
            request = null;
            busy = false;
            retry.disabled = false;
            schedule(failures ? Math.min(15000 * (2 ** failures), 60000) : 15000);
        }
    }
    retry.addEventListener('click', refresh);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            clearTimeout(timer);
            timer = null;
        } else {
            refresh();
        }
    });
    window.addEventListener('online', refresh);
    window.addEventListener('offline', () => {
        clearTimeout(timer);
        timer = null;
        if (!stopped) message('Sin conexión. Conservamos el último estado; actualizaremos cuando vuelvas a conectarte.');
    });
    // Suspend on navigation; a bfcache return reloads the server-rendered state.
    window.addEventListener('pagehide', stop);
    window.addEventListener('pageshow', event => {
        if (event.persisted) window.location.reload();
    });
    if (stopped) {
        retry.hidden = true;
        message('Este pedido ya finalizó. No se realizarán más consultas automáticas.');
    } else {
        refresh();
    }
    return { refresh, stop };
}
