<?php

namespace App\Console\Commands;

use App\Models\MlVentaHistorica;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('ml:importar-historico {csvPath : CSV limpio generado por scripts/ml/etl_historico.py}')]
#[Description('Carga el histórico de facturas y boletas 2025-2026 en la tabla de entrenamiento de la predicción (reemplaza la carga anterior)')]
class ImportarHistoricoMl extends Command
{
    /**
     * @var list<string>
     */
    private const COLUMNAS = ['fecha', 'tipo_doc', 'comprobante', 'documento_cliente', 'nombre_cliente',
        'categoria', 'producto_original', 'cantidad', 'total', 'archivo_origen'];

    public function handle(): int
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
            $valores = str_getcsv(rtrim($linea, "\r"), escape: '');
            $fila = array_combine(self::COLUMNAS, $valores);

            // Solo compras reales de clientes identificables.
            if (! in_array($fila['tipo_doc'], ['F', 'B'], true) || ! preg_match('/^(\d{8}|\d{11})$/', $fila['documento_cliente'])) {
                continue;
            }

            $filas[] = $fila;
        }

        DB::transaction(function () use ($filas): void {
            MlVentaHistorica::query()->delete();

            foreach (array_chunk($filas, 1000) as $lote) {
                MlVentaHistorica::query()->insert($lote);
            }
        });

        $this->info(count($filas).' líneas de facturas y boletas cargadas para el entrenamiento.');

        return self::SUCCESS;
    }
}
