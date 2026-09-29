<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Un entrenamiento del modelo de recompra y su precisión en la prueba.
 *
 * @property int $id
 * @property Carbon $entrenado_at
 * @property string $modelo
 * @property string $roc_auc
 * @property string $accuracy
 * @property string $precision
 * @property string $recall
 * @property int|null $top_100_volvieron
 * @property int $muestras
 * @property int|null $clientes
 * @property list<string> $cortes
 */
class MlEntrenamiento extends Model
{
    protected $table = 'ml_entrenamientos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'entrenado_at' => 'datetime',
            'roc_auc' => 'decimal:4',
            'accuracy' => 'decimal:4',
            'precision' => 'decimal:4',
            'recall' => 'decimal:4',
            'cortes' => 'array',
        ];
    }

    /**
     * Guarda el entrenamiento a partir del JSON que exporta el script de
     * Python (storage/app/ml/retention_model.json).
     *
     * @param  array<string, mixed>  $modelo
     */
    public static function registrar(array $modelo): self
    {
        $prueba = (array) ($modelo['metrics']['test'] ?? []);
        $resumen = (array) ($modelo['dataset_summary'] ?? []);

        return self::create([
            'entrenado_at' => isset($modelo['trained_at']) ? Carbon::parse((string) $modelo['trained_at']) : now(),
            'modelo' => (string) ($modelo['type'] ?? 'logistic'),
            'roc_auc' => (float) ($prueba['roc_auc'] ?? 0),
            'accuracy' => (float) ($prueba['accuracy'] ?? 0),
            'precision' => (float) ($prueba['precision'] ?? 0),
            'recall' => (float) ($prueba['recall'] ?? 0),
            'top_100_volvieron' => isset($prueba['top_100_volvieron']) ? (int) $prueba['top_100_volvieron'] : null,
            'muestras' => (int) ($resumen['total_samples'] ?? 0),
            'clientes' => isset($resumen['clientes']) ? (int) $resumen['clientes'] : null,
            'cortes' => array_values((array) ($modelo['cortes'] ?? [])),
        ]);
    }
}
