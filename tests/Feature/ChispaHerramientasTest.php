<?php

use App\Models\Client;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Services\Chispa\Herramientas;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    config(['services.chispa.provider' => 'deepseek', 'services.chispa.deepseek.key' => 'clave-de-prueba']);
});

function chispaConRol(string $rol): User
{
    $user = User::factory()->create();
    $user->assignRole($rol);

    return $user;
}

/** DeepSeek pide una herramienta y luego responde con texto. */
function chispaDeepseekLlama(string $herramienta, array $args, string $final = 'Te dejé todo listo, revísalo.'): void
{
    Http::fake(['api.deepseek.com/*' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => '', 'tool_calls' => [['id' => 'c1', 'type' => 'function', 'function' => ['name' => $herramienta, 'arguments' => json_encode($args)]]]]]]])
        ->push(['choices' => [['message' => ['content' => $final]]]]),
    ]);
}

test('el vendedor dicta una factura y chispa deja la venta llenada para que la emita el', function () {
    $vendedor = chispaConRol('Vendedor');
    $cliente = Client::factory()->create(['tipo_documento' => 'ruc', 'numero_documento' => '20481234567']);
    $pqs = Product::factory()->create(['codigo' => 'PRD-0001', 'precio_venta' => 70, 'serializado' => true]);
    InventoryUnit::factory()->count(2)->create(['product_id' => $pqs->id, 'estado' => 'disponible']);
    chispaDeepseekLlama('preparar_venta', ['documento_cliente' => '20481234567', 'comprobante' => 'factura', 'items' => [['codigo' => 'PRD-0001', 'cantidad' => 2]]]);

    $respuesta = $this->actingAs($vendedor)->postJson(route('asistente'), ['mensajes' => [['role' => 'user', 'content' => 'factura al 20481234567, dos PQS de 6 kilos']]])
        ->assertOk()
        ->assertJsonPath('fuente', 'deepseek')
        ->assertJsonPath('acciones.0.titulo', 'Factura')
        ->assertJsonPath('acciones.0.total', 140);

    // Nada se guardó: la venta solo se abre llenada.
    expect(Sale::count())->toBe(0);
    Http::assertSent(fn (Request $r) => collect($r['tools'] ?? [])->pluck('function.name')->contains('preparar_venta')
        && ! collect($r['tools'] ?? [])->pluck('function.name')->contains('preparar_cambio_precio'));

    $this->actingAs($vendedor)->get($respuesta->json('acciones.0.url'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('venta.de_chispa', true)
            ->where('venta.comprobante_tipo', 'factura')
            ->where('venta.client.id', $cliente->id)
            ->has('venta.items', 2));
});

test('con servicios chispa no arma la venta: pide cotizacion', function () {
    $vendedor = chispaConRol('Vendedor');
    Client::factory()->create(['tipo_documento' => 'dni', 'numero_documento' => '40405210']);
    Service::factory()->create(['codigo' => 'SRV-0001', 'precio_venta' => 35]);

    $resultado = app(Herramientas::class)->ejecutar($vendedor, 'preparar_venta', ['documento_cliente' => '40405210', 'comprobante' => 'boleta', 'items' => [['codigo' => 'SRV-0001', 'cantidad' => 1]]]);
    expect($resultado['accion'])->toBeNull()->and($resultado['datos']['error'])->toContain('preparar_cotizacion');

    $cotizacion = app(Herramientas::class)->ejecutar($vendedor, 'preparar_cotizacion', ['documento_cliente' => '40405210', 'items' => [['codigo' => 'SRV-0001', 'cantidad' => 3]]]);
    $this->actingAs($vendedor)->get($cotizacion['accion']['url'])
        ->assertInertia(fn (Assert $page) => $page->where('chispa.items.0.cantidad', 3));
});

test('el almacen dicta lo que llego y chispa abre la recepcion llenada', function () {
    $almacen = chispaConRol('Almacen');
    $guantes = Product::factory()->create(['codigo' => 'PRD-0013', 'serializado' => false]);
    chispaDeepseekLlama('preparar_recepcion', ['proveedor' => 'Extintores del Norte', 'items' => [['codigo' => 'PRD-0013', 'cantidad' => 10]]]);

    $url = $this->actingAs($almacen)->postJson(route('asistente'), ['mensajes' => [['role' => 'user', 'content' => 'llegaron 10 guantes']]])
        ->assertOk()->json('acciones.0.url');

    $this->actingAs($almacen)->get($url)
        ->assertInertia(fn (Assert $page) => $page
            ->where('borrador.proveedor', 'Extintores del Norte')
            ->where('borrador.items.0.product_id', $guantes->id)
            ->where('borrador.items.0.cantidad', 10));
});

test('el gerente confirma un cambio de precio y el vendedor no puede', function () {
    $gerente = chispaConRol('Gerente');
    $recarga = Service::factory()->create(['codigo' => 'SRV-0001', 'precio_venta' => 35]);

    $this->actingAs(chispaConRol('Vendedor'))->postJson(route('asistente.precio'))->assertForbidden();

    $preparado = $this->actingAs($gerente);
    app(Herramientas::class)->ejecutar($gerente, 'preparar_cambio_precio', ['codigo' => 'SRV-0001', 'precio' => 40]);
    expect($recarga->fresh()->precio_venta)->toEqual(35);

    $preparado->postJson(route('asistente.precio'))->assertOk()->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'S/ 40.00'));
    expect((float) $recarga->fresh()->precio_venta)->toBe(40.0);

    // Se usa una sola vez.
    $this->actingAs($gerente)->postJson(route('asistente.precio'))->assertStatus(422);
});

test('una herramienta de otro rol no se ejecuta', function () {
    $resultado = app(Herramientas::class)->ejecutar(chispaConRol('Almacen'), 'deudores', []);

    expect($resultado['datos'])->toHaveKey('error');
});
