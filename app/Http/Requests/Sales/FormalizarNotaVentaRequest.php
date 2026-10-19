<?php

namespace App\Http\Requests\Sales;

use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FormalizarNotaVentaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $sale = $this->route('sale');

        return $sale instanceof Sale && $this->user()?->can('sales.create') === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['comprobante_tipo' => ['required', Rule::in(['factura', 'boleta'])]];
    }
}
