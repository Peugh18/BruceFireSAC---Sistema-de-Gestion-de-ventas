<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * X8: se contactó al cliente por una alerta "por vencer" (no ofrecer dos veces).
 *
 * @property int $id
 * @property int $client_id
 * @property int|null $equipment_id
 * @property int|null $user_id
 * @property Carbon $fecha
 * @property string|null $nota
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable(['client_id', 'equipment_id', 'user_id', 'fecha', 'nota'])]
class AlertContact extends Model
{
    /** Un contacto cuenta para las alertas durante estos días. */
    public const DIAS_VIGENTE = 30;

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Último contacto vigente de cada cliente.
     *
     * @param  iterable<int, int>  $clientIds
     * @return Collection<int, array{fecha: string, usuario: string|null, nota: string|null}>
     */
    public static function ultimosPorCliente(iterable $clientIds): Collection
    {
        return self::query()
            ->with('user:id,name')
            ->whereIn('client_id', collect($clientIds)->unique()->values())
            ->whereDate('fecha', '>=', today()->subDays(self::DIAS_VIGENTE))
            ->orderBy('fecha')
            ->orderBy('id')
            ->get()
            ->keyBy('client_id')
            ->map(fn (AlertContact $contacto) => [
                'fecha' => $contacto->fecha->toDateString(),
                'usuario' => $contacto->user?->name,
                'nota' => $contacto->nota,
            ]);
    }
}
