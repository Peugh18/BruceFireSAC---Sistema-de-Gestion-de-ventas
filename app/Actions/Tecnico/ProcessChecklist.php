<?php

namespace App\Actions\Tecnico;

use App\Enums\EquipmentType;
use App\Models\Deficiency;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\TechnicalChecklist;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessChecklist
{
    public function __construct(protected GuardarEvidencia $guardarEvidencia) {}

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
     * Estados de la orden en los que el taller todavía trabaja: solo desde
     * estos se mueve la orden a `esperando_autorizacion` (M7). Una orden que
     * ya espera autorización (o que ya terminó) no se "rebobina".
     */
    public const ESTADOS_DE_TRABAJO = ['pendiente_recepcion', 'recibido_planta', 'autorizado', 'en_proceso'];

    /**
     * Procesa y guarda un checklist técnico digital (§19), generando
     * automáticamente las deficiencias observadas (§20) y ajustando el flujo
     * de la orden de servicio.
     *
     * @param  array{
     *     origen?: string,
     *     equipo_descargado?: bool|null,
     *     items: array<string, array{
     *         estado: 'conforme'|'observado'|'no_aplica',
     *         condicion?: string|null,
     *         nota?: string|null,
     *         accion_recomendada?: string|null,
     *         repuesto_sugerido?: string|null,
     *         requiere_autorizacion?: bool|null,
     *         descargado?: bool|null,
     *         foto?: UploadedFile|null
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
        // En planta se revisa lo recibido y antes de cerrar el trabajo.
        $this->asegurarAbiertaAlTaller($serviceOrder, $data);

        // Solo se revisan los extintores de esta orden (no los de otro cliente).
        if (! $serviceOrder->equipments()->whereKey($equipment->id)->exists()) {
            throw ValidationException::withMessages(['equipment' => 'Ese extintor no está registrado en esta orden.']);
        }

        // En planta, cada ítem observado lleva su foto (§19.2, §33).
        if (($data['origen'] ?? null) === 'planta') {
            foreach ($data['items'] as $clave => $item) {
                if ($item['estado'] === 'observado' && ! ($item['foto'] ?? null) instanceof UploadedFile) {
                    throw ValidationException::withMessages(["items.{$clave}.foto" => 'Toma una foto del componente observado.']);
                }
            }
        }

        return DB::transaction(function () use ($serviceOrder, $equipment, $user, $data) {
            // Bloqueada y releída: dos checklists a la vez no se pisan en el
            // estado de la orden (uno la manda a esperar autorización y el
            // otro la rebobina a en_proceso con un estado viejo).
            $serviceOrder = ServiceOrder::query()->lockForUpdate()->findOrFail($serviceOrder->id);
            $this->asegurarAbiertaAlTaller($serviceOrder, $data);

            $origen = $data['origen'] ?? ($serviceOrder->departamento_tecnico ?: 'planta');
            $checklistItems = [];
            $deficienciesCreated = [];
            $requiereAutorizacionGlobal = false;
            // B4: el técnico también puede marcarlo a mano con
            // `equipo_descargado` en el checklist o `descargado` en el ítem.
            $esDescargadoOUsado = (bool) ($data['equipo_descargado'] ?? false);

            foreach ($data['items'] as $clave => $itemData) {
                $nombreElemento = self::ELEMENTOS[$clave] ?? ucfirst(str_replace('_', ' ', $clave));
                $estado = $itemData['estado'];

                $checklistItems[] = [
                    'clave' => $clave,
                    'elemento' => $nombreElemento,
                    'estado' => $estado,
                    'nota' => $itemData['nota'] ?? null,
                ];

                // Si está observado, se genera la deficiencia (§19.2, §20)
                if ($estado === 'observado') {
                    if ($this->esDescargadoOUsado($itemData)) {
                        $esDescargadoOUsado = true;
                    }

                    $requiereAuth = (bool) ($itemData['requiere_autorizacion'] ?? false);
                    if ($requiereAuth) {
                        $requiereAutorizacionGlobal = true;
                    }

                    $deficiency = Deficiency::create([
                        'service_order_id' => $serviceOrder->id,
                        'equipment_id' => $equipment->id,
                        'componente' => $nombreElemento,
                        'condicion' => $itemData['condicion'] ?? 'Observado durante inspección técnica',
                        'nota' => trim(($itemData['nota'] ?? '')."\nCondición observada: ".($itemData['condicion'] ?? 'Observado durante inspección técnica')),
                        'accion_recomendada' => $itemData['accion_recomendada'] ?? null,
                        'repuesto_sugerido' => $itemData['repuesto_sugerido'] ?? null,
                        'requiere_autorizacion' => $requiereAuth,
                        'estado' => $requiereAuth ? 'esperando_autorizacion' : 'detectada',
                        'reported_by_user_id' => $user->id,
                    ]);

                    if (($itemData['foto'] ?? null) instanceof UploadedFile) {
                        $evidencia = $this->guardarEvidencia->archivo($serviceOrder, $itemData['foto'], 'deficiencia', $user, $equipment->id, null, $deficiency);
                        $deficiency->update(['foto_path' => $evidencia->path]);
                    }

                    $deficienciesCreated[] = $deficiency;
                }
            }

            if ($esDescargadoOUsado) {
                $equipment->update(['estado' => 'descargado']);
            }

            $resultadoGeneral = count($deficienciesCreated) > 0 ? 'observado' : 'conforme';

            $checklist = TechnicalChecklist::create([
                'service_order_id' => $serviceOrder->id,
                'equipment_id' => $equipment->id,
                'user_id' => $user->id,
                'origen' => $origen,
                'tipo_equipo' => EquipmentType::fromDescription($equipment->tipo_agente)->value,
                'items' => $checklistItems,
                'resultado_general' => $resultadoGeneral,
                'observaciones' => $data['observaciones'] ?? null,
            ]);

            // Transición de la Orden de Servicio según el resultado (M7): se
            // decide sobre el estado RELEÍDO bajo la orden bloqueada y solo
            // desde un estado de trabajo; dos checklists a la vez no se pisan.
            if ($requiereAutorizacionGlobal) {
                if (in_array($serviceOrder->estado, self::ESTADOS_DE_TRABAJO, true)) {
                    $serviceOrder->update(['estado' => 'esperando_autorizacion']);
                }

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
                // Si la orden estaba en recibido_planta y todo está conforme o no requiere auth
                if (in_array($serviceOrder->estado, ['recibido_planta'], true)) {
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

    /**
     * Las acciones del taller sobre una orden ya terminada no se repiten. Se
     * revalida también dentro de la transacción, con la orden bloqueada (M7).
     *
     * @param  array<string, mixed>  $data
     */
    protected function asegurarAbiertaAlTaller(ServiceOrder $serviceOrder, array $data): void
    {
        if (in_array($serviceOrder->estado, ServiceOrder::ESTADOS_CERRADOS_AL_TALLER, true)
            || (($data['origen'] ?? null) === 'planta' && $serviceOrder->estado === 'pendiente_recepcion')) {
            throw ValidationException::withMessages(['equipment' => $serviceOrder->estado === 'pendiente_recepcion'
                ? 'Primero confirma la recepción de la orden en planta.'
                : 'Esta orden ya terminó en el taller: no se hacen más checklists.']);
        }
    }

    /**
     * ¿El extintor quedó descargado o usado? (B4) Además del control
     * explícito `descargado` del ítem, se buscan señales en el texto libre de
     * la condición y la nota: «sin carga», «agotado», «vaciado», etc., no solo
     * «descargado».
     *
     * @param  array<string, mixed>  $itemData
     */
    protected function esDescargadoOUsado(array $itemData): bool
    {
        if (($itemData['descargado'] ?? false) === true) {
            return true;
        }

        $condicionLower = mb_strtolower(($itemData['condicion'] ?? '').' '.($itemData['nota'] ?? ''));

        foreach ([
            'descargad', 'usad', 'sin presion', 'sin presión', 'despresurizad',
            'percutad', 'vacio', 'vacío', 'sin carga', 'agotad', 'vaciad', 'descarga total',
        ] as $senal) {
            if (str_contains($condicionLower, $senal)) {
                return true;
            }
        }

        return false;
    }
}
