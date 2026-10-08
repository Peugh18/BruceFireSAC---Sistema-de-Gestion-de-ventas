<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Cash\CloseCashRegister;
use App\Actions\Cash\OpenCashRegister;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cash\StoreCloseCashRegisterRequest;
use App\Http\Requests\Cash\StoreOpenCashRegisterRequest;
use App\Models\CashRegister;
use App\Models\Team;
use App\Services\Caja\DatosDeCaja;
use App\Services\Cobranzas\CarteraDeCobranzas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashRegisterController extends Controller
{
    /**
     * Muestra la vista de caja del vendedor: turno actual (si existe) y cierres pasados.
     */
    public function show(Team $current_team, Request $request): Response
    {
        // Cobranzas y Caja son la misma pantalla: las dos llevan el turno y las cuotas.
        return Inertia::render('vendedor/cobranzas/index', [
            ...DatosDeCaja::para($request->user()),
            'installments' => CarteraDeCobranzas::cuotasPendientes($request->user()),
        ]);
    }

    /**
     * Abre un nuevo turno de caja para el vendedor autenticado.
     */
    public function open(
        Team $current_team,
        StoreOpenCashRegisterRequest $request,
        OpenCashRegister $action
    ): RedirectResponse {
        $action->handle(
            $request->user(),
            $request->user()->sedeRestringidaId() ?? ($request->validated('sede_id') ? (int) $request->validated('sede_id') : null),
            (float) $request->validated('monto_apertura')
        );

        return back();
    }

    /**
     * Cierra el turno de caja especificado. Arqueo ciego: el vendedor declara su contado.
     */
    public function close(
        Team $current_team,
        CashRegister $cash_register,
        StoreCloseCashRegisterRequest $request,
        CloseCashRegister $action
    ): RedirectResponse {
        if ($cash_register->vendedor_id !== $request->user()->id) {
            abort(403);
        }

        $action->handle(
            $cash_register,
            (float) $request->validated('monto_contado_cierre'),
            $request->validated('observacion')
        );

        return back();
    }
}
