<?php

namespace App\Http\Requests\Cotizaciones;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteRequest extends FormRequest
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
            'fecha' => ['required', 'date'],
            'vigencia_hasta' => ['required', 'date', 'after_or_equal:fecha'],
            'condicion_pago_propuesta' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.catalog_item_id' => ['required', 'integer', 'exists:catalog_items,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.descuento' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
