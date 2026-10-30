<section class="mb-6 rounded-none border bg-card p-6 shadow-storefront" aria-labelledby="legal-heading">
    <h2 id="legal-heading" class="text-xl font-bold">Aviso legal, privacidad y cookies</h2>
    <p class="mt-2 text-sm text-foreground/75">Completa datos reales del negocio antes de publicar. Puedes guardar un borrador y revisarlo.</p>
    <nav class="my-4 flex flex-wrap gap-4" aria-label="Vistas previas legales">
        @foreach(['notice' => 'Aviso legal', 'privacy' => 'Privacidad', 'cookies' => 'Cookies'] as $page => $label)
            <a class="font-semibold text-primary underline" href="{{ route('admin.legal.preview', $page) }}">Vista previa: {{ $label }}</a>
        @endforeach
    </nav>
    <form method="POST" action="{{ route('admin.settings.legal.update') }}" class="space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label for="legal_address" class="block font-semibold">Domicilio del responsable</label>
            <input id="legal_address" name="legal_address" maxlength="500"
                   value="{{ old('legal_address', $settings->legal_address) }}"
                   class="mt-1 w-full rounded-none border p-3">
        </div>
        @foreach([
            'privacy_hosting' => ['Alojamiento y otros encargados', 'Indica proveedor, ubicación o país de tratamiento y función; incluye copias de seguridad y otros encargados reales.'],
            'privacy_retention' => ['Conservación y eliminación', 'Indica plazos reales por tipo de dato (cuentas, pedidos, comprobantes, GPS, registros y copias) y cómo se aplican. Guardar este texto no programa eliminaciones.'],
            'privacy_database' => ['Bancos de datos personales', 'Identifica los bancos de datos utilizados y su titular; añade su inscripción si corresponde y está confirmada.'],
        ] as $field => [$label, $help])
            <div>
                <label for="{{ $field }}" class="block font-semibold">{{ $label }}</label>
                <textarea id="{{ $field }}" name="{{ $field }}" maxlength="3000" rows="4"
                          class="mt-1 w-full rounded-none border p-3">{{ old($field, $settings->$field) }}</textarea>
                <p class="mt-1 text-sm text-foreground/75">{{ $help }}</p>
            </div>
        @endforeach
        <p class="text-sm text-foreground/75">Revisa también el RUC en Identidad del negocio y contrasta el inventario con el servidor desplegado. Esta plantilla no certifica cumplimiento legal.</p>
        <input type="hidden" name="legal_pages_published" value="0">
        <label class="flex items-center gap-2">
            <input type="checkbox" name="legal_pages_published" value="1"
                   @checked(old('legal_pages_published', $settings->legal_pages_published))>
            Publicar las tres páginas y sus enlaces
        </label>
        <button class="rounded-none bg-primary px-6 py-3 font-semibold text-primary-foreground" type="submit">Guardar páginas informativas</button>
    </form>
</section>
