<?php

namespace App\Actions\TecnicoCampo;

use App\Models\Equipment;
use App\Models\ServiceOrder;
use App\Services\Inventory\InventorySequenceGenerator;
use Illuminate\Validation\ValidationException;

/**
 * El extintor que el técnico de campo agrega a una orden: uno ya registrado
 * del mismo cliente, o uno nuevo. Nunca el de otro cliente.
 */
class EquipoDeLaOrden
{
    /** Lo que no se pudo leer en la placa del extintor. */
    public const NO_LEGIBLE = 'No legible';

    public function __construct(protected InventorySequenceGenerator $sequenceGenerator) {}

    /**
     * @param  array<string, mixed>  $datos
     * @param  bool  $instalado  true si Bruce Fire lo instala ahora (nuevo, con
     *                           su primera recarga en un año); false si solo se inspecciona (sus
     *                           fechas no se conocen y no se inventan).
     */
    public function resolver(ServiceOrder $orden, array $datos, bool $instalado): Equipment
    {
        if (! empty($datos['equipment_id'])) {
            $equipo = Equipment::query()->findOrFail((int) $datos['equipment_id']);
            $this->asegurarMismoCliente($orden, $equipo);

            return $equipo;
        }

        $serie = trim((string) ($datos['numero_serie'] ?? ''));

        if ($serie !== '') {
            // La serie ya registrada se reutiliza en vez de fallar.
            $existente = Equipment::query()->where('numero_serie', $serie)->first();

            if ($existente) {
                $this->asegurarMismoCliente($orden, $existente);

                return $existente;
            }
        }

        $anio = ! empty($datos['anio_fabricacion']) ? (int) $datos['anio_fabricacion'] : null;

        return Equipment::create([
            'client_id' => $orden->client_id,
            'numero_serie' => $serie !== '' ? $serie : $this->sequenceGenerator->nextEquipmentSerial(),
            'tipo_agente' => $datos['tipo_agente'] ?? self::NO_LEGIBLE,
            'capacidad' => $datos['capacidad'] ?? self::NO_LEGIBLE,
            'marca' => $datos['marca'] ?? self::NO_LEGIBLE,
            'serie_fabricante' => $datos['serie_fabricante'] ?? null,
            'anio_fabricacion' => $anio ?? ($instalado ? (int) date('Y') : null),
            'ubicacion_actual' => $datos['ubicacion_actual'] ?? 'Sede cliente',
            'estado' => 'operativo',
            // La columna es obligatoria: en una inspección queda la fecha en
            // que se registró, no una venta.
            'fecha_venta' => now(),
            'proxima_fecha_atencion' => $instalado ? now()->addYear() : null,
            'proxima_prueba_hidrostatica' => $instalado ? now()->addYears(5) : null,
        ]);
    }

    protected function asegurarMismoCliente(ServiceOrder $orden, Equipment $equipo): void
    {
        if ((int) $equipo->client_id !== (int) $orden->client_id) {
            throw ValidationException::withMessages([
                'equipment_id' => "El extintor {$equipo->numero_serie} es de otro cliente: no se puede agregar a esta orden.",
            ]);
        }
    }

    /**
     * Las acciones del técnico sobre una orden ya terminada no se repiten:
     * duplicarían certificados y equipos.
     */
    public static function asegurarAbierta(ServiceOrder $orden): void
    {
        if (in_array($orden->estado, ['esperando_autorizacion', 'listo_certificado', 'listo_entrega', 'entregado', 'cerrado'], true)) {
            throw ValidationException::withMessages([
                'estado' => 'Esta orden ya se finalizó: no se puede volver a registrar.',
            ]);
        }
    }
}
