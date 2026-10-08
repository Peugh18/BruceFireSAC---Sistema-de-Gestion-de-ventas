<?php

namespace App\Http\Requests\Sales;

use App\Actions\Sales\ProcessSaleItem;
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
            // La fecha de la venta es de hoy o de hasta dos días atrás (SUNAT
            // recibe el comprobante dentro de los 3 días): nunca de mañana.
            // La regla vale al crear y al actualizar (B3); al actualizar solo
            // se respeta la fecha que la venta ya tenía, para poder seguir
            // editando una venta vieja sin mover su fecha.
            'fecha' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$this->fechaMinima()],
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
            'iniciado_at' => ['nullable', 'date'],

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
     * Fecha más antigua aceptada: la ventana de los 2 días de SUNAT, o la
     * fecha que la venta ya tenía si es más vieja (una venta antigua se puede
     * seguir editando sin que el formulario la rechace).
     */
    protected function fechaMinima(): string
    {
        $limite = today()->subDays(2);
        $venta = $this->route('sale');

        if ($venta instanceof Sale && $venta->fecha->lt($limite)) {
            return $venta->fecha->toDateString();
        }

        return $limite->toDateString();
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
                // V1: cada serie es una unidad; la cantidad sale de las series.
                if (in_array($item['tipo_linea'] ?? null, ProcessSaleItem::LINEAS_POR_SERIE, true) && (int) ($item['cantidad'] ?? 0) !== 1) {
                    $validator->errors()->add("items.{$index}.cantidad", ProcessSaleItem::MENSAJE_CANTIDAD_POR_SERIE);
                }

                $importe = (float) ($item['cantidad'] ?? 0) * (float) ($item['precio_unitario'] ?? 0);

                // Ninguna línea puede quedar en S/ 0.00: un regalo se registra
                // con su precio real y su descuento parcial, nunca total.
                if (round((float) ($item['descuento'] ?? 0), 2) >= round($importe, 2)) {
                    $validator->errors()->add("items.{$index}.descuento", 'El descuento debe ser menor que el importe de la línea: ninguna línea puede quedar en S/ 0.00.');
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
            'fecha.before_or_equal' => 'La fecha de la venta no puede ser de mañana.',
            'fecha.after_or_equal' => 'La venta puede ser de hoy o de hasta dos días atrás.',
            'medio_pago.required_if' => 'Elige cómo paga el cliente (efectivo, Yape, transferencia…).',
            'cuotas.required' => 'Define al menos una cuota para la venta a crédito.',
            'cuotas.min' => 'Define al menos una cuota para la venta a crédito.',
        ];
    }
}
