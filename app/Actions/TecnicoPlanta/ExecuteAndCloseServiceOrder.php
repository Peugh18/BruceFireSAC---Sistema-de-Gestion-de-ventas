<?php

namespace App\Actions\TecnicoPlanta;

use App\Actions\Certificates\IssueCertificate;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Deficiency;
use App\Models\Equipment;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExecuteAndCloseServiceOrder
{
    public function __construct(
        protected IssueCertificate $issueCertificate
    ) {}

    /**
     * Consume un repuesto físico del inventario para reparar una deficiencia (§85, Fase 5).
     * Genera un InventoryMovement real con cantidad negativa contra el Kardex de Almacén.
     */
    public function consumeSparePart(
        ServiceOrder $serviceOrder,
        Deficiency $deficiency,
        Product $product,
        int $cantidad,
        User $user,
        ?string $observacion = null
    ): InventoryMovement {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad de repuestos debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($serviceOrder, $deficiency, $product, $cantidad, $user, $observacion) {
            $sedeId = $serviceOrder->sede_id ?: Sede::where('activo', true)->first()?->id ?: 1;

            $obsKardex = sprintf(
                'Consumo en taller para orden %s: %s (%s). %s',
                $serviceOrder->codigo,
                $deficiency->componente,
                $product->nombre,
                $observacion ?: ''
            );

            // Generar movimiento real de salida en el Kardex
            $movement = InventoryMovement::create([
                'product_id' => $product->id,
                'sede_id' => $sedeId,
                'tipo' => 'salida_servicio',
                'cantidad' => -$cantidad,
                'referencia_type' => Deficiency::class,
                'referencia_id' => $deficiency->id,
                'user_id' => $user->id,
                'observacion' => trim($obsKardex),
            ]);

            // Marcar deficiencia como resuelta
            $deficiency->update([
                'estado' => 'resuelta',
                'resolucion' => sprintf('Repuesto %s (x%d) instalado en taller. %s', $product->nombre, $cantidad, $observacion ?: ''),
            ]);

            // Evento inmutable de bitácora
            ServiceOrderEvent::create([
                'service_order_id' => $serviceOrder->id,
                'tipo' => 'otro',
                'user_id' => $user->id,
                'payload' => [
                    'accion' => 'consumo_repuesto_kardex',
                    'deficiency_id' => $deficiency->id,
                    'product_id' => $product->id,
                    'product_nombre' => $product->nombre,
                    'cantidad' => $cantidad,
                    'inventory_movement_id' => $movement->id,
                ],
            ]);

            $this->releaseFromAuthorizationHold($serviceOrder, $user);

            return $movement;
        });
    }

    /**
     * Avanza el estado técnico de la orden en Planta (§16.2, Fase 5).
     * Transiciones válidas:
     * recibido_planta/autorizado -> en_proceso -> trabajo_terminado -> (pendiente_datos -> datos_completos ->) listo_certificado -> listo_entrega.
     * Al llegar a listo_certificado, dispara automáticamente la emisión del certificado.
     *
     * @param  array<string, mixed>  $extraData
     */
    public function advanceState(
        ServiceOrder $serviceOrder,
        string $targetState,
        User $user,
        array $extraData = []
    ): ServiceOrder {
        if (! in_array($targetState, ServiceOrder::ESTADOS, true)) {
            throw new InvalidArgumentException(sprintf('Estado "%s" no es válido en el sistema.', $targetState));
        }

        // Pasos permitidos desde cada estado (los mismos botones de la
        // pantalla de ejecución). Los datos del certificado pueden estar
        // completos desde antes, por eso se puede ir directo a certificado.
        $permitidos = [
            'recibido_planta' => ['en_proceso'],
            'autorizado' => ['en_proceso'],
            'en_proceso' => ['trabajo_terminado'],
            'trabajo_terminado' => ['pendiente_datos', 'datos_completos', 'listo_certificado'],
            'pendiente_datos' => ['datos_completos', 'listo_certificado'],
            'datos_completos' => ['listo_certificado'],
            'listo_certificado' => ['listo_entrega'],
        ][$serviceOrder->estado] ?? [];

        if (! in_array($targetState, $permitidos, true)) {
            throw new InvalidArgumentException('Solo puedes avanzar a la siguiente etapa de la orden.');
        }

        return DB::transaction(function () use ($serviceOrder, $targetState, $user, $extraData) {
            $previousState = $serviceOrder->estado;

            $serviceOrder->update([
                'estado' => $targetState,
            ]);

            // Si llega a listo_certificado, disparo automático del certificado (§85, Fase 5)
            if ($targetState === 'listo_certificado') {
                $this->triggerAutomaticCertificates($serviceOrder, $extraData);
            }

            // Registrar evento append-only de transición
            ServiceOrderEvent::create([
                'service_order_id' => $serviceOrder->id,
                'tipo' => $targetState === 'listo_certificado' ? 'trabajo_completado' : 'otro',
                'user_id' => $user->id,
                'payload' => [
                    'accion' => 'cambio_estado_planta',
                    'estado_anterior' => $previousState,
                    'estado_nuevo' => $targetState,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);

            AuditLogger::log(
                action: 'orden.cerrada',
                entity: $serviceOrder,
                oldValues: ['estado' => $previousState],
                newValues: ['estado' => $targetState],
                userId: $user->id
            );

            return $serviceOrder->refresh();
        });
    }

    /**
     * Saca la orden del estado `esperando_autorizacion` una vez que ya no
     * quedan deficiencias pendientes de autorización o resolución (§17, §85).
     * Sin esto la orden queda bloqueada para siempre: la pantalla de
     * Ejecución no tiene ninguna acción disponible en ese estado.
     */
    public function releaseFromAuthorizationHold(ServiceOrder $serviceOrder, User $user): void
    {
        if ($serviceOrder->estado !== 'esperando_autorizacion') {
            return;
        }

        $pendientes = $serviceOrder->deficiencies()
            ->whereIn('estado', ['esperando_autorizacion', 'detectada'])
            ->where('requiere_autorizacion', true)
            ->exists();

        if ($pendientes) {
            return;
        }

        $serviceOrder->update(['estado' => 'autorizado']);

        ServiceOrderEvent::create([
            'service_order_id' => $serviceOrder->id,
            'tipo' => 'otro',
            'user_id' => $user->id,
            'payload' => [
                'accion' => 'cambio_estado_planta',
                'estado_anterior' => 'esperando_autorizacion',
                'estado_nuevo' => 'autorizado',
                'motivo' => 'Todas las deficiencias que requerian autorizacion ya fueron resueltas',
            ],
        ]);
    }

    /**
     * Emisión automática de certificados para la orden (§85).
     *
     * @param  array<string, mixed>  $extraData
     */
    protected function triggerAutomaticCertificates(ServiceOrder $serviceOrder, array $extraData): void
    {
        // Evitar duplicar certificados si ya existen para esta orden
        if (Certificate::where('service_order_id', $serviceOrder->id)->exists()) {
            return;
        }

        $serviceOrder->loadMissing(['client', 'equipments']);

        $equipments = $serviceOrder->equipments;
        if ($equipments->isEmpty() && $serviceOrder->equipment) {
            $equipments = collect([$serviceOrder->equipment]);
        }

        $unidades = $equipments->map(function (Equipment $eq) use ($extraData) {
            return [
                'equipment_id' => $eq->id,
                'numero_serie' => $eq->numero_serie,
                'fecha_ultima_recarga' => now()->toDateString(),
                'fecha_ultima_ph' => ! empty($extraData['ph_realizada']) ? now()->toDateString() : null,
            ];
        })->all();

        // 1. Certificado principal de Operatividad y Garantía
        $tipoOperatividad = CertificateType::firstOrCreate(
            ['codigo' => 'operatividad_garantia'],
            [
                'nombre' => 'Certificado de Operatividad y Garantía',
                'vigencia_meses' => 12,
                'generado_por_rol' => 'tecnico_planta',
            ]
        );

        $cert = $this->issueCertificate->handle(
            $tipoOperatividad,
            $serviceOrder->client,
            $unidades,
            $serviceOrder->sale_id,
            $serviceOrder->id
        );

        // 2. Si se realizó Prueba Hidrostática, generar también su certificado específico
        if (! empty($extraData['ph_realizada'])) {
            $tipoPH = CertificateType::firstOrCreate(
                ['codigo' => 'prueba_hidrostatica'],
                [
                    'nombre' => 'Certificado de Prueba Hidrostática',
                    'vigencia_meses' => 60,
                    'generado_por_rol' => 'tecnico_planta',
                ]
            );

            $this->issueCertificate->handle(
                $tipoPH,
                $serviceOrder->client,
                $unidades,
                $serviceOrder->sale_id,
                $serviceOrder->id
            );
        }
    }
}
