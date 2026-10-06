<?php

use App\Models\CashRegister;
use App\Models\Certificate;
use App\Models\ElectronicDocument;
use App\Models\Installment;
use App\Models\Quote;
use App\Models\Sale;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Cada acción sensible del vendedor exige su permiso en el servidor: si el
 * Gerente se lo quita al rol, la ruta responde 403 aunque el botón siga
 * en pantalla.
 */
test('la ruta exige su permiso', function (string $permiso, string $metodo, string $ruta, Closure $parametros) {
    $vendedor = vendedorUser();
    $vendedor->roles->first()->revokePermissionTo($permiso);

    $this->actingAs($vendedor)
        ->call($metodo, route($ruta, ['current_team' => $vendedor->currentTeam, ...$parametros()]))
        ->assertForbidden();
})->with([
    'nueva venta' => ['sales.create', 'GET', 'vendedor.ventas.create', fn () => []],
    'guardar venta' => ['sales.create', 'POST', 'vendedor.ventas.store', fn () => []],
    'confirmar venta' => ['sales.create', 'POST', 'vendedor.ventas.confirmar', fn () => ['sale' => Sale::factory()->create()]],
    'nueva cotización' => ['quotes.create', 'GET', 'vendedor.cotizaciones.create', fn () => []],
    'guardar cotización' => ['quotes.create', 'POST', 'vendedor.cotizaciones.store', fn () => []],
    'aceptar cotización' => ['quotes.convert', 'POST', 'vendedor.cotizaciones.accept', fn () => ['quote' => Quote::factory()->create()]],
    'reenviar a SUNAT' => ['billing.resend', 'POST', 'vendedor.facturacion.resend', fn () => ['electronic_document' => ElectronicDocument::factory()->create()]],
    'comunicación de baja' => ['billing.void', 'POST', 'vendedor.facturacion.baja', fn () => ['electronic_document' => ElectronicDocument::factory()->create()]],
    'registrar cobro' => ['collections.register_payment', 'POST', 'vendedor.cobranzas.pagar', fn () => ['installment' => Installment::factory()->create()]],
    'imprimir certificado' => ['certificates.print', 'GET', 'vendedor.certificados.pdf', fn () => ['certificate' => Certificate::factory()->create()]],
    'emitir certificados de la venta' => ['certificates.print', 'POST', 'vendedor.ventas.certificados.store', fn () => ['sale' => Sale::factory()->create()]],
    'abrir caja' => ['cashregister.open', 'POST', 'vendedor.caja.abrir', fn () => []],
    'cerrar caja' => ['cashregister.close', 'POST', 'vendedor.caja.cerrar', fn () => ['cash_register' => CashRegister::factory()->create()]],
]);

test('con todos sus permisos el vendedor sigue entrando a nueva venta', function () {
    $vendedor = vendedorUser();

    $this->actingAs($vendedor)
        ->get(route('vendedor.ventas.create', ['current_team' => $vendedor->currentTeam]))
        ->assertOk();
});
