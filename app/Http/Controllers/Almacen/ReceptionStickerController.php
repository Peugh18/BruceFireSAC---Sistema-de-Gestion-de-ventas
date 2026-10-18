<?php

namespace App\Http\Controllers\Almacen;

use App\Http\Controllers\Controller;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Reception;
use App\Models\Team;
use App\Services\Inventory\StickerPdfService;
use App\Services\Recepcion\AccesoDeAlmacen;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ReceptionStickerController extends Controller
{
    /**
     * Listado de recepciones con unidades serializadas, para imprimir
     * sus stickers de código de barras (§84.9).
     */
    public function index(Team $current_team, Request $request): InertiaResponse
    {
        $almacenId = $request->user()->almacenRestringidoId();
        $recepciones = Reception::query()
            ->when($almacenId, fn ($q, $id) => $q->where('sede_almacen_id', $id))
            ->whereHas('movements')
            ->with(['sedeAlmacen:id,nombre', 'movements.product:id,serializado,codigo,nombre'])
            ->withCount(['movements as unidades_count' => fn ($q) => $q->whereNotNull('inventory_unit_id')])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Reception $reception) => [
                'id' => $reception->id,
                'proveedor' => $reception->proveedor,
                'fecha' => $reception->fecha->toDateString(),
                'sede_almacen' => $reception->sedeAlmacen ? [
                    'id' => $reception->sedeAlmacen->id,
                    'nombre' => $reception->sedeAlmacen->nombre,
                ] : null,
                'unidades_count' => (int) $reception->unidades_count,
                'productos_count' => $reception->movements->filter(fn ($movimiento): bool => ! $movimiento->product->serializado)->pluck('product_id')->unique()->count(),
            ]);

        return Inertia::render('almacen/stickers/index', [
            'recepciones' => $recepciones,
        ]);
    }

    /**
     * Devuelve las etiquetas recibidas en rollo o en hojas A4 (§84.9).
     */
    public function show(
        Team $current_team,
        Reception $reception,
        StickerPdfService $stickerPdfService,
        Request $request,
    ): Response {
        AccesoDeAlmacen::asegurarAlmacen($request, $reception);

        $datos = $request->validate(['formato' => ['sometimes', 'required', 'in:rollo,a4']]);
        $pdf = $stickerPdfService->generate($reception, $datos['formato'] ?? 'rollo');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="stickers-recepcion-%d.pdf"', $reception->id),
        ]);
    }

    /**
     * Reimprime el sticker de una sola unidad, por su serie (BF-EQ-...).
     */
    public function unidad(Team $current_team, StickerPdfService $stickerPdfService, Request $request): Response
    {
        $datos = $request->validate([
            'serie' => ['required', 'string', 'max:50'],
            'formato' => ['sometimes', 'required', 'in:rollo,a4'],
        ]);
        $serie = trim((string) $datos['serie']);
        $almacenId = $request->user()->almacenRestringidoId();

        $unidad = InventoryUnit::query()
            ->where('numero_serie', mb_strtoupper($serie))
            ->when($almacenId, fn ($q) => $q->where('sede_almacen_id', $almacenId))
            ->first();
        abort_if($unidad === null, 404, 'No se encontró esa serie en tu almacén.');

        $pdf = $stickerPdfService->generateForUnit($unidad, $datos['formato'] ?? 'rollo');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="sticker-%s.pdf"', $unidad->numero_serie),
        ]);
    }

    public function producto(Team $current_team, StickerPdfService $stickerPdfService, Request $request): Response
    {
        $datos = $request->validate(['codigo' => ['required', 'string', 'max:50'], 'formato' => ['sometimes', 'required', 'in:rollo,a4']]);
        $producto = Product::query()->where('codigo', trim($datos['codigo']))->where('serializado', false)->where('activo', true)->firstOrFail();

        return response($stickerPdfService->generateForProduct($producto, $datos['formato'] ?? 'rollo')->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="sticker-producto-'.$producto->id.'.pdf"',
        ]);
    }
}
