<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cobranzas\StoreCollectionPaymentRequest;
use App\Models\CashRegister;
use App\Models\Installment;
use App\Models\SalePayment;
use App\Models\Team;
use App\Services\AuditLogger;
use App\Services\Caja\DatosDeCaja;
use App\Services\Cobranzas\CarteraDeCobranzas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    /**
     * Lista de cuotas pendientes, parciales o vencidas para el vendedor autenticado.
     */
    public function index(Team $current_team, Request $request): Response
    {
        // Cobranzas y Caja son la misma pantalla: las dos llevan el turno y las cuotas.
        return Inertia::render('vendedor/cobranzas/pendientes', [
            ...DatosDeCaja::para($request->user()),
            'installments' => CarteraDeCobranzas::cuotasPendientes($request->user()),
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
        $this->assertInstallmentAccess($request, $installment);

        if ($installment->sale->estado !== 'confirmada') {
            throw ValidationException::withMessages([
                'monto' => $installment->sale->estado === 'anulada'
                    ? 'Esta venta está anulada: su cuota ya no se cobra.'
                    : 'Esta venta todavía es un borrador: emítela antes de cobrar sus cuotas.',
            ]);
        }

        if ($request->validated('forma_pago') === 'efectivo') {
            CashRegister::exigirAbiertaParaEfectivo((int) $installment->sale->vendedor_id, 'forma_pago');
        }

        DB::transaction(function () use ($installment, $request): void {
            $installment = Installment::query()->lockForUpdate()->findOrFail($installment->id);
            $saldo = $installment->saldo();
            $monto = (float) $request->validated('monto');

            if ($monto > $saldo) {
                throw ValidationException::withMessages([
                    'monto' => 'El monto no puede superar el saldo de S/ '.number_format($saldo, 2).'.',
                ]);
            }

            SalePayment::create([
                'sale_id' => $installment->sale_id,
                'installment_id' => $installment->id,
                'forma_pago' => $request->validated('forma_pago'),
                'monto' => $monto,
                'numero_operacion' => $request->validated('numero_operacion'),
                'fecha' => today(),
            ]);

            $installment->recalcularEstado();
        });

        return back();
    }

    public function cancelPayment(Team $current_team, SalePayment $payment, Request $request): RedirectResponse
    {
        $data = $request->validate(['motivo' => ['required', 'string', 'min:3', 'max:500']]);
        $payment->loadMissing('installment.sale');
        abort_unless($payment->installment, 404);
        $this->assertInstallmentAccess($request, $payment->installment);
        abort_unless($request->user()->hasRole('Gerente') || $payment->fecha->isToday(), 403, 'Solo se puede anular un cobro del mismo día.');

        DB::transaction(function () use ($payment, $data, $request): void {
            $payment = SalePayment::query()->lockForUpdate()->findOrFail($payment->id);
            $installment = Installment::query()->lockForUpdate()->findOrFail($payment->installment_id);

            AuditLogger::log(
                action: 'cobro.anulado',
                entity: $payment,
                oldValues: $payment->only(['sale_id', 'installment_id', 'forma_pago', 'monto', 'numero_operacion', 'fecha']),
                newValues: ['motivo' => $data['motivo']],
                userId: $request->user()->id,
            );

            $payment->anular($data['motivo'], $request->user()->id);
            $installment->recalcularEstado();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cobro anulado. El saldo de la cuota se actualizó.']);

        return back();
    }

    protected function assertInstallmentAccess(Request $request, Installment $installment): void
    {
        if ($request->user()->hasRole('Gerente')) {
            return;
        }

        $installment->loadMissing('sale');
        abort_unless((int) $installment->sale->vendedor_id === (int) $request->user()->id, 404);

        if ($sedeId = $request->user()->sedeRestringidaId()) {
            abort_unless((int) $installment->sale->sede_id === $sedeId, 404);
        }
    }
}
