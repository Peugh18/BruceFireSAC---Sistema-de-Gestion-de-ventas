<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cobranzas\StoreCollectionPaymentRequest;
use App\Models\CashRegister;
use App\Models\Installment;
use App\Models\SalePayment;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\Carbon;
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
            ...CashRegisterController::datosDeCaja($request->user()),
            'installments' => self::cuotasPendientes($request->user()),
        ]);
    }

    /**
     * Cuotas pendientes, parciales o vencidas de las ventas emitidas del
     * vendedor (y las pagadas hoy).
     *
     * @return array<string, mixed>
     */
    public static function cuotasPendientes(User $user): array
    {

        return Installment::query()
            ->where(function ($query) {
                $query->whereIn('estado', ['pendiente', 'parcial', 'vencido'])
                    ->orWhereHas('payments', fn ($payments) => $payments->whereDate('fecha', today()));
            })
            // Solo ventas emitidas: un borrador o una venta anulada no se cobra.
            ->whereHas('sale', fn ($q) => $q->where('vendedor_id', $user->id)
                ->where('estado', 'confirmada')
                ->when($user->sedeRestringidaId(), fn ($sq, $sedeId) => $sq->where('sede_id', $sedeId)))
            ->with([
                'sale.client',
                'payments' => fn ($query) => $query->latest('id'),
                'paymentsAnulados' => fn ($query) => $query->with('anuladoPor:id,name')->latest('deleted_at'),
            ])
            ->orderBy('fecha_vencimiento')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Installment $inst) use ($user) {
                $fechaVenc = Carbon::parse($inst->fecha_vencimiento);
                $diasVencido = $fechaVenc->isPast() && ! $fechaVenc->isToday()
                    ? (int) $fechaVenc->diffInDays(today())
                    : 0;

                $saldo = max(0, round((float) $inst->monto - (float) $inst->payments->sum('monto'), 2));

                return [
                    'id' => $inst->id,
                    'sale_id' => $inst->sale_id,
                    'sale_numero' => $inst->sale->numero_interno,
                    'numero_cuota' => $inst->numero_cuota,
                    'monto' => $inst->monto,
                    'saldo' => $saldo,
                    'fecha_vencimiento' => $fechaVenc->toDateString(),
                    'estado' => $inst->estado,
                    'dias_vencido' => $diasVencido,
                    'cliente' => $inst->sale->client?->razon_social,
                    'pagos' => $inst->payments->map(fn (SalePayment $payment) => [
                        'id' => $payment->id,
                        'monto' => $payment->monto,
                        'forma_pago' => $payment->forma_pago,
                        'fecha' => $payment->fecha->toDateString(),
                        'puede_anular' => $user->hasRole('Gerente') || $payment->fecha->isToday(),
                    ])->values(),
                    // Cobros anulados: quedan a la vista, tachados, con el motivo.
                    'anulados' => $inst->paymentsAnulados->map(fn (SalePayment $payment) => [
                        'id' => $payment->id,
                        'monto' => $payment->monto,
                        'forma_pago' => $payment->forma_pago,
                        'motivo' => $payment->anulado_motivo,
                        'por' => $payment->anuladoPor?->name,
                    ])->values(),
                ];
            })
            ->toArray();
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
            $saldo = max(0, round((float) $installment->monto - (float) $installment->payments()->sum('monto'), 2));
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

            $this->recalculateInstallmentState($installment);
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
            $this->recalculateInstallmentState($installment);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cobro anulado. El saldo de la cuota se actualizó.']);

        return back();
    }

    protected function recalculateInstallmentState(Installment $installment): void
    {
        $totalPagado = (float) $installment->payments()->sum('monto');
        $estado = match (true) {
            $totalPagado >= (float) $installment->monto => 'pagado',
            $totalPagado > 0 => 'parcial',
            $installment->fecha_vencimiento->isBefore(today()) => 'vencido',
            default => 'pendiente',
        };

        $installment->update(['estado' => $estado]);
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
