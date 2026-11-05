@php
    $linkedProviders = auth()->user()->socialAccounts()->whereIn('provider', ['google', 'facebook'])->pluck('provider');
@endphp
@if(session('social_status'))
    <p role="status" class="mt-6 border border-border bg-card p-4">{{ session('social_status') }}</p>
@endif
@if($linkedProviders->isNotEmpty() || $errors->unlinkSocial->any())
<section class="mt-6 border border-border bg-card p-6" aria-labelledby="social-management-title">
    <h2 id="social-management-title" class="text-xl font-bold">Administrar accesos vinculados</h2>
    <p class="mt-2 text-sm text-foreground/75">Para quitar un acceso, confirma tu contraseña de Chifa Mi Hogar. Conservarás tu cuenta, pedidos y datos de entrega. Las otras sesiones deberán iniciar sesión nuevamente.</p>
    <a class="mt-3 inline-block text-primary underline" href="{{ route('customer.password.request') }}">Si todavía no tienes contraseña, establécela por correo</a>
    @if($errors->unlinkSocial->any())
        <ul role="alert" class="mt-4 text-sm text-destructive">
            @foreach($errors->unlinkSocial->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif
    @foreach($linkedProviders as $provider)
        <details @if($errors->unlinkSocial->any()) open @endif class="mt-4 border border-border p-4">
            <summary class="cursor-pointer py-2 font-semibold">Desvincular {{ ucfirst($provider) }}</summary>
            <form method="POST" action="{{ route('customer.social.destroy', $provider) }}" class="mt-3 space-y-4">
                @csrf
                @method('DELETE')
                <label for="unlink-{{ $provider }}-password" class="block text-sm font-semibold">Contraseña de Chifa Mi Hogar</label>
                <input id="unlink-{{ $provider }}-password" name="current_password" type="password" required
                       maxlength="255" autocomplete="current-password" class="w-full border border-input bg-card p-3 text-foreground">
                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" name="confirm_unlink" value="1" required class="mt-1">
                    <span>Quiero quitar {{ ucfirst($provider) }} y seguir entrando con mi correo y contraseña.</span>
                </label>
                <button type="submit" class="min-h-12 border border-destructive px-4 py-3 font-semibold text-destructive">Confirmar desvinculación de {{ ucfirst($provider) }}</button>
            </form>
        </details>
    @endforeach
    <p class="mt-4 text-sm text-foreground/75">Esto elimina la vinculación en Chifa Mi Hogar. No elimina tu cuenta de Google o Facebook ni revoca sus permisos externos. Puedes revisar esos permisos en la configuración del proveedor.</p>
</section>
@endif
