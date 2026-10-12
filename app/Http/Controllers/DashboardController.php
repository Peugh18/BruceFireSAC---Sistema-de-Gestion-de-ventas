<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Este dashboard genérico del starter es un placeholder vacío. Los roles
     * de negocio tienen el suyo propio y se redirige ahí.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($request->user()->hasRole('Vendedor')) {
            return redirect()->route('vendedor.dashboard', [
                'current_team' => $request->route('current_team'),
            ]);
        }

        if ($request->user()->hasRole('Almacen')) {
            return redirect()->route('almacen.dashboard', [
                'current_team' => $request->route('current_team'),
            ]);
        }

        if ($request->user()->hasRole('Gerente')) {
            return redirect()->route('gerente.dashboard', [
                'current_team' => $request->route('current_team'),
            ]);
        }

        if ($request->user()->hasRole('TecnicoPlanta')) {
            return redirect()->route('tecnico-planta.dashboard', [
                'current_team' => $request->route('current_team'),
            ]);
        }

        if ($request->user()->hasRole('TecnicoCampo')) {
            return redirect()->route('tecnico-campo.dashboard', [
                'current_team' => $request->route('current_team'),
            ]);
        }

        return Inertia::render('dashboard');
    }
}
