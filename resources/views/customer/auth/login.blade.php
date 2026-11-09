@extends('customer.auth.layout')
@section('title', 'Iniciar sesión')
@section('content')
        <h1 id="auth-title" class="mt-5 text-2xl font-bold text-foreground">
            Qué gusto verte
        </h1>



        @if(session('password_status'))
            <p role="status" class="mt-4 border border-border p-3">{{ session('password_status') }}</p>
        @endif
        @include('customer.auth.social')

        <form action="{{ route('customer.login.store') }}"
              method="POST"
              class="mt-6 space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium">Correo electrónico</label>
                <input id="email" type="email" name="email" required
                       value="{{ old('email') }}"
                       autocomplete="email" placeholder="tu@correo.com"
                       class="mt-1 w-full rounded-none border border-input bg-card text-foreground p-3"
                       aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                       @if($errors->has('email')) aria-describedby="email-error" @endif>
                @error('email')
                    <p id="email-error" role="alert" class="mt-1 text-sm text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <x-customer-password autocomplete="current-password" placeholder="Tu contraseña" />

            <button type="submit"
                    class="w-full rounded-none bg-primary p-3 font-semibold text-primary-foreground hover:bg-primary/90">
                Iniciar sesión
            </button>
        </form>
        <a href="{{ route('customer.password.request') }}" class="mt-4 inline-block text-primary underline">Olvidé mi contraseña</a>

        <p class="mt-6 text-center text-sm">
            ¿No tienes cuenta?
            <a href="{{ route('customer.register') }}"
               class="font-semibold text-primary">
                Regístrate
            </a>
        </p>

@endsection
