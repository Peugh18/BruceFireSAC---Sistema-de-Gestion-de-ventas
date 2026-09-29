<?php

namespace App\Services\Ml;

use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;

class RetentionModel
{
    /**
     * Variables que se entienden solas como "en contra" en la frase simple.
     * Monto, ticket, variedad y antigüedad pueden restar por un efecto
     * estadístico (dependen de las demás) que confunde a quien lo lee.
     *
     * @var list<string>
     */
    private const EN_CONTRA_CLARAS = ['recencia_dias', 'frecuencia_compras', 'compras_90d', 'compro_recarga'];

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

        $completo = is_array($json) && (($json['type'] ?? 'logistic') === 'xgboost'
            ? isset($json['trees'], $json['base_margin'])
            : isset($json['coefficients'], $json['intercept'], $json['scaler']));

        if (! $completo) {
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
     * Inferencia matemática pura: el margen (log-odds) del modelo pasa por
     * la sigmoide.
     *
     * @param  array<string, float|int|bool>  $features
     */
    public function predictProbability(array $features): float
    {
        ['base' => $logit, 'contribuciones' => $contribuciones] = $this->contribuciones($features);
        $logit += array_sum($contribuciones);

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
     *     negativos: array<int, array{factor: string, impacto: string, detalle: string}>,
     *     resumen: string
     * }
     */
    public function explainFactors(array $features): array
    {
        $contribuciones = [];

        foreach ($this->contribuciones($features)['contribuciones'] as $featureName => $contribucion) {
            $contribuciones[$featureName] = [
                'feature' => $featureName,
                'raw_value' => isset($features[$featureName]) ? (float) $features[$featureName] : 0.0,
                'contribution' => $contribucion,
                'abs_contribution' => abs($contribucion),
            ];
        }

        uasort($contribuciones, fn ($a, $b) => $b['abs_contribution'] <=> $a['abs_contribution']);

        $positivos = [];
        $negativos = [];
        $frasesAFavor = [];
        $frasesEnContra = [];

        foreach ($contribuciones as $item) {
            if ($item['contribution'] > 0.05 && count($frasesAFavor) < 2) {
                $frasesAFavor[] = $this->frase($item['feature'], $item['raw_value'], true);
            } elseif ($item['contribution'] < -0.05 && count($frasesEnContra) < 1 && in_array($item['feature'], self::EN_CONTRA_CLARAS, true)) {
                $frasesEnContra[] = $this->frase($item['feature'], $item['raw_value'], false);
            }
        }

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
            'resumen' => $this->unirFrases($frasesAFavor, $frasesEnContra),
        ];
    }

    /**
     * La razón en una frase normal: "Compra seguido (42 compras) y es
     * cliente hace 1 año y 7 meses, pero no compra hace 3 meses."
     *
     * @param  list<string>  $aFavor
     * @param  list<string>  $enContra
     */
    protected function unirFrases(array $aFavor, array $enContra): string
    {
        $texto = implode(' y ', $aFavor);

        if ($enContra !== []) {
            $texto = $texto === '' ? implode(' y ', $enContra) : "{$texto}, pero ".implode(' y ', $enContra);
        }

        return $texto === '' ? 'No hay datos suficientes para explicar la predicción.' : mb_strtoupper(mb_substr($texto, 0, 1)).mb_substr($texto, 1).'.';
    }

    /**
     * Una variable dicha en palabras simples.
     */
    protected function frase(string $feature, float $valor, bool $aFavor): string
    {
        $entero = (int) round($valor);

        return match ($feature) {
            'frecuencia_compras' => match (true) {
                $aFavor => "compra seguido ({$entero} compras)",
                $entero <= 1 => 'compró una sola vez',
                default => "compra poco seguido ({$entero} compras)",
            },
            'recencia_dias' => $aFavor ? 'compró hace '.$this->tiempo($entero) : 'no compra hace '.$this->tiempo($entero),
            'antiguedad_dias' => 'es cliente hace '.$this->tiempo($entero),
            'compras_90d' => $entero > 0
                ? "compró {$entero} ".($entero === 1 ? 'vez' : 'veces').' en los últimos 3 meses'
                : 'no compra hace más de 3 meses',
            'compro_recarga' => $entero > 0 ? 'recarga sus extintores con nosotros' : 'nunca ha recargado con nosotros',
            'monto_total' => 'ha comprado S/ '.number_format($valor, 2).' en total',
            'ticket_promedio' => 'gasta S/ '.number_format($valor, 2).' por pedido',
            'diversidad_productos' => "compra {$entero} productos distintos",
            default => str_replace('_', ' ', $feature),
        };
    }

    /**
     * "16 días", "5 meses", "1 año y 7 meses".
     */
    protected function tiempo(int $dias): string
    {
        if ($dias < 60) {
            return $dias === 1 ? '1 día' : "{$dias} días";
        }

        if ($dias < 365) {
            return (int) round($dias / 30).' meses';
        }

        $anios = intdiv($dias, 365);
        $meses = (int) round(($dias - $anios * 365) / 30);
        $textoAnios = $anios === 1 ? '1 año' : "{$anios} años";

        return $meses > 0 && $meses < 12 ? "{$textoAnios} y {$meses} ".($meses === 1 ? 'mes' : 'meses') : $textoAnios;
    }

    /**
     * Reparte el margen (log-odds) de la predicción entre las variables.
     *
     * Regresión logística: coeficiente × valor estandarizado (es el valor
     * SHAP exacto de un modelo lineal). XGBoost: se recorre cada árbol y el
     * cambio de valor esperado en cada división se atribuye a la variable que
     * divide (aproximación de SHAP de Saabas, la misma que xgboost calcula con
     * pred_contribs y approx_contribs=True).
     *
     * @param  array<string, float|int|bool>  $features
     * @return array{base: float, contribuciones: array<string, float>}
     */
    public function contribuciones(array $features): array
    {
        $model = $this->load();
        $valor = fn (string $feature): float => isset($features[$feature]) ? (float) $features[$feature] : 0.0;

        if (($model['type'] ?? 'logistic') !== 'xgboost') {
            $contribuciones = [];
            foreach ($model['coefficients'] as $featureName => $weight) {
                $mean = (float) ($model['scaler']['mean'][$featureName] ?? 0.0);
                $std = (float) ($model['scaler']['std'][$featureName] ?? 1.0);
                $contribuciones[$featureName] = (float) $weight * ($std > 0.000001 ? ($valor($featureName) - $mean) / $std : 0.0);
            }

            return ['base' => (float) $model['intercept'], 'contribuciones' => $contribuciones];
        }

        $contribuciones = array_fill_keys($model['features'] ?? [], 0.0);
        $base = (float) $model['base_margin'];

        foreach ($model['trees'] as $arbol) {
            $nodo = $arbol['0'];
            $base += (float) $nodo['ev'];

            while (! isset($nodo['leaf'])) {
                $x = $this->float32($valor($nodo['f']));
                $siguiente = $arbol[(string) ($x < $this->float32((float) $nodo['t']) ? $nodo['yes'] : $nodo['no'])];
                $contribuciones[$nodo['f']] = ($contribuciones[$nodo['f']] ?? 0.0) + (float) $siguiente['ev'] - (float) $nodo['ev'];
                $nodo = $siguiente;
            }
        }

        return ['base' => $base, 'contribuciones' => $contribuciones];
    }

    /**
     * xgboost compara los valores en precisión simple (float32); se redondea
     * igual para que un valor justo en el umbral vaya a la misma rama.
     */
    protected function float32(float $valor): float
    {
        $empaquetado = unpack('g', pack('g', $valor));

        return $empaquetado === false ? $valor : (float) $empaquetado[1];
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
                'titulo' => 'Monto comprado',
                'detalle' => sprintf('S/ %s acumulados', number_format($value, 2)),
            ],
            'ticket_promedio' => [
                'titulo' => 'Ticket promedio',
                'detalle' => sprintf('S/ %s por pedido', number_format($value, 2)),
            ],
            'antiguedad_dias' => [
                'titulo' => 'Tiempo como cliente',
                'detalle' => sprintf('%d días de relación comercial', (int) $value),
            ],
            'diversidad_productos' => [
                'titulo' => 'Variedad de productos',
                'detalle' => sprintf('%d productos/servicios distintos', (int) $value),
            ],
            'compro_recarga' => [
                'titulo' => $value > 0 ? 'Contrató servicio de recargas' : 'Sin servicio de recarga previo',
                'detalle' => $value > 0 ? 'Mantiene extintores con ciclo de mantenimiento' : 'No ha contratado recargas aún',
            ],
            'compras_90d' => [
                'titulo' => $value > 0 ? 'Compró en los últimos 3 meses' : 'Sin compras en los últimos 3 meses',
                'detalle' => sprintf('%d compra(s) en los últimos 90 días', (int) $value),
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
     *     compro_recarga: int,
     *     compras_90d: int
     * }|null
     */
    public function extractFeaturesForClient(int $clientId, ?Carbon $asOf = null): ?array
    {
        $documento = Client::query()->whereKey($clientId)->value('numero_documento');

        if ($documento === null) {
            return null;
        }

        $historial = app(HistorialCompras::class);
        $compras = $historial->porDocumento([(string) $documento])->get((string) $documento);

        // Cuenta las compras de hasta el mismo día de $asOf.
        return $compras ? $historial->variables($compras, ($asOf ?: Carbon::today())->copy()->startOfDay()->addDay()) : null;
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
     * Resumen para mostrar en pantalla: porcentaje, categoría y las razones
     * que más pesaron (a favor primero).
     *
     * @param  array{probabilidad: float, categoria: string, factores?: array<string, mixed>|null}  $resultado
     * @return array{porcentaje: int, categoria: string, resumen: string, razones: list<array{texto: string, a_favor: bool}>}
     */
    public static function paraPantalla(array $resultado): array
    {
        $razones = [];
        foreach (['positivos' => true, 'negativos' => false] as $grupo => $aFavor) {
            foreach ($resultado['factores'][$grupo] ?? [] as $factor) {
                $razones[] = ['texto' => "{$factor['factor']}: {$factor['detalle']}", 'a_favor' => $aFavor];
            }
        }

        return [
            // Un modelo nunca está 100 % seguro: se muestra entre 1 y 99 %.
            'porcentaje' => max(1, min(99, (int) round($resultado['probabilidad'] * 100))),
            'categoria' => $resultado['categoria'],
            'resumen' => (string) ($resultado['factores']['resumen'] ?? implode('. ', array_column($razones, 'texto'))),
            'razones' => $razones,
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
            'type' => $model['type'] ?? 'logistic',
            'version' => $model['version'] ?? '1.0.0',
            'trained_at' => $model['trained_at'] ?? null,
            'cutoff_date' => $model['cutoff_date'] ?? (isset($model['cortes']) ? end($model['cortes']) : null),
            'metrics' => $model['metrics'] ?? [],
            'dataset_summary' => $model['dataset_summary'] ?? [],
        ];
    }
}
