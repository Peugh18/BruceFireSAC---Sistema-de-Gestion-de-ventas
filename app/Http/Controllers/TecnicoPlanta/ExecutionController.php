<?php

namespace App\Http\Controllers\TecnicoPlanta;

use App\Actions\TecnicoPlanta\ExecuteAndCloseServiceOrder;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Deficiency;
use App\Models\Product;
use App\Models\ServiceOrder;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ExecutionController extends Controller
{
    /**
     * Pantalla de ejecución técnica en taller y cierre de orden (§85, Fase 5).
     */
    public function show(Team $current_team, ServiceOrder $service_order): Response
    {
        $service_order->load([
            'client:id,nombre_comercial,razon_social,telefono,numero_documento',
            'sede:id,nombre',
            'equipments',
            'deficiencies.authorization',
            'events' => fn ($q) => $q->latest('created_at')->limit(10),
        ]);

        $certificates = Certificate::where('service_order_id', $service_order->id)
            ->with(['certificateType:id,codigo,nombre'])
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'numero' => $c->numero,
                'tipo' => $c->certificateType?->nombre,
                'fecha_emision' => $c->fecha_emision?->toDateString(),
                'fecha_vigencia_hasta' => $c->fecha_vigencia_hasta?->toDateString(),
                'estado' => $c->estado,
            ]);

        // Listado de repuestos físicos de Almacén para sugerencias/consumo
        $repuestosDisponibles = Product::where('activo', true)
            ->select(['id', 'codigo', 'nombre', 'precio_venta'])
            ->get();

        return Inertia::render('tecnico-planta/ejecucion/show', [
            'asignacion' => $service_order->asignacionPara(request()->user()),
            'order' => [
                'id' => $service_order->id,
                'codigo' => $service_order->codigo,
                'cliente' => $service_order->client->nombre_comercial ?: $service_order->client->razon_social,
                'cliente_doc' => $service_order->client->numero_documento,
                'telefono' => $service_order->client->telefono,
                'sede' => $service_order->sede?->nombre,
                'tipo_servicio' => $service_order->tipo_servicio,
                'fecha' => $service_order->fecha->toDateString(),
                'prioridad' => $service_order->prioridad,
                'estado' => $service_order->estado,
                'estado_coarse' => $service_order->coarseLabel(),
                'observaciones' => $service_order->observaciones,
                'equipments' => $service_order->equipments->map(fn ($eq) => [
                    'id' => $eq->id,
                    'numero_serie' => $eq->numero_serie,
                    'tipo_agente' => $eq->tipo_agente,
                    'capacidad' => $eq->capacidad,
                    'marca' => $eq->marca,
                ]),
                'deficiencies' => $service_order->deficiencies->map(fn ($d) => [
                    'id' => $d->id,
                    'componente' => $d->componente,
                    'condicion' => $d->condicion,
                    'estado' => $d->estado,
                    'requiere_autorizacion' => $d->requiere_autorizacion,
                    'repuesto_sugerido' => $d->repuesto_sugerido,
                    'resolucion' => $d->resolucion,
                    'authorization' => $d->authorization ? [
                        'autorizado_por' => $d->authorization->autorizado_por,
                        'canal' => $d->authorization->canal,
                        'fecha' => $d->authorization->fecha->toDateString(),
                    ] : null,
                ]),
                'events' => $service_order->events->map(fn ($ev) => [
                    'id' => $ev->id,
                    'tipo' => $ev->tipo,
                    'payload' => $ev->payload,
                    'created_at' => $ev->created_at?->toIso8601String(),
                ]),
            ],
            'repuestos' => $repuestosDisponibles,
            'certificates' => $certificates,
        ]);
    }

    /**
     * Consume un repuesto contra el inventario real de Almacén (§85, Fase 5).
     */
    public function consumeSpare(
        Request $request,
        Team $current_team,
        ServiceOrder $service_order,
        Deficiency $deficiency,
        ExecuteAndCloseServiceOrder $action
    ): RedirectResponse {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        $product = Product::findOrFail($validated['product_id']);

        $action->consumeSparePart(
            $service_order,
            $deficiency,
            $product,
            (int) $validated['cantidad'],
            $request->user(),
            $validated['observacion'] ?? null
        );

        return back()->with('success', sprintf('Repuesto %s consumido de Almacén correctamente.', $product->nombre));
    }

    /**
     * Avanza el estado de la orden en Planta hacia el cierre técnico y emisión de certificados.
     */
    public function advance(
        Request $request,
        Team $current_team,
        ServiceOrder $service_order,
        ExecuteAndCloseServiceOrder $action
    ): RedirectResponse {
        $validated = $request->validate([
            'target_state' => ['required', 'in:en_proceso,trabajo_terminado,pendiente_datos,datos_completos,listo_certificado,listo_entrega'],
            'ph_realizada' => ['nullable', 'boolean'],
        ]);

        try {
            $action->advanceState(
                $service_order,
                $validated['target_state'],
                $request->user(),
                [
                    'ph_realizada' => (bool) ($validated['ph_realizada'] ?? false),
                ]
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['target_state' => $exception->getMessage()]);
        }

        $msg = $validated['target_state'] === 'listo_certificado'
            ? '¡Trabajo de taller finalizado y Certificados emitidos automáticamente!'
            : sprintf('Estado actualizado a "%s".', $validated['target_state']);

        return back()->with('success', $msg);
    }
}
