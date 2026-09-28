<?php

namespace App\Http\Requests\Almacen;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Almacen', 'Gerente']) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'destination_sede_id' => ['required', 'integer', Rule::exists('sedes', 'id')->whereIn('tipo', ['almacen', 'mixta'])->where('activo', true)],
            'serials' => ['nullable', 'array'],
            'serials.*' => ['string', 'distinct', 'max:100'],
            'product_id' => ['nullable', 'required_without:serials', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'required_with:product_id', 'integer', 'min:1'],
            'observation' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
