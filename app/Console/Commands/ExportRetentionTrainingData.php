<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('ml:export-retention-dataset {--cutoff=2026-03-12 : Fecha de corte YYYY-MM-DD} {--output= : Ruta destino del archivo CSV}')]
#[Description('Exporta el dataset de entrenamiento para predecir recompra sin fuga de datos')]
class ExportRetentionTrainingData extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoffStr = (string) $this->option('cutoff');
        $cutoff = Carbon::parse($cutoffStr)->startOfDay();
        $targetWindowEnd = $cutoff->copy()->addMonths(6)->endOfDay();

        $defaultOutput = storage_path('app/ml/retention_training_dataset.csv');
        $outputPath = (string) ($this->option('output') ?: $defaultOutput);

        $dir = dirname($outputPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->info("Extrayendo dataset de recompra con fecha de corte: {$cutoff->toDateString()}");
        $this->info("Ventana de observación de target (6 meses): hasta {$targetWindowEnd->toDateString()}");

        // 1. Obtener clientes elegibles: con al menos una venta pre-corte, excluyendo placeholders
        $eligibleClientIds = DB::table('sales')
            ->join('clients', 'clients.id', '=', 'sales.client_id')
            ->where('sales.estado', '!=', 'anulada')
            ->where('sales.fecha', '<', $cutoff->toDateString())
            ->where('clients.razon_social', 'not like', '%SIN DOCUMENTO%')
            ->where('clients.numero_documento', '!=', '00000000000')
            ->distinct()
            ->pluck('sales.client_id')
            ->all();

        $totalClients = count($eligibleClientIds);
        $this->info("Clientes elegibles encontrados con ventas pre-corte: {$totalClients}");

        if ($totalClients === 0) {
            $this->warn('No se encontraron clientes elegibles pre-corte.');

            return self::FAILURE;
        }

        // 2. Pre-cargar ventas en la ventana de target (6 meses post-corte) para etiquetar
        $recompraClientIds = DB::table('sales')
            ->whereIn('client_id', $eligibleClientIds)
            ->where('estado', '!=', 'anulada')
            ->whereBetween('fecha', [$cutoff->toDateString(), $targetWindowEnd->toDateString()])
            ->distinct()
            ->pluck('client_id')
            ->flip()
            ->all();

        // 3. Pre-cargar agregados pre-corte por cliente
        $saleAggregates = DB::table('sales')
            ->whereIn('client_id', $eligibleClientIds)
            ->where('estado', '!=', 'anulada')
            ->where('fecha', '<', $cutoff->toDateString())
            ->groupBy('client_id')
            ->selectRaw('
                client_id,
                COUNT(id) as frecuencia_compras,
                SUM(total) as monto_total,
                MIN(fecha) as primera_compra,
                MAX(fecha) as ultima_compra
            ')
            ->get()
            ->keyBy('client_id');

        // 4. Pre-cargar diversidad de productos y si compró recarga pre-corte
        $itemAggregates = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('services', 'services.id', '=', 'sale_items.service_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->whereIn('sales.client_id', $eligibleClientIds)
            ->where('sales.estado', '!=', 'anulada')
            ->where('sales.fecha', '<', $cutoff->toDateString())
            ->groupBy('sales.client_id')
            ->selectRaw('
                sales.client_id,
                COUNT(DISTINCT COALESCE(CONCAT("p_", sale_items.product_id), CONCAT("s_", sale_items.service_id))) as diversidad_items,
                MAX(CASE WHEN UPPER(services.nombre) LIKE "%RECARGA%" OR products.categoria = "extintor" THEN 1 ELSE 0 END) as compro_recarga
            ')
            ->get()
            ->keyBy('client_id');

        // 5. Escribir CSV
        $file = fopen($outputPath, 'w');
        if (! $file) {
            $this->error("No se pudo abrir el archivo de salida: {$outputPath}");

            return self::FAILURE;
        }

        fputcsv($file, [
            'client_id',
            'recencia_dias',
            'frecuencia_compras',
            'monto_total',
            'ticket_promedio',
            'antiguedad_dias',
            'diversidad_productos',
            'compro_recarga',
            'target',
        ]);

        $positivos = 0;
        $negativos = 0;

        foreach ($eligibleClientIds as $clientId) {
            $saleData = $saleAggregates->get($clientId);
            if (! $saleData) {
                continue;
            }

            $itemData = $itemAggregates->get($clientId);

            $ultimaCompra = Carbon::parse($saleData->ultima_compra)->startOfDay();
            $primeraCompra = Carbon::parse($saleData->primera_compra)->startOfDay();

            $recenciaDias = max(0, (int) $ultimaCompra->diffInDays($cutoff, false));
            $antiguedadDias = max(0, (int) $primeraCompra->diffInDays($cutoff, false));
            $frecuencia = (int) $saleData->frecuencia_compras;
            $montoTotal = round((float) $saleData->monto_total, 2);
            $ticketPromedio = $frecuencia > 0 ? round($montoTotal / $frecuencia, 2) : 0.0;
            $diversidad = $itemData ? (int) $itemData->diversidad_items : 1;
            $comproRecarga = $itemData ? (int) $itemData->compro_recarga : 0;

            $target = isset($recompraClientIds[$clientId]) ? 1 : 0;
            if ($target === 1) {
                $positivos++;
            } else {
                $negativos++;
            }

            fputcsv($file, [
                $clientId,
                $recenciaDias,
                $frecuencia,
                $montoTotal,
                $ticketPromedio,
                $antiguedadDias,
                $diversidad,
                $comproRecarga,
                $target,
            ]);
        }

        fclose($file);

        $tasaRecompra = $totalClients > 0 ? round(($positivos / $totalClients) * 100, 2) : 0.0;

        $this->info("Dataset exportado exitosamente a: {$outputPath}");
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total registros', $totalClients],
                ['Positivos (recompraron en 6m)', "{$positivos} ({$tasaRecompra}%)"],
                ['Negativos (no recompraron)', "{$negativos} (".round(100 - $tasaRecompra, 2).'%)'],
            ]
        );

        return self::SUCCESS;
    }
}
