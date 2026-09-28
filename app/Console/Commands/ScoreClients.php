<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientRetentionScore;
use App\Models\MlVentaHistorica;
use App\Services\Ml\HistorialCompras;
use App\Services\Ml\RetentionModel;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('ml:score-clients {--limit= : Limitar el número de clientes a evaluar}')]
#[Description('Calcula y actualiza el score de retención y recompra de clientes mediante el modelo de IA')]
class ScoreClients extends Command
{
    public const CACHE_PARA_RECUPERAR = 'ml.clientes_para_recuperar';

    private const MAXIMO_PARA_RECUPERAR = 150;

    /**
     * Execute the console command.
     */
    public function handle(RetentionModel $retentionModel, HistorialCompras $historial): int
    {
        if (! $retentionModel->isAvailable()) {
            $this->error('El archivo del modelo de IA no existe. Ejecuta primero scripts/ml/train_retention_model.py.');

            return self::FAILURE;
        }

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $today = Carbon::today();

        $this->info("Iniciando cálculo de scores de recompra con IA a fecha: {$today->toDateString()}");

        $paraRecuperar = $this->clientesParaRecuperar($retentionModel, $historial, $today);
        Cache::forever(self::CACHE_PARA_RECUPERAR, ['calculado_at' => now()->toIso8601String(), 'clientes' => $paraRecuperar]);
        $this->info(count($paraRecuperar).' clientes del histórico (aún no registrados) listos para recuperar.');

        // Obtener clientes con compras no anuladas, excluyendo el placeholder histórico
        $query = Client::query()
            ->where('activo', true)
            ->where('razon_social', 'not like', '%SIN DOCUMENTO%')
            ->where('numero_documento', '!=', '00000000000')
            ->where(fn ($q) => $q
                ->whereHas('sales', fn ($ventas) => $ventas->where('estado', 'confirmada'))
                ->orWhereIn('numero_documento', MlVentaHistorica::query()->select('documento_cliente')))
            ->select(['id', 'razon_social']);

        if ($limit) {
            $query->limit($limit);
        }

        $clients = $query->get();
        $total = $clients->count();

        if ($total === 0) {
            $this->warn('No se encontraron clientes activos con historial de compras para evaluar.');

            return self::SUCCESS;
        }

        $this->info("Evaluando {$total} clientes...");

        $now = now();
        $records = [];
        $conteoCategorias = ['alta' => 0, 'media' => 0, 'baja' => 0];

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        foreach ($clients as $client) {
            $result = $retentionModel->predictForClient($client->id, $today);

            if (! $result) {
                $progressBar->advance();

                continue;
            }

            $prob = $result['probabilidad'];
            $cat = $result['categoria'];
            $feat = $result['features'];
            $factores = $result['factores'];

            $conteoCategorias[$cat] = ($conteoCategorias[$cat] ?? 0) + 1;

            $records[] = [
                'client_id' => $client->id,
                'probabilidad' => $prob,
                'categoria' => $cat,
                'recencia_dias' => $feat['recencia_dias'],
                'frecuencia_compras' => $feat['frecuencia_compras'],
                'monto_total' => $feat['monto_total'],
                'ticket_promedio' => $feat['ticket_promedio'],
                'antiguedad_dias' => $feat['antiguedad_dias'],
                'diversidad_productos' => $feat['diversidad_productos'],
                'compro_recarga' => (bool) $feat['compro_recarga'],
                'factores_json' => json_encode($factores, JSON_UNESCAPED_UNICODE),
                'scored_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Inserción en bloques de 200 para alto rendimiento
            if (count($records) >= 200) {
                ClientRetentionScore::upsert(
                    $records,
                    ['client_id'],
                    [
                        'probabilidad',
                        'categoria',
                        'recencia_dias',
                        'frecuencia_compras',
                        'monto_total',
                        'ticket_promedio',
                        'antiguedad_dias',
                        'diversidad_productos',
                        'compro_recarga',
                        'factores_json',
                        'scored_at',
                        'updated_at',
                    ]
                );
                $records = [];
            }

            $progressBar->advance();
        }

        if (count($records) > 0) {
            ClientRetentionScore::upsert(
                $records,
                ['client_id'],
                [
                    'probabilidad',
                    'categoria',
                    'recencia_dias',
                    'frecuencia_compras',
                    'monto_total',
                    'ticket_promedio',
                    'antiguedad_dias',
                    'diversidad_productos',
                    'compro_recarga',
                    'factores_json',
                    'scored_at',
                    'updated_at',
                ]
            );
        }

        $progressBar->finish();
        $this->newLine(2);

        $evaluados = array_sum($conteoCategorias);
        $this->info("Scores actualizados exitosamente ({$evaluados} clientes evaluados).");

        $this->table(
            ['Categoría', 'Clientes', '% del Total'],
            [
                ['Alta (P >= 60%)', $conteoCategorias['alta'], $evaluados > 0 ? round(($conteoCategorias['alta'] / $evaluados) * 100, 1).'%' : '0%'],
                ['Media (35% <= P < 60%)', $conteoCategorias['media'], $evaluados > 0 ? round(($conteoCategorias['media'] / $evaluados) * 100, 1).'%' : '0%'],
                ['Baja (P < 35%)', $conteoCategorias['baja'], $evaluados > 0 ? round(($conteoCategorias['baja'] / $evaluados) * 100, 1).'%' : '0%'],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Lista "Para recuperar" del último cálculo diario.
     *
     * @return list<array<mixed>>
     */
    public static function clientesParaRecuperarGuardados(): array
    {
        $guardado = Cache::get(self::CACHE_PARA_RECUPERAR);
        $clientes = is_array($guardado) && isset($guardado['clientes']) && is_array($guardado['clientes']) ? $guardado['clientes'] : [];

        return array_values(array_filter($clientes, fn (mixed $cliente) => is_array($cliente) && isset($cliente['documento'])));
    }

    /**
     * Clientes del sistema anterior que todavía no están registrados,
     * ordenados por su probabilidad de volver a comprar: a quién llamar.
     *
     * @return list<array{documento: string, nombre: string, ultima_compra: string, compras: int, monto_total: float, porcentaje: int, categoria: string, razones: list<array{texto: string, a_favor: bool}>}>
     */
    protected function clientesParaRecuperar(RetentionModel $retentionModel, HistorialCompras $historial, Carbon $today): array
    {
        $registrados = Client::query()->pluck('numero_documento')->flip();
        $nombres = MlVentaHistorica::query()->orderBy('fecha')->pluck('nombre_cliente', 'documento_cliente');
        $manana = $today->copy()->addDay();
        $clientes = [];

        foreach ($historial->porDocumento() as $documento => $compras) {
            $documento = (string) $documento;
            $variables = $historial->variables($compras, $manana);

            if ($variables === null || $registrados->has($documento) || ! $nombres->has($documento)) {
                continue;
            }

            $probabilidad = $retentionModel->predictProbability($variables);
            $clientes[] = [
                'documento' => $documento,
                'nombre' => (string) $nombres[$documento],
                'ultima_compra' => $compras->last()['fecha']->toDateString(),
                'compras' => $variables['frecuencia_compras'],
                'monto_total' => $variables['monto_total'],
                ...RetentionModel::paraPantalla([
                    'probabilidad' => $probabilidad,
                    'categoria' => $retentionModel->categorizeProbability($probabilidad),
                    'factores' => $retentionModel->explainFactors($variables),
                ]),
            ];
        }

        usort($clientes, fn (array $a, array $b) => $b['porcentaje'] <=> $a['porcentaje'] ?: strcmp($b['ultima_compra'], $a['ultima_compra']));

        return array_slice($clientes, 0, self::MAXIMO_PARA_RECUPERAR);
    }
}
