<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $request->session()->regenerate();

        $user = $request->user();

        // Control de Sesión Única (Anti-multisesión)
        $sessionToken = (string) Str::uuid();
        $request->session()->put('auth_session_token', $sessionToken);
        $user->forceFill(['current_session_id' => $sessionToken])->saveQuietly();

        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

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
            $user->forceFill(['current_session_id' => null])->saveQuietly();
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
        $user = $request->user();

        if ($user && $user->current_session_id === $request->session()->get('auth_session_token')) {
            $user->forceFill(['current_session_id' => null])->saveQuietly();
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
