<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDebitNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->can('billing.credit_note') || $user->hasRole(['Gerente', 'Vendedor']));
    }

    /**
     * Catalogo 10 SUNAT actualizado por R.S. 000048-2026 (vigente 1/08/2026).
     * 01 = intereses por mora, 02 = aumento en el valor, 03 = otros conceptos,
     * 11 = ajustes exportacion, 12 = ajustes IVAP, 13 = penalidades (inafecto).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'electronic_document_id' => ['required', 'integer', 'exists:electronic_documents,id'],
            'motivo_catalogo' => ['required', Rule::in(['01', '02', '03', '11', '12', '13'])],
            'detalle' => ['required', 'string'],
            'importe' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
