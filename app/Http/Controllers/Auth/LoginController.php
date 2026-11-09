<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user();

        // 1. Regenerar la sesión actual
        $request->session()->regenerate();

        // 2. Asignar la nueva sesión activa al usuario
        $newSessionId = session()->getId();
        if (Schema::hasColumn('users', 'current_session_id')) {
            $user->update([
                'current_session_id' => $newSessionId,
            ]);
        }
        $request->session()->put('auth_session_token', $newSessionId);

        if ($user->is_superadmin) {
            if ($user->must_change_password) {
                return redirect()->route('password.change');
            }

            return redirect()->intended(route('superadmin.dashboard'));
        }

        // Usuario normal de negocio: verificar membresía activa y estado del negocio
        $membership = $user->businesses()->wherePivot('is_active', true)->first();
        $userIsActive = ! (isset($user->is_active) && ! $user->is_active);
        $businessIsActive = $membership && ($membership->status === 'active' && (bool) $membership->is_active);

        if (! $userIsActive || ! $membership || ! $businessIsActive) {
            try {
                if (Schema::hasColumn('users', 'current_session_id')) {
                    $user->update(['current_session_id' => null]);
                }
            } catch (\Throwable $e) {
                // Silencioso ante rollback de cuenta inactiva
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Tu cuenta no está activa. Contacta a soporte.'])
                ->with('error', 'Tu cuenta no está activa. Contacta a soporte.');
        }

        if ($user->must_change_password) {
            return redirect()->route('password.change');
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        try {
            $user = $request->user();

            if ($user && Schema::hasColumn('users', 'current_session_id')) {
                $user->update(['current_session_id' => null]);
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo limpiar token de sesión única en logout: ' . $e->getMessage());
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
