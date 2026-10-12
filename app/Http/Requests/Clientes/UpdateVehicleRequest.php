<?php

namespace App\Http\Requests\Clientes;

use App\Models\Vehicle;
use App\Rules\PlacaPeru;
use App\Support\DatosPeru;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'placa' => DatosPeru::placa($this->input('placa')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $vehicle = $this->route('vehicle');

        return [
            'placa' => [
                'required',
                'string',
                'max:10',
                new PlacaPeru,
                Rule::unique('vehicles', 'placa')->ignore($vehicle instanceof Vehicle ? $vehicle->id : null),
            ],
            'marca' => ['nullable', 'string', 'max:255'],
            'modelo' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'estado' => ['sometimes', Rule::in(['activo', 'inactivo'])],
        ];
    }
}
