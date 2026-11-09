<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
                    $staleSessionId = $currentSessionId;

                    // 1. Cerrar autenticación del dispositivo viejo
                    Auth::guard('web')->logout();

                    // 2. Ahora sí, purgar la sesión obsoleta de la tabla sessions
                    if (Schema::hasTable('sessions')) {
                        try {
                            DB::table('sessions')->whereIn('id', array_filter([$currentSessionId, $sessionToken]))->delete();
                        } catch (\Throwable $e) {
                            // Silencioso
                        }
                    }

                    // 3. Invalidar la sesión en la petición actual
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    $message = 'Tu sesión ha caducado o se inició sesión desde otro dispositivo.';

                    // 4. Respuesta para peticiones AJAX / JSON
                    if ($request->expectsJson() || $request->ajax()) {
                        return response()->json([
                            'message' => $message,
                            'redirect' => route('login', ['session_status' => 'device_conflict']),
                        ], 401);
                    }

                    // 5. Redirección HTTP blindada con Query Param y Flash
                    return redirect()->route('login', ['session_status' => 'device_conflict'])
                        ->with('session_warning', $message);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Error en middleware EnsureSingleSession: ' . $e->getMessage());
        }

        return $next($request);
    }
}

