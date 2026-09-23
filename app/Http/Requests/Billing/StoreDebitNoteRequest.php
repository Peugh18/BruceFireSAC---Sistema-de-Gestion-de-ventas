<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDebitNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Motivos del Catálogo 10 SUNAT: intereses por mora, aumento en el valor y penalidades.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'electronic_document_id' => ['required', 'integer', 'exists:electronic_documents,id'],
            'motivo_catalogo' => ['required', Rule::in(['01', '02', '03'])],
            'detalle' => ['required', 'string'],
            'importe' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
