<?php

namespace App\Http\Controllers\Almacen;

use App\Actions\Certificates\EmitirCertificadosDeProducto;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Almacén llena una vez la ficha técnica de cada producto con certificado:
 * los campos los pide la plantilla y sirven para todas sus unidades. Con
 * ella el certificado sale solo al vender.
 */
class FichaTecnicaController extends Controller
{
    public function index(Team $current_team): Response
    {
        $productos = Product::query()->with('certificateType')->where('activo', true)->whereNotNull('certificate_type_id')
            ->orderBy('nombre')->get()
            ->map(fn (Product $producto) => [
                'id' => $producto->id,
                'codigo' => $producto->codigo,
                'nombre' => $producto->nombre,
                'certificado' => $producto->certificateType->nombre,
                'campos' => EmitirCertificadosDeProducto::camposDeFicha($producto->certificateType),
                'ficha' => (object) ($producto->ficha_tecnica ?? []),
                'pendiente' => EmitirCertificadosDeProducto::fichaIncompleta($producto),
            ])
            ->filter(fn (array $producto) => $producto['campos'] !== [])
            ->sortByDesc('pendiente')
            ->values();

        return Inertia::render('almacen/fichas/index', [
            'productos' => $productos,
        ]);
    }

    public function update(Request $request, Team $current_team, Product $producto, EmitirCertificadosDeProducto $certificados): RedirectResponse
    {
        abort_if($producto->certificateType === null, 404);
        $campos = EmitirCertificadosDeProducto::camposDeFicha($producto->certificateType);

        $reglas = [];
        $nombres = [];
        foreach ($campos as $campo) {
            $reglas["ficha_tecnica.{$campo['clave']}"] = [
                ($campo['obligatorio'] ?? false) ? 'required' : 'nullable',
                'string',
                'max:120',
                ...(($campo['tipo'] ?? 'texto') === 'numero' ? ['regex:/^\d+([.,]\d+)?$/'] : []),
            ];
            $nombres["ficha_tecnica.{$campo['clave']}"] = '«'.$campo['titulo'].'»';
        }
        $datos = $request->validate($reglas, [
            'required' => 'Completa :attribute.',
            'regex' => ':attribute debe ser un número.',
        ], $nombres);

        // Solo se guardan los campos que pide la plantilla.
        $producto->update(['ficha_tecnica' => array_map(fn (?string $valor) => trim((string) $valor), $datos['ficha_tecnica'] ?? [])]);
        $certificados->alCompletarFicha($producto);

        return back()->with('success', "Ficha de {$producto->nombre} guardada.");
    }
}
