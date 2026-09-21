<?php

namespace App\Http\Requests\Sales;

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
            'fecha' => ['required', 'date'],
            'destino' => ['required', Rule::in(['local_cliente', 'vehiculo'])],
            'condicion_pago' => ['required', Rule::in(['contado', 'credito_30'])],
            'comprobante_tipo' => ['required', Rule::in(['factura', 'boleta'])],
            'observaciones' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.tipo_linea' => ['required', Rule::in(['unidad_nueva', 'recarga_servicio'])],
            'items.*.numero_serie' => ['required', 'string'],
            'items.*.catalog_item_id' => ['required', 'integer', 'exists:catalog_items,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.descuento' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
