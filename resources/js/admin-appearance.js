// Progressive enhancement: preview links and the apply form also work without JS.
const root = document.querySelector('[data-appearance]');
if (root) {
    const dialog = root.querySelector('dialog');
    const frame = root.querySelector('[data-preview-frame]');
    const label = root.querySelector('[data-preview-label]');
    let opener = null;
    const setWidth = (width) => {
        frame.dataset.width = width;
        root.querySelectorAll('[data-preview-width]').forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.previewWidth === width));
        });
    };
    if (dialog && frame && typeof dialog.showModal === 'function') {
        root.querySelectorAll('[data-theme-preview]').forEach((link) => {
            link.addEventListener('click', (event) => {
                if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
                event.preventDefault();
                opener = link;
                label.textContent = link.dataset.themeLabel;
                frame.title = 'Vista previa del tema ' + link.dataset.themeLabel;
                setWidth('desktop');
                frame.src = link.href;
                dialog.showModal();
            });
        });
        root.querySelector('[data-preview-close]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', () => {
            frame.removeAttribute('src');
            opener?.focus();
        });
        root.querySelectorAll('[data-preview-width]').forEach((button) => {
            button.addEventListener('click', () => setWidth(button.dataset.previewWidth));
        });
    }
}
