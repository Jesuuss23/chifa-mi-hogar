export function stepQuantity(stepper, delta) {
    const input = stepper.querySelector('[data-quantity-input]');
    const output = stepper.querySelector('output');
    const minimum = Number(input.min || 1);
    const maximum = Number(input.max || 20);
    const current = Number(input.value);
    const value = Math.max(minimum, Math.min(maximum,
        (Number.isFinite(current) ? Math.trunc(current) : minimum) + delta));
    input.value = String(value);
    output.value = String(value);
    output.textContent = String(value);
    stepper.querySelector('[data-quantity-step="-1"]').disabled = value <= minimum;
    stepper.querySelector('[data-quantity-step="1"]').disabled = value >= maximum;
    // Existing total calculation and form serialization listen to this input.
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

if (typeof document !== 'undefined') {
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-quantity-step]');
        if (!button || button.disabled) return;
        const stepper = button.closest('[data-quantity-stepper]');
        if (stepper) stepQuantity(stepper, Number(button.dataset.quantityStep));
    });
}
