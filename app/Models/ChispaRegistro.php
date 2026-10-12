<?php

namespace App\Models;

use Database\Factories\ChispaRegistroFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una pregunta a Chispa y lo que respondió, ya sin datos sensibles. Sirve para
 * saber qué no supo responder y mejorar los manuales; se borra a los 90 días.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $rol
 * @property string|null $pagina
 * @property string $pregunta
 * @property string $respuesta
 * @property string $fuente gemini, groq, manual o sin_respuesta
 * @property bool|null $util
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
#[Fillable(['user_id', 'rol', 'pagina', 'pregunta', 'respuesta', 'fuente', 'util'])]
class ChispaRegistro extends Model
{
    /** @use HasFactory<ChispaRegistroFactory> */
    use HasFactory, Prunable;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['util' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(90));
    }
}
