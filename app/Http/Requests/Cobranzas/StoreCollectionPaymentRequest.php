<?php

namespace App\Http\Requests\Cobranzas;

use App\Models\Installment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCollectionPaymentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->boolean('es_detraccion')) {
            $this->merge(['numero_operacion' => strtoupper(preg_replace('/\s+/u', '', (string) $this->input('numero_operacion')) ?? '')]);
        }
    }

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
            'monto' => ['required', 'numeric', 'min:0.01', 'decimal:0,2', 'max:9999999.99'],
            'forma_pago' => ['required', 'string', 'in:efectivo,transferencia,yape,plin,pos,deposito,otro'],
            'es_detraccion' => ['sometimes', 'boolean'],
            'numero_operacion' => ['nullable', Rule::requiredIf($this->boolean('es_detraccion')), 'string', 'max:255'],
            'fecha_deposito' => [Rule::excludeIf(! $this->boolean('es_detraccion')), 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'evidencia' => [Rule::excludeIf(! $this->boolean('es_detraccion')), 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->boolean('es_detraccion') && $this->input('forma_pago') !== 'deposito') {
                $validator->errors()->add('forma_pago', 'La constancia de detracción corresponde a un depósito en el Banco de la Nación.');
            }
            if ($validator->errors()->has('monto')) {
                return;
            }

            $installment = $this->route('installment');

            if (! $installment instanceof Installment) {
                return;
            }

            // El saldo real resta lo que una nota de crédito ya acreditó:
            // sin eso se deja cobrar de más.
            $saldo = $installment->saldo();

            if ((float) $this->input('monto') > $saldo) {
                $validator->errors()->add('monto', 'El monto no puede superar el saldo de S/ '.number_format($saldo, 2).'.');
            }
        }];
    }
}
