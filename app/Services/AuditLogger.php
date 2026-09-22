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
            Log::error('Error registrando auditoría: '.$e->getMessage(), [
                'action' => $action,
                'exception' => $e,
            ]);

            return null;
        }
    }
}
