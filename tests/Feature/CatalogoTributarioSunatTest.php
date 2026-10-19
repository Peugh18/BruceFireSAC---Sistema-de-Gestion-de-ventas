<?php

use App\Models\CertificateType;
use App\Models\CompanySetting;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\SunatDetraccion;
use App\Models\TipoDeServicio;
use App\Models\User;
use App\Services\Billing\DetraccionCalculator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

test('el catalogo oficial distingue codigos informativos de reglas soportadas y conserva fuente', function () {
    $alquiler = SunatDetraccion::query()->findOrFail('019');
    $transporte = SunatDetraccion::query()->findOrFail('027');

    expect($alquiler->nombre)->toContain('Arrendamiento')
        ->and($alquiler->fuente)->toContain('sunat.gob.pe')
        ->and($alquiler->reglaPara(now())->porcentaje)->toBe('10.00')
        ->and($transporte->reglaPara(now()))->toBeNull();
});

test('un servicio no sujeto nunca infiere detraccion por categoria y uno pendiente exige revision', function () {
    $servicio = Service::factory()->create(['estado_detraccion' => 'no_sujeto', 'codigo_detraccion' => '022']);
    $venta = Sale::factory()->create(['comprobante_tipo' => 'factura', 'estado' => 'borrador', 'total' => 1180]);
    SaleItem::factory()->forService($servicio)->create(['sale_id' => $venta->id]);

    expect($servicio->codigoDetraccion())->toBeNull()
        ->and(app(DetraccionCalculator::class)->paraVenta($venta)['aplica'])->toBeFalse();

    $servicio->update(['estado_detraccion' => 'pendiente']);
    expect(fn () => app(DetraccionCalculator::class)->paraVenta($venta->fresh()))
        ->toThrow(ValidationException::class, 'clasificación');
});

test('las tasas se resuelven por codigo y fecha sin la tasa global', function () {
    CompanySetting::current()->update(['cuenta_detraccion' => '00123456789']);
    config(['billing.detraccion.tasa' => 0.99]);
    $servicio = Service::factory()->create(['estado_detraccion' => 'sujeto', 'codigo_detraccion' => '019']);
    $venta = Sale::factory()->create(['comprobante_tipo' => 'factura', 'estado' => 'borrador', 'total' => 1180]);
    SaleItem::factory()->forService($servicio)->create(['sale_id' => $venta->id]);

    expect(app(DetraccionCalculator::class)->paraVenta($venta)['monto'])->toBe(118.0);
    $servicio->update(['codigo_detraccion' => '022']);
    expect(app(DetraccionCalculator::class)->paraVenta($venta->fresh())['monto'])->toBe(141.6);
});

test('la revision fiscal del tipo se hereda y no cambia el certificado', function () {
    $certificado = CertificateType::factory()->create();
    $tipo = TipoDeServicio::create(['nombre' => 'Inspección independiente', 'estado_detraccion' => 'pendiente', 'certificate_type_id' => $certificado->id]);
    $servicio = Service::factory()->create(['tipo_de_servicio_id' => $tipo->id]);
    expect($servicio->estado_detraccion)->toBe('pendiente');

    $tipo->update(['estado_detraccion' => 'sujeto', 'codigo_detraccion' => '037']);
    expect($servicio->fresh()->estado_detraccion)->toBe('sujeto')
        ->and($servicio->fresh()->codigoDetraccion())->toBe('037')
        ->and($servicio->fresh()->certificate_type_id)->toBe($certificado->id);
});

test('gerencia no puede guardar un codigo inexistente ni tratar codigo como revision implicita', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');
    $ruta = route('gerente.tipos-de-servicio.store', ['current_team' => $gerente->currentTeam]);

    $this->actingAs($gerente)->post($ruta, ['nombre' => 'Código errado', 'estado_detraccion' => 'sujeto', 'codigo_detraccion' => '999'])
        ->assertSessionHasErrors('codigo_detraccion');
    $this->post($ruta, ['nombre' => 'Sin clasificación confirmada', 'codigo_detraccion' => '022'])
        ->assertSessionHasNoErrors();
    expect(TipoDeServicio::where('nombre', 'Sin clasificación confirmada')->sole()->estado_detraccion)->toBe('pendiente');
});
