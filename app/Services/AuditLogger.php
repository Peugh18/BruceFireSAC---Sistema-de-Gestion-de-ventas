<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Throwable;

class AuditLogger
{
    /**
     * Cantidad de fallos de auditoría acumulados en el proceso actual. La
     * auditoría nunca tumba una operación (§37), pero su fallo debe poder
     * verse: `fallosRecientes()` los expone y el log lleva la entidad.
     */
    private static int $fallos = 0;

    /**
     * @var list<array{action: string, entity: string|null}>
     */
    private static array $detalleDeFallos = [];

    /**
     * Registra un evento de auditoría de manera explícita (§37).
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $context
     */
    public static function log(
        string $action,
        ?Model $entity = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $context = null,
        ?int $userId = null
    ): ?AuditLog {
        try {
            $user = Auth::user();

            return AuditLog::create([
                'user_id' => $userId ?? $user?->id,
                'team_id' => $user?->current_team_id ?? (property_exists($entity ?? (object) [], 'team_id') ? $entity->team_id : null),
                'action' => $action,
                'auditable_type' => $entity ? get_class($entity) : null,
                'auditable_id' => $entity?->getKey(),
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'context' => $context,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // La auditoría nunca debe tumbar la operación que se audita, pero
            // el fallo queda a la vista: contador + log con la entidad afectada.
            $entidad = $entity ? get_class($entity).'#'.$entity->getKey() : null;

            self::$fallos++;
            self::$detalleDeFallos[] = ['action' => $action, 'entity' => $entidad];

            Log::error('Error registrando auditoría de '.($entidad ?? 'sin entidad').' ('.$action.'): '.$e->getMessage(), [
                'action' => $action,
                'entity' => $entidad,
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * ¿Hubo fallos de auditoría recientes (en este proceso)?
     */
    public static function huboFallos(): bool
    {
        return self::$fallos > 0;
    }

    /**
     * Cantidad de fallos de auditoría recientes (en este proceso).
     */
    public static function fallosRecientes(): int
    {
        return self::$fallos;
    }

    /**
     * Detalle de los fallos de auditoría recientes: acción y entidad afectada.
     *
     * @return list<array{action: string, entity: string|null}>
     */
    public static function detalleDeFallos(): array
    {
        return self::$detalleDeFallos;
    }

    /**
     * Reinicia el registro de fallos recientes (para pruebas y tareas).
     */
    public static function reiniciarFallos(): void
    {
        self::$fallos = 0;
        self::$detalleDeFallos = [];
    }
}
