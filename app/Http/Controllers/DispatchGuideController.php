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
use Inertia\Inertia;
use Inertia\Response;
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
            ->with('sede:id,nombre')
            ->latest('id')
            ->paginate(20)
            ->through(fn (DispatchGuide $guia) => [
                'id' => $guia->id,
                'numero' => $guia->numero(),
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

    public function create(Team $current_team, Request $request, GuiaRemisionPrefill $prefill): Response
    {
        $user = $request->user();
        $origen = $request->string('origen')->toString();
        $id = $request->integer('id');

        $datos = match ($origen) {
            'venta' => $prefill->desdeVenta(Sale::query()->visiblePara($user)->whereKey($id)->firstOrFail()),
            'recojo', 'entrega' => $prefill->desdeOrden($this->orden($user, $id), $origen),
            'traslado' => $prefill->desdeTraslado($this->traslado($user, $id)),
            default => abort(404),
        };

        $peso = array_sum(array_column($datos['items'], 'peso_kg'));

        return Inertia::render('guias/form', [
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
            $sede ??= Sale::query()->visiblePara($user)->whereKey($datos['sale_id'])->firstOrFail()->sede_id;
        }
        if (! empty($datos['service_order_id'])) {
            $sede ??= $this->orden($user, (int) $datos['service_order_id'])->sede_id;
        }
        if (! empty($datos['inventory_transfer_id'])) {
            $sede ??= $this->traslado($user, (int) $datos['inventory_transfer_id'])->origen_sede_id;
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
        $almacen = $user->almacenRestringidoId();

        abort_if($almacen !== null && $traslado->origen_sede_id !== $almacen, 404);

        return $traslado;
    }
}
