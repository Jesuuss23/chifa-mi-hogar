export function saleSubtotal(lines, catalog) {
    return lines.reduce((sum, line) => {
        const item = catalog.find(item => item.type === line.type && item.id === line.id);
        return sum + (item ? item.price_cents * line.quantity : 0);
    }, 0);
}

export function addSaleItem(lines, item) {
    const existing = lines.find(line => line.type === item.type && line.id === item.id);
    if (existing) existing.quantity = Math.min(99, existing.quantity + 1);
    else if (lines.length < 50) lines.push({ type: item.type, id: item.id, quantity: 1 });
    return lines;
}

export function initializeQuickSales(root = document) {
    root.querySelectorAll('[data-quick-sale]').forEach(form => {
        if (form.dataset.ready) return;
        form.dataset.ready = 'true';
        const catalog = JSON.parse(form.querySelector('[data-sale-catalog]').textContent);
        const initial = JSON.parse(form.querySelector('[data-sale-initial]').textContent);
        const lines = (Array.isArray(initial) ? initial : []).filter(line => line && catalog.some(item => item.type === line.type && item.id === Number(line.id)))
            .slice(0, 50).map(line => ({ type: line.type, id: Number(line.id), quantity: Math.max(1, Math.min(99, Math.trunc(Number(line.quantity)) || 1)) }));
        const list = form.querySelector('[data-sale-lines]');
        const choice = form.querySelector('[data-sale-choice]');
        const total = form.querySelector('[data-sale-total]');
        const save = form.querySelector('[data-sale-save]');
        const message = form.querySelector('[data-sale-message]');
        let manualTotal = total.value !== '';
        let submitting = false;
        const money = cents => (cents / 100).toFixed(2);
        const element = (tag, className, text) => {
            const node = document.createElement(tag);
            node.className = className;
            if (text !== undefined) node.textContent = text;
            return node;
        };
        const hidden = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = name; input.value = value;
            return input;
        };
        const updateTotal = () => {
            const subtotal = saleSubtotal(lines, catalog);
            form.querySelector('[data-sale-subtotal]').textContent = 'S/ ' + money(subtotal);
            if (!manualTotal) total.value = money(subtotal);
            form.querySelector('[data-sale-empty]').hidden = lines.length > 0;
            save.disabled = submitting || lines.length === 0;
        };
        const render = () => {
            list.replaceChildren();
            lines.forEach((line, index) => {
                const item = catalog.find(item => item.type === line.type && item.id === line.id);
                const row = element('div', 'flex flex-wrap items-center gap-3 rounded-xl border p-3');
                row.append(element('span', 'min-w-0 flex-1 font-semibold', item.name));
                const quantities = element('div', 'flex items-center gap-3');
                const output = element('output', 'min-w-6 text-center', String(line.quantity));
                output.setAttribute('aria-label', 'Cantidad de ' + item.name);
                const stepButton = step => {
                    const button = element('button', 'h-11 w-11 rounded-full border text-xl', step < 0 ? '−' : '+');
                    button.type = 'button';
                    button.setAttribute('aria-label', (step < 0 ? 'Restar ' : 'Sumar ') + item.name);
                    button.disabled = step < 0 ? line.quantity <= 1 : line.quantity >= 99;
                    button.addEventListener('click', () => {
                        line.quantity = Math.max(1, Math.min(99, line.quantity + step));
                        render();
                        list.children[index]?.querySelector(step < 0 ? '[data-minus]' : '[data-plus]')?.focus();
                    });
                    button.setAttribute(step < 0 ? 'data-minus' : 'data-plus', '');
                    return button;
                };
                quantities.append(stepButton(-1), output, stepButton(1));
                row.append(quantities, element('strong', 'whitespace-nowrap', 'S/ ' + money(item.price_cents * line.quantity)));
                const remove = element('button', 'rounded-full px-3 py-2 text-sm underline', 'Quitar');
                remove.type = 'button';
                remove.setAttribute('aria-label', 'Quitar ' + item.name);
                remove.addEventListener('click', () => { lines.splice(index, 1); render(); choice.focus(); });
                row.append(remove, hidden(`items[${index}][type]`, line.type), hidden(`items[${index}][id]`, line.id), hidden(`items[${index}][quantity]`, line.quantity));
                list.append(row);
            });
            updateTotal();
        };
        form.querySelector('[data-sale-add]').addEventListener('click', () => {
            const item = catalog.find(item => `${item.type}:${item.id}` === choice.value);
            if (!item) { choice.focus(); return; }
            addSaleItem(lines, item);
            message.textContent = '';
            render();
        });
        total.addEventListener('input', () => { manualTotal = total.value !== ''; });
        form.querySelector('[data-sale-reset]').addEventListener('click', () => { manualTotal = false; updateTotal(); });
        form.addEventListener('submit', event => {
            if (submitting || !lines.length || navigator.onLine === false) {
                event.preventDefault();
                if (navigator.onLine === false) message.textContent = 'Sin conexión. Conserva esta página y vuelve a guardar cuando regrese internet.';
                return;
            }
            submitting = true;
            save.disabled = true;
            save.textContent = 'Guardando…';
        });
        window.addEventListener('pageshow', () => { submitting = false; save.textContent = 'Guardar venta'; updateTotal(); });
        render();
    });
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => initializeQuickSales(), { once: true });
    else initializeQuickSales();
}
