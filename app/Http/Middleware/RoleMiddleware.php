<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // <-- 1. Importar la fachada Auth
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // 2. Usar Auth::check()
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        // 3. Usar Auth::user()
        $userRole = Auth::user()->role;

        // El Master Admin siempre tiene acceso total, o verificamos si el rol está permitido
        if ($userRole === 'admin' || in_array($userRole, $roles)) {
            return $next($request);
        }

        // Caso de error controlado para la sustentación (Acceso no autorizado)
        abort(403, 'ACCESO RESTRINGIDO: Tu rol (' . strtoupper($userRole) . ') no tiene privilegios para acceder a este módulo operativo.');
    }
}