<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\SetCurrentTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            // Sin excepciones: todas las rutas mutacionales (incluyendo logout) exigen token CSRF
        ]);

        $middleware->appendToGroup('web', [
            SecurityHeadersMiddleware::class,
            \App\Http\Middleware\EnsurePasswordIsChanged::class,
            \App\Http\Middleware\EnsureSingleSession::class,
        ]);

        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            SetCurrentTenant::class
        );

        $middleware->alias([
            'tenant' => SetCurrentTenant::class,
            'role' => CheckRole::class,
            'superadmin' => \App\Http\Middleware\EnsureUserIsSuperadmin::class,
            'must_change_password' => \App\Http\Middleware\EnsurePasswordIsChanged::class,
            'restaurant' => \App\Http\Middleware\EnsureBusinessIsRestaurant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\App\Exceptions\InsufficientStockException $e, $request) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['inventory' => [$e->getMessage()]],
                ], 422);
            }

            return redirect()->back()
                ->withInput()
                ->withErrors(['inventory' => $e->getMessage()])
                ->with('error', $e->getMessage());
        });

        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            \Illuminate\Support\Facades\Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Tu sesión ha expirado por inactividad. Por favor, inicia sesión nuevamente.',
                    'redirect' => route('login'),
                ], 419);
            }

            return redirect()->route('login')
                ->with('session_warning', 'Tu sesión ha expirado por inactividad. Por favor, inicia sesión nuevamente.');
        });
    })->create();
