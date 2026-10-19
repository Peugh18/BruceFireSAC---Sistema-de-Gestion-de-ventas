<?php

namespace App\Http\Middleware;

use App\Models\Sede;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHayVariosAlmacenes
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Sede::hayVariosAlmacenes(), 404);

        return $next($request);
    }
}
