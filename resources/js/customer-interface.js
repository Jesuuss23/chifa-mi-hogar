// Local heuristic only. Never submit, persist or log password values.
export function passwordEstimate(value) {
    const length = Array.from(value).length;
    if (!length) return { score: 0, label: '' };
    if (length < 8) return { score: 0, label: 'Mínimo 8 caracteres' };
    if (/^(.)\1+$/u.test(value) || /^(123456|password|contrase[nñ]a|qwerty)/i.test(value)) {
        return { score: 1, label: 'Predecible' };
    }
    // Length counts more than mandatory symbol combinations; passphrases are welcome.
    const score = length >= 20 ? 4 : length >= 16 ? 3 : length >= 12 ? 2 : 1;
    return { score, label: ['', 'Básica', 'Mejorable', 'Buena', 'Mayor longitud'][score] };
}

export function initPasswordField(field) {
    const input = field.querySelector('input');
    const toggle = field.querySelector('[data-password-toggle]');
    if (!input || !toggle) return;
    const originalLabel = toggle.getAttribute('aria-label');
    toggle.hidden = false;
    toggle.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        toggle.textContent = show ? 'Ocultar' : 'Ver';
        toggle.setAttribute('aria-pressed', String(show));
        toggle.setAttribute('aria-label', show ? originalLabel.replace('Mostrar', 'Ocultar') : originalLabel);
    });
    // Hide revealed values again when leaving the field or submitting.
    const hide = () => {
        input.type = 'password';
        toggle.textContent = 'Ver';
        toggle.setAttribute('aria-pressed', 'false');
        toggle.setAttribute('aria-label', originalLabel);
    };
    field.addEventListener('focusout', event => {
        if (!field.contains(event.relatedTarget)) hide();
    });
    input.form?.addEventListener('submit', hide);
    if (!input.hasAttribute('data-password-strength')) return;
    const feedback = field.querySelector('[data-password-feedback]');
    const meter = field.querySelector('[data-password-meter]');
    const label = field.querySelector('[data-password-label]');
    const update = () => {
        const estimate = passwordEstimate(input.value);
        feedback.hidden = !input.value;
        meter.value = estimate.score;
        meter.setAttribute('aria-valuetext', estimate.label);
        label.textContent = estimate.label;
    };
    input.addEventListener('input', update);
    input.addEventListener('change', update);
    update();
}

export function initCustomerMenu(menu, doc) {
    const summary = menu.querySelector('summary');
    doc.addEventListener('click', event => {
        if (menu.open && !menu.contains(event.target)) menu.open = false;
    });
    menu.addEventListener('keydown', event => {
        if (event.key === 'Escape' && menu.open) {
            menu.open = false;
            summary.focus();
        }
    });
    menu.addEventListener('focusout', event => {
        if (event.relatedTarget && !menu.contains(event.relatedTarget)) menu.open = false;
    });
    menu.querySelectorAll('a').forEach(link => link.addEventListener('click', () => { menu.open = false; }));
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-password-field]').forEach(initPasswordField);
    document.querySelectorAll('[data-customer-menu]').forEach(menu => initCustomerMenu(menu, document));
}
