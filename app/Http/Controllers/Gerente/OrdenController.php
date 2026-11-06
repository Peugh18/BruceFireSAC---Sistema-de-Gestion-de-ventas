<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Models\ServiceOrder;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Órdenes para el gerente: ve todas, con su entrada y salida y el precio que
 * dejó campo (rebajado o sin precio de lista, para revisarlo; no se aprueba).
 */
class OrdenController extends Controller
{
    private const CERRADAS = ['entregado', 'cerrado', 'anulada'];

    /** Precios de campo que el gerente quiere ver: rebajados o sin precio de lista. */
    private const PRECIO_DISTINTO = ['rebajado', 'sin_lista'];

    public function index(Request $request, Team $current_team): Response
    {
        $filtro = $request->string('filtro', 'todas')->toString();
        $buscar = trim((string) $request->input('buscar', ''));

        $ordenes = ServiceOrder::query()
            ->with('client:id,razon_social,nombre_comercial,numero_documento')
            ->withCount([
                'equipments',
                'evidencias as fotos_entrada' => fn (Builder $q) => $q->where('etapa', 'recojo')->where('tipo', 'foto'),
                'evidencias as fotos_salida' => fn (Builder $q) => $q->where('etapa', 'entrega')->where('tipo', 'foto'),
                'lines as precios_distintos' => fn (Builder $q) => $q->whereIn('precio_estado', self::PRECIO_DISTINTO),
            ])
            ->when($buscar !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('codigo', 'like', "%{$buscar}%")
                ->orWhereHas('client', fn (Builder $c) => $c->where('razon_social', 'like', "%{$buscar}%")
                    ->orWhere('nombre_comercial', 'like', "%{$buscar}%")
                    ->orWhere('numero_documento', 'like', "%{$buscar}%"))))
            ->tap(fn (Builder $q) => $this->filtrar($q, $filtro))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ServiceOrder $orden) => [
                'id' => $orden->id,
                'codigo' => $orden->codigo,
                'cliente' => $orden->client->nombre_comercial ?: $orden->client->razon_social,
                'lugar' => preg_replace('/^\s*SEDE\s*:\s*/iu', '', (string) $orden->referencia) ?: null,
                'extintores' => $orden->equipments_count ?: (int) $orden->lines()->where('adicional', false)->sum('cantidad'),
                'fotos_entrada' => (int) $orden->getAttribute('fotos_entrada'),
                'fotos_salida' => (int) $orden->getAttribute('fotos_salida'),
                'estado' => $orden->estado,
                'precio_distinto' => (int) $orden->getAttribute('precios_distintos') > 0,
            ]);

        return Inertia::render('gerente/ordenes/index', [
            'ordenes' => $ordenes,
            'filtros' => ['filtro' => $filtro, 'buscar' => $buscar],
            'conPrecioDistinto' => ServiceOrder::query()->whereHas('lines', fn (Builder $q) => $q->whereIn('precio_estado', self::PRECIO_DISTINTO))->count(),
        ]);
    }

    public function show(Team $current_team, ServiceOrder $service_order): Response
    {
        $service_order->load('client', 'tecnico:id,name', 'sale:id,condicion_pago');

        return Inertia::render('gerente/ordenes/show', [
            'orden' => [
                'id' => $service_order->id,
                'codigo' => $service_order->codigo,
                'cliente' => $service_order->client->nombre_comercial ?: $service_order->client->razon_social,
                'documento' => $service_order->client->numero_documento,
                'estado' => $service_order->estado,
                'tecnico' => $service_order->tecnico?->name,
                'cobrada' => $service_order->sale_id !== null,
                'servicios' => $service_order->resumenServicios(),
            ],
            'entradaSalida' => $service_order->entradaYSalida(),
            'preciosCampo' => $service_order->preciosDeCampo(),
        ]);
    }

    /** @param  Builder<ServiceOrder>  $consulta */
    private function filtrar(Builder $consulta, string $filtro): void
    {
        match ($filtro) {
            'precio_distinto' => $consulta->whereHas('lines', fn (Builder $q) => $q->whereIn('precio_estado', self::PRECIO_DISTINTO)),
            'recojos' => $consulta->where('estado', 'pendiente_recepcion'),
            'planta' => $consulta->whereNotIn('estado', ['pendiente_recepcion', ...ServiceOrder::ESTADOS_LISTOS, ...self::CERRADAS]),
            'entregas' => $consulta->whereIn('estado', ServiceOrder::ESTADOS_LISTOS),
            'cerradas' => $consulta->whereIn('estado', self::CERRADAS),
            default => null,
        };
    }
}
