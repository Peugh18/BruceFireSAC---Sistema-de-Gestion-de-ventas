<?php

namespace App\Http\Middleware;

use App\Models\Deficiency;
use App\Models\ServiceOrder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTechnicalOrderAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $order = $request->route('service_order') ?? $request->route('serviceOrder');

        if (! $order && $request->route('deficiency') instanceof Deficiency) {
            $order = $request->route('deficiency')->serviceOrder;
        }

        if ($order instanceof ServiceOrder) {
            // Su sede o una tienda que depende de ella, y no asignada a otro.
            abort_unless($order->atendiblePor($request->user()), 404);
        }

        return $next($request);
    }
}
