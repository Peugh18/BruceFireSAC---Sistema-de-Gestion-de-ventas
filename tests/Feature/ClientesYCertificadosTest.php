<?php

use App\Actions\Certificates\IssueCertificate;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\ClientSite;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\Certificates\CertificateDocumentData;
use Database\Seeders\CertificateTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, CertificateTypeSeeder::class]);
    $this->vendedor = User::factory()->create();
    $this->vendedor->assignRole('Vendedor');
});

function certificadoDe(Client $client, ?string $referencia = null): array
{
    $tipo = CertificateType::where('codigo', 'operatividad_garantia')->firstOrFail();
    $equipo = Equipment::factory()->create(['client_id' => $client->id, 'tipo_agente' => 'PQS ABC']);
    $certificado = app(IssueCertificate::class)->handle($tipo, $client, [['equipment_id' => $equipo->id]], extra: ['referencia' => $referencia]);

    return app(CertificateDocumentData::class)->desde($certificado->fresh());
}

test('el alta del cliente guarda sus locales y como sale en los certificados', function () {
    $this->actingAs($this->vendedor)
        ->post(route('vendedor.clientes.store', ['current_team' => $this->vendedor->currentTeam]), [
            'tipo_documento' => 'ruc',
            'numero_documento' => '20601234565',
            'razon_social' => 'INVERSIONES NORTE S.A.C.',
            'nombre_comercial' => 'Market Norte',
            'direccion_fiscal' => 'Av. España 100, Trujillo',
            'certificado_con' => 'nombre_comercial',
            'locales' => [
                ['nombre' => 'Tienda Centro', 'direccion' => 'Jr. Pizarro 450, Trujillo'],
                ['nombre' => 'Almacén Moche', 'direccion' => 'Carretera Moche km 2'],
            ],
        ])
        ->assertSessionHasNoErrors();

    $client = Client::where('numero_documento', '20601234565')->sole();
    expect($client->certificado_con)->toBe('nombre_comercial')
        ->and($client->sites()->orderBy('id')->pluck('nombre')->all())->toBe(['Tienda Centro', 'Almacén Moche'])
        ->and($client->sites()->orderBy('id')->first()->direccion)->toBe('Jr. Pizarro 450, Trujillo');
});

test('el certificado sale con la razon social o con el nombre comercial que eligio el cliente', function () {
    $client = Client::factory()->create(['razon_social' => 'INVERSIONES NORTE S.A.C.', 'nombre_comercial' => 'Market Norte', 'certificado_con' => 'razon_social']);
    expect(certificadoDe($client)['cliente'])->toMatchArray(['razon_social' => 'INVERSIONES NORTE S.A.C.', 'nombre_comercial' => 'Market Norte']);

    $client->update(['certificado_con' => 'nombre_comercial']);
    expect(certificadoDe($client)['cliente'])->toMatchArray(['razon_social' => 'Market Norte', 'nombre_comercial' => null]);
});

test('el certificado de un local usa la direccion del local y no repite la referencia', function () {
    $client = Client::factory()->create(['direccion_fiscal' => 'Av. España 100, Trujillo']);
    ClientSite::factory()->create(['client_id' => $client->id, 'nombre' => 'Tienda Centro', 'direccion' => 'Jr. Pizarro 450, Trujillo']);

    $datos = certificadoDe($client, 'SEDE: Tienda Centro');

    expect($datos['cliente']['direccion'])->toBe('Jr. Pizarro 450, Trujillo')
        ->and($datos['cliente']['referencia'])->toBeNull();
    expect(certificadoDe($client, 'PLACA: ABC-123')['cliente']['referencia'])->toBe(['etiqueta' => 'Placa', 'valor' => 'ABC-123']);
});

test('la ficha del cliente muestra que compro, cuando y en que comprobante', function () {
    $client = Client::factory()->create();
    $sale = Sale::factory()->create(['client_id' => $client->id, 'vendedor_id' => $this->vendedor->id, 'estado' => 'confirmada', 'fecha' => '2026-10-15']);
    $producto = Product::factory()->create(['nombre' => 'Extintor PQS 6 kg']);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'tipo_linea' => 'producto', 'product_id' => $producto->id, 'cantidad' => 2, 'precio_unitario' => 80, 'descuento' => 0, 'subtotal' => 160]);
    Sale::factory()->create(['client_id' => $client->id, 'vendedor_id' => $this->vendedor->id, 'estado' => 'anulada']);

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.clientes.show', [$this->vendedor->currentTeam, $client]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('compras', 1)
            ->where('compras.0.nombre', 'Extintor PQS 6 kg')
            ->where('compras.0.cantidad', 2)
            ->where('compras.0.fecha', '2026-10-15'));

    $this->get(route('vendedor.clientes.compras', [$this->vendedor->currentTeam, $client]))
        ->assertOk()->assertDownload('compras-'.$client->numero_documento.'.xlsx');
});

test('la ficha abre aunque el cliente tenga una orden sin servicio principal', function () {
    $client = Client::factory()->create();
    ServiceOrder::factory()->create(['client_id' => $client->id, 'service_id' => null, 'sede_id' => null]);

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.clientes.show', [$this->vendedor->currentTeam, $client]))
        ->assertOk();
});
