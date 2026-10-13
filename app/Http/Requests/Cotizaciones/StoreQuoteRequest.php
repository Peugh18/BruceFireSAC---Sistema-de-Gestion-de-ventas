<?php

namespace App\Http\Requests\Cotizaciones;

use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')->where('client_id', $this->input('client_id'))],
            'destino' => ['required', Rule::in(['local_cliente', 'vehiculo'])],
            'fecha' => ['required', 'date'],
            'vigencia_hasta' => ['required', 'date', 'after_or_equal:fecha'],
            'condicion_pago_propuesta' => ['required', Rule::in([
                'Contado',
                'Crédito 7 días',
                'Crédito 15 días',
                'Crédito 30 días',
            ])],
            'referencia' => ['nullable', 'string', 'max:150'],
            'observaciones' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.deficiency_id' => ['nullable', 'integer', 'exists:deficiencies,id'],
            'items.*.product_id' => ['nullable', 'required_without:items.*.service_id', 'integer', 'exists:products,id'],
            'items.*.service_id' => ['nullable', 'required_without:items.*.product_id', 'integer', 'exists:services,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:99999'],
            'items.*.precio_unitario' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999.99'],
            'items.*.descuento' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999.99'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('destino')) {
            $quote = $this->route('quote');
            $this->merge(['destino' => ($quote instanceof Quote ? $quote : new Quote($this->only(['vehicle_id', 'referencia'])))->destinoResuelto()]);
        }

        if ($this->input('destino') === 'local_cliente') {
            $this->merge(['vehicle_id' => null]);
        }

        if (! $this->filled('condicion_pago_propuesta')) {
            $this->merge(['condicion_pago_propuesta' => 'Contado']);
        }
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
            'destino.in' => 'Elige local o vehículo.',
            'vehicle_id.exists' => 'El vehículo elegido no es de este cliente.',
            'condicion_pago_propuesta.in' => 'Elige una condición de pago válida.',
        ];
    }
}
