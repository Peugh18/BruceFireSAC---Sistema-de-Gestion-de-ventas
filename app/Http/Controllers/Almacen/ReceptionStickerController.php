<?php

namespace App\Http\Controllers\Almacen;

use App\Http\Controllers\Controller;
use App\Models\Reception;
use App\Models\Team;
use App\Services\Inventory\StickerPdfService;
use Illuminate\Http\Response;

class ReceptionStickerController extends Controller
{
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
