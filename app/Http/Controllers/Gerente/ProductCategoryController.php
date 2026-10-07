<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gerente\SaveProductCategoryRequest;
use App\Models\ProductCategory;
use App\Models\Team;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Categorías del catálogo (A2): se crean, se renombran y se desactivan desde
 * el botón "Gestionar" del formulario de productos y servicios.
 */
class ProductCategoryController extends Controller
{
    public function store(SaveProductCategoryRequest $request, Team $current_team): RedirectResponse
    {
        $datos = $request->validated();

        $base = Str::slug($datos['nombre'], '_') ?: 'categoria';
        $clave = Str::limit($base, 34, '');
        for ($n = 2; ProductCategory::query()->where('clave', $clave)->exists(); $n++) {
            $clave = Str::limit($base, 34, '').'_'.$n;
        }

        $categoria = ProductCategory::create([
            'clave' => $clave,
            'nombre' => $datos['nombre'],
            'genera_alertas_vencimiento' => (bool) ($datos['genera_alertas_vencimiento'] ?? false),
            'activo' => true,
        ]);

        AuditLogger::log(
            action: 'categoria.creada',
            entity: $categoria,
            newValues: $categoria->only(['clave', 'nombre', 'genera_alertas_vencimiento']),
            userId: $request->user()?->id,
        );

        return back()->with('success', 'Categoría creada.');
    }

    public function update(SaveProductCategoryRequest $request, Team $current_team, ProductCategory $categoria): RedirectResponse
    {
        $antes = $categoria->only(['nombre', 'genera_alertas_vencimiento', 'activo']);
        $categoria->update($request->validated());

        AuditLogger::log(
            action: 'categoria.actualizada',
            entity: $categoria,
            oldValues: $antes,
            newValues: $categoria->only(array_keys($antes)),
            userId: $request->user()?->id,
        );

        return back()->with('success', 'Categoría actualizada.');
    }

    /**
     * Solo se borra una categoría que nadie usa; si la usan productos o
     * servicios, se desactiva.
     */
    public function destroy(Request $request, Team $current_team, ProductCategory $categoria): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Gerente'), 403);

        if ($categoria->usos() > 0) {
            throw ValidationException::withMessages([
                'categoria' => 'Esta categoría la usan productos o servicios: desactívala en vez de borrarla.',
            ]);
        }

        AuditLogger::log(
            action: 'categoria.eliminada',
            entity: $categoria,
            oldValues: $categoria->only(['clave', 'nombre']),
            userId: $request->user()?->id,
        );
        $categoria->delete();

        return back()->with('success', 'Categoría eliminada.');
    }
}
