<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Cotizaciones\CreateQuote;
use App\Http\Controllers\Controller;
use App\Models\ClientRetentionScore;
use App\Models\Equipment;
use App\Models\Service;
use App\Models\Team;
use App\Services\Ml\RetentionModel;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function offerRecharge(Team $current_team, Request $request, CreateQuote $createQuote): RedirectResponse
    {
        $data = $request->validate(['client_id' => ['required', 'integer', 'exists:clients,id'], 'equipment_ids' => ['required', 'array', 'min:1'], 'equipment_ids.*' => ['integer', 'distinct', 'exists:equipment,id']]);
        $equipments = Equipment::query()->where('client_id', $data['client_id'])->whereIn('id', $data['equipment_ids'])->get();
        if ($equipments->count() !== count($data['equipment_ids'])) {
            throw ValidationException::withMessages(['equipment_ids' => 'Todos los extintores deben pertenecer al cliente.']);
        }
        $service = Service::query()->where('activo', true)->where('nombre', 'like', '%recarga%')->first();
        if (! $service) {
            throw ValidationException::withMessages(['service' => 'Configura un servicio de recarga activo en el catálogo.']);
        }
        $quote = $createQuote->handle(['client_id' => $data['client_id'], 'sede_id' => $request->user()->sedeRestringidaId(), 'fecha' => today(), 'vigencia_hasta' => today()->addDays(15), 'condicion_pago_propuesta' => 'Contado', 'referencia' => 'Recarga de '.$equipments->pluck('numero_serie')->join(', ')], [['service_id' => $service->id, 'cantidad' => $equipments->count(), 'precio_unitario' => (float) $service->precio_venta]], $request->user()->id);
        $quote->equipments()->sync($equipments->modelKeys());

        return redirect()->route('vendedor.cotizaciones.index', ['current_team' => $current_team])->with('success', 'Cotización de recarga creada.');
    }

    public function index(Team $current_team, Request $request): Response
    {
        $today = today();

        $rows = Equipment::query()
            ->with(['client', 'product'])
            ->where(fn ($query) => $query
                ->whereNotNull('proxima_fecha_atencion')
                ->orWhereNotNull('proxima_prueba_hidrostatica'))
            ->get()
            ->map(fn (Equipment $equipment) => $this->alertRow($equipment, $today))
            ->filter()
            ->groupBy(fn (array $row) => "{$row['client_id']}|{$row['fecha']}")
            ->map(fn ($group) => [
                ...$group->first(),
                'cantidad' => $group->count(),
            ])
            ->values()
            ->concat($this->historicalRows($today))
            ->concat($this->sistemaAnteriorRows($today));

        // Cada segmento se trunca por separado (no un límite global) para
        // que "vencidas" no quede vacío solo porque hay más volumen en
        // "este mes" — cada lista prioriza lo más urgente dentro de sí misma.
        $porSegmento = fn (string $segmento) => $rows->where('segmento', $segmento)
            ->sortBy('dias')
            ->take(100)
            ->values();

        $scores = ClientRetentionScore::query()->whereIn('client_id', $rows->pluck('client_id')->unique())->get()->keyBy('client_id');
        $conRecompra = fn (Collection $lista) => $lista->map(fn (array $row) => [
            ...$row,
            'recompra' => ($score = $scores->get($row['client_id']))
                ? RetentionModel::paraPantalla(['probabilidad' => $score->probabilidad, 'categoria' => $score->categoria, 'factores' => $score->factores_json])
                : null,
        ])->values();

        return Inertia::render('vendedor/alertas/index', [
            'alerts' => [
                'vencidas' => $conRecompra($porSegmento('vencidas')),
                'esta_semana' => $conRecompra($porSegmento('esta_semana')),
                'este_mes' => $conRecompra($porSegmento('este_mes')),
            ],
        ]);
    }

    protected function alertRow(Equipment $equipment, CarbonInterface $today): ?array
    {
        $fechaAtencion = $equipment->proxima_fecha_atencion;
        $fechaPrueba = $equipment->proxima_prueba_hidrostatica;
        $fecha = collect([$fechaAtencion, $fechaPrueba])
            ->filter()
            ->sortBy(fn (CarbonInterface $date) => abs($today->diffInDays($date, false)))
            ->first();

        if (! $fecha) {
            return null;
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
     * @return Collection<int, array{client_id: mixed, cliente: mixed, equipment_id: null, equipo: string, numero_serie: null, fecha: string, dias: int, segmento: string, cantidad: int, telefono: mixed, whatsapp: mixed, origen: string}>
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

        return $rows
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
            ->map(function (Collection $group) {
                $first = $group->first();
                $first['cantidad'] = $group->sum('cantidad');

                return $first;
            })
            ->values();
    }
}
