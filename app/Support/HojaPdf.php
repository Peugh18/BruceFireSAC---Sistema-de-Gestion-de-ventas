<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfWrapper;

/**
 * Documentos de la hoja única (resources/views/pdf/hoja) a media hoja: el A4
 * lleva todo en la mitad de arriba y la línea de corte, para reutilizar la
 * otra mitad. Si no entra (muchos ítems o cuotas), va en la hoja completa y
 * sin la línea.
 */
final class HojaPdf
{
    /** Media hoja A4 (210 × 148,5 mm) en puntos. */
    private const MEDIA_HOJA = [0, 0, 595.28, 420.94];

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function cargar(string $vista, array $datos): DompdfWrapper
    {
        $prueba = Pdf::loadView($vista, $datos)->setPaper(self::MEDIA_HOJA);
        $prueba->render();
        $mediaHoja = $prueba->getDomPDF()->getCanvas()->get_page_count() === 1;

        return Pdf::loadView($vista, [...$datos, 'mediaHoja' => $mediaHoja])->setPaper('a4');
    }
}
