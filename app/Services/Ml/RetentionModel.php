<?php

namespace App\Services\Ml;

use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class RetentionModel
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $modelData = null;

    protected string $modelPath;

    public function __construct(?string $customModelPath = null)
    {
        $this->modelPath = $customModelPath ?: storage_path('app/ml/retention_model.json');
    }

    /**
     * Carga el modelo desde el JSON exportado por Python.
     *
     * @return array<string, mixed>
     */
    public function load(): array
    {
        if ($this->modelData !== null) {
            return $this->modelData;
        }

        if (! File::exists($this->modelPath)) {
            throw new RuntimeException("El archivo del modelo de IA no existe en: {$this->modelPath}");
        }

        $content = File::get($this->modelPath);
        $json = json_decode($content, true);

        if (! is_array($json) || ! isset($json['coefficients'], $json['intercept'], $json['scaler'])) {
            throw new RuntimeException("El archivo del modelo de IA está corrupto o incompleto en: {$this->modelPath}");
        }

        $this->modelData = $json;

        return $this->modelData;
    }

    /**
     * Comprueba si el archivo del modelo está presente y disponible.
     */
    public function isAvailable(): bool
    {
        return File::exists($this->modelPath);
    }

    /**
     * Inferencia matemática pura: estandarización + producto punto + sigmoide.
     *
     * @param  array<string, float|int|bool>  $features
     */
    public function predictProbability(array $features): float
    {
        $model = $this->load();

        $coefficients = $model['coefficients'];
        $intercept = (float) $model['intercept'];
        $scaler = $model['scaler'];

        $logit = $intercept;

        foreach ($coefficients as $featureName => $weight) {
            $rawVal = isset($features[$featureName]) ? (float) $features[$featureName] : 0.0;
            $mean = (float) ($scaler['mean'][$featureName] ?? 0.0);
            $std = (float) ($scaler['std'][$featureName] ?? 1.0);

            // Estandarización z = (x - mean) / std
            $scaledVal = $std > 0.000001 ? ($rawVal - $mean) / $std : 0.0;

            // Producto punto
            $logit += ((float) $weight) * $scaledVal;
        }

        // Función sigmoide: 1 / (1 + exp(-z))
        if ($logit > 45.0) {
            return 1.0;
        }

        if ($logit < -45.0) {
            return 0.0;
        }

        return 1.0 / (1.0 + exp(-$logit));
    }

    /**
     * Determina la categoría según el umbral de probabilidad.
     */
    public function categorizeProbability(float $prob): string
    {
        return match (true) {
            $prob >= 0.60 => 'alta',
            $prob >= 0.35 => 'media',
            default => 'baja',
        };
    }

    /**
     * Explica los factores que más influyen en la predicción del cliente.
     *
     * @param  array<string, float|int|bool>  $features
     * @return array{
     *     positivos: array<int, array{factor: string, impacto: string, detalle: string}>,
     *     negativos: array<int, array{factor: string, impacto: string, detalle: string}>
     * }
     */
    public function explainFactors(array $features): array
    {
        $model = $this->load();

        $coefficients = $model['coefficients'];
        $scaler = $model['scaler'];

        $contribuciones = [];

        foreach ($coefficients as $featureName => $weight) {
            $rawVal = isset($features[$featureName]) ? (float) $features[$featureName] : 0.0;
            $mean = (float) ($scaler['mean'][$featureName] ?? 0.0);
            $std = (float) ($scaler['std'][$featureName] ?? 1.0);

            $scaledVal = $std > 0.000001 ? ($rawVal - $mean) / $std : 0.0;
            $contribucion = ((float) $weight) * $scaledVal;

            $contribuciones[$featureName] = [
                'feature' => $featureName,
                'raw_value' => $rawVal,
                'contribution' => $contribucion,
                'abs_contribution' => abs($contribucion),
            ];
        }

        uasort($contribuciones, fn ($a, $b) => $b['abs_contribution'] <=> $a['abs_contribution']);

        $positivos = [];
        $negativos = [];

        foreach ($contribuciones as $item) {
            $f = $item['feature'];
            $contrib = $item['contribution'];
            $val = $item['raw_value'];

            $descripcion = $this->humanReadableFeature($f, $val, $contrib > 0);

            if ($contrib > 0.05 && count($positivos) < 2) {
                $positivos[] = [
                    'factor' => $descripcion['titulo'],
                    'impacto' => 'positivo',
                    'detalle' => $descripcion['detalle'],
                ];
            } elseif ($contrib < -0.05 && count($negativos) < 2) {
                $negativos[] = [
                    'factor' => $descripcion['titulo'],
                    'impacto' => 'negativo',
                    'detalle' => $descripcion['detalle'],
                ];
            }
        }

        return [
            'positivos' => $positivos,
            'negativos' => $negativos,
        ];
    }

    /**
     * Traduce una variable y valor a un factor explicativo legible en español.
     *
     * @return array{titulo: string, detalle: string}
     */
    protected function humanReadableFeature(string $feature, float $value, bool $isPositive): array
    {
        return match ($feature) {
            'recencia_dias' => [
                'titulo' => $isPositive ? 'Compra reciente' : 'Inactividad prolongada',
                'detalle' => sprintf('Última compra hace %d días', (int) $value),
            ],
            'frecuencia_compras' => [
                'titulo' => $isPositive ? 'Alta frecuencia histórica' : 'Baja recurrencia',
                'detalle' => sprintf('%d compras registradas', (int) $value),
            ],
            'monto_total' => [
                'titulo' => $isPositive ? 'Alto volumen de facturación' : 'Volumen facturado menor',
                'detalle' => sprintf('S/ %s acumulados', number_format($value, 2)),
            ],
            'ticket_promedio' => [
                'titulo' => $isPositive ? 'Ticket promedio elevado' : 'Ticket promedio bajo',
                'detalle' => sprintf('S/ %s por pedido', number_format($value, 2)),
            ],
            'antiguedad_dias' => [
                'titulo' => $isPositive ? 'Cliente fidelizado (antiguo)' : 'Cliente de primer ciclo',
                'detalle' => sprintf('%d días de relación comercial', (int) $value),
            ],
            'diversidad_productos' => [
                'titulo' => $isPositive ? 'Catálogo diversificado' : 'Compra concentrada en un ítem',
                'detalle' => sprintf('%d productos/servicios distintos', (int) $value),
            ],
            'compro_recarga' => [
                'titulo' => $value > 0 ? 'Contrató servicio de recargas' : 'Sin servicio de recarga previo',
                'detalle' => $value > 0 ? 'Mantiene extintores con ciclo de mantenimiento' : 'No ha contratado recargas aún',
            ],
            default => [
                'titulo' => ucfirst(str_replace('_', ' ', $feature)),
                'detalle' => sprintf('Valor: %s', $value),
            ]
        };
    }

    /**
     * Extrae las 7 features cuantitativas para un cliente a una fecha dada.
     *
     * @return array{
     *     recencia_dias: int,
     *     frecuencia_compras: int,
     *     monto_total: float,
     *     ticket_promedio: float,
     *     antiguedad_dias: int,
     *     diversidad_productos: int,
     *     compro_recarga: int
     * }|null
     */
    public function extractFeaturesForClient(int $clientId, ?Carbon $asOf = null): ?array
    {
        $asOf = $asOf ?: Carbon::today();
        $asOfStr = $asOf->toDateString();

        $saleData = DB::table('sales')
            ->where('client_id', $clientId)
            ->where('estado', '!=', 'anulada')
            ->where('fecha', '<=', $asOfStr)
            ->selectRaw('
                COUNT(id) as frecuencia,
                SUM(total) as monto_total,
                MIN(fecha) as primera_compra,
                MAX(fecha) as ultima_compra
            ')
            ->first();

        if (! $saleData || (int) $saleData->frecuencia === 0) {
            return null;
        }

        $itemData = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('services', 'services.id', '=', 'sale_items.service_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.client_id', $clientId)
            ->where('sales.estado', '!=', 'anulada')
            ->where('sales.fecha', '<=', $asOfStr)
            ->selectRaw('
                COUNT(DISTINCT COALESCE(CONCAT("p_", sale_items.product_id), CONCAT("s_", sale_items.service_id))) as diversidad,
                MAX(CASE WHEN UPPER(services.nombre) LIKE "%RECARGA%" OR products.categoria = "extintor" THEN 1 ELSE 0 END) as compro_recarga
            ')
            ->first();

        $ultimaCompra = Carbon::parse($saleData->ultima_compra)->startOfDay();
        $primeraCompra = Carbon::parse($saleData->primera_compra)->startOfDay();

        $recenciaDias = max(0, (int) $ultimaCompra->diffInDays($asOf, false));
        $antiguedadDias = max(0, (int) $primeraCompra->diffInDays($asOf, false));
        $frecuencia = (int) $saleData->frecuencia;
        $montoTotal = round((float) $saleData->monto_total, 2);
        $ticketPromedio = $frecuencia > 0 ? round($montoTotal / $frecuencia, 2) : 0.0;
        $diversidad = $itemData ? (int) $itemData->diversidad : 1;
        $comproRecarga = $itemData ? (int) $itemData->compro_recarga : 0;

        return [
            'recencia_dias' => $recenciaDias,
            'frecuencia_compras' => $frecuencia,
            'monto_total' => $montoTotal,
            'ticket_promedio' => $ticketPromedio,
            'antiguedad_dias' => $antiguedadDias,
            'diversidad_productos' => $diversidad,
            'compro_recarga' => $comproRecarga,
        ];
    }

    /**
     * Evalúa y predice probabilidad para un cliente.
     *
     * @return array{
     *     probabilidad: float,
     *     categoria: string,
     *     features: array<string, mixed>,
     *     factores: array{
     *         positivos: array<int, array{factor: string, impacto: string, detalle: string}>,
     *         negativos: array<int, array{factor: string, impacto: string, detalle: string}>
     *     }
     * }|null
     */
    public function predictForClient(Client|int $client, ?Carbon $asOf = null): ?array
    {
        $clientId = $client instanceof Client ? $client->id : $client;
        $features = $this->extractFeaturesForClient($clientId, $asOf);

        if (! $features) {
            return null;
        }

        $prob = round($this->predictProbability($features), 4);
        $categoria = $this->categorizeProbability($prob);
        $factores = $this->explainFactors($features);

        return [
            'probabilidad' => $prob,
            'categoria' => $categoria,
            'features' => $features,
            'factores' => $factores,
        ];
    }

    /**
     * Obtiene las métricas y metadatos registrados en el JSON del modelo.
     *
     * @return array<string, mixed>|null
     */
    public function getModelMetadata(): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $model = $this->load();

        return [
            'model_name' => $model['model_name'] ?? 'Logistic Regression',
            'version' => $model['version'] ?? '1.0.0',
            'trained_at' => $model['trained_at'] ?? null,
            'cutoff_date' => $model['cutoff_date'] ?? null,
            'metrics' => $model['metrics'] ?? [],
            'dataset_summary' => $model['dataset_summary'] ?? [],
        ];
    }
}
