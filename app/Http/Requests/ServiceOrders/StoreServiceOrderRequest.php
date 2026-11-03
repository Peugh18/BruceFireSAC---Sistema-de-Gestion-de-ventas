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
        // D-10: la orden nace de una cotización aceptada; de ella salen el
        // cliente, el destino, los servicios y los extintores.
        return [
            'quote_id' => ['required', 'integer', 'exists:quotes,id'],
            'fecha' => ['required', 'date'],
            'tecnico_id' => ['nullable', 'integer', 'exists:users,id'],
            'departamento_tecnico' => ['nullable', Rule::in(['planta', 'campo'])],
            'prioridad' => ['nullable', Rule::in(['normal', 'alta', 'urgente'])],
            'observaciones' => ['nullable', 'string'],
        ];
    }
}
