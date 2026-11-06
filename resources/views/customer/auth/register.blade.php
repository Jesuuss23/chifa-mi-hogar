@extends('customer.auth.layout')
@section('title', 'Crear cuenta')
@push('auth-head')
    @if(config('turnstile.enabled') && config('turnstile.site_key'))
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
@endpush
@section('content')
        <h1 id="auth-title" class="mt-5 text-2xl font-bold text-foreground">
            Crear mi cuenta
        </h1>

        <p class="mt-2 text-sm text-foreground/75">
            Regístrate para consultar tus pedidos y valorar tus compras.
        </p>

        @include('customer.auth.social')

        <form action="{{ route('customer.register.store') }}"
              method="POST"
              class="mt-6 space-y-4">
            @csrf
            @include('legal.collection-notice')

            <div>
                <label for="name" class="block text-sm font-medium">Nombre</label>
                <input id="name" type="text" name="name" required maxlength="150"
                       value="{{ old('name') }}"
                       autocomplete="name"
                       class="mt-1 w-full rounded-none border border-input bg-card text-foreground p-3"
                       aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                       @if($errors->has('name')) aria-describedby="name-error" @endif>
                @error('name')
                    <p id="name-error" role="alert" class="mt-1 text-sm text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium">Correo electrónico</label>
                <input id="email" type="email" name="email" required maxlength="255"
                       value="{{ old('email') }}"
                       autocomplete="email"
                       class="mt-1 w-full rounded-none border border-input bg-card text-foreground p-3"
                       aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                       @if($errors->has('email')) aria-describedby="email-error" @endif>
                @error('email')
                    <p id="email-error" role="alert" class="mt-1 text-sm text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium">Contraseña</label>
                <input id="password" type="password" name="password" required
                       autocomplete="new-password"
                       minlength="8"
                       class="mt-1 w-full rounded-none border border-input bg-card text-foreground p-3"
                       aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                       aria-describedby="password-guidance{{ $errors->has('password') ? ' password-error' : '' }}">
                @error('password')
                    <p id="password-error" role="alert" class="mt-1 text-sm text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium">
                    Confirmar contraseña
                </label>
                <input id="password_confirmation" type="password" name="password_confirmation" required
                       autocomplete="new-password"
                       minlength="8"
                       class="mt-1 w-full rounded-none border border-input bg-card text-foreground p-3"
                       aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                       aria-describedby="password-guidance{{ $errors->has('password') ? ' password-error' : '' }}">
            </div>

            @if(config('turnstile.enabled'))
                @if(config('turnstile.site_key'))
                    <div class="cf-turnstile"
                         data-sitekey="{{ config('turnstile.site_key') }}"
                         data-action="register" data-language="es" data-size="compact"></div>
                    <noscript><p class="text-sm text-destructive">Activa JavaScript para completar la verificación.</p></noscript>
                @else
                    <p class="text-sm text-destructive">El registro no está disponible temporalmente. Inténtalo más tarde.</p>
                @endif
            @endif
            @error('cf-turnstile-response')
                <p role="alert" class="text-sm text-destructive">{{ $message }}</p>
            @enderror

            <button type="submit"
                    class="w-full rounded-none bg-primary p-3 font-semibold text-primary-foreground hover:bg-primary/90">
                Crear cuenta
            </button>
        </form>

        <p class="mt-6 text-center text-sm">
            ¿Ya tienes cuenta?
            <a href="{{ route('login') }}" class="font-semibold text-primary">
                Inicia sesión
            </a>
        </p>

@endsection
