<?php

namespace App\Http\Middleware;

use App\Models\Client;
use App\Models\Deficiency;
use App\Models\Equipment;
use App\Models\Quote;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'roles' => fn () => $user?->getRoleNames() ?? [],
                'permissions' => fn () => $user?->getAllPermissions()->pluck('name') ?? [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
            // Sede activa (selector de header): por ahora la única sesión guardada;
            // se resuelve completamente cuando el selector de Sede se construya.
            'currentSedeId' => fn () => $request->session()->get('current_sede_id'),
            // Contadores del sidebar del Vendedor. Solo se calculan si el
            // usuario tiene ese rol (evita las consultas en páginas de otros
            // roles); antes eran placeholders fijos en el componente React.
            'sidebarCounts' => fn () => $user?->hasRole('Vendedor') ? [
                'clientes' => Client::where('activo', true)->count(),
                'cotizaciones' => Quote::where('vendedor_id', $user->id)->where('estado', 'enviada')->count(),
                'alertas' => Equipment::where(fn ($query) => $query
                    ->where('proxima_fecha_atencion', '<=', now()->addDays(7))
                    ->orWhere('proxima_prueba_hidrostatica', '<=', now()->addDays(7)))->count(),
                'deficiencias' => Deficiency::where('estado', 'esperando_autorizacion')->count(),
            ] : null,
        ];
    }
}
