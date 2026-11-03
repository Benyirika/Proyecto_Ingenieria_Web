<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - LuxStay PMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-cover bg-center relative"
      style="background-image: url('https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1920&q=80');">
    
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>

    <div class="relative z-10 bg-white w-full max-w-md rounded-2xl shadow-2xl p-8 border border-slate-100">
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-[#0b192c] text-white text-2xl mb-3 shadow">
                🏢
            </div>
            <h1 class="text-2xl font-extrabold text-[#0b192c]">LuxStay PMS</h1>
            <p class="text-sm text-slate-500">Gestión Operativa Hotelera · Autenticación Centralizada</p>
        </div>

        @if($errors->any())
            <div class="mb-5 p-3.5 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 font-medium">
                @foreach($errors->all() as $error)
                    <p>• {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('login.post') }}" method="POST" class="space-y-5">
            @csrf
            <div>
                <label class="block text-xs font-bold uppercase text-slate-700 mb-1.5">Correo Corporativo</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       placeholder="ejemplo@luxstay.com"
                       class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-[#0b192c]">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-700 mb-1.5">Contraseña</label>
                <input type="password" name="password" required
                       placeholder="••••••••"
                       class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-[#0b192c]">
            </div>

            <button type="submit"
                    class="w-full py-3.5 bg-[#0b192c] hover:bg-slate-800 text-white font-semibold rounded-xl shadow-lg transition flex items-center justify-center gap-2 text-sm">
                <span>Acceder al Sistema</span>
                <span>→</span>
            </button>
        </form>

       
    </div>
</body>
</html>