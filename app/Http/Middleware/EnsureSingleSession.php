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
        if ($request->is('logout')) {
            return $next($request);
        }

        try {
            $user = $request->user();

            if ($user && ! empty($user->current_session_id)) {
                $currentSessionId = $request->session()->getId();
                $sessionToken = $request->session()->get('auth_session_token', $currentSessionId);

                // Si no coincide con la sesión activa registrada en el usuario (Kick-out):
                if ($currentSessionId !== $user->current_session_id && $sessionToken !== $user->current_session_id) {
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    $message = 'Tu sesión ha caducado o se inició sesión desde otro dispositivo.';

                    if ($request->expectsJson() || $request->ajax()) {
                        return response()->json([
                            'message' => $message,
                        ], 401);
                    }

                    return redirect()->route('login')
                        ->with('warning', $message)
                        ->withErrors([
                            'email' => $message,
                        ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Error en middleware EnsureSingleSession: ' . $e->getMessage());
        }

        return $next($request);
    }
}

