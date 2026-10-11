<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\TecnicoPlanta\ExecuteAndCloseServiceOrder;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Vendedor\Concerns\AcotaPorSede;
use App\Http\Requests\Deficiencies\StoreDeficiencyAuthorizationRequest;
use App\Models\Deficiency;
use App\Models\InventoryMovement;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\Team;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeficiencyAuthorizationController extends Controller
{
    use AcotaPorSede;

    public function store(Team $current_team, Deficiency $deficiency, StoreDeficiencyAuthorizationRequest $request, ExecuteAndCloseServiceOrder $action): RedirectResponse
    {
        $this->asegurarSede($deficiency->serviceOrder?->sede_id);

        if (! $request->boolean('autorizado')) {
            DB::transaction(function () use ($deficiency): void {
                // Bloqueada y releída (A1): dos requests a la vez no pueden
                // rechazar (ni aprobar) dos veces la misma deficiencia.
                $this->bloquearYValidar($deficiency)->update(['estado' => 'rechazada']);
            });

            $this->recordEvent($deficiency, $request->user()->id, false);
            $action->releaseFromAuthorizationHold($deficiency->serviceOrder, $request->user());

            AuditLogger::log(
                action: 'deficiencia.autorizacion',
                entity: $deficiency,
                newValues: ['resultado' => 'rechazada'],
                userId: $request->user()->id
            );

            return back();
        }

        if (! in_array($deficiency->estado, ['esperando_autorizacion', 'resuelta'], true)) {
            throw ValidationException::withMessages(['deficiency' => 'Esta deficiencia no está esperando autorización.']);
        }

        // La cotización del adicional debe ser del mismo cliente y sede de la orden.
        $orden = $deficiency->serviceOrder;
        $cotizacion = null;
        if ($request->filled('cotizacion_adicional_id')) {
            $cotizacion = Quote::query()
                ->whereKey($request->integer('cotizacion_adicional_id'))
                ->where('client_id', $orden->client_id)
                ->where(fn ($query) => $query->whereNull('sede_id')->orWhere('sede_id', $orden->sede_id))
                ->first();

            if (! $cotizacion) {
                throw ValidationException::withMessages([
                    'cotizacion_adicional_id' => 'La cotización debe ser del mismo cliente y sede de la orden.',
                ]);
            }
        }

        $linea = QuoteItem::query()->where('deficiency_id', $deficiency->id)->first();
        if ($linea) {
            $cotizacion = $linea->quote;
            if (! in_array($cotizacion->estado, ['borrador', 'emitida', 'enviada', 'aceptada'], true)) {
                throw ValidationException::withMessages(['cotizacion_adicional_id' => 'La cotización del adicional ya no está disponible.']);
            }
        }
        if (! $linea && $request->filled('importe') && ! $cotizacion) {
            throw ValidationException::withMessages(['cotizacion_adicional_id' => 'Cotiza el adicional antes de autorizar su importe.']);
        }
        $importe = $linea ? (float) $linea->subtotal : ($request->filled('importe') ? round((float) $request->input('importe'), 2) : ($cotizacion ? (float) $cotizacion->total : null));
        $venta = $orden->sale_id ? Sale::query()->find($orden->sale_id) : null;

        // V6: con la orden ya cobrada, el adicional se cobra aparte: sin
        // importe no habría qué cobrar.
        if ($venta?->estado === 'confirmada' && $importe === null) {
            throw ValidationException::withMessages([
                'importe' => 'La orden ya se cobró: indica el importe del adicional o su cotización para registrarlo como saldo por cobrar.',
            ]);
        }

        DB::transaction(function () use ($deficiency, $request, $cotizacion, $importe, $venta, $orden, $linea): void {
            // Bloqueada y releída (A1): dos requests a la vez no crean dos
            // autorizaciones ni dos cuotas por cobrar por la misma deficiencia.
            $this->bloquearYValidar($deficiency);
            $linea = QuoteItem::query()->where('deficiency_id', $deficiency->id)->lockForUpdate()->first();
            if ($linea) {
                $cotizacion = Quote::query()->lockForUpdate()->findOrFail($linea->quote_id);
                if (! in_array($cotizacion->estado, ['borrador', 'emitida', 'enviada', 'aceptada'], true) || $cotizacion->sale()->exists()) {
                    throw ValidationException::withMessages(['cotizacion_adicional_id' => 'La cotización ya no está disponible para autorizar.']);
                }
                $importe = (float) $linea->subtotal;
            }

            $autorizacion = $deficiency->authorization()->create([
                'autorizado_por' => $request->string('autorizado_por')->toString(),
                'canal' => $request->string('canal')->toString(),
                'fecha' => $request->date('fecha')->toDateString(),
                'observacion' => $request->input('observacion'),
                'cotizacion_adicional_id' => $cotizacion?->id,
                'importe' => $importe,
                'vendedor_id' => $request->user()->id,
            ]);
            $yaConsumido = InventoryMovement::query()->where('referencia_type', $deficiency->getMorphClass())->where('referencia_id', $deficiency->id)->where('tipo', 'salida_servicio')->exists();
            $deficiency->update(['estado' => $yaConsumido ? 'resuelta' : 'autorizada']);

            // Deuda real: una cuota nueva de la venta de la orden, ligada a
            // esta autorización (se ve en Cobranzas).
            if ($venta?->estado === 'confirmada' && $importe !== null && ! $linea) {
                $cuota = $venta->installments()->create([
                    'numero_cuota' => (int) $venta->installments()->max('numero_cuota') + 1,
                    'fecha_vencimiento' => today(),
                    'monto' => $importe,
                    'estado' => 'pendiente',
                    'deficiency_authorization_id' => $autorizacion->id,
                ]);

                $orden->events()->create(['tipo' => 'otro', 'user_id' => $request->user()->id, 'payload' => ['accion' => 'saldo_adicional_pendiente', 'deficiency_id' => $deficiency->id, 'installment_id' => $cuota->id, 'importe' => $importe, 'mensaje' => 'La orden ya fue cobrada; el adicional autorizado quedó como cuota por cobrar en Cobranzas.']]);
            }
        }, 3);
        $this->recordEvent($deficiency, $request->user()->id, true);
        $action->releaseFromAuthorizationHold($deficiency->serviceOrder, $request->user());

        AuditLogger::log(
            action: 'deficiencia.autorizacion',
            entity: $deficiency,
            newValues: [
                'resultado' => 'autorizada',
                'canal' => $request->string('canal')->toString(),
                'importe' => $importe,
            ],
            userId: $request->user()->id
        );

        return back();
    }

    /**
     * Relee la deficiencia bloqueada (FOR UPDATE) y revalida su estado DENTRO
     * de la transacción (A1): lo que se decide sobre ella —autorizarla y
     * crear la cuota por cobrar, o rechazarla— pasa una sola vez.
     */
    protected function bloquearYValidar(Deficiency $deficiency): Deficiency
    {
        $bloqueada = Deficiency::query()->lockForUpdate()->findOrFail($deficiency->id);

        if ($bloqueada->estado !== 'esperando_autorizacion' && ! ($bloqueada->estado === 'resuelta' && ! $bloqueada->authorization()->exists() && QuoteItem::query()->where('deficiency_id', $bloqueada->id)->exists())) {
            throw ValidationException::withMessages([
                'deficiency' => 'Esta deficiencia no está esperando autorización.',
            ]);
        }

        return $bloqueada;
    }

    protected function recordEvent(Deficiency $deficiency, int $userId, bool $autorizado): void
    {
        $deficiency->serviceOrder->events()->create([
            'tipo' => 'autorizacion_registrada',
            'user_id' => $userId,
            'payload' => [
                'deficiency_id' => $deficiency->id,
                'resultado' => $autorizado ? 'autorizada' : 'rechazada',
            ],
        ]);
    }
}
