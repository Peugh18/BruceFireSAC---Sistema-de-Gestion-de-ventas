<?php

namespace App\Http\Controllers\Almacen;

use App\Http\Controllers\Controller;
use App\Models\Reception;
use App\Models\Team;
use App\Services\Inventory\StickerPdfService;
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
        StickerPdfService $stickerPdfService
    ): Response {
        $pdf = $stickerPdfService->generate($reception);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="stickers-recepcion-%d.pdf"', $reception->id),
        ]);
    }
}
