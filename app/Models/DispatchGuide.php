<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Guía de remisión electrónica del remitente (tipo 09, serie T001).
 * Solo está "lista para trasladar" con el CDR aceptado por SUNAT.
 *
 * @property int $id
 * @property int $sede_id
 * @property int $user_id
 * @property string $serie
 * @property int $correlativo
 * @property Carbon $fecha_emision
 * @property Carbon $fecha_traslado
 * @property string $motivo
 * @property string|null $motivo_descripcion
 * @property string $modalidad
 * @property string $destinatario_tipo_doc
 * @property string $destinatario_num_doc
 * @property string $destinatario_nombre
 * @property string $partida_ubigeo
 * @property string $partida_direccion
 * @property string|null $partida_cod_establecimiento
 * @property string $llegada_ubigeo
 * @property string $llegada_direccion
 * @property string|null $llegada_cod_establecimiento
 * @property string $peso_bruto
 * @property int|null $transport_vehicle_id
 * @property int|null $driver_id
 * @property string|null $transportista_ruc
 * @property string|null $transportista_razon
 * @property int|null $sale_id
 * @property int|null $service_order_id
 * @property int|null $inventory_transfer_id
 * @property string|null $doc_relacionado_tipo
 * @property string|null $doc_relacionado_numero
 * @property string $estado_sunat
 * @property string|null $sunat_ticket
 * @property string|null $sunat_codigo
 * @property string|null $sunat_mensaje
 * @property string|null $hash_zip
 * @property string|null $xml_path
 * @property string|null $cdr_path
 * @property Carbon|null $enviado_at
 * @property-read Sede $sede
 * @property-read TransportVehicle|null $vehiculo
 * @property-read Driver|null $conductor
 * @property-read InventoryTransfer|null $traslado
 * @property-read Collection<int, DispatchGuideItem> $items
 */
#[Fillable([
    'sede_id', 'user_id', 'serie', 'correlativo', 'fecha_emision', 'fecha_traslado', 'motivo', 'motivo_descripcion', 'modalidad',
    'destinatario_tipo_doc', 'destinatario_num_doc', 'destinatario_nombre',
    'partida_ubigeo', 'partida_direccion', 'partida_cod_establecimiento',
    'llegada_ubigeo', 'llegada_direccion', 'llegada_cod_establecimiento',
    'peso_bruto', 'transport_vehicle_id', 'driver_id', 'transportista_ruc', 'transportista_razon',
    'sale_id', 'service_order_id', 'inventory_transfer_id', 'doc_relacionado_tipo', 'doc_relacionado_numero',
    'estado_sunat', 'sunat_ticket', 'sunat_codigo', 'sunat_mensaje', 'hash_zip', 'xml_path', 'cdr_path', 'enviado_at',
])]
class DispatchGuide extends Model
{
    public const BORRADOR = 'borrador';

    public const ENVIADA = 'enviada';

    public const ACEPTADA = 'aceptada';

    public const RECHAZADA = 'rechazada';

    /** Catálogo 20 que usa Bruce Fire: 01 venta, 04 entre establecimientos, 13 otros. */
    public const MOTIVOS = ['01' => 'Venta', '04' => 'Traslado entre establecimientos de la misma empresa', '13' => 'Otros'];

    public const MODALIDADES = ['01' => 'Transporte público', '02' => 'Transporte privado'];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime',
            'fecha_traslado' => 'date',
            'peso_bruto' => 'decimal:3',
            'enviado_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * @return BelongsTo<TransportVehicle, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(TransportVehicle::class, 'transport_vehicle_id');
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    /**
     * @return BelongsTo<InventoryTransfer, $this>
     */
    public function traslado(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class, 'inventory_transfer_id');
    }

    /**
     * @return HasMany<DispatchGuideItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DispatchGuideItem::class);
    }

    /**
     * Venta que sustenta la guía (motivo 01).
     *
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Orden de servicio que sustenta la guía.
     *
     * @return BelongsTo<ServiceOrder, $this>
     */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function numero(): string
    {
        return $this->serie.'-'.$this->correlativo;
    }

    /** Con CDR aceptado la mercadería puede salir. */
    public function estaListaParaTrasladar(): bool
    {
        return $this->estado_sunat === self::ACEPTADA;
    }
}
