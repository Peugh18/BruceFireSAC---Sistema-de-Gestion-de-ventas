<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorregirComprobanteRequest extends FormRequest
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
            'comprobante_tipo' => ['required', Rule::in(['factura', 'boleta'])],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
        ];
    }
}
