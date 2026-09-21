<?php

namespace App\Http\Requests\Gerente;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyBankAccountRequest extends FormRequest
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
            'banco' => ['required', 'string', 'max:255'],
            'titular' => ['required', 'string', 'max:255'],
            'numero_cuenta' => ['required', 'string', 'max:50'],
            'cci' => ['nullable', 'string', 'max:50'],
            'moneda' => ['required', 'string', 'in:PEN,USD'],
            'activo' => ['required', 'boolean'],
        ];
    }
}
