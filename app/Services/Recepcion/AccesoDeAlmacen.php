<?php

namespace App\Services\Recepcion;

use App\Models\Reception;
use Illuminate\Http\Request;

/**
 * Acceso a las recepciones por almacén (antes era un método estático de
 * ReceptionController que también llamaba ReceptionStickerController).
 */
class AccesoDeAlmacen
{
    /**
     * El personal de almacén solo opera las recepciones de su almacén (el
     * Gerente ve todas). Lo ajeno responde 404, como si no existiera.
     */
    public static function asegurarAlmacen(Request $request, Reception $reception): void
    {
        $almacenId = $request->user()->almacenRestringidoId();

        abort_if($almacenId !== null && (int) $reception->sede_almacen_id !== $almacenId, 404);
    }
}
