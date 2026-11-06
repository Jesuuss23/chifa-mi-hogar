@extends('layouts.admin-auth')

@section('title', 'Acceso administrativo')

@section('content')
<section class="admin-auth-panel" aria-labelledby="admin-auth-title">
    <p class="admin-auth-eyebrow">PANEL ADMINISTRATIVO</p>
    <h1 id="admin-auth-title">Todo listo para<br>un nuevo pedido.</h1>
    <p class="admin-auth-intro">Ingresa con tu cuenta de administrador para gestionar el restaurante.</p>

    @if($errors->any())
        <div role="alert" class="admin-auth-errors">
            <p class="font-semibold">No pudimos iniciar sesión</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.login.submit') }}" class="admin-auth-form">
        @csrf
        <div>
            <label for="email">Correo electrónico</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   autocomplete="username" autocapitalize="none" spellcheck="false" required
                   aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                   @if($errors->has('email')) aria-describedby="admin-email-error" @endif>
            @error('email')
                <p id="admin-email-error" class="admin-auth-field-error">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="password">Contraseña</label>
            <input id="password" type="password" name="password" autocomplete="current-password" required
                   aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                   @if($errors->has('password')) aria-describedby="admin-password-error" @endif>
            @error('password')
                <p id="admin-password-error" class="admin-auth-field-error">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit">Ingresar al panel <span aria-hidden="true">→</span></button>
    </form>
    <p class="admin-auth-notice">Acceso exclusivo para personal autorizado.</p>
</section>
@endsection
