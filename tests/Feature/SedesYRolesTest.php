<?php

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\Deficiency;
use App\Models\ElectronicDocument;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\Avisos\AvisosDelVendedor;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->almacen = Sede::factory()->mixta()->create(['nombre' => 'Almacén Central']);
    $this->tienda = Sede::factory()->tienda()->create(['nombre' => 'Tienda Centro', 'almacen_id' => $this->almacen->id]);
    $this->otraTienda = Sede::factory()->tienda()->create(['nombre' => 'Tienda Norte', 'almacen_id' => $this->almacen->id]);
    $this->gerente = User::factory()->create();
    $this->gerente->assignRole('Gerente');
});

function conRol(string $rol, ?Sede $sede = null): User
{
    $user = User::factory()->create(['sede_id' => $sede?->id]);
    $user->assignRole($rol);

    return $user;
}

function rutaGerente(User $gerente, string $nombre, array $extra = []): string
{
    return route($nombre, ['current_team' => $gerente->currentTeam, ...$extra]);
}

test('cada rol solo puede quedar en el tipo de sede donde trabaja', function () {
    $vendedor = conRol('Vendedor', $this->tienda);
    $tecnico = conRol('TecnicoPlanta', $this->almacen);

    $this->actingAs($this->gerente)
        ->patch(rutaGerente($this->gerente, 'gerente.usuarios.update-sede', ['user' => $tecnico]), ['sede_id' => $this->tienda->id])
        ->assertSessionHasErrors(['sede_id' => 'Un técnico de planta trabaja en un almacén o una sede mixta; Tienda Centro es una tienda.']);

    $this->actingAs($this->gerente)
        ->patch(rutaGerente($this->gerente, 'gerente.usuarios.update-sede', ['user' => $vendedor]), ['sede_id' => null])
        ->assertSessionHasErrors('sede_id');

    $this->actingAs($this->gerente)
        ->patch(rutaGerente($this->gerente, 'gerente.usuarios.update-sede', ['user' => $vendedor]), ['sede_id' => $this->otraTienda->id])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->gerente)
        ->patch(rutaGerente($this->gerente, 'gerente.usuarios.update-sede', ['user' => $this->gerente]), ['sede_id' => null])
        ->assertSessionHasNoErrors();

    expect($vendedor->fresh()->sede_id)->toBe($this->otraTienda->id);
});

test('no se cambia el rol si la persona queda en una sede donde ese rol no trabaja', function () {
    $vendedor = conRol('Vendedor', $this->tienda);

    $this->actingAs($this->gerente)
        ->patch(rutaGerente($this->gerente, 'gerente.usuarios.update-role', ['user' => $vendedor]), ['role' => 'TecnicoPlanta'])
        ->assertSessionHasErrors('role');

    expect($vendedor->fresh()->hasRole('Vendedor'))->toBeTrue();
});

test('un almacen con tiendas o con tecnicos no se convierte en tienda', function () {
    $otroAlmacen = Sede::factory()->mixta()->create(['nombre' => 'Almacén Norte']);
    $datos = ['nombre' => 'Almacén Central', 'tipo' => 'tienda', 'almacen_id' => $otroAlmacen->id, 'activo' => true];

    $this->actingAs($this->gerente)
        ->put(rutaGerente($this->gerente, 'gerente.sedes.update', ['sede' => $this->almacen]), $datos)
        ->assertSessionHasErrors('tipo');

    $solo = Sede::factory()->almacen()->create(['nombre' => 'Almacén Sur']);
    conRol('TecnicoPlanta', $solo);

    $this->actingAs($this->gerente)
        ->put(rutaGerente($this->gerente, 'gerente.sedes.update', ['sede' => $solo]), [...$datos, 'nombre' => 'Almacén Sur'])
        ->assertSessionHasErrors('tipo');

    expect($solo->fresh()->tipo)->toBe('almacen');
});

test('un trabajador sin sede no entra a su panel y ve el aviso para pedirla', function () {
    config(['app.exigir_sede' => true]);
    $sinSede = conRol('Vendedor');

    $this->actingAs($sinSede)
        ->get(route('vendedor.dashboard', ['current_team' => $sinSede->currentTeam]))
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page->component('errors/sin-sede'));

    $conSede = conRol('Vendedor', $this->tienda);

    $this->actingAs($conSede)
        ->get(route('vendedor.dashboard', ['current_team' => $conSede->currentTeam]))
        ->assertOk();
});

test('la vendedora no ve comprobantes, certificados ni avisos de otra tienda', function () {
    $vendedora = conRol('Vendedor', $this->tienda);
    $ajena = Sale::factory()->create(['client_id' => Client::factory()->create()->id, 'sede_id' => $this->otraTienda->id, 'estado' => 'confirmada']);
    $documento = ElectronicDocument::create(['sale_id' => $ajena->id, 'tipo' => 'boleta', 'serie' => 'B001', 'correlativo' => 50, 'sunat_estado' => 'aceptado', 'pdf_path' => 'pdf/x.pdf']);
    $certificado = Certificate::factory()->create([
        'certificate_type_id' => CertificateType::factory()->create()->id,
        'client_id' => $ajena->client_id,
        'sale_id' => $ajena->id,
    ]);
    $ordenAjena = ServiceOrder::factory()->create(['sede_id' => $this->otraTienda->id, 'client_id' => $ajena->client_id, 'estado' => 'listo_entrega']);
    ServiceOrder::factory()->create(['sede_id' => $this->tienda->id, 'client_id' => $ajena->client_id, 'estado' => 'listo_entrega']);
    Deficiency::factory()->create(['service_order_id' => $ordenAjena->id, 'estado' => 'esperando_autorizacion']);
    $equipo = ['current_team' => $vendedora->currentTeam];

    $this->actingAs($vendedora)->get(route('vendedor.facturacion.pdf', [...$equipo, 'electronic_document' => $documento]))->assertNotFound();
    $this->actingAs($vendedora)->get(route('vendedor.certificados.show', [...$equipo, 'certificate' => $certificado]))->assertNotFound();
    $this->actingAs($vendedora)
        ->get(route('vendedor.certificados.index', $equipo))
        ->assertInertia(fn ($page) => $page->has('certificates.data', 0));

    expect(app(AvisosDelVendedor::class)->contar($vendedora))->toBe(1);
});
