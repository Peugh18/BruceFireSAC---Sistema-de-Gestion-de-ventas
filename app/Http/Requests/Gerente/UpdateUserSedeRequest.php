<?php

namespace App\Http\Requests\Gerente;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserSedeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Gerente') ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'sede_id' => ['nullable', Rule::exists('sedes', 'id')->where('activo', true)],
        ];
    }
}
