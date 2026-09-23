<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gerente\UpdateUserRoleRequest;
use App\Http\Requests\Gerente\UpdateUserSedeRequest;
use App\Models\Sede;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Listado de usuarios del team y matriz de permisos de solo lectura
     * (§86.4.8 y §36 — no builder visual de permisos granulares por usuario).
     */
    public function index(Team $current_team): Response
    {
        $usuarios = $current_team->members()
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name,
                'sede_id' => $user->sede_id,
            ]);

        $matrizPermisos = collect([
            'Vendedor' => RolesAndPermissionsSeeder::VENDEDOR_PERMISSIONS,
            'Almacen' => RolesAndPermissionsSeeder::ALMACEN_PERMISSIONS,
            'Gerente' => RolesAndPermissionsSeeder::GERENTE_PERMISSIONS,
            'TecnicoPlanta' => RolesAndPermissionsSeeder::TECNICO_PLANTA_PERMISSIONS,
            'TecnicoCampo' => RolesAndPermissionsSeeder::TECNICO_CAMPO_PERMISSIONS,
        ]);

        return Inertia::render('gerente/usuarios/index', [
            'usuarios' => $usuarios,
            'roles' => RolesAndPermissionsSeeder::BUSINESS_ROLES,
            'sedes' => Sede::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'matrizPermisos' => $matrizPermisos,
        ]);
    }

    /**
     * Asigna o quita uno de los 5 roles de negocio fijos a un usuario del team.
     */
    public function updateRole(UpdateUserRoleRequest $request, Team $current_team, User $user): RedirectResponse
    {
        $nuevoRol = $request->validated('role');
        $rolAnterior = $user->roles->first()?->name;

        $user->syncRoles([$nuevoRol]);

        AuditLogger::log(
            action: 'usuario.rol_actualizado',
            entity: $user,
            oldValues: ['role' => $rolAnterior],
            newValues: ['role' => $nuevoRol],
            userId: $request->user()->id
        );

        return redirect()
            ->route('gerente.usuarios.index', ['current_team' => $current_team])
            ->with('success', "Rol de {$user->name} actualizado a {$nuevoRol}.");
    }

    /**
     * Asigna la única sede del trabajador. Sin sede ve todas las sedes.
     */
    public function updateSede(UpdateUserSedeRequest $request, Team $current_team, User $user): RedirectResponse
    {
        $sedeAnterior = $user->sede_id;

        $user->update(['sede_id' => $request->validated('sede_id')]);

        AuditLogger::log(
            action: 'usuario.sede_actualizada',
            entity: $user,
            oldValues: ['sede_id' => $sedeAnterior],
            newValues: ['sede_id' => $user->sede_id],
            userId: $request->user()->id
        );

        return redirect()
            ->route('gerente.usuarios.index', ['current_team' => $current_team])
            ->with('success', "Sede de {$user->name} actualizada.");
    }
}
