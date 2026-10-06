<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ElectronicDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
 * @property string|null $baja_nombre
 * @property string|null $baja_ticket
 * @property string|null $baja_motivo
 * @property string|null $baja_mensaje
 * @property string|null $baja_estado_previo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sale $sale
 * @property-read ElectronicDocument|null $cpeAfectado
 */
#[Fillable([
    'sale_id', 'tipo', 'serie', 'correlativo', 'fecha_emision', 'cpe_afectado_id', 'motivo_catalogo', 'importe',
    'xml_path', 'cdr_path', 'pdf_path', 'pdf_firma', 'sunat_estado', 'sunat_codigo_respuesta',
    'sunat_mensaje', 'enviar_desde', 'enviado_at',
    'baja_nombre', 'baja_ticket', 'baja_motivo', 'baja_mensaje', 'baja_estado_previo',
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
     * Día en que SUNAT devolvió el CDR (se guarda en enviado_at al recibir
     * la respuesta). Los comprobantes antiguos sin ese dato usan la fecha
     * de emisión, que es la más conservadora para el plazo de baja.
     */
    public function fechaRecepcionCdr(): CarbonInterface
    {
        return ($this->enviado_at ?? $this->fecha_emision ?? $this->created_at ?? now())->copy()->startOfDay();
    }

    /**
     * Último día para la comunicación de baja: 7 días calendario contados
     * desde el día siguiente a la recepción del CDR.
     */
    public function vencimientoBaja(): CarbonInterface
    {
        return $this->fechaRecepcionCdr()->addDays(7);
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

    /**
     * Estados de un comprobante que todavía no llega a SUNAT con éxito.
     *
     * @var list<string>
     */
    public const ESTADOS_SIN_ENVIAR = ['por_enviar', 'pendiente', 'excepcion'];

    /**
     * Factura y sus notas (serie F...): 3 días calendario contados desde el
     * día siguiente a la emisión. Boleta y sus notas (serie B...): 5 días
     * calendario contando el día de emisión (R.S. 000003-2023; SUNAT.md §1).
     */
    public function plazoLimiteDias(): int
    {
        return $this->esDeFactura() ? self::PLAZO_DIAS_FACTURA : self::PLAZO_DIAS_BOLETA;
    }

    /**
     * Último día en que SUNAT todavía recibe el comprobante.
     */
    public function fechaLimiteEnvio(): CarbonInterface
    {
        $emision = ($this->fecha_emision ?? $this->created_at ?? now())->copy()->startOfDay();

        return $emision->addDays($this->esDeFactura() ? self::PLAZO_DIAS_FACTURA : self::PLAZO_DIAS_BOLETA - 1);
    }

    /**
     * Días calendario que faltan: 0 = vence hoy, negativo = ya venció.
     */
    public function diasRestantesParaEnvio(): int
    {
        return (int) today()->diffInDays($this->fechaLimiteEnvio(), false);
    }

    /**
     * S8: aún no llega a SUNAT y vence hoy, mañana o ya venció.
     */
    public function estaPorVencerSunat(): bool
    {
        return in_array($this->sunat_estado, self::ESTADOS_SIN_ENVIAR, true) && $this->diasRestantesParaEnvio() <= 1;
    }

    /**
     * Sin enviar y con plazo que vence hoy o mañana.
     *
     * @param  Builder<self>  $query
     */
    public function scopePorVencerSunat(Builder $query): void
    {
        $query->whereIn('sunat_estado', self::ESTADOS_SIN_ENVIAR)
            ->whereRaw(self::SQL_FECHA_LIMITE.' BETWEEN ? AND ?', [today()->toDateString(), today()->addDay()->toDateString()]);
    }

    /**
     * Sin enviar y con el plazo ya vencido: SUNAT lo rechazaría.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVencidosSunat(Builder $query): void
    {
        $query->whereIn('sunat_estado', self::ESTADOS_SIN_ENVIAR)
            ->whereRaw(self::SQL_FECHA_LIMITE.' < ?', [today()->toDateString()]);
    }

    /**
     * Misma regla que fechaLimiteEnvio(), en SQL (MySQL).
     */
    protected const SQL_FECHA_LIMITE = "DATE_ADD(DATE(COALESCE(fecha_emision, created_at)), INTERVAL IF(tipo = 'factura' OR (tipo <> 'boleta' AND serie LIKE 'F%'), 3, 4) DAY)";

    protected function esDeFactura(): bool
    {
        // Las notas siguen al comprobante que afectan: su serie empieza con F o B.
        return $this->tipo === 'factura'
            || ($this->tipo !== 'boleta' && str_starts_with(strtoupper($this->serie), 'F'));
    }
}
