<?php

namespace App\Console\Commands;

use App\Services\Ml\CargaHistorico;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ml:importar-historico {csvPath : CSV limpio generado por scripts/ml/etl_historico.py}')]
#[Description('Carga el histórico de facturas y boletas 2025-2026 en la tabla de entrenamiento de la predicción (reemplaza la carga anterior)')]
class ImportarHistoricoMl extends Command
{
    /**
     * @var list<string>
     */
    private const COLUMNAS = ['fecha', 'tipo_doc', 'comprobante', 'documento_cliente', 'nombre_cliente',
        'categoria', 'producto_original', 'cantidad', 'total', 'archivo_origen'];

    public function handle(CargaHistorico $cargaHistorico): int
    {
        $path = (string) $this->argument('csvPath');
        $lineas = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;

        if ($lineas === false || $lineas === []) {
            $this->error("No existe o está vacío el archivo: {$path}");

            return self::FAILURE;
        }

        $cabecera = str_getcsv(trim((string) array_shift($lineas), "\xEF\xBB\xBF \r"), escape: '');
        if ($cabecera !== self::COLUMNAS) {
            $this->error('El CSV no tiene las columnas esperadas: '.implode(', ', self::COLUMNAS));

            return self::FAILURE;
        }

        $filas = [];
        foreach ($lineas as $linea) {
            $valores = array_map(fn (?string $valor) => (string) $valor, str_getcsv(rtrim($linea, "\r"), escape: ''));

            if (count($valores) !== count(self::COLUMNAS)) {
                continue;
            }

            [$fecha, $tipoDoc, $comprobante, $documento, $nombre, $categoria, $producto, $cantidad, $total, $archivo] = $valores;

            // Solo compras reales de clientes identificables.
            if (! in_array($tipoDoc, ['F', 'B'], true) || ! preg_match('/^(\d{8}|\d{11})$/', $documento)) {
                continue;
            }

            $filas[] = [
                'fecha' => $fecha,
                'tipo_doc' => $tipoDoc,
                'comprobante' => $comprobante,
                'documento_cliente' => $documento,
                'nombre_cliente' => $nombre,
                'categoria' => $categoria,
                'producto_original' => $producto,
                'cantidad' => $cantidad,
                'total' => $total,
                'archivo_origen' => $archivo,
            ];
        }

        $cargaHistorico->reemplazar($filas);

        $this->info(count($filas).' líneas de facturas y boletas cargadas para el entrenamiento.');

        return self::SUCCESS;
    }
}
