<?php

namespace App\Http\Controllers\Almacen;

use App\Http\Controllers\Controller;
use App\Models\InventoryUnit;
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
    public function index(Team $current_team): InertiaResponse
    {
        $recepciones = Reception::query()
            ->whereHas('movements', fn ($q) => $q->whereNotNull('inventory_unit_id'))
            ->with(['sedeAlmacen:id,nombre'])
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
            ]);

        return Inertia::render('almacen/stickers/index', [
            'recepciones' => $recepciones,
        ]);
    }

    /**
     * Devuelve el PDF con la grilla de stickers de código de barras
     * para las unidades recibidas (§84.9).
     */
    public function show(
        Team $current_team,
        Reception $reception,
        StickerPdfService $stickerPdfService,
        Request $request,
    ): Response {
        AccesoDeAlmacen::asegurarAlmacen($request, $reception);

        $pdf = $stickerPdfService->generate($reception, $this->posicionInicial($request));

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
        $serie = trim((string) $request->validate(['serie' => ['required', 'string', 'max:50']])['serie']);
        $almacenId = $request->user()->almacenRestringidoId();

        $unidad = InventoryUnit::query()
            ->where('numero_serie', mb_strtoupper($serie))
            ->when($almacenId, fn ($q) => $q->where('sede_almacen_id', $almacenId))
            ->first();
        abort_if($unidad === null, 404, 'No se encontró esa serie en tu almacén.');

        $pdf = $stickerPdfService->generateForUnit($unidad, $this->posicionInicial($request));

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="sticker-%s.pdf"', $unidad->numero_serie),
        ]);
    }

    /**
     * Posición de la hoja (1 a 20) donde empieza la impresión.
     */
    protected function posicionInicial(Request $request): int
    {
        $datos = $request->validate([
            'inicio' => ['nullable', 'integer', 'min:1', 'max:'.StickerPdfService::POR_HOJA],
        ]);

        return (int) ($datos['inicio'] ?? 1);
    }
}
