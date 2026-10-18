<?php

namespace App\Http\Controllers\Gerente;

use App\Actions\Usuarios\CrearTrabajador;
use App\Actions\Usuarios\ValidarSedeDelRol;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gerente\StoreUserRequest;
use App\Http\Requests\Gerente\UpdateUserRoleRequest;
use App\Http\Requests\Gerente\UpdateUserSedeRequest;
use App\Models\Sede;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Listado de usuarios del team y matriz de permisos de solo lectura
     * (§86.4.8 y §36 — no builder visual de permisos granulares por usuario).
     */
    public function index(Team $current_team, Request $request): Response
    {
        $buscar = trim($request->string('buscar')->toString());
        $usuarios = $current_team->members()
            ->with(['roles:id,name', 'sede:id,nombre'])
            ->when($buscar !== '', fn ($query) => $query->where(function ($query) use ($buscar): void {
                $query->where('name', 'like', '%'.$buscar.'%')
                    ->orWhere('email', 'like', '%'.$buscar.'%');
            }))
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name,
                'sede_id' => $user->sede_id,
                'activo' => (bool) ($user->getAttribute('activo') ?? true),
                'sede_nombre' => $user->sede?->nombre,
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
            'filters' => ['buscar' => $buscar],
            'roles' => RolesAndPermissionsSeeder::BUSINESS_ROLES,
            'sedes' => Sede::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'tipo']),
            'tiposDeSedePorRol' => User::TIPOS_DE_SEDE_POR_ROL,
            'matrizPermisos' => $matrizPermisos,
        ]);
    }

    /**
     * Da de alta a un trabajador nuevo con su rol, su sede y una contraseña
     * inicial que el Gerente le entrega (luego la cambia en su perfil).
     */
    public function store(StoreUserRequest $request, Team $current_team, CrearTrabajador $crearTrabajador): RedirectResponse
    {
        $user = $crearTrabajador->handle(
            $current_team,
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('role')->toString(),
            $request->filled('sede_id') ? $request->integer('sede_id') : null,
            $request->string('password')->toString(),
            $request->user()->id,
        );

        return redirect()
            ->route('gerente.usuarios.index', ['current_team' => $current_team])
            ->with('success', "{$user->name} ya puede entrar con {$user->email}.");
    }

    /**
     * Asigna o quita uno de los 5 roles de negocio fijos a un usuario del team.
     */
    public function updateRole(UpdateUserRoleRequest $request, Team $current_team, User $user): RedirectResponse
    {
        abort_unless($current_team->members()->whereKey($user->id)->exists(), 404);
        $nuevoRol = $request->validated('role');
        $rolAnterior = $user->roles->first()?->name;

        // Un Gerente no se quita su propio rol: se quedaría fuera de esta
        // pantalla y la empresa podría quedar sin nadie que la administre.
        if ($rolAnterior === 'Gerente' && $nuevoRol !== 'Gerente' && $user->is($request->user())) {
            throw ValidationException::withMessages(['role' => 'No puedes quitarte tu propio rol de Gerente. Pídeselo a otro Gerente.']);
        }

        // Si ya tiene sede, el nuevo rol tiene que poder trabajar ahí.
        if ($user->sede) {
            app(ValidarSedeDelRol::class)->handle($nuevoRol, $user->sede, 'role');
        }

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
     * Asigna la única sede del trabajador, según lo que permite su rol
     * (solo el Gerente puede quedar sin sede).
     */
    public function updateSede(UpdateUserSedeRequest $request, Team $current_team, User $user): RedirectResponse
    {
        abort_unless($current_team->members()->whereKey($user->id)->exists(), 404);
        $sedeAnterior = $user->sede_id;
        $sede = $request->validated('sede_id') ? Sede::find($request->validated('sede_id')) : null;
        app(ValidarSedeDelRol::class)->handle($user->roles->first()?->name, $sede);

        $user->update(['sede_id' => $sede?->id]);

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

    public function update(Request $request, Team $current_team, User $user): RedirectResponse
    {
        abort_unless($current_team->members()->whereKey($user->id)->exists(), 404);
        $request->merge([
            'name' => trim($request->string('name')->toString()),
            'email' => Str::lower(trim($request->string('email')->toString())),
        ]);
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
        ], [
            'name.required' => 'Escribe el nombre del trabajador.',
            'email.required' => 'Escribe su correo electrónico.',
            'email.email' => 'Escribe un correo electrónico válido.',
            'email.unique' => 'Este correo ya pertenece a otra cuenta.',
        ]);

        DB::transaction(function () use ($user, $datos, $request): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $antes = $user->only(['name', 'email']);
            $user->fill($datos);
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            $user->save();
            AuditLogger::log(
                action: 'usuario.actualizado', entity: $user,
                oldValues: $antes, newValues: $user->only(['name', 'email']),
                userId: $request->user()->id,
            );
        });

        return back()->with('success', 'Datos del trabajador actualizados.');
    }

    public function updateStatus(Request $request, Team $current_team, User $user): RedirectResponse
    {
        abort_unless($current_team->members()->whereKey($user->id)->exists(), 404);
        $datos = $request->validate(['activo' => ['required', 'boolean']]);
        $activo = (bool) $datos['activo'];
        if (! $activo && $user->is($request->user())) {
            throw ValidationException::withMessages(['activo' => 'No puedes desactivar tu propia cuenta.']);
        }

        DB::transaction(function () use ($user, $activo, $request): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $antes = (bool) ($user->getAttribute('activo') ?? true);
            if ($antes === $activo) {
                return;
            }
            $user->forceFill(['activo' => $activo]);
            if (! $activo) {
                $user->setRememberToken(Str::random(60));
            }
            $user->save();
            AuditLogger::log(
                action: $activo ? 'usuario.activado' : 'usuario.desactivado', entity: $user,
                oldValues: ['activo' => $antes], newValues: ['activo' => $activo],
                userId: $request->user()->id,
            );
        });

        return back()->with('success', $activo ? 'Cuenta activada.' : 'Cuenta desactivada. Ya no puede entrar al sistema.');
    }
}
