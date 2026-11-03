<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gerente\IndexInteligenciaRequest;
use App\Models\Certificate;
use App\Models\ChispaRegistro;
use App\Models\Client;
use App\Models\ClientRetentionScore;
use App\Models\MlEntrenamiento;
use App\Models\Team;
use App\Services\Ml\RetentionModel;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class InteligenciaController extends Controller
{
    /** Nombre corto de cada variable del modelo, para la pantalla. */
    private const VARIABLES = [
        'recencia_dias' => 'Días sin comprar',
        'frecuencia_compras' => 'Compras en total',
        'monto_total' => 'Importe histórico',
        'ticket_promedio' => 'Gasto por pedido',
        'antiguedad_dias' => 'Antigüedad como cliente',
        'diversidad_productos' => 'Productos distintos',
        'compro_recarga' => 'Compró recarga',
        'compras_90d' => 'Compras en 90 días',
    ];

    /**
     * Recompra e IA: a quién llamar primero, sin repetir la recarga anual (eso
     * es "Por vencer" del vendedor). Muestra también cómo está el modelo y qué
     * no supo responder Chispa.
     */
    public function __invoke(Team $current_team, IndexInteligenciaRequest $request, RetentionModel $modelo): Response
    {
        $filters = [
            'buscar' => trim($request->string('buscar')->toString()),
            'categoria' => $request->string('categoria', 'todas')->toString(),
            'vigencia' => $request->string('vigencia', 'todas')->toString(),
            'calendario' => $request->string('calendario', 'todos')->toString(),
        ];
        $limite = now()->subDays(30);
        $base = ClientRetentionScore::query()->whereHas('client', fn (Builder $query) => $query->where('activo', true));
        $fueraDelCalendario = $this->fueraDelCalendario(...);
        $categorias = (clone $base)->selectRaw('categoria, COUNT(*) as total')->groupBy('categoria')->pluck('total', 'categoria');
        $clientes = (clone $base)
            ->with('client:id,razon_social,numero_documento')
            ->when($filters['buscar'] !== '', fn (Builder $query) => $query->whereHas('client', fn (Builder $query) => $query->buscar($filters['buscar'])))
            ->when($filters['categoria'] !== 'todas', fn (Builder $query) => $query->where('categoria', $filters['categoria']))
            ->when($filters['vigencia'] === 'actualizados', fn (Builder $query) => $query->where('scored_at', '>=', $limite))
            ->when($filters['vigencia'] === 'desactualizados', fn (Builder $query) => $query->where('scored_at', '<', $limite))
            ->when($filters['calendario'] === 'fuera', $fueraDelCalendario)
            ->orderByDesc('probabilidad')->orderBy('client_id')->paginate(20)->withQueryString();

        $proximasFechas = Certificate::query()->whereIn('client_id', $clientes->pluck('client_id'))
            ->where('estado', '!=', 'anulado')->whereDate('fecha_vigencia_hasta', '>=', today())
            ->selectRaw('client_id, MIN(fecha_vigencia_hasta) as fecha')->groupBy('client_id')->pluck('fecha', 'client_id');

        return Inertia::render('gerente/inteligencia', [
            'filters' => $filters,
            'resumen' => [
                'total' => (clone $base)->count(),
                'sinPuntuacion' => Client::query()->where('activo', true)->whereNotIn('id', ClientRetentionScore::query()->select('client_id'))->count(),
                'calculado' => (clone $base)->max('scored_at'),
                'desactualizados' => (clone $base)->where('scored_at', '<', $limite)->count(),
                'fueraDelCalendario' => (clone $base)->where('categoria', 'alta')->tap($fueraDelCalendario)->count(),
            ],
            'categorias' => collect(['alta', 'media', 'baja'])->map(fn (string $categoria) => ['nombre' => ucfirst($categoria), 'total' => (int) ($categorias[$categoria] ?? 0)])->values(),
            'vencimientos' => collect([[0, 30], [31, 60], [61, 90]])->map(fn (array $tramo) => [
                'nombre' => $tramo[0].'–'.$tramo[1].' días',
                'total' => Certificate::query()->where('estado', '!=', 'anulado')
                    ->whereHas('client', fn (Builder $query) => $query->where('activo', true))
                    ->whereBetween('fecha_vigencia_hasta', [today()->addDays($tramo[0]), today()->addDays($tramo[1])])->count(),
            ])->values(),
            'modelo' => $this->modelo($modelo),
            'chispa' => $this->chispa(),
            'clientes' => $clientes->through(fn (ClientRetentionScore $score) => [
                'id' => $score->client_id,
                'cliente' => $score->client->razon_social,
                'documento' => $score->client->numero_documento,
                ...RetentionModel::paraPantalla(['probabilidad' => $score->probabilidad, 'categoria' => $score->categoria, 'factores' => $score->factores_json]),
                'compras' => $score->frecuencia_compras,
                'monto' => (float) $score->monto_total,
                'recencia' => $score->recencia_dias,
                'calculado' => $score->scored_at->toDateTimeString(),
                'desactualizado' => $score->scored_at->lt($limite),
                'proximoVencimiento' => $proximasFechas->get($score->client_id),
                'url' => route('gerente.consulta.cliente', ['current_team' => $current_team, 'client' => $score->client_id]),
            ]),
        ]);
    }

    /**
     * Fuera del calendario: el cliente no tiene extintores por vencer en 90
     * días, así que si vuelve es por uso u otra compra, no por la recarga anual.
     *
     * @param  Builder<ClientRetentionScore>  $query
     */
    private function fueraDelCalendario(Builder $query): void
    {
        $query->whereDoesntHave('client.certificates', fn (Builder $certificado) => $certificado
            ->where('estado', '!=', 'anulado')->whereBetween('fecha_vigencia_hasta', [today(), today()->addDays(90)]));
    }

    /**
     * Cómo está el modelo vigente: qué tipo ganó, qué tan bien ordena, de qué
     * aprendió, qué variables pesan más (SHAP) y el acierto de los últimos 6
     * entrenamientos. Sin archivo de modelo, null.
     *
     * @return array{tipo: string, rocAuc: float|null, top100: int|null, muestras: int|null, entrenado: string|null, importancia: list<array{nombre: string, porcentaje: int}>, historial: list<float>}|null
     */
    private function modelo(RetentionModel $modelo): ?array
    {
        try {
            $datos = $modelo->load();
        } catch (Throwable) {
            return null;
        }

        $prueba = (array) ($datos['metrics']['test'] ?? []);
        $shap = array_map('floatval', (array) ($datos['metrics']['importancia_shap'] ?? []));
        $suma = array_sum($shap) ?: 1;
        arsort($shap);
        $importancia = [];
        foreach (array_slice($shap, 0, 5, true) as $variable => $peso) {
            $importancia[] = ['nombre' => self::VARIABLES[$variable] ?? str_replace('_', ' ', (string) $variable), 'porcentaje' => (int) round($peso / $suma * 100)];
        }
        if (count($shap) > 5) {
            $importancia[] = ['nombre' => 'Otros ('.(count($shap) - 5).')', 'porcentaje' => (int) round(array_sum(array_slice($shap, 5)) / $suma * 100)];
        }

        return [
            'tipo' => (string) ($datos['type'] ?? 'logistic'),
            'rocAuc' => isset($prueba['roc_auc']) ? round((float) $prueba['roc_auc'], 2) : null,
            'top100' => isset($prueba['top_100_volvieron']) ? (int) $prueba['top_100_volvieron'] : null,
            'muestras' => isset($prueba['samples']) ? (int) $prueba['samples'] : (isset($datos['dataset_summary']['total_samples']) ? (int) $datos['dataset_summary']['total_samples'] : null),
            'entrenado' => isset($datos['trained_at']) ? (string) $datos['trained_at'] : null,
            'importancia' => $importancia,
            'historial' => array_values(MlEntrenamiento::query()->latest('entrenado_at')->limit(6)->pluck('roc_auc')
                ->reverse()->map(fn ($valor) => round((float) $valor, 2))->all()),
        ];
    }

    /**
     * Resumen de Chispa: cuántas preguntas recibió, cuántas sirvieron y las
     * últimas que no supo responder (de ahí salen las secciones del manual).
     *
     * @return array{total: int, utiles: int, sinRespuesta: int, ultimas: list<array{pregunta: string, rol: string}>}
     */
    private function chispa(): array
    {
        $total = ChispaRegistro::query()->count();

        return [
            'total' => $total,
            'utiles' => $total > 0 ? (int) round(ChispaRegistro::query()->where('util', true)->count() / $total * 100) : 0,
            'sinRespuesta' => ChispaRegistro::query()->where('fuente', 'sin_respuesta')->count(),
            'ultimas' => array_values(ChispaRegistro::query()->where('fuente', 'sin_respuesta')->latest('id')->limit(2)->get(['pregunta', 'rol'])
                ->map(fn (ChispaRegistro $registro) => ['pregunta' => (string) $registro->pregunta, 'rol' => (string) $registro->rol])->all()),
        ];
    }
}
