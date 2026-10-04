<?php

namespace App\Actions\TecnicoCampo;

use App\Actions\Certificates\IssueCertificate;
use App\Models\CertificateType;
use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegisterInstallation
{
    public function __construct(
        protected IssueCertificate $issueCertificate,
        protected EquipoDeLaOrden $equipoDeLaOrden,
    ) {}

    /**
     * Registra una instalación técnica en campo (§25).
     * Los extintores y componentes instalados se integran a Equipos del Cliente (Equipment)
     * sin crear tablas paralelas.
     *
     * @param  array{
     *     area: string,
     *     ubicacion_instalada: string,
     *     pruebas?: string|array<int, string>|null,
     *     foto_antes_path?: string|null,
     *     foto_despues_path?: string|null,
     *     observaciones?: string|null,
     *     conformidad_nombre: string,
     *     conformidad_aceptada: bool,
     *     emitir_certificado?: bool|null,
     *     tipo_certificado_codigo?: string|null,
     *     equipos: array<int, array{
     *         equipment_id?: int|null,
     *         numero_serie?: string|null,
     *         tipo_agente?: string|null,
     *         capacidad?: string|null,
     *         marca?: string|null,
     *         serie_fabricante?: string|null,
     *         anio_fabricacion?: int|null,
     *         ubicacion_actual?: string|null
     *     }>
     * }  $data
     */
    public function execute(
        ServiceOrder $serviceOrder,
        User $user,
        array $data
    ): ServiceOrder {
        if (empty($data['conformidad_nombre']) || empty($data['conformidad_aceptada'])) {
            throw new InvalidArgumentException('La instalación en campo requiere la conformidad expresa del cliente en sitio.');
        }

        // Registrarla dos veces duplicaría equipos y certificados.
        EquipoDeLaOrden::asegurarAbierta($serviceOrder);

        return DB::transaction(function () use ($serviceOrder, $user, $data) {
            $equiposCreados = [];

            if (! empty($data['equipos']) && is_array($data['equipos'])) {
                foreach ($data['equipos'] as $eqData) {
                    // Del mismo cliente (también por su serie) o uno nuevo con su
                    // primera recarga en un año: nunca el de otro cliente.
                    $equipment = $this->equipoDeLaOrden->resolver($serviceOrder, [
                        ...$eqData,
                        'ubicacion_actual' => $eqData['ubicacion_actual'] ?? $data['ubicacion_instalada'],
                    ], instalado: true);
                    $equipment->update(['ubicacion_actual' => $eqData['ubicacion_actual'] ?? $data['ubicacion_instalada']]);

                    if (! $serviceOrder->equipments()->where('equipment_id', $equipment->id)->exists()) {
                        $serviceOrder->equipments()->attach($equipment->id, [
                            'recibido' => true,
                            'observaciones' => "Instalado en {$data['area']}",
                        ]);
                    }

                    $equiposCreados[] = $equipment;
                }
            }

            // Registrar evento de instalación con bitácora append-only y cadena de custodia (§22.4, §25, §85.6.3)
            ServiceOrderEvent::create([
                'service_order_id' => $serviceOrder->id,
                'tipo' => 'trabajo_completado',
                'user_id' => $user->id,
                'payload' => [
                    'accion' => 'instalacion_campo_realizada',
                    'area' => $data['area'],
                    'ubicacion_instalada' => $data['ubicacion_instalada'],
                    'pruebas' => $data['pruebas'] ?? 'Pruebas de soporte y montaje conforme norma técnica',
                    'foto_antes_path' => $data['foto_antes_path'] ?? null,
                    'foto_despues_path' => $data['foto_despues_path'] ?? null,
                    'observaciones' => $data['observaciones'] ?? null,
                    'conformidad_nombre' => $data['conformidad_nombre'],
                    'responsable_nombre' => $user->name,
                    'fecha_instalacion' => now()->toIso8601String(),
                    'eslabon_custodia' => 'instalacion_campo',
                    'equipos_instalados_count' => count($equiposCreados),
                ],
            ]);

            // Emisión de certificado aplicable si se solicitó (§25)
            if (! empty($data['emitir_certificado'])) {
                $codigoCert = $data['tipo_certificado_codigo'] ?? 'operatividad_garantia';
                $tipoCert = CertificateType::where('codigo', $codigoCert)->first()
                    ?? CertificateType::where('codigo', 'operatividad_garantia')->first();

                if ($tipoCert && count($equiposCreados) > 0) {
                    $unidades = array_map(fn (Equipment $eq) => [
                        'equipment_id' => $eq->id,
                        'numero_serie' => $eq->numero_serie,
                        'fecha_ultima_recarga' => now()->toDateString(),
                    ], $equiposCreados);

                    $this->issueCertificate->handle(
                        $tipoCert,
                        $serviceOrder->client,
                        $unidades,
                        $serviceOrder->sale_id,
                        $serviceOrder->id
                    );
                }
            }

            // Actualizar estado de la orden a listo_entrega
            $serviceOrder->update([
                'estado' => 'listo_entrega',
            ]);

            return $serviceOrder->refresh();
        });
    }
}
