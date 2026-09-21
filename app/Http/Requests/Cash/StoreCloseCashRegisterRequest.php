<?php

namespace App\Http\Requests\Cash;

use Illuminate\Foundation\Http\FormRequest;

class StoreCloseCashRegisterRequest extends FormRequest
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
            'monto_contado_cierre' => ['required', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string'],
        ];
    }
}
