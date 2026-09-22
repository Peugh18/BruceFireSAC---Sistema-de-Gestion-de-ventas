<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gerente\UpdateUserRoleRequest;
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
}
