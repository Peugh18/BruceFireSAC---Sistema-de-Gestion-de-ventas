<?php

namespace App\Http\Requests\Gerente;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexInteligenciaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Gerente') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:200'],
            'categoria' => ['sometimes', 'in:todas,alta,media,baja'],
            'vigencia' => ['sometimes', 'in:todas,actualizados,desactualizados'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
