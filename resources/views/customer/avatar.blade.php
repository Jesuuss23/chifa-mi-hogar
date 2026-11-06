<section id="account-avatar" class="border border-border bg-card p-5 sm:p-6" aria-labelledby="avatar-title">
    <h2 id="avatar-title" class="text-xl font-bold">Foto de perfil (opcional)</h2>
    <p class="mt-2 text-sm text-foreground/75">Personaliza tu cuenta. La foto se muestra en tu cuenta y en el encabezado mientras tienes la sesión iniciada; puedes reemplazarla o quitarla cuando quieras.</p>
    <div class="mt-4 flex flex-wrap items-center gap-4">
        @if(auth()->user()->avatar_path)
            <img src="{{ route('customer.avatar.show') }}" alt="Tu foto de perfil" width="96" height="96"
                 class="h-24 w-24 rounded-full border border-border object-cover">
        @else
            <span aria-hidden="true" class="flex h-24 w-24 items-center justify-center rounded-full border border-border bg-muted text-3xl font-bold">
                {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
            </span>
            <span class="text-sm">Todavía no has agregado una foto.</span>
        @endif
    </div>
    @if(session('avatar_status'))<p role="status" class="mt-4">{{ session('avatar_status') }}</p>@endif
    @if($errors->avatar->any())
        <ul id="avatar-errors" role="alert" class="mt-4 text-sm text-destructive">
            @foreach($errors->avatar->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif
    <form method="POST" action="{{ route('customer.avatar.update') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
        @csrf @method('PUT')
        <label for="avatar-file" class="block text-sm font-semibold">Seleccionar foto</label>
        <input id="avatar-file" type="file" name="avatar" required accept="image/jpeg,image/png,image/webp"
               class="block w-full min-w-0 border border-input bg-background p-3 text-sm"
               aria-describedby="avatar-help{{ $errors->avatar->any() ? ' avatar-errors' : '' }}"
               aria-invalid="{{ $errors->avatar->any() ? 'true' : 'false' }}">
        <p id="avatar-help" class="text-sm text-foreground/75">JPG, PNG o WebP. Hasta 2 MB y entre 32 y 2048 píxeles por lado. Guardaremos una copia pequeña; el archivo original no se conserva.</p>
        <button type="submit" class="min-h-12 bg-primary px-5 py-3 font-semibold text-primary-foreground">Guardar foto</button>
    </form>
    @if(auth()->user()->avatar_path)
        <form method="POST" action="{{ route('customer.avatar.destroy') }}" class="mt-3">
            @csrf @method('DELETE')
            <button type="submit" class="min-h-12 border border-border px-5 py-3 font-semibold">Quitar foto</button>
        </form>
    @endif
</section>
