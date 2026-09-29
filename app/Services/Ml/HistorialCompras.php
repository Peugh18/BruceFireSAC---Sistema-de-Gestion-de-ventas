<?php

namespace App\Services\Ml;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Compras de cada cliente para la predicción de recompra: junta el histórico
 * del sistema anterior (ml_lineas_historicas) con las ventas confirmadas del
 * sistema, unidas por el número de documento del cliente. Una compra es un
 * comprobante, no una línea.
 *
 * El entrenamiento y el cálculo diario usan esta misma clase, así las
 * variables se calculan igual en los dos lados.
 */
class HistorialCompras
{
    /**
     * Categorías del histórico que cuentan como servicio a extintores.
     *
     * @var list<string>
     */
    private const CATEGORIAS_RECARGA = ['recarga_mantenimiento', 'mantenimiento', 'prueba_hidrostatica'];

    /**
     * @param  list<string>|null  $documentos  null = todos los clientes
     * @return Collection<string, Collection<int, array{fecha: Carbon, total: float, recarga: bool, items: list<string>}>>
     */
    public function porDocumento(?array $documentos = null): Collection
    {
        /** @var array<string, array{documento: string, fecha: Carbon, total: float, recarga: bool, items: array<string, true>}> $compras */
        $compras = [];

        DB::table('ml_lineas_historicas')
            ->join('ml_comprobantes_historicos', 'ml_comprobantes_historicos.comprobante', '=', 'ml_lineas_historicas.comprobante')
            ->join('ml_productos_historicos', 'ml_productos_historicos.id', '=', 'ml_lineas_historicas.ml_producto_id')
            ->when($documentos !== null, fn ($query) => $query->whereIn('ml_comprobantes_historicos.documento_cliente', $documentos))
            ->orderBy('ml_lineas_historicas.id')
            ->get(['ml_comprobantes_historicos.documento_cliente', 'ml_lineas_historicas.comprobante', 'ml_comprobantes_historicos.fecha', 'ml_productos_historicos.categoria', 'ml_productos_historicos.nombre as producto', 'ml_lineas_historicas.total'])
            ->each(function (object $linea) use (&$compras): void {
                $clave = 'h:'.$linea->comprobante;
                $compras[$clave] ??= ['documento' => (string) $linea->documento_cliente, 'fecha' => Carbon::parse($linea->fecha)->startOfDay(), 'total' => 0.0, 'recarga' => false, 'items' => []];
                $compras[$clave]['total'] += (float) $linea->total;
                $compras[$clave]['recarga'] = $compras[$clave]['recarga'] || in_array($linea->categoria, self::CATEGORIAS_RECARGA, true);
                $compras[$clave]['items'][$this->normalizarItem((string) $linea->producto)] = true;
            });

        DB::table('sales')
            ->join('clients', 'clients.id', '=', 'sales.client_id')
            ->leftJoin('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('services', 'services.id', '=', 'sale_items.service_id')
            ->where('sales.estado', 'confirmada')
            ->when($documentos !== null, fn ($query) => $query->whereIn('clients.numero_documento', $documentos))
            ->orderBy('sales.id')
            ->get(['sales.id as sale_id', 'sales.fecha', 'sales.total', 'clients.numero_documento', 'sale_items.tipo_linea', 'products.nombre as producto', 'services.nombre as servicio'])
            ->each(function (object $linea) use (&$compras): void {
                $clave = 's:'.$linea->sale_id;
                $servicio = mb_strtoupper((string) $linea->servicio);
                $compras[$clave] ??= ['documento' => (string) $linea->numero_documento, 'fecha' => Carbon::parse($linea->fecha)->startOfDay(), 'total' => (float) $linea->total, 'recarga' => false, 'items' => []];
                $compras[$clave]['recarga'] = $compras[$clave]['recarga']
                    || $linea->tipo_linea === 'recarga_servicio'
                    || str_contains($servicio, 'RECARGA') || str_contains($servicio, 'MANTENIMIENTO') || str_contains($servicio, 'HIDROST');
                if ($linea->producto !== null || $linea->servicio !== null) {
                    $compras[$clave]['items'][$this->normalizarItem((string) ($linea->producto ?? $linea->servicio))] = true;
                }
            });

        return collect($compras)
            ->map(fn (array $compra) => [
                'documento' => $compra['documento'],
                'fecha' => $compra['fecha'],
                'total' => round($compra['total'], 2),
                'recarga' => $compra['recarga'],
                'items' => array_keys($compra['items']),
            ])
            ->groupBy('documento')
            ->map(fn (Collection $comprasDelCliente) => $comprasDelCliente
                ->map(fn (array $compra) => ['fecha' => $compra['fecha'], 'total' => $compra['total'], 'recarga' => $compra['recarga'], 'items' => $compra['items']])
                ->sortBy(fn (array $compra) => $compra['fecha']->getTimestamp())
                ->values());
    }

    /**
     * Variables del modelo con las compras anteriores a $antesDe (sin incluir
     * ese día): así el corte del entrenamiento no ve el futuro.
     *
     * @param  Collection<int, array{fecha: Carbon, total: float, recarga: bool, items: list<string>}>  $compras
     * @return array{recencia_dias: int, frecuencia_compras: int, monto_total: float, ticket_promedio: float, antiguedad_dias: int, diversidad_productos: int, compro_recarga: int, compras_90d: int}|null
     */
    public function variables(Collection $compras, Carbon $antesDe): ?array
    {
        $previas = $compras->filter(fn (array $compra) => $compra['fecha']->lt($antesDe));

        if ($previas->isEmpty()) {
            return null;
        }

        $frecuencia = $previas->count();
        $monto = round((float) $previas->sum('total'), 2);

        return [
            'recencia_dias' => (int) $previas->last()['fecha']->diffInDays($antesDe),
            'frecuencia_compras' => $frecuencia,
            'monto_total' => $monto,
            'ticket_promedio' => round($monto / $frecuencia, 2),
            'antiguedad_dias' => (int) $previas->first()['fecha']->diffInDays($antesDe),
            'diversidad_productos' => max(1, $previas->flatMap(fn (array $compra) => $compra['items'])->unique()->count()),
            'compro_recarga' => $previas->contains(fn (array $compra) => $compra['recarga']) ? 1 : 0,
            'compras_90d' => $previas->filter(fn (array $compra) => $compra['fecha']->gte($antesDe->copy()->subDays(90)))->count(),
        ];
    }

    /**
     * ¿Compró entre $desde (incluido) y $hasta (excluido)?
     *
     * @param  Collection<int, array{fecha: Carbon, total: float, recarga: bool, items: list<string>}>  $compras
     */
    public function comproEntre(Collection $compras, Carbon $desde, Carbon $hasta): bool
    {
        return $compras->contains(fn (array $compra) => $compra['fecha']->gte($desde) && $compra['fecha']->lt($hasta));
    }

    protected function normalizarItem(string $nombre): string
    {
        return (string) preg_replace('/\s+/', ' ', mb_strtoupper(trim($nombre)));
    }
}
