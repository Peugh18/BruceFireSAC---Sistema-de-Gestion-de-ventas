<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Deja en auditoría el alta, cada edición (solo los campos que cambiaron, con
 * su valor anterior y nuevo) y el activar o desactivar de un producto o
 * servicio, venga de la pantalla que venga. El modelo define AUDITORIA_PREFIJO.
 */
trait AuditaElCatalogo
{
    /** Campos que no aportan a la auditoría. */
    private const CAMPOS_IGNORADOS = ['updated_at', 'created_at', 'igv_revisado_at'];

    public static function bootAuditaElCatalogo(): void
    {
        static::created(function (Model $model): void {
            AuditLogger::log(
                action: static::AUDITORIA_PREFIJO.'.creado',
                entity: $model,
                newValues: ['codigo' => $model->getAttribute('codigo'), 'nombre' => $model->getAttribute('nombre')],
            );
        });

        static::updated(function (Model $model): void {
            $cambios = array_diff_key($model->getChanges(), array_flip(self::CAMPOS_IGNORADOS));
            if ($cambios === []) {
                return;
            }

            $antes = array_intersect_key($model->getRawOriginal(), $cambios);
            $accion = array_key_exists('activo', $cambios)
                ? ($model->getAttribute('activo') ? 'activado' : 'desactivado')
                : 'actualizado';

            AuditLogger::log(
                action: static::AUDITORIA_PREFIJO.'.'.$accion,
                entity: $model,
                oldValues: $antes,
                newValues: $cambios,
            );
        });
    }
}
