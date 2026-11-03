<?php

namespace App\Http\Requests\Cash;

use App\Models\CashRegister;
use App\Models\CashRegisterCount;
use Illuminate\Foundation\Http\FormRequest;

class StoreCloseCashRegisterRequest extends FormRequest
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
        // Con el cajón ya contado solo falta la explicación: el conteo no se vuelve a enviar.
        $caja = $this->route('cash_register');
        $yaContado = $caja instanceof CashRegister && $caja->conteoVigente() !== null;

        return [
            // El cajón se cuenta por billetes y monedas; el total suelto queda para integraciones.
            'denominaciones' => [$yaContado ? 'nullable' : 'required_without:monto_contado_cierre', 'array:'.implode(',', CashRegisterCount::DENOMINACIONES)],
            'denominaciones.*' => ['integer', 'min:0', 'max:100000'],
            'monto_contado_cierre' => [$yaContado ? 'nullable' : 'required_without:denominaciones', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999.99'],
            'observacion' => [$yaContado ? 'required' : 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'denominaciones.required_without' => 'Cuenta los billetes y monedas del cajón.',
            'denominaciones.array' => 'Solo se cuentan billetes y monedas en soles.',
            'denominaciones.*.integer' => 'Escribe cuántos hay, en números enteros.',
            'denominaciones.*.min' => 'La cantidad no puede ser negativa.',
            'observacion.required' => 'Explica qué pasó para cerrar el turno.',
        ];
    }
}
