// Explicit opt-in: enhancement does not alter upload names, validation or submit.
document.querySelectorAll('[data-admin-image-preview]').forEach((root) => {
    const input = document.getElementById(root.dataset.inputId);
    if (!input || input.type !== 'file' || typeof URL.createObjectURL !== 'function') return;
    const panel = root.querySelector('[data-preview-panel]');
    const holder = root.querySelector('[data-preview-image]');
    const name = root.querySelector('[data-preview-name]');
    const meta = root.querySelector('[data-preview-meta]');
    const status = root.querySelector('[data-preview-status]');
    const clear = root.querySelector('[data-preview-clear]');
    const maximum = Number(root.dataset.maxBytes);
    let objectUrl = null;

    const release = () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    };
    const render = () => {
        release();
        holder.replaceChildren();
        const file = input.files?.[0];
        panel.hidden = !file;
        status.textContent = '';
        name.textContent = '';
        meta.textContent = '';
        if (!file) return;
        name.textContent = file.name;
        meta.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
        const allowed = ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)
            || (!file.type && /\.(jpe?g|png|webp)$/i.test(file.name));
        if (!allowed) {
            status.textContent = 'No hay vista previa para este formato. Selecciona JPG, PNG o WebP.';
            return;
        }
        if (file.size > maximum) {
            status.textContent = 'El archivo supera el límite de ' + (maximum / 1024 / 1024) + ' MB. Elige una imagen más pequeña.';
            return;
        }
        try {
            objectUrl = URL.createObjectURL(file);
            const currentUrl = objectUrl;
            const image = document.createElement('img');
            image.alt = 'Vista previa de la imagen seleccionada';
            image.onload = () => {
                if (objectUrl !== currentUrl) return;
                meta.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB · ' + image.naturalWidth + ' × ' + image.naturalHeight + ' px';
                status.textContent = 'Vista previa lista. La nueva imagen todavía no se ha guardado.';
            };
            image.onerror = () => {
                if (objectUrl !== currentUrl) return;
                holder.replaceChildren();
                release();
                status.textContent = 'No pudimos mostrar esta imagen. Comprueba el archivo o selecciona otro.';
            };
            holder.append(image);
            status.textContent = 'Cargando vista previa…';
            image.src = currentUrl;
        } catch {
            release();
            holder.replaceChildren();
            status.textContent = 'No pudimos mostrar esta imagen. Comprueba el archivo o selecciona otro.';
        }
    };

    input.addEventListener('change', render);
    clear.addEventListener('click', () => {
        input.value = '';
        render();
        status.textContent = 'Selección quitada. La imagen guardada no se ha eliminado.';
        input.focus();
    });
    input.form?.addEventListener('reset', (event) => {
        queueMicrotask(() => { if (!event.defaultPrevented) render(); });
    });
    window.addEventListener('pagehide', release);
    window.addEventListener('pageshow', (event) => { if (event.persisted) render(); });
    render();
});
