<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer">
<title>{{ $reset ? 'Establecer contraseña' : 'Recuperar acceso' }} | Chifa Mi Hogar</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="storefront-theme customer-surface min-h-screen bg-background p-4 text-foreground">
<main class="mx-auto mt-8 w-full max-w-md border border-border bg-card p-6">
<a href="{{ route('login') }}" class="text-primary underline">Volver a iniciar sesión</a>
<h1 class="mt-5 text-2xl font-bold">{{ $reset ? 'Establecer contraseña' : 'Recuperar acceso' }}</h1>
<p class="mt-3 text-sm">{{ $reset ? 'Elige una contraseña de al menos 8 caracteres.' : 'Te enviaremos un enlace a tu correo. También sirve para establecer una contraseña si creaste tu cuenta con Google o Facebook.' }}</p>
@if(session('password_status'))<p role="status" class="mt-4 border border-border p-3">{{ session('password_status') }}</p>@endif
@if($errors->any())<ul role="alert" class="mt-4 text-destructive">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
@auth
<p class="mt-3 text-sm">Cuando recibas el enlace, cierra sesión antes de abrirlo o utiliza un navegador sin sesión iniciada.</p>
@endauth
<form method="POST" action="{{ route($reset ? 'customer.password.store' : 'customer.password.email') }}" class="mt-5 space-y-4">
@csrf
@if($reset)<input type="hidden" name="token" value="{{ $token }}">@endif
<div><label for="email" class="block text-sm font-semibold">Correo electrónico</label>
<input id="email" name="email" type="email" required maxlength="255" autocomplete="email" value="{{ old('email', $email ?? auth()->user()?->email ?? '') }}" class="mt-1 w-full border border-input bg-card p-3 text-foreground"></div>
@if($reset)
<div><label for="password" class="block text-sm font-semibold">Nueva contraseña</label>
<input id="password" name="password" type="password" required maxlength="255" minlength="8" autocomplete="new-password"  class="mt-1 w-full border border-input bg-card p-3 text-foreground"></div>
<div><label for="password_confirmation" class="block text-sm font-semibold">Repite la nueva contraseña</label>
<input id="password_confirmation" name="password_confirmation" type="password" required maxlength="255" minlength="8" autocomplete="new-password"  class="mt-1 w-full border border-input bg-card p-3 text-foreground"></div>
@endif
<button type="submit" class="min-h-12 w-full bg-primary px-4 py-3 font-semibold text-primary-foreground">{{ $reset ? 'Guardar contraseña' : 'Enviar enlace' }}</button>
</form>
@if($reset)<a class="mt-5 block text-primary underline" href="{{ route('customer.password.request') }}">Solicitar otro enlace</a>@endif
</main></body></html>
