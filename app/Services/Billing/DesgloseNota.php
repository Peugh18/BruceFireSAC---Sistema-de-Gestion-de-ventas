<?php

namespace App\Services\Billing;

use App\Models\ElectronicDocument;
use Illuminate\Validation\ValidationException;

class DesgloseNota
{
    /** @return array<string, mixed> */
    public function calcular(ElectronicDocument $original, string $tipo, string $motivo, float $importe): array
    {
        if (in_array($motivo, ['11', '12'], true)) {
            throw ValidationException::withMessages(['motivo_catalogo' => 'Esta operación requiere datos de exportación o IVAP que el formulario aún no recoge.']);
        }
        if ($tipo === 'nota_credito' && $motivo === '13') {
            throw ValidationException::withMessages(['motivo_catalogo' => 'Para corregir una venta a crédito se necesitan el monto neto pendiente y las cuotas corregidas. Este formulario solo recoge un importe.']);
        }
        $datos = app(DatosEmision::class)->recuperar($original);
        if ($datos === null) {
            throw ValidationException::withMessages(['electronic_document_id' => 'Falta el XML o la copia del comprobante original para determinar su afectación.']);
        }
        $lineas = $datos['lineas'];
        if ($tipo === 'nota_debito' && $motivo === '13') {
            $afectacion = '30';
        } else {
            $tipos = array_unique(array_column($lineas, 'tipo_afectacion_igv'));
            if (array_diff($tipos, ['10', '20', '30']) !== []) {
                throw ValidationException::withMessages(['electronic_document_id' => 'El original contiene afectaciones que requieren datos fiscales adicionales. Este formulario solo admite operaciones gravadas, exoneradas o inafectas.']);
            }
            if (count($tipos) !== 1) {
                if ($tipo === 'nota_credito' && in_array($motivo, ['01', '02', '06'], true) && abs($importe - $datos['totales']['total']) < 0.009) {
                    return ['cliente' => $datos['cliente'], 'lineas' => $lineas];
                }
                throw ValidationException::withMessages(['importe' => 'El comprobante combina afectaciones. Debes identificar las líneas e importes a ajustar; no se distribuye el importe automáticamente.']);
            }
            $afectacion = $tipos[0];
        }
        if (! in_array($afectacion, ['10', '20', '30'], true)) {
            throw ValidationException::withMessages(['electronic_document_id' => 'La afectación del original requiere datos fiscales que este formulario no recoge.']);
        }
        $desglose = $afectacion === '10' ? PrecioConIgv::desglosar($importe) : ['base' => $importe, 'igv' => 0.0];

        return ['cliente' => $datos['cliente'], 'lineas' => [[
            'codigo' => $tipo === 'nota_credito' ? 'NC-01' : 'ND-01',
            'nombre' => $tipo === 'nota_credito' ? GreenterService::descripcionMotivoCredito($motivo) : GreenterService::descripcionMotivoDebito($motivo),
            'unidad_medida' => 'ZZ', 'cantidad' => 1, 'precio_unitario' => $importe,
            'valor_unitario' => $desglose['base'], 'base' => $desglose['base'], 'igv' => $desglose['igv'],
            'subtotal' => $importe, 'descuento' => 0.0, 'tipo_afectacion_igv' => $afectacion,
        ]]];
    }
}
