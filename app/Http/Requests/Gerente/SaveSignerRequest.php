<?php

namespace App\Http\Requests\Gerente;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSignerRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:120'],
            'cargo' => ['required', 'string', 'max:120'],
            'cip' => ['nullable', 'string', 'max:20'],
            'activo' => ['sometimes', 'boolean'],
            'tipos' => ['sometimes', 'array'],
            'tipos.*' => ['string', Rule::exists('certificate_types', 'codigo')],
            'firma' => ['nullable', 'image', 'max:10240'],
            'sello' => ['nullable', 'image', 'max:10240'],
            'quitar_firma' => ['sometimes', 'boolean'],
            'quitar_sello' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'firma.image' => 'La firma debe ser una foto (JPG o PNG).',
            'sello.image' => 'El sello debe ser una foto (JPG o PNG).',
            'firma.max' => 'La foto de la firma pesa más de 10 MB.',
            'sello.max' => 'La foto del sello pesa más de 10 MB.',
        ];
    }
}
