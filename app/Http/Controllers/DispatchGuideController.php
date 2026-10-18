<?php

namespace App\Http\Controllers;

use App\Actions\Billing\CreateDispatchGuide;
use App\Http\Requests\Billing\StoreDispatchGuideRequest;
use App\Models\DispatchGuide;
use App\Models\Driver;
use App\Models\Sale;
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
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Guías de clientes vinculadas a una venta con boleta o factura.
 * El vendedor completa los datos del traslado y gestiona el envío.
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
        if (isset($tipo, $id) && $tipo === 'venta' && $user->can('guias_remision.create')) {
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
        $rules['transport_vehicle_id'] = ['nullable', 'required_if:modalidad,02', Rule::exists('transport_vehicles', 'id')->where('activo', true)];
        $rules['driver_id'] = ['nullable', 'required_if:modalidad,02', Rule::exists('drivers', 'id')->where('activo', true)];
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

        abort_unless($origen === 'venta', 404);
        $fuente = Sale::query()->visiblePara($user)->whereKey($id)->firstOrFail();
        $datos = $prefill->desdeVentaParaCliente($fuente);
        $descripcion = 'Venta '.$fuente->numero_interno;
        $regreso = route('vendedor.ventas.show', ['current_team' => $current_team, 'sale' => $fuente]);

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
            'conductores' => Driver::query()->where('activo', true)->orderBy('apellidos')->get(['id', 'nombres', 'apellidos', 'dni', 'licencia']),
            'motivos' => ['01' => 'Venta'],
        ]);
    }

    public function store(Team $current_team, StoreDispatchGuideRequest $request, CreateDispatchGuide $crear): RedirectResponse
    {
        $user = $request->user();
        $datos = $request->validated();

        $venta = Sale::query()->visiblePara($user)->whereKey($datos['sale_id'])->firstOrFail();
        $origen = app(GuiaRemisionPrefill::class)->desdeVentaParaCliente($venta);
        // El cliente, comprobante y bienes se obtienen de la venta, nunca del navegador.
        foreach (['sale_id', 'sede_id', 'motivo', 'motivo_descripcion', 'doc_relacionado_tipo', 'doc_relacionado_numero', 'destinatario_tipo_doc', 'destinatario_num_doc', 'destinatario_nombre', 'items'] as $campo) {
            $datos[$campo] = $origen[$campo];
        }
        $guia = DB::transaction(function () use ($datos, $crear, $user): DispatchGuide {
            if (! empty($datos['nuevo_vehiculo_placa'])) {
                $datos['transport_vehicle_id'] = TransportVehicle::create([
                    'placa' => $datos['nuevo_vehiculo_placa'], 'categoria' => $datos['nuevo_vehiculo_categoria'],
                ])->id;
            }
            if (! empty($datos['nuevo_conductor_dni'])) {
                $datos['driver_id'] = Driver::create([
                    'dni' => $datos['nuevo_conductor_dni'], 'nombres' => $datos['nuevo_conductor_nombres'],
                    'apellidos' => $datos['nuevo_conductor_apellidos'], 'licencia' => $datos['nuevo_conductor_licencia'],
                ])->id;
            }
            $datos = array_filter($datos, fn (string $campo): bool => ! str_starts_with($campo, 'nuevo_'), ARRAY_FILTER_USE_KEY);

            return $crear->handle($datos, $user);
        });

        return redirect()->route('guias.index', ['current_team' => $current_team])
            ->with('success', "Guía {$guia->numero()} creada. Envíala a SUNAT: solo con el CDR aceptado puede salir la mercadería.");
    }

    /**
     * Envía a SUNAT (token, ZIP con hash, ticket) y consulta una vez.
     */
    public function enviar(Team $current_team, Request $request, DispatchGuide $guide, GuiaRemisionService $servicio): RedirectResponse
    {
        $this->asegurarVisible($request->user(), $guide);

        abort_unless($guide->sale_id !== null && $guide->inventory_transfer_id === null && $guide->service_order_id === null, 422, 'Solo se envían guías de ventas a clientes con boleta o factura.');
        $origen = app(GuiaRemisionPrefill::class)->desdeVentaParaCliente($guide->sale);
        abort_unless($guide->doc_relacionado_tipo === $origen['doc_relacionado_tipo'] && $guide->doc_relacionado_numero === $origen['doc_relacionado_numero'], 422, 'El comprobante relacionado cambió. Crea una guía con el comprobante vigente.');

        try {
            $servicio->consultar($servicio->enviar($guide));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo enviar la guía a SUNAT: '.$e->getMessage());
        }

        $guide->refresh();

        if ($guide->estado_sunat === DispatchGuide::RECHAZADA) {
            return back()->with('error', 'SUNAT rechazó la guía: '.($guide->sunat_mensaje ?: 'revisa los datos y vuelve a enviarla.'));
        }

        if ($guide->estado_sunat === DispatchGuide::ACEPTADA) {
            return back()->with('success', str_contains((string) $guide->sunat_mensaje, 'SIMULADO')
                ? 'Guía aceptada en modo de prueba: no se envió a SUNAT.'
                : 'Guía aceptada por SUNAT: ya puede salir la mercadería.');
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
}
