<?php

namespace App\Services\Avisos;

use App\Models\Client;
use App\Models\ClientRetentionScore;
use App\Models\Equipment;
use App\Services\Ml\RetentionModel;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Extintores de los clientes que vencen o ya vencieron, con la probabilidad
 * de recompra de cada cliente. Lo usan la pantalla "Por vencer" y el Inicio
 * del Vendedor, así las dos muestran exactamente lo mismo.
 */
class ExtintoresPorVencer
{
    /**
     * @return array{vencidas: Collection<int, array<string, mixed>>, esta_semana: Collection<int, array<string, mixed>>, este_mes: Collection<int, array<string, mixed>>}
     */
    public function segmentos(CarbonInterface $today, int $porSegmento = 100): array
    {
        $rows = Equipment::query()
            ->with(['client', 'product'])
            ->where(fn ($query) => $query
                ->whereNotNull('proxima_fecha_atencion')
                ->orWhereNotNull('proxima_prueba_hidrostatica')
                ->orWhereIn('estado', ['descargado', 'usado']))
            ->get()
            ->map(fn (Equipment $equipment) => $this->alertRow($equipment, $today))
            ->filter()
            ->groupBy(fn (array $row): string => "{$row['client_id']}|{$row['fecha']}")
            ->map(function (Collection $group): array {
                /** @var array<string, mixed> $first */
                $first = (array) $group->first();

                return [
                    ...$first,
                    'cantidad' => $group->count(),
                ];
            })
            ->values()
            ->concat($this->historicalRows($today))
            ->concat($this->sistemaAnteriorRows($today));

        // Cada segmento se trunca por separado (no un límite global) para
        // que "vencidas" no quede vacío solo porque hay más volumen en
        // "este mes" — cada lista prioriza lo más urgente dentro de sí misma.
        $porSegmentoOrdenado = fn (string $segmento): Collection => $rows->where('segmento', $segmento)
            ->sortBy('dias')
            ->take($porSegmento)
            ->values();

        $scores = ClientRetentionScore::query()->whereIn('client_id', $rows->pluck('client_id')->unique())->get()->keyBy('client_id');

        /**
         * @param  Collection<int, mixed>  $lista
         * @return Collection<int, array<string, mixed>>
         */
        $conRecompra = function (Collection $lista) use ($scores): Collection {
            /** @var Collection<int, array<string, mixed>> $result */
            $result = $lista->map(function ($row) use ($scores): array {
                /** @var array<string, mixed> $rowArray */
                $rowArray = (array) $row;
                $score = $scores->get($rowArray['client_id'] ?? null);

                return [
                    ...$rowArray,
                    'recompra' => $score
                        ? RetentionModel::paraPantalla(['probabilidad' => $score->probabilidad, 'categoria' => $score->categoria, 'factores' => $score->factores_json])
                        : null,
                ];
            })->values();

            return $result;
        };

        return [
            'vencidas' => $conRecompra($porSegmentoOrdenado('vencidas')),
            'esta_semana' => $conRecompra($porSegmentoOrdenado('esta_semana')),
            'este_mes' => $conRecompra($porSegmentoOrdenado('este_mes')),
        ];
    }

    /**
     * Todos los extintores de los clientes agrupados por empresa, vengan
     * cuando vengan: cuántos tiene, cuántos están vencidos o vencen en 30
     * días y su próximo vencimiento. Así la pantalla nunca queda vacía
     * aunque nada venza este mes. Ordenado por el vencimiento más próximo.
     *
     * Además de los extintores registrados con serie, cuenta lo que se vendió
     * sin serie (recargas y extintores por cantidad) y lo que el cliente
     * compró en el sistema anterior, con la recarga estimada al año de la
     * compra y marcado como estimado.
     *
     * @return array<int, array<string, mixed>>
     */
    public function porEmpresa(CarbonInterface $today, int $limite = 300): array
    {
        $registrados = Equipment::query()
            ->with('product')
            ->whereIn('estado', ['activo', 'descargado', 'usado'])
            ->get()
            ->map(function (Equipment $equipo) use ($today): array {
                $descargado = in_array($equipo->estado, ['descargado', 'usado'], true);
                $proxima = $descargado
                    ? $today->toDateString()
                    : collect([$equipo->proxima_fecha_atencion, $equipo->proxima_prueba_hidrostatica])
                        ->filter()
                        ->min(fn (CarbonInterface $fecha) => $fecha->toDateString());

                return [
                    'client_id' => $equipo->client_id,
                    ...$this->equipoEmpresa($today, $proxima, $descargado),
                    'equipment_id' => $equipo->id,
                    'numero_serie' => $equipo->numero_serie,
                    'equipo' => $equipo->product->nombre,
                    'capacidad' => $equipo->capacidad,
                    'ubicacion' => $equipo->ubicacion_actual,
                    'recarga' => $equipo->proxima_fecha_atencion?->toDateString(),
                    'prueba_hidrostatica' => $equipo->proxima_prueba_hidrostatica?->toDateString(),
                ];
            });

        $vendidosSinSerie = $this->vendidosSinSerie($today);
        $conDatos = $registrados->pluck('client_id')->merge($vendidosSinSerie->pluck('client_id'))->unique();
        $equipos = $registrados
            ->concat($vendidosSinSerie)
            ->concat($this->compradosEnSistemaAnterior($today)->whereNotIn('client_id', $conDatos->all()));

        // CLIENTES VARIOS no es una empresa a la que se le pueda ofrecer nada.
        $clientes = Client::query()
            ->whereIn('id', $equipos->pluck('client_id')->unique())
            ->where('tipo_documento', '!=', Client::TIPO_DOCUMENTO_VARIOS)
            ->get()
            ->keyBy('id');

        return $equipos
            ->filter(fn (array $equipo) => $clientes->has($equipo['client_id']))
            ->groupBy('client_id')
            ->map(function (Collection $deLaEmpresa, int $clientId) use ($clientes): array {
                /** @var Client $client */
                $client = $clientes->get($clientId);
                $detalle = $deLaEmpresa
                    ->map(fn (array $equipo) => collect($equipo)->except('client_id')->all())
                    ->sortBy(fn (array $equipo) => $equipo['proxima'] ?? '9999-12-31')
                    ->values();
                $proximo = $detalle->first();

                return [
                    'client_id' => $client->id,
                    'cliente' => $client->razon_social,
                    'numero_documento' => $client->numero_documento,
                    'whatsapp' => $client->whatsappInternacional(),
                    'extintores' => (int) $detalle->sum('cantidad'),
                    'vencidos' => (int) $detalle->whereIn('estado', ['vencido', 'descargado'])->sum('cantidad'),
                    'por_vencer' => (int) $detalle->where('estado', 'por_vencer')->sum('cantidad'),
                    'estimados' => (int) $detalle->where('estimado', true)->sum('cantidad'),
                    'proximo_vencimiento' => $proximo['proxima'] ?? null,
                    'dias' => $proximo['dias'] ?? null,
                    'equipos' => $detalle->all(),
                ];
            })
            ->sortBy(fn (array $empresa) => $empresa['proximo_vencimiento'] ?? '9999-12-31')
            ->take($limite)
            ->values()
            ->all();
    }

    /**
     * Estado y días al próximo vencimiento de una línea de "Por empresa".
     *
     * @return array{estado: string, proxima: string|null, dias: int|null, cantidad: int, estimado: bool}
     */
    protected function equipoEmpresa(CarbonInterface $today, ?string $proxima, bool $descargado = false, int $cantidad = 1, bool $estimado = false): array
    {
        $dias = $proxima ? (int) $today->diffInDays(Carbon::parse($proxima), false) : null;

        return [
            'estado' => $descargado ? 'descargado' : ($dias !== null && $dias < 0 ? 'vencido' : ($dias !== null && $dias <= 30 ? 'por_vencer' : 'al_dia')),
            'proxima' => $proxima,
            'dias' => $dias,
            'cantidad' => $cantidad,
            'estimado' => $estimado,
        ];
    }

    /**
     * Línea estimada (sin serie): se recarga al año de la última compra.
     *
     * @return array<string, mixed>
     */
    protected function equipoEstimado(CarbonInterface $today, int $clientId, string $nombre, string $fechaCompra, int $cantidad, string $origen): array
    {
        $proxima = Carbon::parse($fechaCompra)->addYear()->toDateString();

        return [
            'client_id' => $clientId,
            ...$this->equipoEmpresa($today, $proxima, cantidad: max(1, $cantidad), estimado: true),
            'equipment_id' => null,
            'numero_serie' => null,
            'equipo' => $nombre,
            'capacidad' => null,
            'ubicacion' => $origen,
            'recarga' => $proxima,
            'prueba_hidrostatica' => null,
        ];
    }

    /**
     * Recargas y extintores vendidos en este sistema sin un equipo con serie:
     * por cliente y por producto, la última compra.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function vendidosSinSerie(CarbonInterface $today): Collection
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('services', 'services.id', '=', 'sale_items.service_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.estado', 'confirmada')
            ->whereNull('sale_items.equipment_id')
            ->where(fn ($query) => $query
                ->where('services.nombre', 'like', '%RECARGA%')
                ->orWhere('products.categoria', 'extintor'))
            ->get([
                'sales.client_id',
                'sales.fecha',
                DB::raw('COALESCE(products.nombre, services.nombre) as nombre'),
                'sale_items.cantidad',
            ])
            ->groupBy(fn (object $linea) => "{$linea->client_id}|{$linea->nombre}")
            ->map(function (Collection $compras) use ($today): array {
                $fecha = (string) $compras->max('fecha');
                $ultima = $compras->filter(fn (object $linea) => (string) $linea->fecha === $fecha);
                $primera = $ultima->first();

                return $this->equipoEstimado($today, (int) $primera->client_id, (string) $primera->nombre, $fecha, (int) round((float) $ultima->sum('cantidad')), 'Sin serie (estimado por la venta)');
            })
            ->values();
    }

    /**
     * Lo que el cliente compró en el sistema anterior (recargas y
     * extintores): por cliente, lo de su última compra.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function compradosEnSistemaAnterior(CarbonInterface $today): Collection
    {
        return DB::table('ml_lineas_historicas')
            ->join('ml_comprobantes_historicos', 'ml_comprobantes_historicos.comprobante', '=', 'ml_lineas_historicas.comprobante')
            ->join('ml_productos_historicos', 'ml_productos_historicos.id', '=', 'ml_lineas_historicas.ml_producto_id')
            ->join('clients', 'clients.numero_documento', '=', 'ml_comprobantes_historicos.documento_cliente')
            ->where(fn ($query) => $query
                ->whereIn('ml_productos_historicos.categoria', ['recarga_mantenimiento', 'mantenimiento'])
                ->orWhere('ml_productos_historicos.nombre', 'like', 'EXTINTOR%'))
            ->get([
                'clients.id as client_id', 'ml_comprobantes_historicos.fecha',
                'ml_productos_historicos.nombre', 'ml_lineas_historicas.cantidad',
            ])
            ->groupBy('client_id')
            ->flatMap(function (Collection $delCliente) use ($today): Collection {
                $fecha = (string) $delCliente->max('fecha');

                return $delCliente
                    ->filter(fn (object $linea) => (string) $linea->fecha === $fecha)
                    ->groupBy('nombre')
                    ->map(fn (Collection $lineas, string $nombre) => $this->equipoEstimado($today, (int) $lineas->first()->client_id, $nombre, $fecha, (int) round((float) $lineas->sum('cantidad')), 'Sistema anterior (estimado)'))
                    ->values();
            })
            ->values();
    }

    /**
     * @return array{client_id: int, cliente: string, equipment_id: int, equipo: string, numero_serie: string|null, fecha: string, dias: int, segmento: string, tipo_alerta: string, motivo_alerta: string, telefono: string|null, whatsapp: string|null, origen: string}|null
     */
    protected function alertRow(Equipment $equipment, CarbonInterface $today): ?array
    {
        // Regla de un solo uso: si el extintor fue percutado o usado, pierde presión
        // inmediatamente y no puede esperar a la fecha anual de vencimiento.
        $esDescargado = in_array($equipment->estado, ['descargado', 'usado'], true);
        if ($esDescargado) {
            return [
                'client_id' => $equipment->client_id,
                'cliente' => $equipment->client->razon_social,
                'equipment_id' => $equipment->id,
                'equipo' => $equipment->product->nombre,
                'numero_serie' => $equipment->numero_serie,
                'fecha' => $today->toDateString(),
                'dias' => -1,
                'segmento' => 'vencidas',
                'tipo_alerta' => 'descargado_uso',
                'motivo_alerta' => 'Extintor usado / descargado (Equipo de un solo uso — requiere recarga inmediata)',
                'telefono' => $equipment->client->telefono,
                'whatsapp' => $equipment->client->whatsapp,
                'origen' => 'equipo_registrado',
            ];
        }

        $fechaAtencion = $equipment->proxima_fecha_atencion;
        $fechaPrueba = $equipment->proxima_prueba_hidrostatica;

        if (! $fechaAtencion && ! $fechaPrueba) {
            return null;
        }

        $vencidaAtencion = $fechaAtencion && $today->diffInDays($fechaAtencion, false) < 0;
        $vencidaPrueba = $fechaPrueba && $today->diffInDays($fechaPrueba, false) < 0;

        $fecha = null;
        $tipoAlerta = 'recarga_anual';
        $motivoAlerta = 'Recarga anual obligatoria (1 año)';

        if ($vencidaAtencion && $vencidaPrueba) {
            // Ambas vencidas: se toma la más antigua para priorizar urgencia
            $fecha = $fechaAtencion->lt($fechaPrueba) ? $fechaAtencion : $fechaPrueba;
            $tipoAlerta = 'recarga_y_ph';
            $motivoAlerta = 'Recarga anual (1 año) y Prueba Hidrostática PH (5 años) vencidas';
        } elseif ($vencidaPrueba) {
            $fecha = $fechaPrueba;
            $tipoAlerta = 'prueba_hidrostatica';
            $motivoAlerta = 'Prueba Hidrostática PH vencida (Ciclo de 5 años)';
        } elseif ($vencidaAtencion) {
            $fecha = $fechaAtencion;
            $tipoAlerta = 'recarga_anual';
            $motivoAlerta = 'Recarga anual vencida (1 año)';
        } else {
            // Ninguna vencida aún: ordenar por la que vencerá más pronto
            $fecha = collect([$fechaAtencion, $fechaPrueba])
                ->filter()
                ->sortBy(fn (CarbonInterface $date) => abs($today->diffInDays($date, false)))
                ->first();

            if ($fecha === $fechaPrueba) {
                $tipoAlerta = 'prueba_hidrostatica';
                $motivoAlerta = 'Prueba Hidrostática PH por vencer (Ciclo obligatorio de 5 años)';
            }
        }

        $dias = (int) $today->diffInDays($fecha, false);
        $segmento = match (true) {
            $dias < 0 => 'vencidas',
            $dias <= 7 => 'esta_semana',
            $dias <= 30 => 'este_mes',
            default => null,
        };

        if (! $segmento) {
            return null;
        }

        return [
            'client_id' => $equipment->client_id,
            'cliente' => $equipment->client->razon_social,
            'equipment_id' => $equipment->id,
            'equipo' => $equipment->product->nombre,
            'numero_serie' => $equipment->numero_serie,
            'fecha' => $fecha->toDateString(),
            'dias' => $dias,
            'segmento' => $segmento,
            'tipo_alerta' => $tipoAlerta,
            'motivo_alerta' => $motivoAlerta,
            'telefono' => $equipment->client->telefono,
            'whatsapp' => $equipment->client->whatsapp,
            'origen' => 'equipo_registrado',
        ];
    }

    /**
     * Recarga estimada de clientes ya registrados que compraban en el sistema
     * anterior (ml_lineas_historicas): su próxima atención es un año después
     * de su última compra de extintores o de recarga/mantenimiento. Una
     * alerta por cliente, con lo que llevó esa última vez. No aplica si el
     * cliente ya tiene extintores registrados (esos avisan por sí mismos) ni
     * si ya volvió a comprar en el sistema nuevo después de esa fecha.
     *
     * @return Collection<int, array{client_id: mixed, cliente: mixed, equipment_id: null, equipo: string, numero_serie: null, fecha: string, dias: int, segmento: string, tipo_alerta: string, motivo_alerta: string, cantidad: int, telefono: mixed, whatsapp: mixed, origen: string}>
     */
    protected function sistemaAnteriorRows(CarbonInterface $today): Collection
    {
        $lineas = DB::table('ml_lineas_historicas')
            ->join('ml_comprobantes_historicos', 'ml_comprobantes_historicos.comprobante', '=', 'ml_lineas_historicas.comprobante')
            ->join('ml_productos_historicos', 'ml_productos_historicos.id', '=', 'ml_lineas_historicas.ml_producto_id')
            ->join('clients', 'clients.numero_documento', '=', 'ml_comprobantes_historicos.documento_cliente')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('equipment')->whereColumn('equipment.client_id', 'clients.id'))
            ->where(fn ($query) => $query
                ->whereIn('ml_productos_historicos.categoria', ['recarga_mantenimiento', 'mantenimiento'])
                ->orWhere('ml_productos_historicos.nombre', 'like', 'EXTINTOR%'))
            ->get([
                'clients.id as client_id', 'clients.razon_social', 'clients.telefono', 'clients.whatsapp',
                'ml_comprobantes_historicos.fecha', 'ml_productos_historicos.nombre', 'ml_lineas_historicas.cantidad',
            ]);

        $ultimaVentaNueva = DB::table('sales')
            ->where('estado', 'confirmada')
            ->whereIn('client_id', $lineas->pluck('client_id')->unique())
            ->groupBy('client_id')
            ->selectRaw('client_id, MAX(fecha) as ultima')
            ->pluck('ultima', 'client_id');

        return $lineas
            ->groupBy('client_id')
            ->map(function (Collection $delCliente) use ($today, $ultimaVentaNueva): ?array {
                $ultimaFecha = $delCliente->max('fecha');
                $ultima = $delCliente->where('fecha', $ultimaFecha);
                $primera = $ultima->first();
                $ventaNueva = $ultimaVentaNueva->get($primera->client_id);

                if ($ventaNueva && Carbon::parse($ventaNueva)->gt(Carbon::parse($ultimaFecha))) {
                    return null;
                }

                $proximaFecha = Carbon::parse($ultimaFecha)->addYear();
                $dias = (int) $today->diffInDays($proximaFecha, false);
                $segmento = match (true) {
                    $dias < 0 => 'vencidas',
                    $dias <= 7 => 'esta_semana',
                    $dias <= 30 => 'este_mes',
                    default => null,
                };

                if (! $segmento) {
                    return null;
                }

                $productos = $ultima->pluck('nombre')->unique()->values();

                return [
                    'client_id' => $primera->client_id,
                    'cliente' => $primera->razon_social,
                    'equipment_id' => null,
                    'equipo' => $productos->first().($productos->count() > 1 ? ' y '.($productos->count() - 1).' más' : '').' (estimado)',
                    'numero_serie' => null,
                    'fecha' => $proximaFecha->toDateString(),
                    'dias' => $dias,
                    'segmento' => $segmento,
                    'tipo_alerta' => 'recarga_anual',
                    'motivo_alerta' => 'Recarga anual estimada (1 año)',
                    'cantidad' => (int) round((float) $ultima->sum('cantidad')),
                    'telefono' => $primera->telefono,
                    'whatsapp' => $primera->whatsapp,
                    'origen' => 'estimado_historico',
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Alertas estimadas desde el histórico de ventas real (import de
     * BruceFire_Historico_Limpio), para clientes que no tienen un
     * `Equipment` registrado con número de serie. Regla de negocio fija:
     * un extintor se recarga obligatoriamente cada 12 meses. No se inventa
     * número de serie ni marca — se marca explícitamente como estimado.
     *
     * Granularidad por empresa Y por línea de compra (no se colapsa todo
     * el historial de un cliente a una sola fecha): una empresa que compró
     * extintores en fechas distintas tiene una alerta separada por cada
     * compra, porque cada una vence en un momento distinto.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function historicalRows(CarbonInterface $today): Collection
    {
        // Solo interesan compras cuyo vencimiento (fecha + 1 año) cae dentro
        // de la misma ventana que ya usa alertRow() (vencidas sin límite
        // hacia atrás, hasta 30 días hacia adelante) — se filtra en SQL para
        // no traer a PHP miles de líneas históricas irrelevantes.
        $limiteSuperior = $today->copy()->addDays(30)->subYear();

        $rows = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('clients', 'clients.id', '=', 'sales.client_id')
            ->leftJoin('services', 'services.id', '=', 'sale_items.service_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.estado', '!=', 'anulada')
            ->where('sales.fecha', '<=', $limiteSuperior->toDateString())
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNotNull('services.nombre')
                        ->where('services.nombre', 'like', '%RECARGA%')
                        ->where('services.nombre', 'like', '%EXTINTOR%');
                })->orWhere('products.categoria', 'extintor');
            })
            ->select([
                'sales.client_id',
                'clients.razon_social',
                'clients.telefono',
                'clients.whatsapp',
                'sales.fecha',
                DB::raw('COALESCE(products.nombre, services.nombre) as item_nombre'),
                'sale_items.cantidad',
            ])
            ->get();

        $mapped = $rows
            ->map(function ($row) use ($today) {
                $proximaFecha = Carbon::parse($row->fecha)->addYear();
                $dias = (int) $today->diffInDays($proximaFecha, false);

                $segmento = match (true) {
                    $dias < 0 => 'vencidas',
                    $dias <= 7 => 'esta_semana',
                    $dias <= 30 => 'este_mes',
                    default => null,
                };

                if (! $segmento) {
                    return null;
                }

                return [
                    'client_id' => $row->client_id,
                    'cliente' => $row->razon_social,
                    'equipment_id' => null,
                    'equipo' => "{$row->item_nombre} (estimado)",
                    'numero_serie' => null,
                    'fecha' => $proximaFecha->toDateString(),
                    'dias' => $dias,
                    'segmento' => $segmento,
                    'tipo_alerta' => 'recarga_anual',
                    'motivo_alerta' => 'Recarga anual estimada (1 año)',
                    'cantidad' => (int) $row->cantidad,
                    'telefono' => $row->telefono,
                    'whatsapp' => $row->whatsapp,
                    'origen' => 'estimado_historico',
                ];
            })
            ->filter()
            // Fusiona solo duplicados exactos (mismo cliente, mismo ítem,
            // misma fecha de vencimiento) que puedan venir de líneas
            // repetidas dentro de una misma venta — nunca fechas distintas.
            ->groupBy(fn (array $row) => "{$row['client_id']}|{$row['equipo']}|{$row['fecha']}")
            ->map(function (Collection $group): array {
                /** @var array<string, mixed> $first */
                $first = (array) $group->first();
                $first['cantidad'] = (int) $group->sum('cantidad');

                return $first;
            })
            ->values();

        /** @var Collection<int, array<string, mixed>> $result */
        $result = $mapped;

        return $result;
    }
}
