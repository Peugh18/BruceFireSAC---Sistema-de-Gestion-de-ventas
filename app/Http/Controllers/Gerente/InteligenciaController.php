<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gerente\IndexInteligenciaRequest;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\ClientRetentionScore;
use App\Models\Team;
use App\Services\Ml\RetentionModel;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class InteligenciaController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Team $current_team, IndexInteligenciaRequest $request): Response
    {
        $filters = [
            'buscar' => trim($request->string('buscar')->toString()),
            'categoria' => $request->string('categoria', 'todas')->toString(),
            'vigencia' => $request->string('vigencia', 'todas')->toString(),
        ];
        $limite = now()->subDays(30);
        $base = ClientRetentionScore::query()->whereHas('client', fn (Builder $query) => $query->where('activo', true));
        $categorias = (clone $base)->selectRaw('categoria, COUNT(*) as total')->groupBy('categoria')->pluck('total', 'categoria');
        $clientes = (clone $base)
            ->with('client:id,razon_social,numero_documento')
            ->when($filters['buscar'] !== '', fn (Builder $query) => $query->whereHas('client', fn (Builder $query) => $query->buscar($filters['buscar'])))
            ->when($filters['categoria'] !== 'todas', fn (Builder $query) => $query->where('categoria', $filters['categoria']))
            ->when($filters['vigencia'] === 'actualizados', fn (Builder $query) => $query->where('scored_at', '>=', $limite))
            ->when($filters['vigencia'] === 'desactualizados', fn (Builder $query) => $query->where('scored_at', '<', $limite))
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
            ],
            'categorias' => collect(['alta', 'media', 'baja'])->map(fn (string $categoria) => ['nombre' => ucfirst($categoria), 'total' => (int) ($categorias[$categoria] ?? 0)])->values(),
            'vencimientos' => collect([[0, 30], [31, 60], [61, 90]])->map(fn (array $tramo) => [
                'nombre' => $tramo[0].'–'.$tramo[1].' días',
                'total' => Certificate::query()->where('estado', '!=', 'anulado')
                    ->whereHas('client', fn (Builder $query) => $query->where('activo', true))
                    ->whereBetween('fecha_vigencia_hasta', [today()->addDays($tramo[0]), today()->addDays($tramo[1])])->count(),
            ])->values(),
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
}
