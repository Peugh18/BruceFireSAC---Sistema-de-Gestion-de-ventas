<?php

namespace App\Http\Requests\ServiceOrders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'sede_id' => ['nullable', 'integer', 'exists:sedes,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'referencia' => ['nullable', 'string', 'max:150'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'fecha' => ['required', 'date'],
            'tecnico_id' => ['nullable', 'integer', 'exists:users,id'],
            'departamento_tecnico' => ['nullable', Rule::in(['planta', 'campo'])],
            'prioridad' => ['nullable', Rule::in(['normal', 'alta', 'urgente'])],
            'observaciones' => ['nullable', 'string'],
        ];
    }
}
