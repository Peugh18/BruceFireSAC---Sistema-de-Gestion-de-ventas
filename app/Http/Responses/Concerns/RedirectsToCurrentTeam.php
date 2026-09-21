<?php

namespace App\Http\Responses\Concerns;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

trait RedirectsToCurrentTeam
{
    protected function redirectPathForCurrentTeam(Request $request, string $redirect): string
    {
        $team = $this->currentTeam($request);

        URL::defaults(['current_team' => $team->slug]);

        return "/{$team->slug}".$this->roleAwareHome($request, $redirect);
    }

    /**
     * El "home" genérico de Fortify (config('fortify.home'), normalmente
     * /dashboard) es un placeholder vacío del starter. Los roles de negocio
     * tienen su propio dashboard — se redirige ahí en vez del genérico.
     */
    protected function roleAwareHome(Request $request, string $default): string
    {
        $user = $request->user();

        if ($user?->hasRole('Vendedor')) {
            return '/vendedor/dashboard';
        }

        return $default;
    }

    protected function currentTeam(Request $request): Team
    {
        $user = $request->user();

        abort_if(! $user, 403);

        $team = $user->currentTeam ?? $user->personalTeam();

        abort_if(! $team, 403);

        return $team;
    }
}
