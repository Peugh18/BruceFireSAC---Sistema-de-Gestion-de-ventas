<?php

namespace App\Http\Requests\Gerente;

use App\Support\DatosPeru;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_cuenta' => DatosPeru::digitos($this->input('numero_cuenta')),
            'cci' => DatosPeru::digitos($this->input('cci')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'banco' => ['required', 'string', 'max:255'],
            'titular' => ['required', 'string', 'max:255'],
            'numero_cuenta' => ['required', 'regex:/^\d{8,20}$/'],
            'cci' => ['nullable', 'digits:20'],
            'moneda' => ['required', 'string', 'in:PEN,USD'],
            'activo' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero_cuenta.regex' => 'El número de cuenta tiene solo números (entre 8 y 20).',
            'cci.digits' => 'El CCI tiene 20 números.',
        ];
    }
}
