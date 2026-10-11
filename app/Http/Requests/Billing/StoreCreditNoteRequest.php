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
            'detalle' => ['required', 'string', 'max:500'],
            'nota_datos' => ['sometimes', 'array:lineas,devolucion_fisica'],
            'nota_datos.devolucion_fisica' => ['sometimes', 'boolean'],
            'nota_datos.lineas' => ['sometimes', 'array', 'min:1'],
            'nota_datos.lineas.*' => ['array:indice,descripcion,importe,cantidad'],
            'nota_datos.lineas.*.indice' => ['required', 'integer', 'min:0', 'distinct'],
            'nota_datos.lineas.*.descripcion' => ['sometimes', 'string', 'max:500'],
            'nota_datos.lineas.*.importe' => ['sometimes', 'numeric', 'min:0.01'],
            'nota_datos.lineas.*.cantidad' => ['sometimes', 'numeric', 'min:0.001'],
            'importe' => ['required', 'numeric', $this->input('motivo_catalogo') === '03' ? 'in:0' : 'min:0.01'],
        ];
    }
}
