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
            'referencia' => ['nullable', 'string', 'max:150'],
            'observaciones' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'required_without:items.*.service_id', 'integer', 'exists:products,id'],
            'items.*.service_id' => ['nullable', 'required_without:items.*.product_id', 'integer', 'exists:services,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.precio_unitario' => ['required', 'numeric', 'gt:0'],
            'items.*.descuento' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ($this->input('items', []) as $index => $item) {
                $importe = (float) ($item['cantidad'] ?? 0) * (float) ($item['precio_unitario'] ?? 0);

                if ((float) ($item['descuento'] ?? 0) > $importe) {
                    $validator->errors()->add("items.{$index}.descuento", 'El descuento no puede superar el importe de la línea.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.*.precio_unitario.gt' => 'El precio debe ser mayor a cero.',
        ];
    }
}
