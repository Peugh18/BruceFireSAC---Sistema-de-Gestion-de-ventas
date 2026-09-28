<?php

namespace App\Console\Commands;

use App\Services\Ml\HistorialCompras;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ml:export-retention-dataset
    {--horizonte=6 : Meses en los que se mide si el cliente vuelve a comprar}
    {--cortes= : Fechas de corte YYYY-MM-DD separadas por coma (por defecto, tres cortes trimestrales hasta la última fecha con datos)}
    {--output= : Ruta destino del archivo CSV}')]
#[Description('Exporta el dataset de recompra (histórico + ventas del sistema) para entrenar el modelo')]
class ExportRetentionTrainingData extends Command
{
    public function handle(HistorialCompras $historial): int
    {
        $horizonte = max(1, (int) $this->option('horizonte'));
        $outputPath = (string) ($this->option('output') ?: storage_path('app/ml/retention_training_dataset.csv'));
        $comprasPorCliente = $historial->porDocumento();

        if ($comprasPorCliente->isEmpty()) {
            $this->warn('No hay compras para armar el dataset. Carga primero el histórico con ml:importar-historico.');

            return self::FAILURE;
        }

        $cortes = $this->cortes($comprasPorCliente->flatten(1)->max(fn (array $compra) => $compra['fecha']->getTimestamp()), $horizonte);

        if (! is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }

        $file = fopen($outputPath, 'w');
        if ($file === false) {
            $this->error("No se pudo abrir el archivo de salida: {$outputPath}");

            return self::FAILURE;
        }

        fputcsv($file, ['documento', 'corte', 'recencia_dias', 'frecuencia_compras', 'monto_total', 'ticket_promedio', 'antiguedad_dias', 'diversidad_productos', 'compro_recarga', 'target']);

        $resumen = [];
        foreach ($cortes as $corte) {
            $finVentana = $corte->copy()->addMonths($horizonte);
            $resumen[$corte->toDateString()] = [0, 0];

            foreach ($comprasPorCliente as $documento => $compras) {
                $variables = $historial->variables($compras, $corte);

                if ($variables === null) {
                    continue;
                }

                $target = $historial->comproEntre($compras, $corte, $finVentana) ? 1 : 0;
                $resumen[$corte->toDateString()][$target]++;

                fputcsv($file, [$documento, $corte->toDateString(), ...array_values($variables), $target]);
            }
        }

        fclose($file);

        $this->info("Dataset exportado a: {$outputPath} (horizonte {$horizonte} meses)");
        $this->table(['Corte', 'Clientes', 'Volvieron a comprar'], collect($resumen)->map(fn (array $conteo, string $corte) => [
            $corte, $conteo[0] + $conteo[1], $conteo[1].' ('.round($conteo[1] / max(1, $conteo[0] + $conteo[1]) * 100, 1).'%)',
        ])->values()->all());

        return self::SUCCESS;
    }

    /**
     * Cada corte necesita la ventana completa de observación después de él:
     * el último corte es la última fecha con datos menos el horizonte.
     *
     * @return list<Carbon>
     */
    protected function cortes(int $ultimaCompra, int $horizonte): array
    {
        if ($this->option('cortes')) {
            return array_values(collect(explode(',', (string) $this->option('cortes')))
                ->map(fn (string $fecha) => Carbon::parse(trim($fecha))->startOfDay())
                ->sort()
                ->all());
        }

        $ultimo = Carbon::createFromTimestamp($ultimaCompra, config('app.timezone'))->startOfDay()->addDay()->subMonths($horizonte);

        return [$ultimo->copy()->subMonths(6), $ultimo->copy()->subMonths(3), $ultimo];
    }
}
