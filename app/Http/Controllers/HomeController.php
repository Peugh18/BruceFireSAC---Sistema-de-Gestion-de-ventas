<?php

namespace App\Http\Controllers;

use App\Http\Responses\Concerns\RedirectsToCurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;

class HomeController extends Controller
{
    use RedirectsToCurrentTeam;

    /**
     * Sistema empresarial sin landing pública: sin sesión se va directo al
     * login, con sesión se va directo a su panel (no tiene sentido mostrar
     * una pantalla intermedia de bienvenida).
     */
    public function __invoke(Request $request): RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        return redirect($this->redirectPathForCurrentTeam($request, Fortify::redirects('login')));
    }
}
