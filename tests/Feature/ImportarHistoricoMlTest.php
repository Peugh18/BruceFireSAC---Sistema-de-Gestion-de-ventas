<?php

use App\Models\MlClienteHistorico;
use App\Models\MlLineaHistorica;
use App\Models\Sale;

function csvHistorico(array $filas): string
{
    $path = tempnam(sys_get_temp_dir(), 'hist');
    file_put_contents($path, implode("\n", [
        'fecha,tipo_doc,comprobante,documento_cliente,nombre_cliente,categoria,producto_original,cantidad,total,archivo_origen',
        ...$filas,
    ]));

    return $path;
}

test('carga solo facturas y boletas de clientes identificables y no toca las ventas', function () {
    $path = csvHistorico([
        '2025-04-01,F,F001-11097,20100077044,HERMES S A,seguridad,ESPEJO,2.0,40.0,ABRIL.xlsx',
        '2025-04-02,B,B001-1000,44556677,JUAN PEREZ,recarga_mantenimiento,RECARGA PQS 6 KG,1.0,55.0,ABRIL.xlsx',
        '2025-04-03,PR,0001-181,20100077044,HERMES S A,seguridad,CINTA,1.0,10.0,ABRIL.xlsx',
        '2025-04-04,F,F001-11100,0,CLIENTE VARIOS,extintor,EXTINTOR 6KG,1.0,70.0,ABRIL.xlsx',
    ]);

    $this->artisan('ml:importar-historico', ['csvPath' => $path])->assertSuccessful();

    expect(MlLineaHistorica::query()->pluck('comprobante')->sort()->values()->all())->toBe(['B001-1000', 'F001-11097'])
        ->and(MlClienteHistorico::query()->pluck('nombre', 'documento')->all())->toEqual(['20100077044' => 'HERMES S A', '44556677' => 'JUAN PEREZ'])
        ->and(Sale::query()->count())->toBe(0);

    unlink($path);
});

test('volver a cargar reemplaza la carga anterior sin duplicar', function () {
    $path = csvHistorico(['2025-04-01,F,F001-11097,20100077044,HERMES S A,seguridad,ESPEJO,2.0,40.0,ABRIL.xlsx']);

    $this->artisan('ml:importar-historico', ['csvPath' => $path])->assertSuccessful();
    $this->artisan('ml:importar-historico', ['csvPath' => $path])->assertSuccessful();

    expect(MlLineaHistorica::query()->count())->toBe(1);

    unlink($path);
});

test('rechaza un archivo con otras columnas', function () {
    $path = tempnam(sys_get_temp_dir(), 'hist');
    file_put_contents($path, "a,b,c\n1,2,3");

    $this->artisan('ml:importar-historico', ['csvPath' => $path])->assertFailed();

    unlink($path);
});
