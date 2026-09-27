<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureTieneSede
{
    /**
     * Un trabajador (no Gerente) sin sede asignada vería los datos de todas
     * las sedes: se le muestra que pida su sede al Gerente.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (config('app.exigir_sede') && $user && ! $user->sede_id && $user->necesitaSede()) {
            return Inertia::render('errors/sin-sede', [
                'nombre' => $user->name,
            ])->toResponse($request)->setStatusCode(403);
        }

        return $next($request);
    }
}
