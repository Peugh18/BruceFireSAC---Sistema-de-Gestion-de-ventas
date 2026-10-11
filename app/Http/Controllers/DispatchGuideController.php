<?php

namespace App\Http\Controllers;

use App\Actions\Billing\CreateDispatchGuide;
use App\Http\Requests\Billing\StoreDispatchGuideRequest;
use App\Models\DispatchGuide;
use App\Models\Driver;
use App\Models\InventoryTransfer;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\TransportVehicle;
use App\Models\User;
use App\Services\Billing\GuiaRemisionPrefill;
use App\Services\Billing\GuiaRemisionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Guías de remisión electrónicas (SUNAT.md §5). Nacen desde una venta, una
 * orden de recojo o entrega, o un traslado entre sedes, con los datos ya
 * copiados; cada rol ve solo las guías de su sede.
 */
class DispatchGuideController extends Controller
{
    public function index(Team $current_team, Request $request): Response
    {
        $guias = $this->visibles($request->user())
            ->with(['sede:id,nombre', 'sale:id,numero_interno', 'serviceOrder:id,codigo'])
            ->latest('id')
            ->paginate(20)
            ->through(fn (DispatchGuide $guia) => [
                'id' => $guia->id,
                'detalle_url' => route('guias.show', ['current_team' => $current_team, 'guide' => $guia]),
                'puede_enviar' => $request->user()->can('guias_remision.create') && $guia->estado_sunat === DispatchGuide::BORRADOR,
                'numero' => $guia->numero(),
                'origen' => match (true) {
                    $guia->sale !== null => 'Venta '.$guia->sale->numero_interno,
                    $guia->serviceOrder !== null => 'Orden '.$guia->serviceOrder->codigo,
                    $guia->inventory_transfer_id !== null => 'Traslado #'.$guia->inventory_transfer_id,
                    default => 'Sin operación vinculada',
                },
                'comprobante_relacionado' => $guia->doc_relacionado_numero,
                'fecha_traslado' => $guia->fecha_traslado->format('d/m/Y'),
                'motivo' => DispatchGuide::MOTIVOS[$guia->motivo] ?? $guia->motivo,
                'destinatario' => $guia->destinatario_nombre,
                'sede' => $guia->sede->nombre,
                'estado_sunat' => $guia->estado_sunat,
                'lista_para_trasladar' => $guia->estaListaParaTrasladar(),
                'sunat_mensaje' => $guia->sunat_mensaje,
            ]);

        return Inertia::render('guias/index', ['guias' => $guias]);
    }

    public function show(Team $current_team, Request $request, DispatchGuide $guide): Response
    {
        $this->asegurarVisible($request->user(), $guide);
        $guide->load('sede', 'items', 'vehiculo', 'conductor', 'sale', 'serviceOrder', 'traslado');
        $user = $request->user();
        $origen = ['descripcion' => 'Sin operación vinculada', 'url' => null, 'crear_url' => null];
        if ($guide->sale !== null) {
            $origen['descripcion'] = 'Venta '.$guide->sale->numero_interno;
            if ($user->hasRole('Vendedor') && $user->can('sales.view') && Sale::query()->visiblePara($user)->whereKey($guide->sale_id)->exists()) {
                $origen['url'] = route('vendedor.ventas.show', ['current_team' => $current_team, 'sale' => $guide->sale]);
            }
            $tipo = 'venta';
            $id = $guide->sale_id;
        } elseif ($guide->serviceOrder !== null) {
            $origen['descripcion'] = 'Orden '.$guide->serviceOrder->codigo;
            $tipo = str_contains(mb_strtolower($guide->motivo_descripcion ?? ''), 'recojo') ? 'recojo' : 'entrega';
            $id = $guide->service_order_id;
            if ($user->hasRole('Vendedor') && $user->can('service_orders.view') && ServiceOrder::query()->visiblePara($user)->whereKey($id)->exists()) {
                $origen['url'] = route('vendedor.ordenes-servicio.show', ['current_team' => $current_team, 'service_order' => $guide->serviceOrder]);
            } elseif ($user->hasRole('TecnicoCampo') && $guide->serviceOrder->atendiblePor($user)) {
                $origen['url'] = route('tecnico-campo.'.($tipo === 'recojo' ? 'recojos' : 'entregas').'.show', ['current_team' => $current_team, 'service_order' => $guide->serviceOrder]);
            }
        } elseif ($guide->traslado !== null) {
            $origen['descripcion'] = 'Traslado #'.$guide->inventory_transfer_id;
            $tipo = 'traslado';
            $id = $guide->inventory_transfer_id;
            if ($user->hasRole('Almacen')) {
                $origen['url'] = route('almacen.traslados.index', ['current_team' => $current_team]);
            }
        }
        if (isset($tipo, $id) && $user->can('guias_remision.create')) {
            $origen['crear_url'] = route('guias.create', ['current_team' => $current_team, 'origen' => $tipo, 'id' => $id]);
        }
        $documentos = [];
        foreach (['xml' => $guide->xml_path, 'cdr' => $guide->cdr_path] as $tipoDocumento => $path) {
            if ($path !== null && Storage::disk('local')->exists($path)) {
                $documentos[$tipoDocumento] = route('guias.documento', ['current_team' => $current_team, 'guide' => $guide, 'tipo' => $tipoDocumento]);
            }
        }

        return Inertia::render('guias/show', [
            'guia' => [
                ...$guide->only(['id', 'estado_sunat', 'motivo_descripcion', 'modalidad', 'destinatario_tipo_doc', 'destinatario_num_doc', 'destinatario_nombre', 'partida_ubigeo', 'partida_direccion', 'llegada_ubigeo', 'llegada_direccion', 'peso_bruto', 'transport_vehicle_id', 'driver_id', 'transportista_ruc', 'transportista_razon', 'doc_relacionado_tipo', 'doc_relacionado_numero', 'sunat_ticket', 'sunat_codigo', 'sunat_mensaje']),
                'numero' => $guide->numero(), 'sede' => $guide->sede->nombre,
                'fecha_emision' => $guide->fecha_emision->format('d/m/Y H:i'), 'fecha_traslado' => $guide->fecha_traslado->toDateString(),
                'motivo' => DispatchGuide::MOTIVOS[$guide->motivo] ?? $guide->motivo,
                'items' => $guide->items, 'vehiculo' => $guide->vehiculo?->placa,
                'conductor' => $guide->conductor === null ? null : $guide->conductor->nombres.' '.$guide->conductor->apellidos,
                'origen' => $origen, 'documentos' => $documentos,
                'lista_para_trasladar' => $guide->estaListaParaTrasladar(),
                'puede_editar' => $user->can('guias_remision.create') && $this->esBorradorEditable($guide),
                'puede_enviar' => $user->can('guias_remision.create') && in_array($guide->estado_sunat, [DispatchGuide::BORRADOR, 'rechazada'], true),
            ],
            'vehiculos' => TransportVehicle::query()->where('activo', true)->get(['id', 'placa']),
            'conductores' => Driver::query()->where('activo', true)->get(['id', 'nombres', 'apellidos']),
        ]);
    }

    public function documento(Team $current_team, Request $request, DispatchGuide $guide, string $tipo): StreamedResponse
    {
        $this->asegurarVisible($request->user(), $guide);
        $path = match ($tipo) {
            'xml' => $guide->xml_path, 'cdr' => $guide->cdr_path, default => null
        };
        abort_unless($path !== null && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $guide->numero().($tipo === 'xml' ? '.xml' : '-CDR.zip'));
    }

    public function update(Team $current_team, Request $request, DispatchGuide $guide): RedirectResponse
    {
        $this->asegurarVisible($request->user(), $guide);
        abort_unless($this->esBorradorEditable($guide), 403);
        $rules = (new StoreDispatchGuideRequest)->rules();
        $campos = ['fecha_traslado', 'modalidad', 'peso_bruto', 'transport_vehicle_id', 'driver_id', 'transportista_ruc', 'transportista_razon'];
        $datos = $request->validate(array_intersect_key($rules, array_flip($campos)));
        DB::transaction(function () use ($guide, $datos): void {
            $bloqueada = DispatchGuide::query()->whereKey($guide->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->esBorradorEditable($bloqueada), 403);
            $bloqueada->update($datos + ($datos['modalidad'] === '01' ? ['transport_vehicle_id' => null, 'driver_id' => null] : ['transportista_ruc' => null, 'transportista_razon' => null]));
        });

        return back()->with('success', 'Transporte del borrador actualizado. El número, origen y bienes se conservaron.');
    }

    protected function esBorradorEditable(DispatchGuide $guide): bool
    {
        return $guide->estado_sunat === DispatchGuide::BORRADOR && $guide->sunat_ticket === null
            && $guide->enviado_at === null && $guide->xml_path === null && $guide->cdr_path === null;
    }

    public function create(Team $current_team, Request $request, GuiaRemisionPrefill $prefill): Response
    {
        $user = $request->user();
        $origen = $request->string('origen')->toString();
        $id = $request->integer('id');

        $fuente = match ($origen) {
            'venta' => Sale::query()->visiblePara($user)->whereKey($id)->firstOrFail(),
            'recojo', 'entrega' => $this->orden($user, $id),
            'traslado' => $this->traslado($user, $id),
            default => abort(404),
        };
        $datos = match (true) {
            $fuente instanceof Sale => $prefill->desdeVenta($fuente),
            $fuente instanceof ServiceOrder => $prefill->desdeOrden($fuente, $origen),
            default => $prefill->desdeTraslado($fuente),
        };
        $descripcion = match (true) {
            $fuente instanceof Sale => 'Venta '.$fuente->numero_interno,
            $fuente instanceof ServiceOrder => ucfirst($origen).' de '.$fuente->codigo,
            default => 'Traslado #'.$fuente->id,
        };
        $regreso = match (true) {
            $fuente instanceof Sale && $user->hasRole('Vendedor') && $user->can('sales.view') => route('vendedor.ventas.show', ['current_team' => $current_team, 'sale' => $fuente]),
            $fuente instanceof ServiceOrder && $user->hasRole('Vendedor') && $user->can('service_orders.view') => route('vendedor.ordenes-servicio.show', ['current_team' => $current_team, 'service_order' => $fuente]),
            $fuente instanceof ServiceOrder && $user->hasRole('TecnicoCampo') && $fuente->atendiblePor($user) => route('tecnico-campo.'.($origen === 'recojo' ? 'recojos' : 'entregas').'.show', ['current_team' => $current_team, 'service_order' => $fuente]),
            $fuente instanceof InventoryTransfer && $user->hasRole('Almacen') => route('almacen.traslados.index', ['current_team' => $current_team]),
            default => route('guias.index', ['current_team' => $current_team]),
        };

        $peso = array_sum(array_column($datos['items'], 'peso_kg'));

        return Inertia::render('guias/form', [
            'origen' => ['descripcion' => $descripcion, 'url' => $regreso],
            'datos' => [
                ...$datos,
                'modalidad' => '02',
                'fecha_traslado' => now()->toDateString(),
                'peso_bruto' => $peso > 0 ? round($peso, 3) : '',
                'transport_vehicle_id' => '',
                'driver_id' => '',
                'transportista_ruc' => '',
                'transportista_razon' => '',
            ],
            'vehiculos' => TransportVehicle::query()->where('activo', true)->orderBy('placa')->get(['id', 'placa', 'categoria']),
            'conductores' => Driver::query()->where('activo', true)->orderBy('apellidos')->get(['id', 'nombres', 'apellidos', 'dni']),
            'motivos' => DispatchGuide::MOTIVOS,
        ]);
    }

    public function store(Team $current_team, StoreDispatchGuideRequest $request, CreateDispatchGuide $crear): RedirectResponse
    {
        $user = $request->user();
        $datos = $request->validated();

        // Lo que enlaza la guía se verifica contra lo que el usuario puede ver.
        $sede = $user->sedeRestringidaId();
        if (! empty($datos['sale_id'])) {
            // Siempre se valida el acceso; ??= no debe saltarse la consulta.
            $venta = Sale::query()->visiblePara($user)->whereKey($datos['sale_id'])->firstOrFail();
            $sede ??= $venta->sede_id;
        }
        if (! empty($datos['service_order_id'])) {
            $orden = $this->orden($user, (int) $datos['service_order_id']);
            $sede ??= $orden->sede_id;
        }
        if (! empty($datos['inventory_transfer_id'])) {
            $traslado = $this->traslado($user, (int) $datos['inventory_transfer_id']);
            $sede ??= $traslado->origen_sede_id;
        }
        $datos['sede_id'] = $sede ?? Sede::query()->orderBy('id')->value('id');

        $guia = $crear->handle($datos, $user);

        return redirect()->route('guias.index', ['current_team' => $current_team])
            ->with('success', "Guía {$guia->numero()} creada. Envíala a SUNAT: solo con el CDR aceptado puede salir la mercadería.");
    }

    /**
     * Envía a SUNAT (token, ZIP con hash, ticket) y consulta una vez.
     */
    public function enviar(Team $current_team, Request $request, DispatchGuide $guide, GuiaRemisionService $servicio): RedirectResponse
    {
        $this->asegurarVisible($request->user(), $guide);

        try {
            $servicio->consultar($servicio->enviar($guide));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo enviar la guía a SUNAT: '.$e->getMessage());
        }

        return back()->with('success', 'Guía enviada a SUNAT. Consulta el ticket hasta ver el CDR aceptado.');
    }

    public function consultar(Team $current_team, Request $request, DispatchGuide $guide, GuiaRemisionService $servicio): RedirectResponse
    {
        $this->asegurarVisible($request->user(), $guide);

        try {
            $guia = $servicio->consultar($guide);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo consultar a SUNAT: '.$e->getMessage());
        }

        return match ($guia->estado_sunat) {
            DispatchGuide::ACEPTADA => back()->with('success', 'CDR aceptado: la guía está lista para trasladar.'),
            DispatchGuide::RECHAZADA => back()->with('error', 'SUNAT rechazó la guía: '.$guia->sunat_mensaje),
            default => back()->with('success', 'SUNAT aún está procesando la guía. Vuelve a consultar en un momento.'),
        };
    }

    /**
     * @return Builder<DispatchGuide>
     */
    protected function visibles(User $user): Builder
    {
        return DispatchGuide::query()->when($user->sedeRestringidaId(), fn (Builder $query, int $sedeId) => $query->where('sede_id', $sedeId));
    }

    protected function asegurarVisible(User $user, DispatchGuide $guia): void
    {
        abort_unless($this->visibles($user)->whereKey($guia->id)->exists(), 404);
    }

    protected function orden(User $user, int $id): ServiceOrder
    {
        $orden = ServiceOrder::query()->findOrFail($id);

        abort_unless($orden->atendiblePor($user) || ServiceOrder::query()->visiblePara($user)->whereKey($id)->exists(), 404);

        return $orden;
    }

    protected function traslado(User $user, int $id): InventoryTransfer
    {
        $traslado = InventoryTransfer::query()->findOrFail($id);
        abort_if($user->almacenRestringidoId() !== null && $user->almacenRestringidoId() !== $traslado->origen_sede_id, 404);
        // El almacén origen emite la guía; el Gerente puede operar entre sedes.
        $propias = array_filter([$user->sedeRestringidaId(), $user->almacenRestringidoId()]);

        abort_if($propias !== [] && array_intersect($propias, [$traslado->origen_sede_id, $traslado->destino_sede_id]) === [], 404);

        return $traslado;
    }
}
