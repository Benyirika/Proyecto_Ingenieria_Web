<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $user->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'La cuenta está inactiva. Contacta a administración.',
            ]);
        }

        if ($user->isAdmin() || in_array($user->role, $roles, true)) {
            return $next($request);
        }

        abort(403, 'Tu rol no tiene permiso para esta operación.');
    }
}
