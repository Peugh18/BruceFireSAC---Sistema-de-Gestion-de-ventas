<?php

namespace App\Actions\Tecnico;

use App\Models\Deficiency;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\TechnicalChecklist;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProcessChecklist
{
    /**
     * Elementos estándar del checklist técnico (§19.1).
     */
    public const ELEMENTOS = [
        'identificacion' => 'Identificación / Etiqueta Bruce Fire',
        'cilindro' => 'Estado del Cilindro',
        'corrosion' => 'Corrosión y Óxido',
        'golpes_deformacion' => 'Golpes / Abolladuras / Deformación',
        'valvula' => 'Válvula de Descarga',
        'manometro' => 'Manómetro de Presión',
        'pasador' => 'Pasador de Seguridad',
        'precinto' => 'Precinto de Seguridad',
        'manguera' => 'Manguera de Descarga',
        'boquilla_difusor' => 'Boquilla / Difusor / Corneta',
        'manija_palanca' => 'Manija de Transporte / Palanca',
        'rotulado' => 'Rotulado e Instrucciones de Uso',
        'agente_carga' => 'Agente Extintor / Peso de Carga',
        'prueba_hidrostatica' => 'Bloque Prueba Hidrostática (P.H.)',
    ];

    /**
     * Procesa y guarda un checklist técnico digital (§19), generando
     * automáticamente las deficiencias observadas (§20) y ajustando el flujo
     * de la orden de servicio.
     *
     * @param  array{
     *     origen?: string,
     *     items: array<string, array{
     *         estado: 'conforme'|'observado'|'no_aplica',
     *         condicion?: string|null,
     *         nota?: string|null,
     *         accion_recomendada?: string|null,
     *         repuesto_sugerido?: string|null,
     *         requiere_autorizacion?: bool|null,
     *         foto_path?: string|null
     *     }>,
     *     observaciones?: string|null
     * }  $data
     */
    public function execute(
        ServiceOrder $serviceOrder,
        Equipment $equipment,
        User $user,
        array $data
    ): TechnicalChecklist {
        if (! isset($data['items']) || ! is_array($data['items'])) {
            throw new InvalidArgumentException('El checklist debe incluir los elementos evaluados.');
        }

        return DB::transaction(function () use ($serviceOrder, $equipment, $user, $data) {
            $origen = $data['origen'] ?? ($serviceOrder->departamento_tecnico ?: 'planta');
            $checklistItems = [];
            $deficienciesCreated = [];
            $requiereAutorizacionGlobal = false;

            foreach ($data['items'] as $clave => $itemData) {
                $nombreElemento = self::ELEMENTOS[$clave] ?? ucfirst(str_replace('_', ' ', $clave));
                $estado = $itemData['estado'] ?? 'conforme';

                $checklistItems[] = [
                    'clave' => $clave,
                    'elemento' => $nombreElemento,
                    'estado' => $estado,
                    'nota' => $itemData['nota'] ?? null,
                ];

                // Si está observado, se genera la deficiencia (§19.2, §20)
                if ($estado === 'observado') {
                    $requiereAuth = (bool) ($itemData['requiere_autorizacion'] ?? false);
                    if ($requiereAuth) {
                        $requiereAutorizacionGlobal = true;
                    }

                    $deficiency = Deficiency::create([
                        'service_order_id' => $serviceOrder->id,
                        'equipment_id' => $equipment->id,
                        'componente' => $nombreElemento,
                        'condicion' => $itemData['condicion'] ?? 'Observado durante inspección técnica',
                        'foto_path' => $itemData['foto_path'] ?? null,
                        'nota' => $itemData['nota'] ?? null,
                        'accion_recomendada' => $itemData['accion_recomendada'] ?? null,
                        'repuesto_sugerido' => $itemData['repuesto_sugerido'] ?? null,
                        'requiere_autorizacion' => $requiereAuth,
                        'estado' => $requiereAuth ? 'esperando_autorizacion' : 'detectada',
                        'reported_by_user_id' => $user->id,
                    ]);

                    $deficienciesCreated[] = $deficiency;
                }
            }

            $resultadoGeneral = count($deficienciesCreated) > 0 ? 'observado' : 'conforme';

            $checklist = TechnicalChecklist::create([
                'service_order_id' => $serviceOrder->id,
                'equipment_id' => $equipment->id,
                'user_id' => $user->id,
                'origen' => $origen,
                'tipo_equipo' => $equipment->tipo_agente,
                'items' => $checklistItems,
                'resultado_general' => $resultadoGeneral,
                'observaciones' => $data['observaciones'] ?? null,
            ]);

            // Transición de la Orden de Servicio según el resultado
            if ($requiereAutorizacionGlobal) {
                $serviceOrder->update(['estado' => 'esperando_autorizacion']);

                ServiceOrderEvent::create([
                    'service_order_id' => $serviceOrder->id,
                    'tipo' => 'deficiencia_detectada',
                    'user_id' => $user->id,
                    'payload' => [
                        'accion' => 'deficiencias_requieren_autorizacion',
                        'equipment_id' => $equipment->id,
                        'numero_serie' => $equipment->numero_serie,
                        'deficiencias_count' => count($deficienciesCreated),
                        'deficiencias' => array_map(fn (Deficiency $d) => [
                            'id' => $d->id,
                            'componente' => $d->componente,
                            'condicion' => $d->condicion,
                            'accion' => $d->accion_recomendada,
                            'repuesto' => $d->repuesto_sugerido,
                        ], $deficienciesCreated),
                    ],
                ]);

                // Notificación a Vendedor (§17, §20)
                ServiceOrderEvent::create([
                    'service_order_id' => $serviceOrder->id,
                    'tipo' => 'notificacion_vendedor',
                    'user_id' => $user->id,
                    'payload' => [
                        'mensaje' => sprintf(
                            'Se detectaron deficiencias en el equipo %s que requieren autorización comercial.',
                            $equipment->numero_serie
                        ),
                        'equipment_id' => $equipment->id,
                    ],
                ]);
            } else {
                // Si la orden estaba en recibido_planta o en_revision y todo está conforme o no requiere auth
                if (in_array($serviceOrder->estado, ['recibido_planta', 'en_revision'])) {
                    $serviceOrder->update(['estado' => 'en_proceso']);
                }

                ServiceOrderEvent::create([
                    'service_order_id' => $serviceOrder->id,
                    'tipo' => 'otro',
                    'user_id' => $user->id,
                    'payload' => [
                        'accion' => 'checklist_tecnico_completado',
                        'equipment_id' => $equipment->id,
                        'numero_serie' => $equipment->numero_serie,
                        'resultado_general' => $resultadoGeneral,
                        'deficiencias_count' => count($deficienciesCreated),
                    ],
                ]);
            }

            return $checklist;
        });
    }
}
