<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Administración') | Chifa Mi Hogar</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-[#141414] text-white antialiased">

    <main class="min-h-screen flex items-center justify-center p-4">

        @yield('content')

    </main>

</body>
</html>