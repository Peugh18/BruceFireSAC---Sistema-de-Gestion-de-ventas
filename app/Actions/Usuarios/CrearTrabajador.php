<?php

namespace App\Actions\Usuarios;

use App\Enums\TeamRole;
use App\Models\Sede;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * El Gerente da de alta a un trabajador: queda con su rol, su sede (según lo
 * que permite el rol) y dentro de la empresa, listo para entrar con la
 * contraseña inicial que le entregan.
 */
class CrearTrabajador
{
    public function __construct(protected ValidarSedeDelRol $validarSede) {}

    public function handle(Team $team, string $nombre, string $correo, string $rol, ?int $sedeId, string $clave, ?int $creadoPor = null): User
    {
        $sede = $sedeId ? Sede::find($sedeId) : null;
        $this->validarSede->handle($rol, $sede);

        return DB::transaction(function () use ($team, $nombre, $correo, $rol, $clave, $sede, $creadoPor) {
            $user = User::create([
                'name' => $nombre,
                'email' => mb_strtolower($correo),
                'password' => Hash::make($clave),
                'current_team_id' => $team->id,
                'sede_id' => $sede?->id,
            ]);

            $user->syncRoles([$rol]);
            $team->members()->attach($user, ['role' => TeamRole::Member->value]);

            AuditLogger::log(
                action: 'usuario.creado',
                entity: $user,
                newValues: ['email' => $user->email, 'role' => $rol, 'sede_id' => $user->sede_id],
                userId: $creadoPor,
            );

            return $user;
        });
    }
}
