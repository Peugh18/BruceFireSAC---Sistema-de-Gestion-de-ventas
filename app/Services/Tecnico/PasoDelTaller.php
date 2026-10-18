<?php

namespace App\Services\Tecnico;

use App\Models\Deficiency;
use App\Models\Equipment;
use App\Models\ServiceOrder;

/**
 * En qué paso va la orden en el taller y qué le falta: Recibir, Revisar,
 * Hallazgos, Reparar y Cierre. Lo usan el Inicio y la pantalla de la orden.
 */
class PasoDelTaller
{
    /** @var list<string> */
    public const PASOS = ['recibir', 'revisar', 'hallazgos', 'reparar', 'cierre'];

    /** @var list<string> */
    private const ESTADOS_DE_CIERRE = ['trabajo_terminado', 'pendiente_datos', 'datos_completos', 'listo_certificado', 'listo_entrega', 'entregado', 'cerrado'];

    /**
     * @return array{numero: int, clave: string, detalle: string, completa: bool}
     */
    public static function de(ServiceOrder $orden): array
    {
        if ($orden->estado === 'pendiente_recepcion') {
            return self::paso(1, $orden->departamento_tecnico === 'campo' ? 'falta la visita del técnico' : 'aún no llega al taller');
        }

        if (in_array($orden->estado, self::ESTADOS_DE_CIERRE, true)) {
            return match ($orden->estado) {
                'listo_certificado' => self::paso(5, 'marca la orden lista para entrega'),
                'listo_entrega', 'entregado', 'cerrado' => self::paso(5, 'lista para entrega', true),
                default => self::paso(5, 'emite los certificados'),
            };
        }

        $orden->loadMissing(['equipments', 'checklists:id,service_order_id,equipment_id', 'deficiencies:id,service_order_id,estado']);

        $revisados = $orden->checklists->pluck('equipment_id')->unique();
        $recibidos = $orden->equipments->filter(fn (Equipment $equipo) => (bool) ($equipo->pivot->recibido ?? true));
        $faltanRevisar = $recibidos->reject(fn (Equipment $equipo) => $revisados->contains($equipo->id))->count();

        if ($recibidos->isEmpty()) {
            return self::paso(2, 'aún no hay extintores registrados');
        }

        if ($faltanRevisar > 0) {
            return self::paso(2, sprintf('faltan %d de %d extintores', $faltanRevisar, $recibidos->count()));
        }

        $enEspera = $orden->deficiencies->where('estado', 'esperando_autorizacion')->count();

        if ($enEspera > 0) {
            return self::paso(3, $enEspera === 1 ? '1 deficiencia sin respuesta' : "{$enEspera} deficiencias sin respuesta");
        }

        $porReparar = $orden->deficiencies->filter(fn (Deficiency $d) => in_array($d->estado, ['detectada', 'autorizada'], true))->count();

        if ($porReparar > 0) {
            return self::paso(4, $porReparar === 1 ? 'falta 1 reparación' : "faltan {$porReparar} reparaciones");
        }

        return self::paso(5, 'emite los certificados');
    }

    /**
     * @return array{numero: int, clave: string, detalle: string, completa: bool}
     */
    private static function paso(int $numero, string $detalle, bool $completa = false): array
    {
        return ['numero' => $numero, 'clave' => self::PASOS[$numero - 1], 'detalle' => $detalle, 'completa' => $completa];
    }
}
