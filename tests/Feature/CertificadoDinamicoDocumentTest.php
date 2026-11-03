<?php

use App\Actions\Certificates\IssueCertificate;
use App\Models\CertificateType;
use App\Models\Client;
use App\Services\Certificates\CertificateDocumentData;
use App\Services\Certificates\CertificatePdfService;
use App\Services\Certificates\CertificateWordService;
use Database\Seeders\CertificateTypeSeeder;
use PhpOffice\PhpWord\IOFactory;

test('dynamic documents render only selected blocks in order and escape text', function () {
    $this->seed(CertificateTypeSeeder::class);
    $tipo = CertificateType::where('codigo', 'luces_emergencia')->firstOrFail();
    $tipo->update(['composicion' => [
        ['tipo' => 'texto', 'titulo' => 'Garantía elegida', 'texto' => "Garantía <b>literal</b> & válida\nSegunda línea"],
        ['tipo' => 'datos', 'titulo' => 'Cliente seleccionado'],
        ['tipo' => 'texto', 'titulo' => 'Mantenimiento elegido', 'texto' => 'Último bloque'],
    ]]);
    $datos = app(CertificateDocumentData::class)->muestra($tipo);

    $html = app(CertificatePdfService::class)->html($datos);
    $texto = html_entity_decode(strip_tags($html));

    expect($texto)->toContain('Garantía <b>literal</b> & válida', 'Segunda línea', 'ASNEEX', 'EMPRESA ASOCIADA')
        ->not->toContain('Pruebas realizadas', 'Resultado de la evaluación', 'Equipos inspeccionados');
    expect(strpos($texto, 'Garantía elegida'))->toBeLessThan(strpos($texto, 'Cliente seleccionado'));
    expect(strpos($texto, 'Cliente seleccionado'))->toBeLessThan(strpos($texto, 'Mantenimiento elegido'));
    expect($html)->not->toContain('<b>');

    $word = app(CertificateWordService::class)->documento($datos);
    $ruta = tempnam(sys_get_temp_dir(), 'dynamic-word');
    try {
        IOFactory::createWriter($word, 'Word2007')->save($ruta);
        $zip = new ZipArchive;
        $zip->open($ruta);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        expect($xml)->toContain('Garantía elegida', '&lt;b&gt;literal&lt;/b&gt;', 'Mantenimiento elegido')
            ->not->toContain('Pruebas realizadas', 'Resultado de la evaluación');
    } finally {
        unlink($ruta);
    }
});

test('dynamic diplomas honor orientation and fit the sample in one page', function (string $orientacion) {
    $this->seed(CertificateTypeSeeder::class);
    $tipo = CertificateType::where('codigo', 'capacitacion')->firstOrFail();
    $tipo->update(['orientacion' => $orientacion, 'composicion' => [['tipo' => 'diploma']]]);
    $datos = app(CertificateDocumentData::class)->muestra($tipo);

    $pdf = app(CertificatePdfService::class)->dibujar($datos);
    $canvas = $pdf->getDomPDF()->getCanvas();

    expect(html_entity_decode(strip_tags(app(CertificatePdfService::class)->html($datos))))->toContain('COMERCIAL LOS ANDES S.A.C.', 'USO Y MANEJO DE EXTINTORES', '4 horas', 'ASNEEX');
    expect($canvas->get_page_count())->toBe(1);
    expect($canvas->get_width() > $canvas->get_height())->toBe($orientacion === 'horizontal');
    $section = app(CertificateWordService::class)->documento($datos)->getSections()[0];
    expect($section->getStyle()->getOrientation())->toBe($orientacion === 'horizontal' ? 'landscape' : 'portrait');
})->with(['vertical', 'horizontal']);

test('long dynamic tables continue across pages without dropping the last row', function () {
    $this->seed(CertificateTypeSeeder::class);
    $tipo = CertificateType::where('codigo', 'luces_emergencia')->firstOrFail();
    $tipo->update(['composicion' => [['tipo' => 'tabla']]]);
    $datos = app(CertificateDocumentData::class)->muestra($tipo);
    $datos['filas'] = array_map(fn (int $n) => ['item' => (string) $n, 'ubicacion' => 'Equipo número '.$n], range(1, 110));

    $pdf = app(CertificatePdfService::class)->dibujar($datos);

    expect($pdf->getDomPDF()->getCanvas()->get_page_count())->toBeGreaterThan(1);
    expect(app(CertificatePdfService::class)->html($datos))->toContain('Equipo número 110');
});

test('an issued dynamic document retains its composition snapshot after editing the template', function () {
    $this->seed(CertificateTypeSeeder::class);
    $tipo = CertificateType::where('codigo', 'luces_emergencia')->firstOrFail();
    $tipo->update(['composicion' => [['tipo' => 'texto', 'texto' => 'Garantía original']]]);
    $certificate = app(IssueCertificate::class)->handle($tipo, Client::factory()->create(), [], extra: ['datos' => ['fecha' => '2026-04-15']]);
    $tipo->update(['composicion' => [['tipo' => 'texto', 'texto' => 'Garantía nueva']], 'vigencia_meses' => 36, 'version' => 2]);

    $datos = app(CertificateDocumentData::class)->desde($certificate->fresh());

    expect($datos['tipo']['composicion'])->toBe([['tipo' => 'texto', 'texto' => 'Garantía original']]);
    expect($datos['capacitacion']['fecha_larga'])->toBe('15 de abril de 2026');
    expect($datos['certificado']['vigencia'])->toBeNull();
    expect(app(CertificatePdfService::class)->html($datos))
        ->toContain('Garantía original')->not->toContain('Garantía nueva');
});
