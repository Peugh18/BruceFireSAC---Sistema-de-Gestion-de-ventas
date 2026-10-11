<?php

namespace App\Services\Billing;

use App\Models\ElectronicDocument;
use Illuminate\Validation\ValidationException;

class DesgloseNota
{
    /**
     * @param  array<string, mixed>  $opciones  `lineas` (indice, descripcion, importe, cantidad) para los motivos 03, 05 y 07.
     * @return array<string, mixed>
     */
    public function calcular(ElectronicDocument $original, string $tipo, string $motivo, float $importe, array $opciones = []): array
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
        if ($tipo === 'nota_credito' && in_array($motivo, ['03', '05', '07'], true)) {
            return ['cliente' => $datos['cliente'], 'lineas' => $this->lineasPorMotivo($lineas, $motivo, $importe, $opciones['lineas'] ?? [])];
        }
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

    /**
     * Motivos que se aplican a líneas concretas del original: 03 corrige la
     * descripción (importe 0), 05 descuenta un importe por ítem y 07 devuelve
     * una cantidad por ítem. Nunca se reparte un importe suelto entre líneas.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @param  list<array<string, mixed>>  $seleccion
     * @return list<array<string, mixed>>
     */
    protected function lineasPorMotivo(array $lineas, string $motivo, float $importe, array $seleccion): array
    {
        if ($seleccion === []) {
            throw ValidationException::withMessages(['importe' => 'Indica las líneas del comprobante a las que se aplica la nota; no se reparte el importe automáticamente.']);
        }
        if ($motivo === '03' && abs($importe) > 0.001) {
            throw ValidationException::withMessages(['importe' => 'La corrección de descripción no lleva importe.']);
        }

        $resultado = [];
        $suma = 0.0;
        foreach ($seleccion as $elegida) {
            $original = $lineas[$elegida['indice'] ?? -1] ?? null;
            if ($original === null) {
                throw ValidationException::withMessages(['importe' => 'Una de las líneas elegidas no existe en el comprobante original.']);
            }
            if (! in_array($original['tipo_afectacion_igv'], ['10', '20', '30'], true)) {
                throw ValidationException::withMessages(['importe' => 'La línea elegida tiene una afectación que este formulario no admite.']);
            }
            $linea = ['codigo' => $original['codigo'], 'nombre' => $original['nombre'], 'unidad_medida' => $original['unidad_medida'], 'tipo_afectacion_igv' => $original['tipo_afectacion_igv'], 'descuento' => 0.0, 'descuento_base' => 0.0];
            $totalLinea = round($original['base'] + $original['igv'], 2);

            if ($motivo === '03') {
                $descripcion = trim((string) ($elegida['descripcion'] ?? ''));
                if ($descripcion === '') {
                    throw ValidationException::withMessages(['importe' => 'Escribe la descripción corregida de cada línea elegida.']);
                }
                $resultado[] = ['nombre' => $descripcion, 'cantidad' => $original['cantidad'], 'precio_unitario' => 0.0, 'valor_unitario' => 0.0, 'base' => 0.0, 'igv' => 0.0, 'subtotal' => 0.0] + $linea;

                continue;
            }

            if ($motivo === '05') {
                $monto = round((float) ($elegida['importe'] ?? 0), 2);
                if ($monto <= 0 || $monto > $totalLinea + 0.001) {
                    throw ValidationException::withMessages(['importe' => 'El descuento de cada línea debe ser mayor que cero y no superar lo que se cobró por ella.']);
                }
                $cantidad = 1.0;
            } else {
                $cantidad = (float) ($elegida['cantidad'] ?? 0);
                if ($cantidad <= 0 || $cantidad > (float) $original['cantidad'] + 0.0001) {
                    throw ValidationException::withMessages(['importe' => 'La cantidad devuelta de cada línea debe ser mayor que cero y no superar la vendida.']);
                }
                $monto = round($totalLinea * $cantidad / (float) $original['cantidad'], 2);
            }

            $partes = $original['tipo_afectacion_igv'] === '10' ? PrecioConIgv::desglosar($monto) : ['base' => $monto, 'igv' => 0.0];
            $suma += $monto;
            $resultado[] = $linea + ['cantidad' => $cantidad, 'precio_unitario' => round($monto / $cantidad, 2), 'valor_unitario' => round($partes['base'] / $cantidad, 2), 'base' => $partes['base'], 'igv' => $partes['igv'], 'subtotal' => $monto];
        }

        if ($motivo !== '03' && abs($suma - $importe) > 0.011) {
            throw ValidationException::withMessages(['importe' => 'El importe de la nota debe ser igual a la suma de las líneas elegidas (S/ '.number_format($suma, 2).').']);
        }

        return $resultado;
    }
}
