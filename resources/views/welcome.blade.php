<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <title>Dashboard | Chifa Mi Hogar</title>
</head>
<body>
    <h1>Panel administrativo</h1>

    <p>
        Bienvenido, {{ auth()->user()->name }}
    </p>

    <form method="POST"
          action="{{ route('admin.logout') }}">
        @csrf
        <button type="submit">Cerrar sesión</button>
    </form>
</body>
</html>