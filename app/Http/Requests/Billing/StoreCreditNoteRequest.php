<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreditNoteRequest extends FormRequest
{
    /**
     * El permiso se revisa de verdad: sin billing.credit_note, 403.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('billing.credit_note');
    }

    /**
     * S9: Catalogo 09 SUNAT completo (01 al 13).
     * Los motivos 04, 05 y 08 son validos en facturas; IssueCreditNote los
     * bloquea sobre boletas (S2, guia NC linea 888).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'electronic_document_id' => ['required', 'integer', 'exists:electronic_documents,id'],
            'motivo_catalogo' => ['required', Rule::in(['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13'])],
            'detalle' => ['required', 'string'],
            'importe' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
