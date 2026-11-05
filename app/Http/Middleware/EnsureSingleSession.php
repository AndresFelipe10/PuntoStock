<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureSingleSession
{
    /**
     * Handle an incoming request to ensure only one active session per user.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = $request->user();

            if ($user && ! empty($user->current_session_id)) {
                $sessionToken = $request->session()->get('auth_session_token');

                // Si el usuario tiene un current_session_id registrado por un login real
                // y el token de esta sesión no coincide (porque inició sesión en otro dispositivo):
                if ($sessionToken !== $user->current_session_id) {
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    if ($request->expectsJson() || $request->ajax()) {
                        return response()->json([
                            'message' => 'Tu sesión fue cerrada porque se inició sesión en otro dispositivo.',
                        ], 401);
                    }

                    return redirect()->route('login')->withErrors([
                        'email' => 'Tu sesión se cerró automáticamente porque tu cuenta inició sesión en otro dispositivo.',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Error en middleware EnsureSingleSession: ' . $e->getMessage());
        }

        return $next($request);
    }
}

