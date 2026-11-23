<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b192c">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('icons/luxstay.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>@yield('title', 'LuxStay Hotels')</title>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="text-xl font-extrabold tracking-tight text-slate-900">LuxStay <span class="text-blue-700">Hotels</span></a>
            <nav class="flex items-center gap-3 text-sm font-semibold">
                <a href="#sucursales" class="hidden text-slate-600 hover:text-blue-700 sm:inline">Sucursales</a>
                <a href="#reservar" class="rounded-lg bg-blue-700 px-4 py-2 text-white hover:bg-blue-800">Reservar</a>
                <a href="{{ route('login') }}" class="text-slate-600 hover:text-blue-700">Personal</a>
            </nav>
        </div>
    </header>
    <main>
        @yield('content')
    </main>
    <footer class="border-t border-slate-200 bg-white px-4 py-6 text-center text-xs text-slate-500">
        LuxStay Hotels · Reservas sujetas a confirmación de recepción.
    </footer>
</body>
</html>
