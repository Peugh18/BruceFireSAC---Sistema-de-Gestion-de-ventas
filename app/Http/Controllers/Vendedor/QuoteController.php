<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Cotizaciones\CreateQuote;
use App\Actions\Cotizaciones\IniciarOrdenDesdeCotizacion;
use App\Actions\Cotizaciones\TransitionQuoteState;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Vendedor\Concerns\AcotaPorSede;
use App\Http\Requests\Cotizaciones\StoreQuoteRequest;
use App\Models\Client;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Services\Billing\AfectacionIgv;
use App\Services\Quotes\QuotePdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class QuoteController extends Controller
{
    use AcotaPorSede;

    public function index(Request $request, QuotePdfService $pdf): Response
    {
        $estado = $request->string('estado')->toString();
        $sedeId = $request->user()->sedeRestringidaId();
        $porSede = fn ($query) => $query->when($sedeId, fn ($q) => $q->where('sede_id', $sedeId));

        $quotes = $porSede(Quote::query())
            ->with(['client', 'sale', 'serviceOrders' => fn ($q) => $q->where('estado', '!=', 'anulada')->select('id', 'quote_id', 'codigo')])
            ->withExists(['items as tiene_servicio' => fn ($q) => $q->whereNotNull('service_id')])
            ->when($estado !== '' && $estado !== 'todas', function ($query) use ($estado) {
                return $estado === 'enviada'
                    ? $query->whereIn('estado', ['emitida', 'enviada', 'pendiente'])
                    : $query->where('estado', $estado);
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        // Cada vendedor abre solo sus ventas: de las demás se muestra el número.
        $ventasVisibles = Sale::query()->visiblePara($request->user())
            ->whereIn('id', $quotes->getCollection()->pluck('sale.id')->filter())
            ->pluck('id');

        $quotes->through(fn (Quote $quote) => [
            'id' => $quote->id,
            'numero' => $quote->numero,
            'cliente' => $quote->client->razon_social,
            'client_id' => $quote->client_id,
            'total' => $quote->total,
            'vigencia_hasta' => $quote->vigencia_hasta->toDateString(),
            'estado' => $quote->estado,
            'whatsapp' => $quote->client->esClientesVarios() ? null : $quote->client->whatsappInternacional(),
            'puede_guardar_numero' => ! $quote->client->esClientesVarios(),
            'enlace_pdf' => $pdf->enlacePublico($quote),
            'venta' => $quote->sale ? ['id' => $ventasVisibles->contains($quote->sale->id) ? $quote->sale->id : null, 'numero' => $quote->sale->numero_interno] : null,
            // La orden de taller solo existe para cotizaciones con un servicio (recarga, mantenimiento).
            'tiene_servicio' => (bool) $quote->tiene_servicio,
            'orden' => ($orden = $quote->serviceOrders->first()) ? ['id' => $orden->id, 'codigo' => $orden->codigo] : null,
        ]);

        return Inertia::render('vendedor/cotizaciones/index', [
            'quotes' => $quotes,
            'filters' => ['estado' => $estado],
            'kpis' => [
                'activas' => $porSede(Quote::query())->whereIn('estado', ['borrador', 'emitida', 'enviada', 'aceptada'])->count(),
                'por_vencer' => $porSede(Quote::query())->where('estado', 'enviada')
                    ->whereBetween('vigencia_hasta', [now(), now()->addDays(7)])
                    ->count(),
                'aceptadas_este_mes' => $porSede(Quote::query())->whereIn('estado', ['aceptada', 'convertida'])
                    ->whereBetween('updated_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'vencidas' => $porSede(Quote::query())->where('estado', 'vencida')->count(),
            ],
        ]);
    }

    public function create(Team $current_team, Request $request): Response
    {
        $quote = null;
        $ordenOrigen = null;

        if ($request->filled('service_order_id')) {
            $request->validate(['service_order_id' => ['required', 'integer']]);
            $ordenOrigen = ServiceOrder::query()->visiblePara($request->user())
                ->with(['client', 'lines.service', 'service', 'equipments'])
                ->findOrFail($request->integer('service_order_id'));
            abort_if($ordenOrigen->quote_id !== null || $ordenOrigen->sale_id !== null || $ordenOrigen->estado === 'anulada', 422, 'Esta orden ya tiene un documento comercial o está anulada.');
        }

        if ($request->filled('renovar')) {
            $quote = Quote::query()
                ->with('client', 'items.product', 'items.service')
                ->where('estado', 'vencida')
                ->when($request->user()->sedeRestringidaId(), fn ($query, $sedeId) => $query->where('sede_id', $sedeId))
                ->find($request->integer('renovar'));
        }

        return Inertia::render('vendedor/cotizaciones/nueva', [
            'noGravados' => AfectacionIgv::noGravados(),
            'ordenOrigen' => $ordenOrigen ? [
                'id' => $ordenOrigen->id, 'codigo' => $ordenOrigen->codigo,
                'tipo_servicio' => $ordenOrigen->resumenServicios(),
                'client' => $ordenOrigen->client->ficha(),
                'referencia' => $ordenOrigen->referencia, 'destino' => $ordenOrigen->vehicle_id ? 'vehiculo' : 'local_cliente',
                'vehicle_id' => $ordenOrigen->vehicle_id, 'observaciones' => $ordenOrigen->observaciones,
                'items' => $ordenOrigen->lines->isNotEmpty()
                    ? $ordenOrigen->lines->map(fn ($line) => ['tipo' => 'service', 'service_id' => $line->service_id, 'codigo' => $line->service?->codigo, 'nombre' => $line->service?->nombre, 'cantidad' => (int) $line->cantidad, 'precio_unitario' => (float) $line->service?->precio_venta, 'descuento' => 0])->values()
                    : ($ordenOrigen->service ? [['tipo' => 'service', 'service_id' => $ordenOrigen->service_id, 'codigo' => $ordenOrigen->service->codigo, 'nombre' => $ordenOrigen->service->nombre, 'cantidad' => max(1, $ordenOrigen->equipments->count()), 'precio_unitario' => (float) $ordenOrigen->service->precio_venta, 'descuento' => 0]] : []),
            ] : null,
            // Precarga desde el enlace "Nueva cotización" de la ficha del cliente (?client_id=).
            'cliente' => $request->filled('client_id')
                // El cliente se comparte entre sedes: no hay clients.sede_id que filtrar.
                ? Client::query()->find($request->integer('client_id'))?->ficha()
                : null,
            'renovacion' => $quote ? [
                'numero' => $quote->numero,
                'client' => $quote->client->ficha(),
                'referencia' => $quote->referencia,
                'direccion_del_local' => (bool) $quote->direccion_del_local,
                'destino' => $quote->destinoResuelto(),
                'vehicle_id' => $quote->vehicle_id,
                'condicion_pago_propuesta' => $quote->condicion_pago_propuesta,
                'observaciones' => $quote->observaciones,
                'items' => $quote->items->map(fn ($item) => [
                    'tipo' => $item->product_id ? 'product' : 'service',
                    'product_id' => $item->product_id,
                    'service_id' => $item->service_id,
                    'codigo' => $item->product->codigo ?? $item->service?->codigo,
                    'nombre' => $item->product->nombre ?? $item->service?->nombre,
                    'cantidad' => (int) $item->cantidad,
                    'precio_unitario' => (float) $item->precio_unitario,
                    'descuento' => (float) $item->descuento,
                ])->values(),
            ] : null,
        ]);
    }

    /**
     * Búsqueda liviana de productos + servicios para el selector de ítems
     * de Nueva Cotización (ver nota en create() sobre por qué no se manda
     * el catálogo completo como prop).
     */
    public function iniciarOrden(Team $current_team, Quote $quote, Request $request, IniciarOrdenDesdeCotizacion $iniciar): RedirectResponse
    {
        $this->asegurarSede($quote->sede_id);
        $opciones = $request->validate([
            'departamento_tecnico' => ['nullable', 'in:planta,campo'],
            'fecha' => ['nullable', 'date'],
            'prioridad' => ['nullable', 'in:normal,alta,urgente'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);
        $orden = $iniciar->handle($quote, $request->user(), array_filter($opciones));

        return redirect()->route('vendedor.ordenes-servicio.show', [$current_team, $orden]);
    }

    public function edit(Team $current_team, Quote $quote): Response
    {
        $this->asegurarSede($quote->sede_id);
        abort_unless(in_array($quote->estado, ['borrador', 'emitida', 'enviada', 'aceptada'], true) && ! $quote->sale()->exists(), 422);
        $quote->load('client', 'items.product', 'items.service');

        return Inertia::render('vendedor/cotizaciones/nueva', ['noGravados' => AfectacionIgv::noGravados(), 'edicion' => [
            'id' => $quote->id, 'numero' => $quote->numero, 'fecha' => $quote->fecha->toDateString(), 'vigencia_hasta' => $quote->vigencia_hasta->toDateString(),
            'client' => $quote->client->ficha(), 'referencia' => $quote->referencia, 'direccion_del_local' => (bool) $quote->direccion_del_local,
            'destino' => $quote->destinoResuelto(), 'vehicle_id' => $quote->vehicle_id,
            'condicion_pago_propuesta' => $quote->condicion_pago_propuesta, 'observaciones' => $quote->observaciones,
            'items' => $quote->items->map(fn ($item): array => ['deficiency_id' => $item->deficiency_id, 'tipo' => $item->product_id ? 'product' : 'service', 'product_id' => $item->product_id, 'service_id' => $item->service_id, 'codigo' => $item->product->codigo ?? $item->service?->codigo, 'nombre' => $item->product->nombre ?? $item->service?->nombre, 'cantidad' => (int) $item->cantidad, 'precio_unitario' => (float) $item->precio_unitario, 'descuento' => (float) $item->descuento])->values(),
        ]]);
    }

    public function update(Team $current_team, Quote $quote, StoreQuoteRequest $request, CreateQuote $action): RedirectResponse
    {
        $this->asegurarSede($quote->sede_id);
        $items = $request->safe()->input('items');
        DB::transaction(function () use ($quote, $request, $items, $action): void {
            // La orden de recojo nace con la cantidad de extintores esperada.
            $ordenRecojo = $quote->serviceOrders()
                ->whereHas('events', fn ($evento) => $evento->where('tipo', 'creada')->whereNotNull('payload->cantidad_esperada'))
                ->where('quote_id', $quote->id)
                ->lockForUpdate()
                ->first();
            $actualizaServicios = $ordenRecojo !== null
                && $ordenRecojo->lines()->whereNull('quote_item_id')->doesntExist();

            if ($actualizaServicios) {
                if (! in_array($ordenRecojo->estado, ['pendiente_recepcion', 'recibido_planta'], true)
                    || $ordenRecojo->checklists()->exists()) {
                    throw ValidationException::withMessages(['items' => 'La orden ya empezó el trabajo técnico; no se puede cambiar el servicio cotizado.']);
                }

                $this->validarItemsDeOrden($ordenRecojo, $items, true);
            }

            $action->actualizar($quote, $request->safe()->except('items'), $items, $request->user()->id);

            if ($actualizaServicios) {
                $this->crearLineasDesdeCotizacion($ordenRecojo, $quote->fresh(), true);
            }
        }, 3);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cotización corregida; historial conservado.']);

        return redirect()->route('vendedor.cotizaciones.index', [$current_team]);
    }

    public function searchCatalogo(Request $request): JsonResponse
    {
        $search = $request->string('search')->toString();

        if (mb_strlen($search) < 2) {
            return response()->json([]);
        }

        $products = Product::query()
            ->where('activo', true)
            ->where('nombre', 'like', "%{$search}%")
            ->orderBy('nombre')
            ->limit(10)
            ->get(['id', 'nombre', 'precio_venta'])
            ->map(fn (Product $product) => [...$product->only(['id', 'nombre', 'precio_venta']), 'tipo' => 'product']);

        $services = Service::query()
            ->where('activo', true)
            ->where('nombre', 'like', "%{$search}%")
            ->orderBy('nombre')
            ->limit(10)
            ->get(['id', 'nombre', 'precio_venta'])
            ->map(fn (Service $service) => [...$service->only(['id', 'nombre', 'precio_venta']), 'tipo' => 'service']);

        return response()->json($products->concat($services)->values());
    }

    public function store(StoreQuoteRequest $request, CreateQuote $createQuote): RedirectResponse
    {
        $data = $request->safe()->except(['items', 'service_order_id']);
        $items = $request->safe()->input('items');
        foreach ($items as $item) {
            abort_if(! empty($item['deficiency_id']), 422);
        }

        if ($sedeId = $request->user()->sedeRestringidaId()) {
            $data['sede_id'] = $sedeId;
        }

        $quote = DB::transaction(function () use ($request, $data, $items, $createQuote): Quote {
            $orden = $request->filled('service_order_id')
                ? ServiceOrder::query()->visiblePara($request->user())->lockForUpdate()->findOrFail($request->integer('service_order_id'))
                : null;
            if ($orden) {
                if ($orden->quote_id !== null || $orden->sale_id !== null || $orden->estado === 'anulada') {
                    throw ValidationException::withMessages(['service_order_id' => 'La orden ya tiene cotización o venta, o está anulada. Abre su documento existente.']);
                }
                if ((int) $data['client_id'] !== $orden->client_id) {
                    throw ValidationException::withMessages(['client_id' => 'Conserva el cliente de la orden; corrige sus datos en el mismo registro.']);
                }
                $this->validarItemsDeOrden($orden, $items);
                $data['sede_id'] = $orden->sede_id;
                $data['referencia'] = $orden->referencia;
                $data['vehicle_id'] = $orden->vehicle_id;
                $data['destino'] = $orden->vehicle_id ? 'vehiculo' : 'local_cliente';
                $data['observaciones'] = implode("\n", array_unique(array_filter([
                    $orden->observaciones,
                    $data['observaciones'] ?? null,
                ])));
            }
            $quote = $createQuote->handle($data, $items, $request->user()->id);
            if ($orden) {
                $this->crearLineasDesdeCotizacion($orden, $quote);
                $orden->update(['quote_id' => $quote->id]);
                $quote->equipments()->sync($orden->equipments()->pluck('equipment.id')->all());
                $orden->events()->create(['tipo' => 'otro', 'user_id' => $request->user()->id, 'payload' => ['accion' => 'recojo_cotizado', 'quote_id' => $quote->id, 'mensaje' => 'Cotización vinculada al mismo servicio; vendedor responsable asignado.']]);
            }

            return $quote;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Cotización {$quote->numero} creada. Pulsa Enviar cuando se la mandes al cliente."]);

        return redirect()->route('vendedor.cotizaciones.index', [
            'current_team' => $request->route('current_team'),
        ]);
    }

    /**
     * Los precios de una cotización pueden variar, pero las líneas de un
     * recojo ya pertenecen a la orden que Campo llevó a Planta. No se deben
     * omitir, duplicar ni reemplazar al formalizarla comercialmente.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function validarItemsDeOrden(ServiceOrder $orden, array $items, bool $redefinirServicios = false): void
    {
        $lineas = $orden->lines()->get(['service_id', 'cantidad']);

        if (($lineas->isEmpty() && $orden->service_id === null) || $redefinirServicios) {
            $servicios = collect($items)
                ->filter(fn (array $item): bool => ! empty($item['service_id']) && empty($item['product_id']))
                ->values();
            $cantidadCotizada = (int) $servicios->sum('cantidad');
            $cantidadEsperada = $orden->cantidadEquiposEsperada();

            if ($servicios->count() !== count($items) || $cantidadEsperada < 1 || $cantidadCotizada !== $cantidadEsperada) {
                throw ValidationException::withMessages([
                    'items' => 'Agrega servicios que cubran la cantidad de extintores registrada en el recojo.',
                ]);
            }

            $catalogo = Service::query()
                ->whereIn('id', $servicios->pluck('service_id')->unique())
                ->get()
                ->keyBy('id');
            $cupos = $servicios->map(fn (array $item): array => [
                'service' => $catalogo->get((int) $item['service_id']),
                'cantidad' => (int) $item['cantidad'],
                'asignados' => 0,
            ])->all();

            foreach ($orden->equipments()->get() as $equipment) {
                $asignado = false;
                foreach ($cupos as &$cupo) {
                    if ($cupo['service'] !== null
                        && $cupo['asignados'] < $cupo['cantidad']
                        && $cupo['service']->incompatibilidadConEquipo($equipment) === null) {
                        $cupo['asignados']++;
                        $asignado = true;
                        break;
                    }
                }
                unset($cupo);

                if (! $asignado) {
                    throw ValidationException::withMessages([
                        'items' => "No hay un servicio compatible con el extintor {$equipment->numero_serie}.",
                    ]);
                }
            }

            return;
        }

        $esperados = $lineas
            ->groupBy('service_id')
            ->map(fn ($lineas): int => $lineas->sum('cantidad'))
            ->all();

        if ($esperados === [] && $orden->service_id !== null) {
            $esperados[$orden->service_id] = max(1, $orden->equipments()->count());
        }

        $recibidos = collect($items)
            ->filter(fn (array $item): bool => ! empty($item['service_id']) && empty($item['product_id']))
            ->groupBy('service_id')
            ->map(fn ($lineas): int => $lineas->sum('cantidad'))
            ->all();

        ksort($esperados);
        ksort($recibidos);

        if (count($recibidos) !== count($items) || $esperados !== $recibidos) {
            throw ValidationException::withMessages([
                'items' => 'Conserva los servicios y cantidades registrados en la orden. Solo puedes ajustar los precios y descuentos.',
            ]);
        }
    }

    private function crearLineasDesdeCotizacion(ServiceOrder $orden, Quote $quote, bool $reemplazar = false): void
    {
        if (! $reemplazar && ($orden->lines()->exists() || $orden->service_id !== null)) {
            return;
        }

        $items = $quote->items()->whereNotNull('service_id')->orderBy('id')->get();
        $servicioPrincipal = $items->first();

        if ($servicioPrincipal === null) {
            return;
        }

        if ($reemplazar) {
            $orden->lines()->delete();
        }

        $orden->update(['service_id' => $servicioPrincipal->service_id]);
        foreach ($items as $item) {
            $orden->lines()->create([
                'service_id' => $item->service_id,
                'cantidad' => (int) $item->cantidad,
                'quote_item_id' => $item->id,
            ]);
        }

        $orden->load('lines.service');
        $asignados = [];
        foreach ($orden->equipments()->get() as $equipment) {
            $line = $orden->lines->first(function ($line) use ($equipment, &$asignados): bool {
                if (($asignados[$line->id] ?? 0) >= $line->cantidad) {
                    return false;
                }

                return $line->service->incompatibilidadConEquipo($equipment) === null;
            });

            if ($line === null) {
                continue;
            }

            $asignados[$line->id] = ($asignados[$line->id] ?? 0) + 1;
            $orden->equipments()->updateExistingPivot($equipment->id, [
                'service_order_line_id' => $line->id,
            ]);
        }
    }

    /**
     * Nota: {current_team} es un segmento de ruta que precede a {quote} en
     * routes/web.php (grupo compartido con todo el panel autenticado). El
     * dispatcher de Laravel pasa los parámetros de ruta POR POSICIÓN a menos
     * que cada uno tenga un parámetro de método correspondiente — por eso
     * $current_team se declara aquí (con ese nombre exacto, para el binding
     * implícito) aunque no se use, en vez de dejar que "current_team" se
     * cuele posicionalmente en el lugar de $quote.
     */
    /**
     * La lista solo ofrece "Enviar": un borrador se emite y se envía en el
     * mismo paso, respetando la secuencia borrador → emitida → enviada.
     */
    /**
     * PDF de la cotización con la marca: se abre para ver o imprimir, o se
     * descarga para adjuntarlo.
     */
    public function pdf(Team $current_team, Quote $quote, Request $request, QuotePdfService $pdf): HttpResponse
    {
        $sedeId = $request->user()->sedeRestringidaId();
        abort_if($sedeId !== null && (int) $quote->sede_id !== $sedeId, 404);

        $documento = $pdf->generate($quote);

        return $request->boolean('descargar')
            ? $documento->download($pdf->nombreArchivo($quote))
            : $documento->stream($pdf->nombreArchivo($quote));
    }

    public function send(Team $current_team, Quote $quote): RedirectResponse
    {
        $this->asegurarSede($quote->sede_id);

        $transitionQuoteState = app(TransitionQuoteState::class);

        DB::transaction(function () use ($quote, $transitionQuoteState) {
            if ($quote->estado === 'borrador') {
                $transitionQuoteState->handle($quote, 'emitida');
            }

            $transitionQuoteState->handle($quote, 'enviada');
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Cotización {$quote->numero} enviada al cliente."]);

        return back();
    }

    public function accept(Team $current_team, Quote $quote, Request $request): RedirectResponse
    {
        $this->asegurarSede($quote->sede_id);
        $datos = $request->validate([
            'aceptada_por' => ['required', 'string', 'max:120'],
            'aceptada_canal' => ['required', 'in:orden_compra,whatsapp,correo,presencial,llamada'],
            'orden_compra' => ['nullable', 'required_if:aceptada_canal,orden_compra', 'string', 'max:40'],
            'orden_compra_archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'orden_compra.required_if' => 'Escribe el número de la orden de compra del cliente.',
        ]);
        $datos['orden_compra_path'] = $request->file('orden_compra_archivo')?->store('cotizaciones/ordenes-compra', 'local');

        app(TransitionQuoteState::class)->aceptar($quote, $datos);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Cotización {$quote->numero} aceptada por {$datos['aceptada_por']}. Ya puedes pasarla a venta u orden."]);

        return back();
    }

    public function ordenCompra(Team $current_team, Quote $quote): HttpResponse
    {
        $this->asegurarSede($quote->sede_id);
        abort_unless($quote->orden_compra_path && Storage::disk('local')->exists($quote->orden_compra_path), 404);

        return Storage::disk('local')->response($quote->orden_compra_path);
    }

    public function reject(Team $current_team, Quote $quote): RedirectResponse
    {
        $this->asegurarSede($quote->sede_id);

        app(TransitionQuoteState::class)->handle($quote, 'rechazada');

        Inertia::flash('toast', ['type' => 'success', 'message' => "Cotización {$quote->numero} rechazada."]);

        return back();
    }
}
