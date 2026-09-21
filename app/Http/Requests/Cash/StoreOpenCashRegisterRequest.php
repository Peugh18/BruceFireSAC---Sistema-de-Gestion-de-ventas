<?php

namespace App\Http\Requests\Cash;

use Illuminate\Foundation\Http\FormRequest;

class StoreOpenCashRegisterRequest extends FormRequest
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
            'sede_id' => ['nullable', 'integer', 'exists:sedes,id'],
            'monto_apertura' => ['required', 'numeric', 'min:0'],
        ];
    }
}
