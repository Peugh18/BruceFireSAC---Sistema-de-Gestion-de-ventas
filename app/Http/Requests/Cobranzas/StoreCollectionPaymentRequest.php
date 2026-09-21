<?php

namespace App\Http\Requests\Cobranzas;

use Illuminate\Foundation\Http\FormRequest;

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
}
