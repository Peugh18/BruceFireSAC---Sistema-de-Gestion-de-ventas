<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gerente\StoreSedeRequest;
use App\Http\Requests\Gerente\UpdateSedeRequest;
use App\Models\Sede;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SedeController extends Controller
{
    /**
     * Listado y gestión de sedes: tiendas, almacenes y sedes mixtas.
     */
    public function index(Team $current_team): Response
    {
        $sedes = Sede::query()
            ->with(['almacen:id,nombre', 'tiendas:id,nombre,almacen_id', 'usuarios' => fn ($query) => $query->with('roles:id,name')->orderBy('name')])
            ->withCount('usuarios')
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Sede $sede) => [
                'id' => $sede->id,
                'nombre' => $sede->nombre,
                'tipo' => $sede->tipo,
                'ciudad' => $sede->ciudad,
                'ubigeo' => $sede->ubigeo,
                'almacen_id' => $sede->almacen_id,
                'almacen_nombre' => $sede->almacen?->nombre,
                'usuarios_count' => $sede->usuarios_count,
                'tiendas' => $sede->tiendas->pluck('nombre')->values(),
                'trabajadores' => $sede->usuarios->map(fn (User $usuario) => [
                    'nombre' => $usuario->name,
                    'rol' => $usuario->roles->first()?->name,
                ])->values(),
                'activo' => $sede->activo,
            ]);

        return Inertia::render('gerente/sedes/index', [
            'sedes' => $sedes,
            'almacenes' => Sede::query()
                ->whereIn('tipo', ['almacen', 'mixta'])
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'kpis' => [
                'total' => $sedes->count(),
                'activas' => $sedes->where('activo', true)->count(),
            ],
        ]);
    }

    public function store(StoreSedeRequest $request, Team $current_team): RedirectResponse
    {
        $sede = Sede::create($request->validated());

        AuditLogger::log(
            action: 'sede.creada',
            entity: $sede,
            oldValues: [],
            newValues: $sede->only(['nombre', 'tipo', 'ciudad', 'almacen_id']),
            userId: $request->user()->id
        );

        return redirect()->route('gerente.sedes.index', ['current_team' => $current_team])
            ->with('success', 'Sede creada exitosamente.');
    }

    public function update(UpdateSedeRequest $request, Team $current_team, Sede $sede): RedirectResponse
    {
        $anterior = $sede->only(['nombre', 'tipo', 'ciudad', 'almacen_id', 'activo']);
        $this->validarCambioDeTipo($sede, $request->validated('tipo'));

        $sede->update($request->validated());

        AuditLogger::log(
            action: 'sede.actualizada',
            entity: $sede,
            oldValues: $anterior,
            newValues: $sede->only(['nombre', 'tipo', 'ciudad', 'almacen_id', 'activo']),
            userId: $request->user()->id
        );

        return redirect()->route('gerente.sedes.index', ['current_team' => $current_team])
            ->with('success', 'Sede actualizada exitosamente.');
    }

    /**
     * Cambiar el tipo no puede dejar tiendas sin almacén ni trabajadores en
     * una sede donde su rol no puede trabajar (por ejemplo, un técnico en
     * una tienda sin taller).
     */
    protected function validarCambioDeTipo(Sede $sede, string $nuevoTipo): void
    {
        if ($nuevoTipo === $sede->tipo) {
            return;
        }

        if ($nuevoTipo === 'tienda' && $sede->tiendas()->exists()) {
            $tiendas = $sede->tiendas()->pluck('nombre')->implode(', ');

            throw ValidationException::withMessages([
                'tipo' => "{$sede->nombre} abastece a {$tiendas}: conéctalas primero a otro almacén.",
            ]);
        }

        $noPuedenQuedarse = $sede->usuarios()->with('roles:id,name')->get()
            ->filter(function (User $usuario) use ($nuevoTipo) {
                $tipos = User::tiposDeSedePara($usuario->roles->first()?->name);

                return $tipos !== null && ! in_array($nuevoTipo, $tipos, true);
            });

        if ($noPuedenQuedarse->isNotEmpty()) {
            throw ValidationException::withMessages([
                'tipo' => 'Primero cambia de sede a: '.$noPuedenQuedarse->pluck('name')->implode(', ').'. Su rol no puede trabajar en ese tipo de sede.',
            ]);
        }
    }

    /**
     * Las sedes nunca se eliminan: se desactivan si nada operativo depende de ellas.
     */
    public function toggleStatus(Request $request, Team $current_team, Sede $sede): RedirectResponse
    {
        if ($sede->activo) {
            if ($sede->tiendas()->where('activo', true)->exists()) {
                return redirect()->back()
                    ->with('error', 'No se puede desactivar la sede porque tiene tiendas activas que sacan stock de ella.');
            }

            if ($sede->usuarios()->exists()) {
                return redirect()->back()
                    ->with('error', 'No se puede desactivar la sede porque tiene trabajadores asignados. Reasígnelos primero.');
            }
        }

        $sede->update(['activo' => ! $sede->activo]);

        AuditLogger::log(
            action: $sede->activo ? 'sede.activada' : 'sede.desactivada',
            entity: $sede,
            oldValues: ['activo' => ! $sede->activo],
            newValues: ['activo' => $sede->activo],
            userId: $request->user()->id
        );

        return redirect()->back()->with('success', $sede->activo ? 'Sede activada exitosamente.' : 'Sede desactivada exitosamente.');
    }
}
