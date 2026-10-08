<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Vendedor\Concerns\AcotaPorSede;
use App\Http\Requests\ServiceOrders\StoreServiceOrderRequest;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ServiceOrders\ServiceOrderNumberGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ServiceOrderController extends Controller
{
    use AcotaPorSede;

    public function index(Team $current_team, Request $request): Response
    {
        $estado = $request->string('estado')->toString();
        $estadoMap = [
            'en_camino' => ['pendiente_recepcion', 'recibido_planta', 'esperando_autorizacion'],
            'en_proceso' => ['autorizado', 'en_proceso', 'trabajo_terminado', 'pendiente_datos', 'datos_completos'],
            'completadas' => ['listo_certificado', 'listo_entrega', 'entregado', 'cerrado'],
            'anuladas' => ['anulada'],
        ];

        $sedeId = $request->user()->sedeRestringidaId();

        $orders = ServiceOrder::query()
            ->with(['client', 'service', 'tecnico'])
            ->visiblePara($request->user())
            ->when(isset($estadoMap[$estado]), fn ($query) => $query->whereIn('estado', $estadoMap[$estado]))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ServiceOrder $order) => [
                'id' => $order->id,
                'codigo' => $order->codigo,
                'cliente' => $order->client->razon_social,
                'tecnico' => $order->tecnico?->name,
                'tipo_servicio' => $order->service->nombre,
                'fecha' => $order->fecha->toDateString(),
                'estado' => $order->estado,
                'coarse_label' => $order->coarseLabel(),
                'departamento_tecnico' => $order->departamento_tecnico,
                'prioridad' => $order->prioridad,
            ]);

        return Inertia::render('vendedor/ordenes-servicio/index', [
            'orders' => $orders,
            'filters' => ['estado' => $estado],
            'services' => Service::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'precio_venta']),
            // Técnicos de la sede para asignar un responsable; toda el área
            // (planta o campo) ve la orden aunque no se elija a nadie.
            'tecnicos' => User::role(['TecnicoPlanta', 'TecnicoCampo'])
                ->when($sedeId, fn ($query) => $query->whereIn('sede_id', array_filter([$sedeId, Sede::find($sedeId)?->almacenEfectivoId()])))
                ->orderBy('name')
                ->get()
                ->map(fn (User $tecnico) => [
                    'id' => $tecnico->id,
                    'name' => $tecnico->name,
                    'departamento' => $tecnico->hasRole('TecnicoCampo') ? 'campo' : 'planta',
                ])
                ->values(),
        ]);
    }

    public function store(Team $current_team, StoreServiceOrderRequest $request, ServiceOrderNumberGenerator $numberGenerator): RedirectResponse
    {
        if ($request->filled('tecnico_id')) {
            // Solo un técnico del área que atiende la sede de la orden.
            $borrador = new ServiceOrder([
                'departamento_tecnico' => $request->input('departamento_tecnico'),
                'sede_id' => $request->user()->sedeRestringidaId() ?? $request->input('sede_id'),
            ]);

            if (! $this->tecnicosQueAtienden($borrador)->whereKey($request->integer('tecnico_id'))->exists()) {
                throw ValidationException::withMessages([
                    'tecnico_id' => 'El técnico elegido no pertenece al área de la orden.',
                ]);
            }
        }

        $order = ServiceOrder::create([
            ...$request->validated(),
            ...($request->user()->sedeRestringidaId() ? ['sede_id' => $request->user()->sedeRestringidaId()] : []),
            'codigo' => $numberGenerator->next(),
            'prioridad' => $request->input('prioridad', 'normal'),
            'estado' => 'pendiente_recepcion',
        ]);

        $order->events()->create([
            'tipo' => 'creada',
            'user_id' => $request->user()->id,
            'payload' => [
                'mensaje' => 'Orden de servicio creada por vendedor.',
            ],
        ]);

        return back();
    }

    public function show(Team $current_team, ServiceOrder $service_order, Request $request): Response
    {
        $this->asegurarVisible($service_order);

        $service_order->load([
            'client',
            'sede',
            'tecnico',
            'events' => fn ($query) => $query->with('user')->orderBy('created_at'),
            'equipments',
            'sale',
            'service',
        ]);

        return Inertia::render('vendedor/ordenes-servicio/show', [
            'serviceOrder' => $service_order,
            'tecnicos' => $this->tecnicosQueAtienden($service_order)->get(['id', 'name']),
            'services' => Service::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function assign(Team $current_team, ServiceOrder $service_order, Request $request): RedirectResponse
    {
        $this->asegurarVisible($service_order);

        $validated = $request->validate(['tecnico_id' => ['required', 'integer', 'exists:users,id']]);
        $technician = $this->tecnicosQueAtienden($service_order)->findOrFail($validated['tecnico_id']);
        $anterior = $service_order->tecnico?->name;
        $service_order->update(['tecnico_id' => $technician->id]);
        $mensaje = $anterior ? "Orden reasignada de {$anterior} a {$technician->name}." : "Orden asignada a {$technician->name}.";
        $service_order->events()->create(['tipo' => 'otro', 'user_id' => $request->user()->id, 'payload' => ['accion' => 'tecnico_asignado', 'tecnico_id' => $technician->id, 'mensaje' => $mensaje]]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $mensaje]);

        return back();
    }

    /**
     * Anula la orden cuando el cliente se arrepiente, antes de que el taller
     * emita el certificado. Si ya se cobró, primero se anula la venta. Los
     * extintores que estaban en el taller vuelven a quedar del cliente para
     * devolvérselos.
     */
    public function anular(Team $current_team, ServiceOrder $service_order, Request $request): RedirectResponse
    {
        $this->asegurarVisible($service_order);

        $datos = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']], [
            'motivo.required' => 'Escribe por qué se anula la orden.',
            'motivo.min' => 'Escribe por qué se anula la orden.',
        ]);

        DB::transaction(function () use ($service_order, $datos, $request): void {
            $orden = ServiceOrder::query()->lockForUpdate()->findOrFail($service_order->id);

            if (! in_array($orden->estado, ServiceOrder::ESTADOS_ANULABLES, true)) {
                throw ValidationException::withMessages(['motivo' => $orden->estado === 'anulada'
                    ? 'La orden ya está anulada.'
                    : 'El taller ya emitió el certificado: la orden no se anula.']);
            }

            if ($orden->sale_id !== null && $orden->sale()->where('estado', '!=', 'anulada')->exists()) {
                throw ValidationException::withMessages(['motivo' => 'La orden ya se cobró: primero anula su venta.']);
            }

            $anterior = $orden->estado;
            $orden->update(['estado' => 'anulada']);
            $orden->equipments()->where('equipment.estado', 'en_servicio')->update(['equipment.estado' => 'activo']);
            $orden->events()->create([
                'tipo' => 'otro',
                'user_id' => $request->user()->id,
                'payload' => ['accion' => 'orden_anulada', 'origen' => 'vendedor', 'estado_anterior' => $anterior, 'mensaje' => "Orden anulada: {$datos['motivo']}"],
            ]);

            AuditLogger::log(
                action: 'orden.anulada',
                entity: $orden,
                oldValues: ['estado' => $anterior],
                newValues: ['estado' => 'anulada', 'motivo' => $datos['motivo']],
                userId: $request->user()->id,
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Orden anulada.']);

        return back();
    }

    /**
     * Editar la orden mientras no se entregue: fecha, prioridad, área,
     * técnico e indicaciones. El área solo cambia si aún no la recibieron;
     * cada cambio queda en la bitácora para que el técnico lo vea.
     */
    public function update(Team $current_team, ServiceOrder $service_order, Request $request): RedirectResponse
    {
        $this->asegurarVisible($service_order);

        if (in_array($service_order->estado, ['entregado', 'cerrado', 'anulada'], true)) {
            throw ValidationException::withMessages(['estado' => $service_order->estado === 'anulada' ? 'La orden está anulada.' : 'La orden ya se entregó: ya no se puede editar.']);
        }

        $datos = $request->validate([
            'fecha' => ['required', 'date'],
            'prioridad' => ['required', 'in:normal,alta,urgente'],
            'departamento_tecnico' => ['required', 'in:planta,campo'],
            'tecnico_id' => ['nullable', 'integer'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
        ]);

        if ($datos['departamento_tecnico'] !== $service_order->departamento_tecnico && $service_order->estado !== 'pendiente_recepcion') {
            throw ValidationException::withMessages(['departamento_tecnico' => 'La orden ya está en manos del técnico: no se puede cambiar de área.']);
        }

        // Recibida en el taller, ya no cambia de servicio (recarga a inspección
        // con el certificado de por medio no tiene sentido).
        if ((int) $datos['service_id'] !== (int) $service_order->service_id && $service_order->estado !== 'pendiente_recepcion') {
            throw ValidationException::withMessages(['service_id' => 'La orden ya está en el taller: no se cambia el servicio. Anúlala y crea otra.']);
        }

        $antes = $service_order->only(['fecha', 'prioridad', 'departamento_tecnico', 'tecnico_id', 'observaciones', 'service_id']);
        $service_order->fill(collect($datos)->except('tecnico_id')->all());

        $tecnico = null;
        if (! empty($datos['tecnico_id'])) {
            $tecnico = $this->tecnicosQueAtienden($service_order)->find($datos['tecnico_id']);

            if (! $tecnico) {
                throw ValidationException::withMessages(['tecnico_id' => 'Ese técnico no atiende esta área o esta sede.']);
            }
        }

        $service_order->tecnico_id = $tecnico?->id;
        $service_order->save();

        $cambios = collect([
            'fecha' => 'fecha',
            'prioridad' => 'prioridad',
            'departamento_tecnico' => 'área',
            'tecnico_id' => 'técnico',
            'observaciones' => 'indicaciones',
        ])->filter(fn (string $nombre, string $campo) => (string) ($antes[$campo] ?? '') !== (string) ($service_order->{$campo} ?? ''))->values();

        if ($cambios->isNotEmpty()) {
            $service_order->events()->create([
                'tipo' => 'otro',
                'user_id' => $request->user()->id,
                'payload' => [
                    'accion' => 'orden_editada',
                    'origen' => 'vendedor',
                    'mensaje' => 'Ventas actualizó '.$cambios->implode(', ').'.'.($tecnico ? " Técnico: {$tecnico->name}." : ''),
                ],
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Orden {$service_order->codigo} actualizada."]);

        return back();
    }

    /**
     * Técnicos del área (Planta o Campo) que atienden la sede de la orden:
     * los de la misma sede y, si es una tienda, los de su almacén.
     */
    protected function tecnicosQueAtienden(ServiceOrder $serviceOrder): Builder
    {
        $serviceOrder->loadMissing('sede', 'tecnico');

        return User::role($serviceOrder->departamento_tecnico === 'campo' ? 'TecnicoCampo' : 'TecnicoPlanta')
            ->when($serviceOrder->sede, fn (Builder $query) => $query->whereIn('sede_id', $serviceOrder->sede->idsQueAtienden()))
            ->orderBy('name');
    }
}
