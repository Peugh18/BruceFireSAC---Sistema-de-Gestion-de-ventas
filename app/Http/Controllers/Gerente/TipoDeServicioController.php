<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TipoDeServicio;
use App\Support\CatalogosSunat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * El gerente agrega, cambia o quita los tipos de servicio. Cada tipo define el
 * certificado, la detracción y el código SUNAT de sus servicios.
 */
class TipoDeServicioController extends Controller
{
    public function store(Request $request, Team $current_team): RedirectResponse
    {
        TipoDeServicio::create($this->validar($request));

        return back()->with('success', 'Tipo de servicio agregado.');
    }

    public function update(Request $request, Team $current_team, TipoDeServicio $tipo_de_servicio): RedirectResponse
    {
        $tipo_de_servicio->update($this->validar($request, $tipo_de_servicio));

        return back()->with('success', 'Tipo de servicio actualizado.');
    }

    /** Con servicios se desactiva (conserva su historial); sin servicios se borra. */
    public function destroy(Team $current_team, TipoDeServicio $tipo_de_servicio): RedirectResponse
    {
        if ($tipo_de_servicio->services()->exists()) {
            $tipo_de_servicio->update(['activo' => false]);

            return back()->with('success', 'El tipo tiene servicios: quedó desactivado.');
        }

        $tipo_de_servicio->delete();

        return back()->with('success', 'Tipo de servicio eliminado.');
    }

    /**
     * La clasificación de detracción se confirma a propósito: sin estado
     * "sujeto", el tipo queda por revisar aunque traiga un código.
     *
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?TipoDeServicio $tipo = null): array
    {
        $request->mergeIfMissing(['estado_detraccion' => 'pendiente']);

        $datos = $request->validate([
            'estado_detraccion' => ['required', Rule::in(['no_sujeto', 'pendiente', 'sujeto'])],
            'nombre' => ['required', 'string', 'max:120', Rule::unique('tipos_de_servicio', 'nombre')->ignore($tipo)],
            'codigo_detraccion' => ['nullable', 'required_if:estado_detraccion,sujeto', Rule::in(CatalogosSunat::codigosDeDetraccion())],
            'certificate_type_id' => ['nullable', 'integer', 'exists:certificate_types,id'],
            'codigo_sunat' => CatalogosSunat::reglaCodigoProducto(),
            'pide_extintor' => ['boolean'],
            'atencion' => ['nullable', Rule::in(['recarga', 'mantenimiento'])],
            'activo' => ['boolean'],
        ], [
            'nombre.unique' => 'Ya existe un tipo de servicio con ese nombre.',
            'codigo_sunat.digits' => 'El código SUNAT tiene 8 números.',
            'codigo_sunat.exists' => 'Ese código no está en el catálogo de SUNAT. Búscalo por su nombre.',
            'codigo_detraccion.required_if' => 'Elige el código de detracción del catálogo de SUNAT.',
            'codigo_detraccion.in' => 'Ese código no está en el catálogo de SUNAT o no tiene porcentaje vigente.',
        ]);

        if ($datos['estado_detraccion'] === 'no_sujeto') {
            $datos['codigo_detraccion'] = null;
        }

        return $datos;
    }
}
