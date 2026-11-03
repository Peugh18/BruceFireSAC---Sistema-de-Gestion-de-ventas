<?php

use App\Models\DispatchGuide;
use App\Models\DispatchGuideItem;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\Billing\ComprobantePdfService;
use App\Services\Billing\GuiaRemisionPdfService;
use App\Services\Quotes\QuotePdfService;
use Illuminate\Database\Eloquent\Collection;

/*
| Comprobantes, cotización y guía van en la mitad de arriba del A4 con una
| línea de corte, para reutilizar la otra mitad. Si no entra, van en la hoja
| completa sin la línea (App\Support\HojaPdf).
*/

function textoDelPdf(string $pdf): string
{
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $partes);

    return implode("\n", array_map(fn (string $parte) => @gzuncompress($parte) ?: $parte, $partes[1]));
}

it('un comprobante corto lleva la línea de corte a media hoja', function () {
    expect(textoDelPdf(app(ComprobantePdfService::class)->vistaPrevia()))->toContain('cortar aqu');
});

it('un comprobante con muchos ítems va en la hoja completa, sin línea de corte', function () {
    $sale = Sale::factory()->create(['numero_nota_venta' => 'NV-000001']);
    SaleItem::factory()->count(40)->create(['sale_id' => $sale->id]);

    $texto = textoDelPdf(app(ComprobantePdfService::class)->notaDeVenta($sale->fresh()));

    expect($texto)->not->toContain('cortar aqu')
        ->and($texto)->toContain('NV-000001');
});

it('la cotización y la guía cortas también van a media hoja', function () {
    $cotizacion = app(QuotePdfService::class)->generate(Quote::factory()->create())->output();

    $guia = new DispatchGuide([
        'serie' => 'T001', 'correlativo' => 1, 'fecha_emision' => today(), 'fecha_traslado' => today(),
        'motivo' => '01', 'modalidad' => '01', 'destinatario_tipo_doc' => '6', 'destinatario_num_doc' => '20481234567',
        'destinatario_nombre' => 'CLIENTE S.A.C.', 'partida_ubigeo' => '130101', 'partida_direccion' => 'Av. España 1234',
        'llegada_ubigeo' => '130101', 'llegada_direccion' => 'Av. Larco 567', 'peso_bruto' => '6.00',
        'transportista_ruc' => '20123456789', 'transportista_razon' => 'TRANSPORTES S.A.C.', 'estado_sunat' => 'borrador',
    ]);
    $guia->setRelation('items', new Collection([new DispatchGuideItem(['codigo' => 'PRD-1', 'descripcion' => 'Extintor PQS 6 kg', 'unidad' => 'NIU', 'cantidad' => 1, 'peso_kg' => 6])]));
    $guia->setRelation('client', null);

    expect(textoDelPdf($cotizacion))->toContain('cortar aqu')
        ->and(textoDelPdf(app(GuiaRemisionPdfService::class)->generate($guia)))->toContain('cortar aqu');
});
