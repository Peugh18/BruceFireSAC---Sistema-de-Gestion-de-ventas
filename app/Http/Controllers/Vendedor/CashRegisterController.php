<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Cash\CloseCashRegister;
use App\Actions\Cash\OpenCashRegister;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cash\StoreCloseCashRegisterRequest;
use App\Http\Requests\Cash\StoreOpenCashRegisterRequest;
use App\Models\CashRegister;
use App\Models\SalePayment;
use App\Models\Sede;
use App\Models\Team;
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
        $user = $request->user();

        /** @var CashRegister|null $turnoActual */
        $turnoActual = CashRegister::query()
            ->with('sede')
            ->where('vendedor_id', $user->id)
            ->where('estado', 'abierto')
            ->first();

        $ventasPorFormaPago = [];
        if ($turnoActual) {
            $ventasPorFormaPago = SalePayment::query()
                ->whereHas('sale', fn ($q) => $q->where('vendedor_id', $user->id))
                ->whereBetween('created_at', [$turnoActual->fecha_apertura, now()])
                ->selectRaw('forma_pago, SUM(monto) as total')
                ->groupBy('forma_pago')
                ->pluck('total', 'forma_pago')
                ->map(fn ($total) => round((float) $total, 2))
                ->all();
        }

        $cierres = CashRegister::query()
            ->with('sede')
            ->where('vendedor_id', $user->id)
            ->where('estado', 'cerrado')
            ->orderByDesc('fecha_cierre')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (CashRegister $cr) => [
                'id' => $cr->id,
                'sede' => $cr->sede?->nombre,
                'fecha_apertura' => $cr->fecha_apertura?->toDateTimeString(),
                'fecha_cierre' => $cr->fecha_cierre?->toDateTimeString(),
                'monto_apertura' => $cr->monto_apertura,
                'monto_contado_cierre' => $cr->monto_contado_cierre,
                'monto_esperado_calculado' => $cr->monto_esperado_calculado,
                'diferencia' => $cr->diferencia,
                'observacion' => $cr->observacion,
                'estado' => $cr->estado,
            ]);

        $sedes = Sede::query()
            ->where('activo', true)
            ->get(['id', 'nombre', 'tipo']);

        return Inertia::render('vendedor/cobranzas/index', [
            'turno_actual' => $turnoActual ? [
                'id' => $turnoActual->id,
                'sede' => $turnoActual->sede?->nombre,
                'sede_id' => $turnoActual->sede_id,
                'fecha_apertura' => $turnoActual->fecha_apertura?->toDateTimeString(),
                'monto_apertura' => $turnoActual->monto_apertura,
                'estado' => $turnoActual->estado,
                'ventas_por_forma_pago' => $ventasPorFormaPago,
            ] : null,
            'cierres' => $cierres,
            'sedes' => $sedes,
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
            $request->validated('sede_id') ? (int) $request->validated('sede_id') : null,
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
