<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LuxStay PMS - Sistema de Gestión Hotelera</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex">

    <!-- Sidebar Unificado LuxStay PMS -->
    <aside class="w-64 bg-[#0b192c] text-white flex flex-col justify-between shrink-0 shadow-xl">
        <div>
            <div class="p-6 border-b border-slate-800 flex items-center gap-3">
                <div class="bg-blue-600 p-2 rounded-lg font-bold text-xl">🏢</div>
                <div>
                    <h1 class="text-lg font-bold tracking-tight">LuxStay PMS</h1>
                    <p class="text-xs text-slate-400">
                        @if(auth()->user()->isAdmin())
                            👑 Master Multi-Sede
                        @else
                            📍 Sede: {{ auth()->user()->branch->name }}
                        @endif
                    </p>
                </div>
            </div>

            <nav class="p-4 space-y-2">
                @if(in_array(auth()->user()->role, ['admin', 'recepcion']))
                    <a href="{{ route('dashboard', ['branch_id' => request('branch_id')]) }}"
                       class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition {{ request()->routeIs('dashboard') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                        <span>📊</span> Recepción y Reservas
                    </a>
                    <a href="{{ route('reservations.create', ['branch_id' => request('branch_id')]) }}"
                       class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition {{ request()->routeIs('reservations.create') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                        <span>🛎️</span> Nuevo Check-in
                    </a>
                @endif

                <a href="{{ route('housekeeping.index', ['branch_id' => request('branch_id')]) }}"
                   class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition {{ request()->routeIs('housekeeping.*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <span>🧹</span> Centro Housekeeping
                </a>
            </nav>
        </div>

        <div class="p-4 border-t border-slate-800 bg-[#07111e]">
            <div class="mb-3">
                <p class="text-sm font-semibold truncate">{{ auth()->user()->name }}</p>
                <span class="inline-block mt-1 px-2 py-0.5 text-[11px] font-bold uppercase rounded bg-blue-500/20 text-blue-300 border border-blue-400/30">
                    Rol: {{ auth()->user()->role }}
                </span>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full py-2 px-3 bg-red-600/90 hover:bg-red-600 text-white rounded-lg text-xs font-semibold transition">
                    Cerrar Sesión Segura
                </button>
            </form>
        </div>
    </aside>

    <!-- Contenido Principal -->
    <div class="flex-1 flex flex-col">
        <!-- Topbar: Selector de los 3 locales EXCLUSIVO para el Master Admin -->
        <header class="bg-white border-b border-slate-200 px-8 py-4 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-900">@yield('title', 'Panel Operativo')</h2>
                <p class="text-xs text-slate-500">@yield('subtitle', 'Datos sincronizados en tiempo real con MySQL')</p>
            </div>

            @if(auth()->user()->isAdmin())
                <div class="flex items-center gap-2 bg-slate-100 p-1.5 rounded-xl border border-slate-200">
                    <span class="text-xs font-bold text-slate-600 px-2">Vista Master (3 Locales):</span>
                    <a href="{{ request()->fullUrlWithQuery(['branch_id' => null]) }}"
                       class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ !request('branch_id') ? 'bg-[#0b192c] text-white shadow' : 'text-slate-600 hover:bg-white' }}">
                        🌐 Consolidado (3 Sedes)
                    </a>
                    @foreach(\App\Models\Branch::all() as $b)
                        <a href="{{ request()->fullUrlWithQuery(['branch_id' => $b->id]) }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ request('branch_id') == $b->id ? 'bg-[#0b192c] text-white shadow' : 'text-slate-600 hover:bg-white' }}">
                            🏨 {{ $b->name }}
                        </a>
                    @endforeach
                </div>
            @else
                <div class="px-4 py-2 bg-slate-100 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700">
                    🏨 Sucursal Asignada: <span class="text-blue-700 font-bold">{{ auth()->user()->branch->name }}</span>
                </div>
            @endif
        </header>

        <main class="p-8 flex-1 overflow-y-auto">
            @if(session('success'))
                <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-300 text-emerald-800 text-sm font-medium flex items-center justify-between">
                    <span>✅ {{ session('success') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-300 text-red-800 text-sm">
                    <p class="font-bold mb-1">⚠️ Se detectaron errores de validación en el servidor:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>