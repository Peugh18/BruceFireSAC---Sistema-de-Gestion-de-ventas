<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreditNoteRequest extends FormRequest
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
            'electronic_document_id' => ['required', 'integer', 'exists:electronic_documents,id'],
            'motivo_catalogo' => ['required', Rule::in(['01', '02', '03', '04', '05', '06', '07'])],
            'detalle' => ['required', 'string'],
            'importe' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
