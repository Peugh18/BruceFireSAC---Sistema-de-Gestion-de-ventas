<?php

namespace App\Actions\Usuarios;

use App\Models\Sede;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Revisa que la sede sirva para el rol: un vendedor en una tienda o mixta;
 * almacén y técnicos donde hay stock y taller. Solo el Gerente puede quedar
 * sin sede.
 */
class ValidarSedeDelRol
{
    private const NOMBRE_ROL = [
        'Vendedor' => 'Un vendedor',
        'Almacen' => 'El personal de almacén',
        'TecnicoPlanta' => 'Un técnico de planta',
        'TecnicoCampo' => 'Un técnico de campo',
    ];

    private const NOMBRE_TIPO = [
        'tienda' => 'una tienda',
        'almacen' => 'un almacén',
        'mixta' => 'una sede mixta',
    ];

    public function handle(?string $rol, ?Sede $sede, string $campo = 'sede_id'): void
    {
        $tipos = User::tiposDeSedePara($rol);

        if ($tipos === null) {
            return;
        }

        $quien = self::NOMBRE_ROL[$rol] ?? 'Este trabajador';

        if (! $sede) {
            throw ValidationException::withMessages([$campo => "{$quien} necesita una sede para trabajar."]);
        }

        if (! in_array($sede->tipo, $tipos, true)) {
            $permitidos = collect($tipos)->map(fn (string $tipo) => self::NOMBRE_TIPO[$tipo])->implode(' o ');
            $es = self::NOMBRE_TIPO[$sede->tipo] ?? $sede->tipo;

            throw ValidationException::withMessages([$campo => "{$quien} trabaja en {$permitidos}; {$sede->nombre} es {$es}."]);
        }
    }
}
