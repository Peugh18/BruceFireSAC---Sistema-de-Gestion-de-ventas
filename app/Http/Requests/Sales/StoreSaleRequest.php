<?php

namespace App\Http\Requests\Sales;

use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
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
            'quote_id' => ['nullable', 'integer', 'exists:quotes,id'],
            'service_order_id' => ['nullable', 'integer', 'exists:service_orders,id'],
            'fecha' => ['required', 'date'],
            'destino' => ['required', Rule::in(['local_cliente', 'vehiculo'])],
            'condicion_pago' => ['required', Rule::in(['contado', 'credito'])],
            'medio_pago' => ['required_if:condicion_pago,contado', 'nullable', Rule::in(array_keys(Sale::MEDIOS_PAGO))],
            'numero_operacion' => ['nullable', 'string', 'max:60'],
            'emitir' => ['sometimes', 'boolean'],
            'cuotas' => ['exclude_unless:condicion_pago,credito', 'required', 'array', 'min:1', 'max:36'],
            'cuotas.*.fecha_vencimiento' => ['exclude_unless:condicion_pago,credito', 'required', 'date', 'after:fecha'],
            'cuotas.*.monto' => ['exclude_unless:condicion_pago,credito', 'required', 'numeric', 'min:0.01'],
            'referencia' => ['nullable', 'string', 'max:150'],
            'comprobante_tipo' => ['required', Rule::in(['factura', 'boleta', 'nota_venta'])],
            'observaciones' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.tipo_linea' => ['required', Rule::in(['unidad_nueva', 'recarga_servicio', 'producto', 'servicio'])],
            'items.*.numero_serie' => ['required_if:items.*.tipo_linea,unidad_nueva,recarga_servicio', 'nullable', 'string'],
            'items.*.product_id' => ['required_if:items.*.tipo_linea,unidad_nueva,producto', 'nullable', 'integer', 'exists:products,id'],
            'items.*.service_id' => ['required_if:items.*.tipo_linea,recarga_servicio,servicio', 'nullable', 'integer', 'exists:services,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.precio_unitario' => ['required', 'numeric', 'gt:0'],
            'items.*.descuento' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Al contado sin medio elegido se asume efectivo (igual que la pantalla);
     * a crédito no hay medio: se cobra por cuotas.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('condicion_pago') === 'credito') {
            $this->merge(['medio_pago' => null, 'numero_operacion' => null]);
        } elseif (! $this->filled('medio_pago')) {
            $this->merge(['medio_pago' => 'efectivo']);
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.precio_unitario.gt' => 'El precio debe ser mayor a cero.',
            'medio_pago.required_if' => 'Elige cómo paga el cliente (efectivo, Yape, transferencia…).',
            'cuotas.required' => 'Define al menos una cuota para la venta a crédito.',
            'cuotas.min' => 'Define al menos una cuota para la venta a crédito.',
        ];
    }
}
