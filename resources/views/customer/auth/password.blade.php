@extends('customer.auth.layout')
@section('title', $reset ? 'Establecer contraseña' : 'Recuperar acceso')
@section('content')
<a href="{{ route('login') }}" class="text-primary underline">Volver a iniciar sesión</a>
<h1 id="auth-title" class="mt-5 text-2xl font-bold">{{ $reset ? 'Establecer contraseña' : 'Recuperar acceso' }}</h1>
@unless($reset)<p class="mt-3 text-sm">Recibe un enlace en tu correo.</p>@endunless
@if(session('password_status'))<p role="status" class="mt-4 border border-border p-3">{{ session('password_status') }}</p>@endif
@php($accessErrors = collect($errors->getMessages())->except(['password', 'password_confirmation'])->flatten())
@if($accessErrors->isNotEmpty())<ul id="password-form-errors" role="alert" class="mt-4 text-destructive">@foreach($accessErrors as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
@auth
<p class="mt-3 text-sm">Cuando recibas el enlace, cierra sesión antes de abrirlo o utiliza un navegador sin sesión iniciada.</p>
@endauth
<form method="POST" action="{{ route($reset ? 'customer.password.store' : 'customer.password.email') }}" class="mt-5 space-y-4">
@csrf
@if($reset)<input type="hidden" name="token" value="{{ $token }}">@endif
<div><label for="email" class="block text-sm font-semibold">Correo electrónico</label>
<input id="email" name="email" type="email" required maxlength="255" autocomplete="email" placeholder="tu@correo.com"
       value="{{ old('email', $email ?? auth()->user()?->email ?? '') }}"
       class="mt-1 w-full border border-input bg-card p-3 text-foreground"
       aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
       @if($errors->has('email')) aria-describedby="password-form-errors" @endif></div>
@if($reset)
<x-customer-password label="Nueva contraseña" :min-length="8" :max-length="255" :strength="true" placeholder="Mínimo 8 caracteres" />
<x-customer-password id="password_confirmation" name="password_confirmation" label="Confirmar contraseña"
    :min-length="8" :max-length="255" placeholder="Repite tu contraseña" />
@endif
<button type="submit" class="min-h-12 w-full bg-primary px-4 py-3 font-semibold text-primary-foreground">{{ $reset ? 'Guardar contraseña' : 'Enviar enlace' }}</button>
</form>
@if($reset)<a class="mt-5 block text-primary underline" href="{{ route('customer.password.request') }}">Solicitar otro enlace</a>@endif

@endsection
