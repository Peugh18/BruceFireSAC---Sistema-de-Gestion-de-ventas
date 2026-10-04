<?php

use App\Actions\Certificates\EmitirCertificadoDeServicio;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\ElectronicDocument;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CertificateTypeSeeder::class);
});

function vendedorDeCertificados(): User
{
    $user = User::factory()->create();
    $user->assignRole('Vendedor');

    return $user;
}

function certificadoDeLuces(string $razonSocial): array
{
    $sale = Sale::factory()->create([
        'client_id' => Client::factory()->create(['razon_social' => $razonSocial])->id,
        'estado' => 'confirmada',
        'numero_interno' => 'VTA-'.fake()->unique()->numerify('####'),
    ]);
    ElectronicDocument::create(['sale_id' => $sale->id, 'tipo' => 'boleta', 'serie' => 'B001', 'correlativo' => ElectronicDocument::count() + 7, 'sunat_estado' => 'aceptado']);

    $certificado = app(EmitirCertificadoDeServicio::class)->handle(
        $sale,
        CertificateType::where('codigo', 'luces_emergencia')->firstOrFail(),
        ['filas' => [['ubicacion' => 'Recepción', 'marca' => 'OPALUX', 'modelo' => 'LED', 'potencia' => '4', 'autonomia' => '2', 'resultado' => 'operativo']]],
    );

    return [$sale, $certificado];
}

test('la lista muestra la venta y el comprobante de cada certificado y filtra en el servidor', function () {
    $user = vendedorDeCertificados();
    [$sale] = certificadoDeLuces('COMERCIAL LOS ANDES');
    certificadoDeLuces('OTRO CLIENTE');

    $this->actingAs($user)
        ->get(route('vendedor.certificados.index', ['current_team' => $user->currentTeam, 'search' => $sale->numero_interno]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendedor/certificados/index')
            ->has('certificates.data', 1)
            ->where('certificates.data.0.venta.numero', $sale->numero_interno)
            ->where('certificates.data.0.venta.comprobante', 'B001-7'));

    $this->actingAs($user)
        ->get(route('vendedor.certificados.index', ['current_team' => $user->currentTeam, 'tipo' => 'capacitacion']))
        ->assertInertia(fn ($page) => $page->has('certificates.data', 0));
});

test('el detalle trae la venta, el cliente y a donde ir para corregirlo', function () {
    $user = vendedorDeCertificados();
    [$sale, $certificado] = certificadoDeLuces('COMERCIAL LOS ANDES');

    $this->actingAs($user)
        ->get(route('vendedor.certificados.show', ['current_team' => $user->currentTeam, 'certificate' => $certificado]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendedor/certificados/show')
            ->where('certificate.venta.id', $sale->id)
            ->where('certificate.cliente.razon_social', 'COMERCIAL LOS ANDES')
            ->where('certificate.corregir_url', route('vendedor.ventas.certificado-servicio.create', [
                'current_team' => $user->currentTeam,
                'sale' => $sale->id,
                'tipo' => 'luces_emergencia',
            ])));
});

test('la lista de ventas muestra el comprobante vigente y su estado en sunat', function () {
    $user = vendedorDeCertificados();
    $sale = Sale::factory()->create(['client_id' => Client::factory()->create()->id, 'estado' => 'confirmada', 'vendedor_id' => $user->id, 'fecha' => today()]);
    ElectronicDocument::create(['sale_id' => $sale->id, 'tipo' => 'factura', 'serie' => 'F001', 'correlativo' => 90, 'sunat_estado' => 'excepcion']);
    ElectronicDocument::create(['sale_id' => $sale->id, 'tipo' => 'boleta', 'serie' => 'B001', 'correlativo' => 91, 'sunat_estado' => 'aceptado']);

    $this->actingAs($user)
        ->get(route('vendedor.ventas.index', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sales.data.0.comprobante', 'B001-91')
            ->where('sales.data.0.sunat_estado', 'aceptado'));
});
