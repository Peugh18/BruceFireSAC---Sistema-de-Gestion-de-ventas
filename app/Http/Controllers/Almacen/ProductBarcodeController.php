<?php

namespace App\Http\Controllers\Almacen;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Al recibir, Almacén escanea el código del fabricante y lo liga al producto
 * del catálogo. Así la próxima lectura lo reconoce sola. El gerente no lo
 * escribe.
 */
class ProductBarcodeController extends Controller
{
    public function __invoke(Request $request, Team $current_team, Product $producto): RedirectResponse
    {
        $datos = $request->validate([
            'codigo_barras' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('products', 'codigo_barras')],
        ], [
            'codigo_barras.unique' => 'Ese código de barras ya es de otro producto.',
            'codigo_barras.regex' => 'El código de barras solo lleva letras, números y guiones.',
        ]);

        if (filled($producto->codigo_barras)) {
            throw ValidationException::withMessages([
                'codigo_barras' => "{$producto->nombre} ya tiene el código {$producto->codigo_barras}.",
            ]);
        }

        // La auditoría del cambio la deja el modelo (AuditaElCatalogo).
        $producto->update(['codigo_barras' => $datos['codigo_barras']]);

        return back()->with('success', "Código ligado a {$producto->nombre}.");
    }
}
