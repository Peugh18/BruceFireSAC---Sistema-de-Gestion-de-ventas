<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cobranzas\StoreCollectionPaymentRequest;
use App\Models\Installment;
use App\Models\SalePayment;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    /**
     * Lista de cuotas pendientes, parciales o vencidas para el vendedor autenticado.
     * Actualiza automáticamente a 'vencido' las cuotas cuya fecha de vencimiento sea anterior a hoy.
     */
    public function index(Team $current_team, Request $request): Response
    {
        Installment::query()
            ->where('estado', 'pendiente')
            ->whereDate('fecha_vencimiento', '<', today())
            ->update(['estado' => 'vencido']);

        $user = $request->user();

        $installments = Installment::query()
            ->whereIn('estado', ['pendiente', 'parcial', 'vencido'])
            ->whereHas('sale', fn ($q) => $q->where('vendedor_id', $user->id))
            ->with(['sale.client'])
            ->orderBy('fecha_vencimiento')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Installment $inst) {
                $fechaVenc = Carbon::parse($inst->fecha_vencimiento);
                $diasVencido = $fechaVenc->isPast() && ! $fechaVenc->isToday()
                    ? (int) $fechaVenc->diffInDays(today())
                    : 0;

                return [
                    'id' => $inst->id,
                    'sale_id' => $inst->sale_id,
                    'sale_numero' => $inst->sale->numero_interno,
                    'numero_cuota' => $inst->numero_cuota,
                    'monto' => $inst->monto,
                    'fecha_vencimiento' => $fechaVenc->toDateString(),
                    'estado' => $inst->estado,
                    'dias_vencido' => $diasVencido,
                    'cliente' => $inst->sale->client?->razon_social,
                ];
            });

        return Inertia::render('vendedor/cobranzas/pendientes', [
            'installments' => $installments,
        ]);
    }

    /**
     * Registra un pago sobre una cuota específica y actualiza su estado.
     */
    public function registerPayment(
        Team $current_team,
        Installment $installment,
        StoreCollectionPaymentRequest $request
    ): RedirectResponse {
        SalePayment::create([
            'sale_id' => $installment->sale_id,
            'installment_id' => $installment->id,
            'forma_pago' => $request->validated('forma_pago'),
            'monto' => $request->validated('monto'),
            'numero_operacion' => $request->validated('numero_operacion'),
            'fecha' => today(),
        ]);

        $totalPagado = (float) SalePayment::where('installment_id', $installment->id)->sum('monto');

        if ($totalPagado >= (float) $installment->monto) {
            $installment->update(['estado' => 'pagado']);
        } elseif ($totalPagado > 0) {
            $installment->update(['estado' => 'parcial']);
        }

        return back();
    }
}
