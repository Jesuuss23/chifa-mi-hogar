// Progressive enhancement: every detail link remains a normal page without JS.
const directory = document.querySelector('[data-customer-directory]');
if (directory) {
    const dialog = directory.querySelector('dialog');
    const content = directory.querySelector('[data-customer-content]');
    const status = directory.querySelector('[data-customer-status]');
    const page = directory.querySelector('[data-customer-page]');
    let opener;
    let pending;
    let timer;
    const clearRequest = () => {
        clearTimeout(timer);
        pending?.abort();
        pending = null;
    };
    if (typeof dialog?.showModal === 'function') {
        directory.querySelectorAll('[data-customer-open]').forEach((link) => {
            link.addEventListener('click', async (event) => {
                if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
                event.preventDefault();
                clearRequest();
                opener = link;
                content.replaceChildren();
                content.setAttribute('aria-busy', 'true');
                status.textContent = 'Cargando ficha…';
                page.href = link.href;
                dialog.showModal();
                const request = new AbortController();
                pending = request;
                timer = setTimeout(() => request.abort(), 15000);
                try {
                    const response = await fetch(link.href, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                        credentials: 'same-origin', cache: 'no-store', signal: request.signal,
                    });
                    if (!response.ok || response.redirected || response.headers.get('X-Customer-Details') !== '1') {
                        throw new Error('Unavailable detail');
                    }
                    const html = await response.text();
                    if (pending !== request || !dialog.open) return;
                    // Only our authenticated Blade fragment is accepted; all profile values are escaped there.
                    content.innerHTML = html;
                    status.textContent = '';
                } catch {
                    if (pending === request && dialog.open) {
                        status.textContent = 'No pudimos cargar la ficha. Abre la página para reintentar o revisar tu sesión.';
                    }
                } finally {
                    if (pending === request) {
                        clearTimeout(timer);
                        content.removeAttribute('aria-busy');
                    }
                }
            });
        });
        directory.querySelector('[data-customer-close]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', () => {
            clearRequest();
            content.replaceChildren();
            content.removeAttribute('aria-busy');
            status.textContent = '';
            page.removeAttribute('href');
            opener?.focus();
        });
    }
}
