<?php

namespace App\Http\Requests\Cobranzas;

use App\Models\Installment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCollectionPaymentRequest extends FormRequest
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
            'monto' => ['required', 'numeric', 'min:0.01'],
            'forma_pago' => ['required', 'string', 'in:efectivo,transferencia,yape,plin,pos,deposito,otro'],
            'numero_operacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('monto')) {
                return;
            }

            $installment = $this->route('installment');

            if (! $installment instanceof Installment) {
                return;
            }

            $saldo = max(0, round((float) $installment->monto - (float) $installment->payments()->sum('monto'), 2));

            if ((float) $this->input('monto') > $saldo) {
                $validator->errors()->add('monto', 'El monto no puede superar el saldo de S/ '.number_format($saldo, 2).'.');
            }
        }];
    }
}
