<?php

namespace App\Models;

use Database\Factories\ElectronicDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sale_id
 * @property string $tipo
 * @property string $serie
 * @property int $correlativo
 * @property Carbon|null $fecha_emision
 * @property int|null $cpe_afectado_id
 * @property string|null $motivo_catalogo
 * @property string|null $importe
 * @property string|null $xml_path
 * @property string|null $cdr_path
 * @property string|null $pdf_path
 * @property string|null $pdf_firma
 * @property string $sunat_estado
 * @property Carbon|null $enviar_desde
 * @property string|null $sunat_codigo_respuesta
 * @property string|null $sunat_mensaje
 * @property Carbon|null $enviado_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sale $sale
 * @property-read ElectronicDocument|null $cpeAfectado
 */
#[Fillable([
    'sale_id', 'tipo', 'serie', 'correlativo', 'fecha_emision', 'cpe_afectado_id', 'motivo_catalogo', 'importe',
    'xml_path', 'cdr_path', 'pdf_path', 'pdf_firma', 'sunat_estado', 'sunat_codigo_respuesta',
    'sunat_mensaje', 'enviar_desde', 'enviado_at',
])]
class ElectronicDocument extends Model
{
    /** @use HasFactory<ElectronicDocumentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'correlativo' => 'integer',
            'importe' => 'decimal:2',
            // S5: guardamos la hora de emision (no solo la fecha) para cumplir
            // el requisito SUNAT de incluir la hora en el XML y el PDF.
            'fecha_emision' => 'datetime',
            'enviar_desde' => 'datetime',
            'enviado_at' => 'datetime',
        ];
    }

    /**
     * Mientras no se envía, SUNAT no conoce el comprobante: se puede corregir
     * sin nota de crédito.
     */
    public function estaPorEnviar(): bool
    {
        return $this->sunat_estado === 'por_enviar';
    }

    /**
     * Rechazado o con excepción: el comprobante no tiene validez y se corrige
     * emitiendo uno nuevo con otro número.
     */
    public function fueRechazado(): bool
    {
        return in_array($this->sunat_estado, ['rechazado', 'excepcion'], true);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function cpeAfectado(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cpe_afectado_id');
    }

    public const PLAZO_DIAS_FACTURA = 3;

    public const PLAZO_DIAS_BOLETA = 5;

    public function plazoLimiteDias(): int
    {
        return $this->tipo === 'factura' ? self::PLAZO_DIAS_FACTURA : self::PLAZO_DIAS_BOLETA;
    }

    public function diasRestantesParaEnvio(): int
    {
        $fecha = $this->fecha_emision ?? $this->created_at ?? now();
        $limite = $fecha->copy()->addDays($this->plazoLimiteDias());

        return (int) now()->diffInDays($limite, false);
    }

    /**
     * S8: Avisa si el comprobante pendiente esta a 1 dia o menos de vencer su plazo SUNAT.
     */
    public function estaPorVencerSunat(): bool
    {
        return $this->estaPorEnviar() && $this->diasRestantesParaEnvio() <= 1;
    }
}
