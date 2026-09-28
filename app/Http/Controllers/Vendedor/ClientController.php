<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Clientes\CreateClient;
use App\Actions\Clientes\UpdateClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clientes\StoreClientRequest;
use App\Http\Requests\Clientes\UpdateClientRequest;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientSite;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Team;
use App\Services\Sunat\RucLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $clients = Client::query()
            ->withMax('sales', 'fecha')
            ->buscar($search)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Client $client) => [
                'id' => $client->id,
                'codigo_interno' => $client->codigo_interno,
                'cliente' => [
                    'razon_social' => $client->razon_social,
                    'tipo_documento' => $client->tipo_documento,
                    'avatar' => mb_substr($client->razon_social, 0, 1),
                ],
                'documento' => $client->numero_documento,
                'direccion' => $client->direccion_fiscal,
                'estado_sunat' => [
                    'estado_contribuyente' => $client->estado_contribuyente,
                    'condicion_domicilio' => $client->condicion_domicilio,
                ],
                'ultima_compra' => $client->sales_max_fecha
                    ? Carbon::parse($client->sales_max_fecha)->toDateString()
                    : null,
                'activo' => $client->activo,
            ]);

        return Inertia::render('vendedor/clientes/index', [
            'clients' => $clients,
            'filters' => [
                'search' => $search,
            ],
            'columns' => ['Cliente', 'RUC/DNI', 'Direccion', 'Estado SUNAT', 'Ultima compra', 'Acciones'],
            'kpis' => [
                'total_clientes' => Client::count(),
                'nuevos_este_mes' => Client::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
                'porcentaje_activo_habido' => $this->activeAndLocatedPercentage(),
                'inactivos' => Client::where('activo', false)->count(),
            ],
        ]);
    }

    /**
     * Búsqueda liviana de clientes para selectores de otras pantallas
     * (Nueva Venta, Nueva Cotización) que no pueden mandar la tabla
     * completa de clientes como prop en cada carga de página.
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->string('search')->toString();

        if (mb_strlen(trim($search)) < 1) {
            return response()->json([]);
        }

        $clients = Client::query()
            ->buscar($search)
            ->orderBy('razon_social')
            ->limit(10)
            ->get(['id', 'tipo_documento', 'razon_social', 'numero_documento']);

        return response()->json($clients);
    }

    /**
     * Descarga en CSV (abre directo en Excel) los clientes que coinciden con
     * la búsqueda actual del listado, o todos si no hay búsqueda.
     */
    public function export(Request $request): StreamedResponse
    {
        $search = $request->string('search')->toString();

        return response()->streamDownload(function () use ($search) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Código', 'Tipo doc.', 'Número', 'Razón social / Nombre', 'Nombre comercial', 'Teléfono', 'Email', 'Dirección fiscal', 'Estado SUNAT', 'Condición', 'Última compra', 'Activo'], ';');

            Client::query()
                ->withMax('sales', 'fecha')
                ->buscar($search)
                ->orderBy('razon_social')
                ->lazy()
                ->each(fn (Client $client) => fputcsv($salida, [
                    $client->codigo_interno,
                    strtoupper($client->tipo_documento),
                    $client->numero_documento,
                    $client->razon_social,
                    $client->nombre_comercial,
                    $client->telefono ?? $client->whatsapp,
                    $client->email,
                    $client->direccion_fiscal,
                    $client->estado_contribuyente,
                    $client->condicion_domicilio,
                    $client->sales_max_fecha ? Carbon::parse($client->sales_max_fecha)->toDateString() : '',
                    $client->activo ? 'Sí' : 'No',
                ], ';'));

            fclose($salida);
        }, 'clientes-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(StoreClientRequest $request, CreateClient $createClient): RedirectResponse|JsonResponse
    {
        $client = $createClient->handle($request->validated());

        if ($request->expectsJson()) {
            return response()->json($client->only(['id', 'tipo_documento', 'razon_social', 'numero_documento']), 201);
        }

        return redirect()->route('vendedor.clientes.show', [
            'current_team' => $request->route('current_team'),
            'client' => $client,
        ]);
    }

    /**
     * Nota: {current_team} precede a {client}/{quote}/etc. en la ruta, y el
     * dispatcher de Laravel pasa los parámetros de ruta por POSICIÓN salvo
     * que cada uno tenga su propio parámetro de método — por eso se declara
     * $current_team aquí (con ese nombre, para el binding implícito) en vez
     * de dejar que se cuele posicionalmente en el lugar de otro parámetro.
     */
    public function show(Team $current_team, Client $client, Request $request): Response
    {
        $sedeId = $request->user()->sedeRestringidaId();
        $client->load(['sites.ubicacion', 'vehicles', 'ubicacion']);

        $quotes = $client->quotes()->with('sale')->when($sedeId, fn ($query) => $query->where('sede_id', $sedeId))->latest('fecha')->get();
        $sales = $client->sales()->with(['electronicDocuments', 'installments.payments'])->when($sedeId, fn ($query) => $query->where('sede_id', $sedeId))->latest('fecha')->latest('id')->get();
        $equipos = $client->equipment()->with('product:id,nombre')->latest('fecha_venta')->get();
        $serviceOrders = $client->serviceOrders()->with(['service:id,nombre', 'tecnico:id,name'])->when($sedeId, fn ($query) => $query->where('sede_id', $sedeId))->latest('fecha')->get();
        $certificates = $client->certificates()->with(['certificateType:id,nombre', 'sale:id,numero_interno'])->latest('fecha_emision')->get();
        $installments = $client->sales()->with(['installments.payments'])->when($sedeId, fn ($query) => $query->where('sede_id', $sedeId))->get()->flatMap->installments->sortByDesc('fecha_vencimiento')->values();
        $payments = $client->sales()->with('payments')->when($sedeId, fn ($query) => $query->where('sede_id', $sedeId))->get()->flatMap->payments->sortByDesc('fecha')->values();

        $confirmadas = $sales->where('estado', 'confirmada');
        $saldoDe = fn ($installment) => max(0, round((float) $installment->monto - (float) $installment->payments->sum('monto'), 2));
        $cuotasConSaldo = $confirmadas->flatMap->installments->filter(fn ($installment) => $saldoDe($installment) > 0);
        $en30Dias = fn ($fecha) => $fecha && $fecha->between(today(), today()->addDays(30));

        return Inertia::render('vendedor/clientes/show', [
            'client' => [...$client->makeHidden(['sites', 'ubicacion'])->toArray(), 'ubicacion' => $client->ubicacion?->paraFormulario()],
            'sites' => $client->sites->map(fn (ClientSite $site) => [...$site->makeHidden('ubicacion')->toArray(), 'ubicacion' => $site->ubicacion?->paraFormulario()]),
            'vehicles' => $client->vehicles,
            'resumen' => [
                'total_comprado' => round((float) $confirmadas->sum('total'), 2),
                'deuda_pendiente' => round((float) $cuotasConSaldo->sum($saldoDe), 2),
                'cuotas_vencidas' => $cuotasConSaldo->filter(fn ($installment) => $installment->fecha_vencimiento?->isBefore(today()))->count(),
                'extintores_activos' => $equipos->where('estado', 'activo')->count(),
                'por_vencer_30_dias' => $equipos->where('estado', 'activo')->filter(fn ($equipo) => $en30Dias($equipo->proxima_fecha_atencion) || $en30Dias($equipo->proxima_prueba_hidrostatica))->count(),
                'ultima_compra' => $confirmadas->max(fn ($sale) => $sale->fecha?->toDateString()),
                'cotizaciones_abiertas' => $quotes->whereIn('estado', ['borrador', 'emitida', 'enviada', 'pendiente', 'aceptada'])->count(),
            ],
            'cotizaciones' => $quotes->map(fn ($quote) => ['id' => $quote->id, 'numero' => $quote->numero, 'fecha' => $quote->fecha?->toDateString(), 'total' => (float) $quote->total, 'estado' => $quote->estado, 'vigencia_hasta' => $quote->vigencia_hasta?->toDateString(), 'venta' => $quote->sale ? ['id' => $quote->sale->id, 'numero' => $quote->sale->numero_interno] : null]),
            // Al contado ya está pagada; a crédito debe lo que falta de sus cuotas.
            'ventas' => $sales->map(fn ($sale) => [
                'id' => $sale->id,
                'numero_interno' => $sale->numero_interno,
                'fecha' => $sale->fecha?->toDateString(),
                'total' => (float) $sale->total,
                'estado' => $sale->estado,
                'comprobante' => $sale->numeroComprobante(),
                'sunat_estado' => $sale->electronicDocuments->whereIn('tipo', ['factura', 'boleta'])->sortBy('id')->last()?->sunat_estado,
                'condicion_pago' => $sale->esCredito() ? 'credito' : 'contado',
                'medio_pago' => $sale->medioPagoTexto(),
                'saldo_pendiente' => $sale->estado === 'confirmada' && $sale->esCredito() ? round((float) $sale->installments->sum($saldoDe), 2) : 0.0,
            ]),
            'extintores' => $equipos->map(fn ($equipment) => ['id' => $equipment->id, 'numero_serie' => $equipment->numero_serie, 'producto' => $equipment->product?->nombre, 'capacidad' => $equipment->capacidad, 'marca' => $equipment->marca, 'estado' => $equipment->estado, 'fecha_venta' => $equipment->fecha_venta?->toDateString(), 'proxima_fecha_atencion' => $equipment->proxima_fecha_atencion?->toDateString(), 'proxima_prueba_hidrostatica' => $equipment->proxima_prueba_hidrostatica?->toDateString(), 'vencido' => ($equipment->proxima_fecha_atencion?->isPast() ?? false) || ($equipment->proxima_prueba_hidrostatica?->isPast() ?? false)]),
            'certificados' => $certificates->map(fn ($certificate) => ['id' => $certificate->id, 'numero' => $certificate->numero, 'tipo' => $certificate->certificateType->nombre, 'fecha_emision' => $certificate->fecha_emision?->toDateString(), 'fecha_vigencia_hasta' => $certificate->fecha_vigencia_hasta?->toDateString(), 'estado' => $certificate->estado, 'venta' => $certificate->sale ? ['id' => $certificate->sale->id, 'numero' => $certificate->sale->numero_interno] : null]),
            'servicios' => $serviceOrders->map(fn ($order) => ['id' => $order->id, 'numero' => $order->codigo, 'servicio' => $order->service->nombre, 'area' => $order->departamento_tecnico, 'estado' => $order->estado, 'estado_texto' => $order->coarseLabel(), 'tecnico' => $order->tecnico?->name, 'fecha' => $order->fecha?->toDateString()]),
            'cobranzas' => [
                'cuotas' => $installments->map(fn ($installment) => ['id' => $installment->id, 'venta' => ['id' => $installment->sale_id, 'numero' => $sales->firstWhere('id', $installment->sale_id)?->numero_interno], 'numero_cuota' => $installment->numero_cuota, 'monto' => (float) $installment->monto, 'saldo' => max(0, (float) $installment->monto - (float) $installment->payments->sum('monto')), 'fecha_vencimiento' => $installment->fecha_vencimiento?->toDateString(), 'estado' => $installment->estado, 'dias_vencido' => $installment->fecha_vencimiento?->isPast() ? (int) $installment->fecha_vencimiento->diffInDays(today()) : 0]),
                'pagos' => $payments->map(fn ($payment) => ['id' => $payment->id, 'fecha' => $payment->fecha?->toDateString(), 'monto' => (float) $payment->monto, 'forma_pago' => $payment->forma_pago, 'numero_operacion' => $payment->numero_operacion, 'venta' => ['id' => $payment->sale_id, 'numero' => $sales->firstWhere('id', $payment->sale_id)?->numero_interno]]),
            ],
            'historial' => AuditLog::query()->with('user:id,name')->where(function ($query) use ($client, $sales, $quotes) {
                $query->where(fn ($query) => $query->where('auditable_type', Client::class)->where('auditable_id', $client->id))->orWhere(fn ($query) => $query->where('auditable_type', Sale::class)->whereIn('auditable_id', $sales->pluck('id')))->orWhere(fn ($query) => $query->where('auditable_type', Quote::class)->whereIn('auditable_id', $quotes->pluck('id')));
            })->latest('created_at')->limit(50)->get()->map(fn ($log) => ['fecha' => $log->created_at?->toIso8601String(), 'texto' => $this->textoDeHistorial($log), 'usuario' => $log->user?->name]),
            'sunat' => ['verificado' => $client->consultado_at !== null, 'estado' => $client->estado_contribuyente, 'condicion' => $client->condicion_domicilio, 'consultado_at' => $client->consultado_at?->toIso8601String()],
        ]);
    }

    public function verifySunat(Team $current_team, Client $client, RucLookupService $lookupService): RedirectResponse
    {
        abort_unless($client->tieneRuc(), 422, 'La consulta SUNAT solo aplica a clientes con RUC.');

        $data = $lookupService->lookup($client->numero_documento, refresh: true);
        $client->update([
            'estado_contribuyente' => $data['estado_contribuyente'],
            'condicion_domicilio' => $data['condicion_domicilio'],
            'direccion_fiscal' => $data['direccion'] ?: $client->direccion_fiscal,
            'ubigeo' => $data['ubigeo'] ?? $client->ubigeo,
            'consultado_at' => now(),
        ]);

        return back()->with('success', 'Datos SUNAT actualizados.');
    }

    public function update(Team $current_team, UpdateClientRequest $request, Client $client, UpdateClient $updateClient): RedirectResponse
    {
        $updateClient->handle($client, $request->validated());

        return back();
    }

    /**
     * Lo que pasó, en palabras que entiende la vendedora.
     */
    protected function textoDeHistorial(AuditLog $log): string
    {
        $numero = $log->new_values['numero_interno'] ?? $log->new_values['numero'] ?? null;
        $texto = [
            'venta.creada' => 'Se registró una venta',
            'venta.borrador_editado' => 'Se editó el borrador de una venta',
            'venta.anulada' => 'Se anuló una venta',
            'venta.nota_venta_confirmada' => 'Se emitió una nota de venta',
            'venta.extintor_cambiado' => 'Se cambió un extintor de la venta',
            'cotizacion.creada' => 'Se creó una cotización',
            'cliente.creado' => 'Se registró el cliente',
            'cliente.actualizado' => 'Se actualizaron los datos del cliente',
        ][$log->action] ?? ucfirst(str_replace(['.', '_'], ' ', $log->action));

        return $numero ? "{$texto} ({$numero})" : $texto;
    }

    protected function activeAndLocatedPercentage(): float
    {
        $total = Client::where('tipo_documento', 'ruc')->count();

        if ($total === 0) {
            return 0.0;
        }

        $activeAndLocated = Client::where('tipo_documento', 'ruc')
            ->where('estado_contribuyente', 'ACTIVO')
            ->where('condicion_domicilio', 'HABIDO')
            ->count();

        return round(($activeAndLocated / $total) * 100, 2);
    }
}
