<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectByRole(Auth::user());
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt([...$credentials, 'is_active' => true])) {
            $user = Auth::user()->load('branch');

            if (! $user->isAdmin() && (! $user->branch_id || ! $user->branch?->is_active)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'No se pudo validar el acceso del usuario.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();

            return $this->redirectByRole($user);
        }

        return back()->withErrors([
            'email' => 'No se pudo validar el acceso del usuario.',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function redirectByRole(User $user): RedirectResponse
    {
        return $user->role === 'limpieza'
            ? redirect()->route('housekeeping.index')
            : redirect()->route('dashboard');
    }
}
