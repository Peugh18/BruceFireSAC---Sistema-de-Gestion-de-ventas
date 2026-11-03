<?php

use App\Actions\Certificates\EmitirCertificadosDeProducto;
use App\Actions\Sales\ConfirmSale;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Certificates\CertificateDocumentData;
use App\Services\Certificates\CertificatePdfService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->garantia = CertificateType::factory()->create([
        'codigo' => 'garantia_mangueras',
        'nombre' => 'Garantía de mangueras',
        'prefijo' => 'GM',
        'vigencia_meses' => 12,
        'checklist' => null,
        'composicion' => [['tipo' => 'datos'], ['tipo' => 'tabla'], ['tipo' => 'texto', 'texto' => 'Doce meses de garantía.']],
        'columnas' => [
            ['clave' => 'producto', 'titulo' => 'Producto', 'tipo' => 'texto', 'obligatorio' => true],
            ['clave' => 'cantidad', 'titulo' => 'Cantidad', 'tipo' => 'numero', 'obligatorio' => true],
            ['clave' => 'marca', 'titulo' => 'Marca', 'tipo' => 'texto', 'obligatorio' => true],
            ['clave' => 'presion', 'titulo' => 'Presión', 'tipo' => 'numero', 'unidad' => 'psi', 'obligatorio' => false],
        ],
    ]);
});

function usuarioDeFichas(string $rol): User
{
    $user = User::factory()->create();
    $user->assignRole($rol);

    return $user;
}

function ventaDeManguera(Product $manguera, int $cantidad = 10, string $estado = 'borrador'): Sale
{
    $sale = Sale::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'estado' => $estado,
        'comprobante_tipo' => 'nota_venta',
        'referencia' => 'Planta Norte - Almacén 2',
    ]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $manguera->id, 'tipo_linea' => 'producto', 'cantidad' => $cantidad]);

    return $sale;
}

test('al confirmar la venta el certificado del producto sale solo con su ficha y la referencia', function () {
    $manguera = Product::factory()->create([
        'nombre' => 'Manguera 1½" 15 m',
        'certificate_type_id' => $this->garantia->id,
        'ficha_tecnica' => ['marca' => 'Xtreme', 'presion' => '250'],
    ]);
    $sale = ventaDeManguera($manguera);

    app(ConfirmSale::class)->handle($sale);

    $certificado = Certificate::where('sale_id', $sale->id)->sole();
    expect($certificado->certificate_type_id)->toBe($this->garantia->id)
        ->and($certificado->referencia)->toBe('Planta Norte - Almacén 2')
        ->and($certificado->datos['filas'])->toEqual([['producto' => 'Manguera 1½" 15 m', 'cantidad' => '10', 'marca' => 'Xtreme', 'presion' => '250']])
        ->and($sale->fresh()->certificados_en_espera)->toBeNull();
});

test('volver a emitir no duplica el certificado del producto', function () {
    $manguera = Product::factory()->create(['certificate_type_id' => $this->garantia->id, 'ficha_tecnica' => ['marca' => 'Xtreme']]);
    $sale = ventaDeManguera($manguera, estado: 'confirmada');

    app(EmitirCertificadosDeProducto::class)->handle($sale);
    app(EmitirCertificadosDeProducto::class)->handle($sale);

    expect(Certificate::where('sale_id', $sale->id)->count())->toBe(1);
});

test('con la ficha incompleta la venta se confirma y el certificado sale cuando almacen la completa', function () {
    $manguera = Product::factory()->create(['certificate_type_id' => $this->garantia->id, 'ficha_tecnica' => null]);
    $sale = ventaDeManguera($manguera);

    app(ConfirmSale::class)->handle($sale);

    expect($sale->fresh()->estado)->toBe('confirmada')
        ->and(Certificate::where('sale_id', $sale->id)->count())->toBe(0)
        ->and($sale->fresh()->certificados_en_espera)->toBe([$this->garantia->id]);

    $almacen = usuarioDeFichas('Almacen');
    $this->actingAs($almacen)
        ->put(route('almacen.fichas.update', ['current_team' => $almacen->currentTeam, 'producto' => $manguera]), [
            'ficha_tecnica' => ['marca' => 'Xtreme', 'presion' => '250', 'ajeno' => 'no se guarda'],
        ])
        ->assertSessionHasNoErrors();

    expect($manguera->fresh()->ficha_tecnica)->toEqual(['marca' => 'Xtreme', 'presion' => '250'])
        ->and(Certificate::where('sale_id', $sale->id)->sole()->datos['filas'][0]['marca'])->toBe('Xtreme')
        ->and($sale->fresh()->certificados_en_espera)->toBeNull();
});

test('almacen ve las fichas pendientes y no guarda una ficha sin los campos obligatorios', function () {
    $manguera = Product::factory()->create(['nombre' => 'Manguera 2"', 'certificate_type_id' => $this->garantia->id]);
    Product::factory()->create(['certificate_type_id' => null]);
    $almacen = usuarioDeFichas('Almacen');
    $equipo = ['current_team' => $almacen->currentTeam];

    $this->actingAs($almacen)->get(route('almacen.fichas.index', $equipo))
        ->assertInertia(fn (Assert $page) => $page
            ->component('almacen/fichas/index')
            ->has('productos', 1)
            ->where('productos.0.nombre', 'Manguera 2"')
            ->where('productos.0.pendiente', true)
            ->where('productos.0.campos', fn ($campos) => collect($campos)->pluck('clave')->all() === ['marca', 'presion']));

    $this->put(route('almacen.fichas.update', [...$equipo, 'producto' => $manguera]), ['ficha_tecnica' => ['presion' => 'mucha']])
        ->assertSessionHasErrors(['ficha_tecnica.marca', 'ficha_tecnica.presion']);

    $vendedor = usuarioDeFichas('Vendedor');
    $this->actingAs($vendedor)->get(route('almacen.fichas.index', ['current_team' => $vendedor->currentTeam]))->assertForbidden();
});

test('un producto no se liga a una plantilla que necesita pruebas o un resultado revisado', function () {
    $conPruebas = CertificateType::factory()->create([
        'codigo' => 'informe_deteccion',
        'composicion' => [['tipo' => 'pruebas']],
        'checklist' => [['texto' => 'Prueba de humo']],
        'columnas' => null,
    ]);
    $gerente = usuarioDeFichas('Gerente');
    $producto = Product::factory()->create();

    $this->actingAs($gerente)
        ->put(route('gerente.productos.update', ['current_team' => $gerente->currentTeam, 'producto' => $producto]), [
            ...$producto->only(['nombre', 'unidad_medida', 'precio_venta']),
            'certificate_type_id' => $conPruebas->id,
        ])
        ->assertSessionHasErrors('certificate_type_id');

    $this->put(route('gerente.productos.update', ['current_team' => $gerente->currentTeam, 'producto' => $producto]), [
        ...$producto->only(['nombre', 'unidad_medida', 'precio_venta']),
        'certificate_type_id' => $this->garantia->id,
    ])->assertSessionHasNoErrors();

    expect($producto->fresh()->certificate_type_id)->toBe($this->garantia->id);
});

test('el certificado de producto se une a su venta por el comprobante y muestra la unidad de cada columna', function () {
    $manguera = Product::factory()->create(['certificate_type_id' => $this->garantia->id, 'ficha_tecnica' => ['marca' => 'Xtreme', 'presion' => '250']]);
    $sale = ventaDeManguera($manguera);
    app(ConfirmSale::class)->handle($sale);

    $datos = app(CertificateDocumentData::class)->desde(Certificate::where('sale_id', $sale->id)->sole());
    $texto = html_entity_decode(strip_tags(app(CertificatePdfService::class)->html($datos)));

    expect($texto)->toContain('Datos del certificado', 'Comprobante', $sale->fresh()->numero_nota_venta, 'Presión (psi)')
        ->not->toContain('Datos del servicio', 'Tipo de atención');
});
