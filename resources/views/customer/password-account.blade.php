<section class="mt-6 border border-border bg-card p-6">
<h2 class="text-xl font-bold">Tu contraseña</h2>
@if(session('password_status'))<p role="status" class="mt-3">{{ session('password_status') }}</p>@endif
<p class="mt-2 text-sm">Si ingresas con Google o Facebook y todavía no tienes una contraseña, puedes establecerla mediante un enlace enviado a tu correo. Tus métodos de acceso seguirán vinculados.</p>
<a href="{{ route('customer.password.request') }}" class="mt-3 inline-block text-primary underline">Establecer o recuperar contraseña por correo</a>
@if($errors->changePassword->any())<ul role="alert" class="mt-4 text-destructive">@foreach($errors->changePassword->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
<form method="POST" action="{{ route('customer.password.update') }}" class="mt-4 space-y-4">
@csrf @method('PUT')
<label for="current_password" class="block text-sm font-semibold">Contraseña actual</label>
<input id="current_password" name="current_password" type="password" required maxlength="255" autocomplete="current-password" class="w-full border border-input bg-card p-3">
<div><label for="password" class="block text-sm font-semibold">Nueva contraseña</label>
<input id="password" name="password" type="password" required maxlength="255" minlength="8" autocomplete="new-password"  class="mt-1 w-full border border-input bg-card p-3 text-foreground"></div>
<div><label for="password_confirmation" class="block text-sm font-semibold">Repite la nueva contraseña</label>
<input id="password_confirmation" name="password_confirmation" type="password" required maxlength="255" minlength="8" autocomplete="new-password"  class="mt-1 w-full border border-input bg-card p-3 text-foreground"></div>
<p class="text-sm text-foreground/75">Al cambiarla, las otras sesiones deberán iniciar sesión nuevamente.</p>
<button type="submit" class="min-h-12 bg-primary px-4 py-3 font-semibold text-primary-foreground">Cambiar contraseña</button>
</form></section>
