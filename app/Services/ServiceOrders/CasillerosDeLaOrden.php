<?php

namespace App\Services\ServiceOrders;

use App\Enums\EquipmentType;
use App\Models\Equipment;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderLine;

/**
 * Lo que ve la pantalla de una orden (vendedor y técnico de planta, la misma
 * lista): cada línea de servicio con sus casilleros y los extintores ya
 * identificados en el orden en que saldrán en el certificado.
 */
class CasillerosDeLaOrden
{
    /** Estados en que todavía se agregan, quitan u ordenan extintores. */
    public const ESTADOS_EDITABLES = ['pendiente_recepcion', 'recibido_planta', 'en_proceso'];

    /**
     * @return array{lineas: list<array<string, mixed>>, equipos: list<array<string, mixed>>, esperados: int, identificados: int, editable: bool, servicios: list<array{id: int, nombre: string}>}
     */
    public static function paraPagina(ServiceOrder $orden): array
    {
        $orden->loadMissing('lines.service', 'equipments', 'service');

        $equipos = $orden->equipments
            ->sortBy(fn (Equipment $equipo) => [$equipo->getRelation('pivot')->getAttribute('posicion') ?? PHP_INT_MAX, $equipo->id])
            ->values()
            ->map(fn (Equipment $equipo) => [
                'id' => $equipo->id,
                'numero_serie' => $equipo->numero_serie,
                'tipo_agente' => $equipo->tipo_agente,
                'capacidad' => $equipo->capacidad,
                'marca' => $equipo->marca,
                'serie_fabricante' => $equipo->serie_fabricante,
                'anio_fabricacion' => $equipo->anio_fabricacion,
                'linea_id' => $equipo->getRelation('pivot')->getAttribute('service_order_line_id'),
                'recibido' => (bool) $equipo->getRelation('pivot')->getAttribute('recibido'),
            ]);

        $lineas = $orden->lines->map(function (ServiceOrderLine $linea) use ($equipos) {
            $servicio = $linea->service;

            return [
                'id' => $linea->id,
                'servicio' => $servicio->nombre,
                'agente' => $servicio ? self::agente($servicio) : null,
                'capacidad' => $servicio ? Service::capacidadDelTexto($servicio->capacidad ?: $servicio->nombre) : null,
                'cantidad' => $linea->cantidad,
                'adicional' => $linea->adicional,
                'asignados' => $equipos->where('linea_id', $linea->id)->count(),
            ];
        });

        return [
            'lineas' => array_values($lineas->all()),
            'equipos' => array_values($equipos->all()),
            'esperados' => (int) $lineas->sum('cantidad'),
            'identificados' => $equipos->whereNotNull('linea_id')->count(),
            'editable' => in_array($orden->estado, self::ESTADOS_EDITABLES, true),
            'servicios' => array_values(Service::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
                ->map(fn (Service $s) => ['id' => $s->id, 'nombre' => $s->nombre])->all()),
        ];
    }

    private static function agente(Service $servicio): ?string
    {
        $tipo = EquipmentType::tryFrom((string) $servicio->agente) ?? EquipmentType::fromDescription($servicio->nombre);

        return $tipo === EquipmentType::Otro ? null : $tipo->etiqueta();
    }
}
