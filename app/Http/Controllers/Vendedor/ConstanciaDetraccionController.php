<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cobranzas\StoreDetraccionRequest;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Team;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ConstanciaDetraccionController extends Controller
{
    public function store(Team $current_team, Sale $sale, StoreDetraccionRequest $request): RedirectResponse
    {
        abort_unless((int) $sale->vendedor_id === (int) $request->user()->id, 404);
        if ($sedeId = $request->user()->sedeRestringidaId()) {
            abort_unless((int) $sale->sede_id === $sedeId, 404);
        }

        DB::transaction(function () use ($sale, $request): void {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            if ($sale->estado !== 'confirmada' || $sale->esCredito()) {
                throw ValidationException::withMessages(['monto' => 'Acredita constancias de ventas al contado emitidas. Para crédito, registra el depósito en su cuota de Cobranzas.']);
            }

            $monto = round((float) $request->validated('monto'), 2);
            $operacion = trim((string) $request->validated('numero_operacion'));
            SalePayment::validarDetraccion($sale, $monto, 'deposito', $operacion);
            $saldo = round((float) $sale->total - (float) $sale->payments()->sum('monto'), 2);
            if ($monto > $saldo) {
                throw ValidationException::withMessages(['monto' => 'El depósito no puede superar el saldo pendiente de la venta.']);
            }

            $pago = SalePayment::create([
                'sale_id' => $sale->id,
                'forma_pago' => 'deposito',
                'es_detraccion' => true,
                'monto' => $monto,
                'numero_operacion' => $operacion,
                'fecha' => today(),
            ]);
            AuditLogger::log(action: 'detraccion.acreditada', entity: $pago, newValues: $pago->only(['sale_id', 'monto', 'numero_operacion']), userId: $request->user()->id);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Constancia de detracción acreditada. La deuda se actualizó sin ingreso en caja.']);

        return back();
    }
}
