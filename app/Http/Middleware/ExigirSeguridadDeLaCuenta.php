<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * X3: quien entra con la contraseña inicial que le dio el Gerente debe
 * cambiarla antes de usar el sistema, y el Gerente debe tener activa la
 * verificación en dos pasos. Mientras tanto solo puede usar la pantalla de
 * Seguridad (y lo que ella necesita) o salir.
 */
class ExigirSeguridadDeLaCuenta
{
    /**
     * Rutas que siguen abiertas mientras la cuenta no cumple.
     *
     * @var list<string>
     */
    protected const RUTAS_PERMITIDAS = [
        'security.edit',
        'user-password.update',
        'logout',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
        'two-factor.*',
        'passkey.*',
        'verification.*',
        'appearance.edit',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $request->routeIs(...self::RUTAS_PERMITIDAS)) {
            return $next($request);
        }

        $mensaje = match (true) {
            (bool) $user->must_change_password => 'Cambia tu contraseña inicial para continuar.',
            config('seguridad.exigir_2fa_gerente') && $user->hasRole('Gerente') && ! $user->hasEnabledTwoFactorAuthentication() => 'Activa la verificación en dos pasos para continuar. Es obligatoria para el Gerente.',
            default => null,
        };

        if ($mensaje === null) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $mensaje], 403);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $mensaje]);

        return redirect()->route('security.edit');
    }
}
