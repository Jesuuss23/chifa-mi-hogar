<section class="mb-6 rounded-none border bg-card p-6 shadow-storefront" aria-labelledby="brand-heading">
    <h2 id="brand-heading" class="text-xl font-bold text-foreground">Identidad y datos del negocio</h2>
    <p class="mt-2 text-sm text-foreground/75">Configura el logo y los datos que usaremos en las páginas informativas.</p>

    <form method="POST" action="{{ route('admin.settings.brand.update') }}"
          enctype="multipart/form-data" class="mt-5 space-y-5">
        @csrf
        @method('PUT')

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="business_owner" class="mb-2 block text-sm font-semibold">Titular del negocio</label>
                <input id="business_owner" name="business_owner" required maxlength="150"
                       value="{{ old('business_owner', $settings->business_owner) }}"
                       class="w-full rounded-none border border-input p-3">
            </div>
            <div>
                <label for="privacy_email" class="mb-2 block text-sm font-semibold">Correo de privacidad y contacto</label>
                <input id="privacy_email" name="privacy_email" type="email" required maxlength="255"
                       value="{{ old('privacy_email', $settings->privacy_email) }}"
                       class="w-full rounded-none border border-input p-3">
            </div>
            <div>
                <label for="website_url" class="mb-2 block text-sm font-semibold">Sitio web oficial</label>
                <input id="website_url" name="website_url" type="url" required maxlength="500"
                       value="{{ old('website_url', $settings->website_url) }}"
                       class="w-full rounded-none border border-input p-3">
            </div>
            <div>
                <label for="business_ruc" class="mb-2 block text-sm font-semibold">RUC (pendiente si se deja vacío)</label>
                <input id="business_ruc" name="business_ruc" inputmode="numeric" maxlength="11" pattern="[0-9]{11}"
                       placeholder="11111111… (pendiente)"
                       value="{{ old('business_ruc', $settings->business_ruc) }}"
                       class="w-full rounded-none border border-input p-3">
                <p class="mt-2 text-xs text-foreground/75">El ejemplo no se guarda. Completa el RUC real cuando esté disponible.</p>
            </div>
        </div>

        @if($settings->logoUrl())
            <img src="{{ $settings->logoUrl() }}" alt="Logo actual de Chifa Mi Hogar"
                 width="120" height="120" class="h-30 w-30 border object-contain">
        @else
            <p class="text-sm text-foreground/75">Aún no hay logo. La tienda muestra las iniciales CM.</p>
        @endif

        <div>
            <label for="logo" class="mb-2 block text-sm font-semibold">Subir o reemplazar logo</label>
            <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp"
                   class="block w-full rounded-none border border-input p-3 text-sm">
            <p class="mt-2 text-xs text-foreground/75">
                JPG, PNG o WebP, máximo 2 MB y 4096 × 4096 píxeles.
                Puedes subir logo.jpeg. Se optimiza automáticamente.
                Sin un archivo nuevo se conserva el logo actual.
            </p>
        </div>
        <p class="text-sm text-foreground/75">
            El logo también se usa en la imagen al compartir la carta.
            <a class="font-semibold underline" href="{{ route('brand.share-image') }}" target="_blank" rel="noopener noreferrer">Ver imagen para compartir</a>
        </p>
        <button type="submit" class="rounded-none bg-primary px-6 py-3 font-semibold text-primary-foreground hover:bg-primary/90">
            Guardar identidad
        </button>
    </form>
</section>
