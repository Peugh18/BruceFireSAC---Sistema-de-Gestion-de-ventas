<?php

namespace App\Http\Controllers\TecnicoPlanta;

use App\Http\Controllers\Controller;
use App\Models\Deficiency;
use App\Models\Product;
use App\Models\QuoteItem;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\TechnicalChecklist;
use App\Services\ServiceOrders\CasillerosDeLaOrden;
use App\Services\Tecnico\ConversacionDeLaOrden;
use App\Services\Tecnico\PasoDelTaller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Una sola pantalla para la orden en el taller: recibir, revisar extintor por
 * extintor (el checklist va aquí mismo), esperar la autorización, reparar y
 * certificar. Arriba se ve siempre en qué paso va.
 */
class OrdenTallerController extends Controller
{
    public function show(Request $request, Team $current_team, ServiceOrder $service_order, ConversacionDeLaOrden $conversacion): Response
    {
        $service_order->load([
            'client:id,nombre_comercial,razon_social,telefono,numero_documento,direccion_fiscal',
            'sede',
            'deficiencies' => fn ($q) => $q->oldest('id'),
            'deficiencies.authorization',
            'deficiencies.equipment:id,numero_serie',
            'checklists' => fn ($q) => $q->latest('id'),
        ]);

        $ultimoChecklist = $service_order->checklists->unique('equipment_id')->keyBy('equipment_id');
        $casilleros = CasillerosDeLaOrden::paraPagina($service_order);
        $estadoEquipo = $service_order->equipments->pluck('estado', 'id');
        // Lo que el cliente aceptó en la cotización: el repuesto ya viene elegido.
        $cotizado = QuoteItem::query()
            ->whereIn('deficiency_id', $service_order->deficiencies->pluck('id'))
            ->whereNotNull('product_id')
            ->get(['deficiency_id', 'product_id', 'cantidad'])
            ->keyBy('deficiency_id');

        return Inertia::render('tecnico-planta/orden/show', [
            'asignacion' => $service_order->asignacionPara($request->user()),
            'paso' => PasoDelTaller::de($service_order),
            'order' => [
                'id' => $service_order->id,
                'codigo' => $service_order->codigo,
                'cliente' => $service_order->client->nombre_comercial ?: $service_order->client->razon_social,
                'cliente_doc' => $service_order->client->numero_documento,
                'referencia' => $service_order->referencia,
                'direccion' => $service_order->client->direccion_fiscal,
                'telefono' => $service_order->client->telefono,
                'tipo_servicio' => $service_order->resumenServicios(),
                'estado' => $service_order->estado,
                'prioridad' => $service_order->prioridad,
                'observaciones' => $service_order->observaciones,
            ],
            'casilleros' => $casilleros,
            'evidenciasRecojo' => $service_order->evidencias()->where('etapa', 'recojo')->where('tipo', 'foto')->get(['id', 'etapa', 'tipo', 'equipment_id']),
            // Los extintores en el orden del certificado, con su checklist de esta orden.
            'revision' => collect($casilleros['equipos'])->map(function (array $equipo) use ($ultimoChecklist, $estadoEquipo) {
                /** @var TechnicalChecklist|null $checklist */
                $checklist = $ultimoChecklist->get($equipo['id']);

                return [
                    ...$equipo,
                    'es_co2' => str_contains(mb_strtoupper((string) $equipo['tipo_agente']), 'CO2'),
                    'no_apto' => $estadoEquipo->get($equipo['id']) === 'baja',
                    'checklist' => $checklist ? $checklist->resultado_general : null,
                ];
            })->values(),
            'deficiencias' => $service_order->deficiencies->map(fn (Deficiency $d) => [
                'id' => $d->id,
                'componente' => $d->componente,
                'condicion' => $d->condicion,
                'estado' => $d->estado,
                'serie' => $d->equipment?->numero_serie,
                'repuesto_sugerido' => $d->repuesto_sugerido,
                'resolucion' => $d->resolucion,
                'autorizado_por' => $d->authorization?->autorizado_por,
                'canal' => $d->authorization?->canal,
                'cotizado' => $cotizado->has($d->id) ? [
                    'product_id' => (int) $cotizado[$d->id]->product_id,
                    'cantidad' => (int) $cotizado[$d->id]->cantidad,
                ] : null,
                'creada' => $d->created_at?->toIso8601String(),
            ])->values(),
            // Con el stock del almacén que abastece a la sede de la orden.
            'repuestos' => Product::query()->select(['id', 'codigo', 'nombre', 'serializado'])
                ->where('activo', true)->where('serializado', false)
                ->conStock(($almacen = $service_order->sede?->almacenEfectivoId()) ? [$almacen] : null)
                ->orderBy('nombre')->get()
                ->map(fn (Product $p) => ['id' => $p->id, 'codigo' => $p->codigo, 'nombre' => $p->nombre, 'stock' => $p->stockDisponible()]),
            'certificados' => $service_order->certificadosParaEntrega()->map(fn ($c) => [
                'id' => $c->id,
                'numero' => $c->numero,
                'tipo' => $c->certificateType->nombre,
            ])->values(),
            'conversacion' => $conversacion->paraPagina($service_order),
        ]);
    }
}
