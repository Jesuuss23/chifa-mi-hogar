@extends('customer.auth.layout')
@section('title', $reset ? 'Establecer contraseña' : 'Recuperar acceso')
@section('content')
<a href="{{ route('login') }}" class="text-primary underline">Volver a iniciar sesión</a>
<h1 id="auth-title" class="mt-5 text-2xl font-bold">{{ $reset ? 'Establecer contraseña' : 'Recuperar acceso' }}</h1>
<p class="mt-3 text-sm">{{ $reset ? 'Elige una contraseña de al menos 8 caracteres.' : 'Te enviaremos un enlace a tu correo. También sirve para establecer una contraseña si creaste tu cuenta con Google o Facebook.' }}</p>
@if(session('password_status'))<p role="status" class="mt-4 border border-border p-3">{{ session('password_status') }}</p>@endif
@if($errors->any())<ul id="password-form-errors" role="alert" class="mt-4 text-destructive">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
@auth
<p class="mt-3 text-sm">Cuando recibas el enlace, cierra sesión antes de abrirlo o utiliza un navegador sin sesión iniciada.</p>
@endauth
<form method="POST" action="{{ route($reset ? 'customer.password.store' : 'customer.password.email') }}" class="mt-5 space-y-4">
@csrf
@if($reset)<input type="hidden" name="token" value="{{ $token }}">@endif
<div><label for="email" class="block text-sm font-semibold">Correo electrónico</label>
<input id="email" name="email" type="email" required maxlength="255" autocomplete="email"
       value="{{ old('email', $email ?? auth()->user()?->email ?? '') }}"
       class="mt-1 w-full border border-input bg-card p-3 text-foreground"
       aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
       @if($errors->any()) aria-describedby="password-form-errors" @endif></div>
@if($reset)
<div><label for="password" class="block text-sm font-semibold">Nueva contraseña</label>
<input id="password" name="password" type="password" required maxlength="255" minlength="8" autocomplete="new-password"  class="mt-1 w-full border border-input bg-card p-3 text-foreground" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @if($errors->any()) aria-describedby="password-form-errors" @endif></div>
<div><label for="password_confirmation" class="block text-sm font-semibold">Repite la nueva contraseña</label>
<input id="password_confirmation" name="password_confirmation" type="password" required maxlength="255" minlength="8" autocomplete="new-password"  class="mt-1 w-full border border-input bg-card p-3 text-foreground" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @if($errors->any()) aria-describedby="password-form-errors" @endif></div>
@endif
<button type="submit" class="min-h-12 w-full bg-primary px-4 py-3 font-semibold text-primary-foreground">{{ $reset ? 'Guardar contraseña' : 'Enviar enlace' }}</button>
</form>
@if($reset)<a class="mt-5 block text-primary underline" href="{{ route('customer.password.request') }}">Solicitar otro enlace</a>@endif

@endsection
