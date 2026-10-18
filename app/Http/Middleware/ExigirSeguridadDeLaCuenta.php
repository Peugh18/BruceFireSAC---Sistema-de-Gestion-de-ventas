<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // Una cuenta suspendida tampoco puede conservar una sesión recordada
        // ni completar un desafío 2FA iniciado antes de la suspensión.
        $pendiente = $user === null && $request->session()->has('login.id')
            ? User::query()->whereKey($request->session()->get('login.id'))->first()
            : null;
        $cuenta = $user ?? $pendiente;
        if ($cuenta !== null && ! (bool) ($cuenta->getAttribute('activo') ?? true)) {
            Auth::guard(config('fortify.guard', 'web'))->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $mensaje = 'Tu cuenta está desactivada. Consulta con el gerente.';

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => $mensaje], 403);
            }

            return redirect()->route('login')->withErrors(['email' => $mensaje]);
        }

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
