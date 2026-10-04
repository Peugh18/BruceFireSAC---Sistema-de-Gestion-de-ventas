<?php

namespace App\Actions\TecnicoPlanta;

use App\Actions\Certificates\IssueCertificate;
use App\Actions\Equipment\RenewEquipmentAttentionDate;
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
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ExecuteAndCloseServiceOrder
{
    public function __construct(
        protected IssueCertificate $issueCertificate,
        protected RenewEquipmentAttentionDate $renewEquipmentAttentionDate,
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

        if ($deficiency->service_order_id !== $serviceOrder->id) {
            throw ValidationException::withMessages(['deficiency' => 'Esa deficiencia no es de esta orden.']);
        }

        self::asegurarQueSePuedeReparar($deficiency);

        return DB::transaction(function () use ($serviceOrder, $deficiency, $product, $cantidad, $user, $observacion) {
            // Del almacén que abastece a la sede de la orden, y sin dejarlo
            // en negativo.
            $sedeId = $serviceOrder->sede?->almacenEfectivoId() ?? Sede::where('activo', true)->whereIn('tipo', ['almacen', 'mixta'])->value('id');

            if ($sedeId === null) {
                throw ValidationException::withMessages(['product_id' => 'La orden no tiene un almacén del que sacar el repuesto.']);
            }

            if ($product->serializado) {
                throw ValidationException::withMessages(['product_id' => "{$product->nombre} se controla por serie: no se consume como repuesto."]);
            }

            InventoryMovement::exigirSaldo($product, (int) $sedeId, $cantidad);

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
     * Solo se repara lo que el cliente aceptó: una deficiencia que espera su
     * autorización, que rechazó o que ya se resolvió no gasta repuestos.
     */
    public static function asegurarQueSePuedeReparar(Deficiency $deficiency): void
    {
        $motivo = match ($deficiency->estado) {
            'esperando_autorizacion' => 'Esta reparación todavía espera la autorización del cliente.',
            'rechazada' => 'El cliente rechazó esta reparación: no se puede ejecutar.',
            'resuelta' => 'Esta deficiencia ya está resuelta.',
            default => null,
        };

        if ($motivo !== null) {
            throw ValidationException::withMessages(['deficiency' => $motivo]);
        }
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

        if ($targetState === 'trabajo_terminado') {
            $this->asegurarTrabajoCompleto($serviceOrder);
        }

        return DB::transaction(function () use ($serviceOrder, $targetState, $user, $extraData) {
            // Bloqueada mientras avanza: un doble envío (mala señal en el
            // celular) no emite dos certificados ni renueva dos veces.
            $bloqueada = ServiceOrder::query()->lockForUpdate()->findOrFail($serviceOrder->id);
            if ($bloqueada->estado !== $serviceOrder->estado) {
                throw new InvalidArgumentException('La orden ya cambió de etapa: recarga la pantalla.');
            }

            $previousState = $serviceOrder->estado;

            $serviceOrder->update([
                'estado' => $targetState,
            ]);

            // Si llega a listo_certificado, disparo automático del certificado (§85, Fase 5).
            // Solo los extintores que llegaron y no fueron rechazados; la
            // P.H. se marca por extintor.
            if ($targetState === 'listo_certificado') {
                $aptos = $serviceOrder->equiposAptos();

                // Un servicio sin extintores (luces, señalización) se
                // certifica igual; con extintores, alguno tiene que estar apto.
                if ($aptos->isEmpty() && $serviceOrder->equipments()->exists()) {
                    throw new InvalidArgumentException('No hay extintores recibidos y aptos para certificar.');
                }

                $conPh = array_values(array_map('intval', array_key_exists('ph_equipos', $extraData)
                    ? (array) $extraData['ph_equipos']
                    : (! empty($extraData['ph_realizada']) ? $aptos->modelKeys() : [])));

                $this->triggerAutomaticCertificates($serviceOrder, $aptos, $conPh, $extraData);
                $this->renewEquipmentAttentionDate->execute($aptos->whereIn('id', $conPh)->values(), true);
                $this->renewEquipmentAttentionDate->execute($aptos->whereNotIn('id', $conPh)->values(), false);
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
     * Para cerrar el trabajo: llegó al menos un extintor, cada uno que llegó
     * tiene su checklist en esta orden y no queda ninguna deficiencia sin
     * resolver ni esperando al cliente.
     */
    protected function asegurarTrabajoCompleto(ServiceOrder $serviceOrder): void
    {
        $recibidos = $serviceOrder->equipments()->wherePivot('recibido', true)->get();

        if ($recibidos->isEmpty() && $serviceOrder->equipments()->exists()) {
            throw new InvalidArgumentException('La orden no tiene extintores recibidos en el taller.');
        }

        $conChecklist = $serviceOrder->checklists()->pluck('equipment_id')->unique();
        $sinChecklist = $recibidos->reject(fn (Equipment $eq) => $conChecklist->contains($eq->id));

        if ($sinChecklist->isNotEmpty()) {
            throw new InvalidArgumentException('Falta el checklist de: '.$sinChecklist->pluck('numero_serie')->implode(', ').'.');
        }

        $pendientes = $serviceOrder->deficiencies()
            ->whereIn('estado', ['detectada', 'esperando_autorizacion', 'autorizada', 'en_correccion'])
            ->count();

        if ($pendientes > 0) {
            throw new InvalidArgumentException("Quedan {$pendientes} deficiencia(s) sin resolver o esperando al cliente.");
        }
    }

    /**
     * Emisión automática de certificados para la orden (§85).
     *
     * @param  EloquentCollection<int, Equipment>  $aptos
     * @param  list<int>  $conPh
     * @param  array<string, mixed>  $extraData
     */
    protected function triggerAutomaticCertificates(ServiceOrder $serviceOrder, EloquentCollection $aptos, array $conPh, array $extraData): void
    {
        // Evitar duplicar certificados si ya existen para esta orden
        if (Certificate::where('service_order_id', $serviceOrder->id)->exists()) {
            return;
        }

        $serviceOrder->loadMissing(['client', 'service.certificateType']);

        $unidades = $aptos->map(function (Equipment $eq) use ($conPh) {
            return [
                'equipment_id' => $eq->id,
                'numero_serie' => $eq->numero_serie,
                'fecha_ultima_recarga' => now()->toDateString(),
                'fecha_ultima_ph' => in_array($eq->id, $conPh, true) ? now()->toDateString() : null,
            ];
        })->values()->all();

        // 1. Certificado principal de Operatividad y Garantía
        $tipoOperatividad = ($serviceOrder->service_id ? $serviceOrder->service->certificateType : null) ?? CertificateType::firstOrCreate(
            ['codigo' => 'operatividad_garantia'],
            [
                'nombre' => 'Certificado de Operatividad y Garantía',
                'vigencia_meses' => 12,
                'generado_por_rol' => 'tecnico_planta',
            ]
        );

        $extra = $serviceOrder->service?->certificateType && ! empty($extraData['certificate_data'])
            ? ['datos' => $extraData['certificate_data'], 'referencia' => $serviceOrder->referencia]
            : [];

        $this->issueCertificate->handle(
            $tipoOperatividad,
            $serviceOrder->client,
            $unidades,
            $serviceOrder->sale_id,
            $serviceOrder->id,
            $extra,
        );

        // 2. Los extintores con Prueba Hidrostática llevan además su certificado
        if ($conPh !== []) {
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
                array_values(array_filter($unidades, fn (array $unidad) => in_array($unidad['equipment_id'], $conPh, true))),
                $serviceOrder->sale_id,
                $serviceOrder->id
            );
        }
    }
}
