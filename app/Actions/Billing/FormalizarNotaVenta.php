<?php

namespace App\Actions\Billing;

use App\Actions\Sales\ConfirmSale;
use App\Models\ElectronicDocument;
use App\Models\Sale;
use App\Services\AuditLogger;
use App\Services\Billing\DetraccionCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FormalizarNotaVenta
{
    public function __construct(
        protected ConfirmSale $confirmSale,
        protected EmitElectronicDocument $emitElectronicDocument,
        protected DetraccionCalculator $detraccion,
    ) {}

    public function handle(Sale $sale, string $tipo): ElectronicDocument
    {
        return DB::transaction(function () use ($sale, $tipo): ElectronicDocument {
            $bloqueada = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            if (! in_array($tipo, ['factura', 'boleta'], true) || $bloqueada->estado !== 'confirmada' || $bloqueada->numero_nota_venta === null) {
                throw ValidationException::withMessages(['comprobante_tipo' => 'Solo una nota de venta confirmada puede formalizarse como factura o boleta.']);
            }

            $existente = $bloqueada->electronicDocuments()->whereIn('tipo', ['factura', 'boleta'])->first();
            if ($existente !== null) {
                if ($existente->tipo === $tipo && $bloqueada->comprobante_tipo === $tipo) {
                    return $existente;
                }

                throw ValidationException::withMessages(['comprobante_tipo' => 'Esta operación ya tiene un comprobante electrónico. Usa su flujo de corrección; no emitas otra venta.']);
            }

            if (! $bloqueada->esNotaVenta()) {
                throw ValidationException::withMessages(['comprobante_tipo' => 'Esta venta ya no es una nota de venta.']);
            }

            $bloqueada->forceFill(['comprobante_tipo' => $tipo, 'codigo_detraccion_snapshot' => null, 'monto_detraccion_snapshot' => null]);
            $this->confirmSale->validarAntesDeEmitir($bloqueada);
            $calculada = $this->detraccion->paraVenta($bloqueada);
            $neto = round((float) $bloqueada->total - $calculada['monto'], 2);
            $cobrado = round((float) $bloqueada->payments()->where('es_detraccion', false)->sum('monto') - (float) $bloqueada->refunds()->sum('monto'), 2);

            if ($calculada['aplica'] && $cobrado > $neto) {
                throw ValidationException::withMessages(['comprobante_tipo' => 'Los cobros comerciales registrados superan el neto de esta factura con detracción. Regulariza el cobro o la devolución real antes de formalizar; no se creará un depósito ni se moverá caja automáticamente.']);
            }

            $bloqueada->save();
            $this->confirmSale->guardarDetraccion($bloqueada);
            $documento = $this->emitElectronicDocument->programar($bloqueada);

            AuditLogger::log(action: 'venta.nota_venta_formalizada', entity: $bloqueada, newValues: ['numero_nota_venta' => $bloqueada->numero_nota_venta, 'electronic_document_id' => $documento->id, 'comprobante_tipo' => $tipo], userId: auth()->user()?->getKey() === null ? null : (int) auth()->user()->getKey());

            return $documento;
        });
    }
}
