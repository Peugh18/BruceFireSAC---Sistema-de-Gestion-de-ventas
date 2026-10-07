<?php

namespace App\Http\Requests\Gerente;

use App\Models\Sede;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSedeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Gerente') ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $sede = $this->route('sede');
        $sedeId = $sede instanceof Sede ? $sede->id : null;

        return [
            'nombre' => ['required', 'string', 'max:255', Rule::unique('sedes', 'nombre')->ignore($sedeId)],
            'tipo' => ['required', Rule::in(['tienda', 'almacen', 'mixta'])],
            'ubigeo' => ['nullable', 'string', 'size:6', 'exists:ubigeos,codigo'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'cod_establecimiento_anexo' => ['sometimes', 'required', 'string', 'regex:/^\d{4}$/'],
            'almacen_id' => [
                Rule::requiredIf(fn () => $this->input('tipo') === 'tienda'),
                'nullable',
                Rule::exists('sedes', 'id')->whereIn('tipo', ['almacen', 'mixta'])->where('activo', true),
                Rule::notIn([$sedeId ?? 0]),
            ],
            'activo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'almacen_id.required' => 'Una tienda debe indicar de qué almacén saca stock.',
            'almacen_id.exists' => 'El almacén elegido debe ser una sede activa de tipo almacén o mixta.',
            'almacen_id.not_in' => 'Una sede no puede ser su propio almacén.',
            'nombre.unique' => 'Ya existe una sede con ese nombre.',
        ];
    }

    /**
     * Solo las tiendas dependen de un almacén.
     *
     * @param  array-key|null  $key
     */
    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated();

        if (($data['tipo'] ?? null) !== 'tienda') {
            $data['almacen_id'] = null;
        }

        return $key === null ? $data : data_get($data, $key, $default);
    }
}
