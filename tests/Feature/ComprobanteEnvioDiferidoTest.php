<?php

use App\Actions\Billing\AnularVentaPorEnviar;
use App\Actions\Billing\IssueCreditNote;
use App\Actions\Sales\ConfirmSale;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\EditarVentaEmitida;
use App\Contracts\SunatClientInterface;
use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\ElectronicDocument;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');

    config([
        'billing.sunat.cert_path' => base_path('tests/Fixtures/certificates/test-certificate.pem'),
        'billing.envio_diferido_horas' => 6,
    ]);

    $this->sunat = new class implements SunatClientInterface
    {
        public int $enviados = 0;

        public bool $caido = false;

        public function send(string $xmlSigned, string $documentName): array
        {
            if ($this->caido) {
                throw new RuntimeException('SUNAT no responde');
            }

            $this->enviados++;

            return ['cdr_zip' => 'fake-zip-content', 'codigo' => 0, 'mensaje' => 'Aceptado', 'notas' => []];
        }
    };

    $this->app->instance(SunatClientInterface::class, $this->sunat);
});

if (! function_exists('vendedorUser')) {
    function vendedorUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Vendedor');

        return $user;
    }
}

function ventaConfirmada(Client $client, string $comprobante = 'factura', ?User $vendedor = null): Sale
{
    $sede = Sede::factory()->almacen()->create();
    $product = Product::factory()->create();
    $unit = InventoryUnit::factory()->create([
        'product_id' => $product->id,
        'sede_almacen_id' => $sede->id,
        'estado' => 'disponible',
    ]);

    $sale = app(CreateSale::class)->handle([
        'client_id' => $client->id,
        'sede_id' => $sede->id,
        'fecha' => now()->toDateString(),
        'destino' => 'local_cliente',
        'condicion_pago' => 'contado',
        'comprobante_tipo' => $comprobante,
    ], [
        [
            'tipo_linea' => 'unidad_nueva',
            'numero_serie' => $unit->numero_serie,
            'product_id' => $product->id,
            'cantidad' => 1,
            'precio_unitario' => 65,
        ],
    ], ($vendedor ?? vendedorUser())->id);

    return app(ConfirmSale::class)->handle($sale);
}

/**
 * Edita una venta emitida con el mismo formulario: parte de lo que ya tiene
 * y cambia solo lo indicado.
 *
 * @param  array<string, mixed>  $cambios
 */
function editarVenta(Sale $sale, array $cambios = [], ?array $items = null): Sale
{
    $sale->load('items.inventoryUnit');

    return app(EditarVentaEmitida::class)->handle($sale, [
        'client_id' => $sale->client_id,
        'sede_id' => $sale->sede_id,
        'destino' => $sale->destino,
        'condicion_pago' => 'contado',
        'medio_pago' => 'efectivo',
        'comprobante_tipo' => $sale->comprobante_tipo,
        ...$cambios,
    ], $items ?? $sale->items->map(fn ($item) => [
        'tipo_linea' => 'unidad_nueva',
        'numero_serie' => $item->inventoryUnit->numero_serie,
        'product_id' => $item->product_id,
        'cantidad' => 1,
        'precio_unitario' => (float) $item->precio_unitario,
    ])->all(), $sale->vendedor_id);
}

function comprobanteDe(Sale $sale): ElectronicDocument
{
    return $sale->electronicDocuments()->latest('id')->firstOrFail();
}

test('al confirmar la factura queda por enviar seis horas con su xml y pdf listos, sin tocar SUNAT', function () {
    $this->freezeTime();

    $documento = comprobanteDe(ventaConfirmada(Client::factory()->create()));

    expect($documento->sunat_estado)->toBe('por_enviar')
        ->and($documento->serie.'-'.$documento->correlativo)->toBe('F001-1')
        ->and($documento->enviar_desde->toDateTimeString())->toBe(now()->addHours(6)->toDateTimeString())
        ->and($this->sunat->enviados)->toBe(0);

    Storage::disk('local')->assertExists([$documento->xml_path, $documento->pdf_path]);
});

test('el envio automatico solo manda los comprobantes cuya ventana ya vencio', function () {
    $documento = comprobanteDe(ventaConfirmada(Client::factory()->create()));

    $this->travel(5)->hours();
    $this->artisan('billing:enviar-programados')->assertSuccessful();
    expect($documento->fresh()->sunat_estado)->toBe('por_enviar');

    $this->travel(2)->hours();
    $this->artisan('billing:enviar-programados')->assertSuccessful();

    expect($documento->fresh()->sunat_estado)->toBe('aceptado')
        ->and($documento->fresh()->enviado_at)->not->toBeNull()
        ->and($this->sunat->enviados)->toBe(1);
});

test('si SUNAT no responde el comprobante sigue por enviar y se reintenta en la siguiente pasada', function () {
    $documento = comprobanteDe(ventaConfirmada(Client::factory()->create()));
    $this->travel(7)->hours();

    $this->sunat->caido = true;
    $this->artisan('billing:enviar-programados')->assertSuccessful();
    expect($documento->fresh()->sunat_estado)->toBe('por_enviar');

    $this->sunat->caido = false;
    $this->artisan('billing:enviar-programados')->assertSuccessful();
    expect($documento->fresh()->sunat_estado)->toBe('aceptado');
});

test('enviar ya manda el comprobante a SUNAT sin esperar la ventana', function () {
    $user = vendedorUser();
    $sale = ventaConfirmada(Client::factory()->create(), vendedor: $user);

    $this->actingAs($user)
        ->post(route('vendedor.ventas.enviar-sunat', ['current_team' => $user->currentTeam, 'sale' => $sale]))
        ->assertRedirect();

    expect(comprobanteDe($sale)->sunat_estado)->toBe('aceptado');
});

test('pasar una factura por enviar a boleta libera su numero para la siguiente factura', function () {
    $user = vendedorUser();
    $equivocada = ventaConfirmada(Client::factory()->create(), vendedor: $user);
    ventaConfirmada(Client::factory()->create(), vendedor: $user);
    $clienteDni = Client::factory()->dni()->create();

    $equivocada->load('items.inventoryUnit');
    $item = $equivocada->items->first();

    $this->actingAs($user)
        ->put(route('vendedor.ventas.update', ['current_team' => $user->currentTeam, 'sale' => $equivocada]), [
            'client_id' => $clienteDni->id,
            'sede_id' => $equivocada->sede_id,
            'fecha' => now()->toDateString(),
            'destino' => 'local_cliente',
            'condicion_pago' => 'contado',
            'medio_pago' => 'efectivo',
            'comprobante_tipo' => 'boleta',
            'items' => [['tipo_linea' => 'unidad_nueva', 'numero_serie' => $item->inventoryUnit->numero_serie, 'product_id' => $item->product_id, 'cantidad' => 1, 'precio_unitario' => 65]],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('vendedor.ventas.show', ['current_team' => $user->currentTeam, 'sale' => $equivocada]));

    $boleta = comprobanteDe($equivocada);

    expect($boleta->serie.'-'.$boleta->correlativo)->toBe('B001-1')
        ->and($boleta->sunat_estado)->toBe('por_enviar')
        ->and($equivocada->fresh()->comprobante_tipo)->toBe('boleta')
        ->and($equivocada->fresh()->client_id)->toBe($clienteDni->id)
        ->and($equivocada->electronicDocuments()->count())->toBe(1);

    $siguiente = comprobanteDe(ventaConfirmada(Client::factory()->create(), vendedor: $user));

    expect($siguiente->serie.'-'.$siguiente->correlativo)->toBe('F001-1');
});

test('corregir solo el cliente de un comprobante por enviar mantiene su numero y regenera el pdf', function () {
    $sale = ventaConfirmada(Client::factory()->create());
    $antes = comprobanteDe($sale);
    $otroRuc = Client::factory()->create();

    $documento = comprobanteDe(editarVenta($sale, ['client_id' => $otroRuc->id]));

    expect($documento->id)->toBe($antes->id)
        ->and($documento->correlativo)->toBe($antes->correlativo)
        ->and($sale->fresh()->client_id)->toBe($otroRuc->id)
        ->and($sale->items()->first()->equipment->client_id)->toBe($otroRuc->id);

    Storage::disk('local')->assertExists($documento->pdf_path);
});

test('editar productos y precios de una factura por enviar conserva la venta y el numero', function () {
    $sale = ventaConfirmada(Client::factory()->create());
    $antes = comprobanteDe($sale);
    $item = $sale->items()->with('inventoryUnit')->first();
    $unidadAnterior = $item->inventoryUnit;
    $otra = InventoryUnit::factory()->create([
        'product_id' => $item->product_id,
        'sede_almacen_id' => $unidadAnterior->sede_almacen_id,
        'estado' => 'disponible',
    ]);

    $editada = editarVenta($sale, items: [[
        'tipo_linea' => 'unidad_nueva',
        'numero_serie' => $otra->numero_serie,
        'product_id' => $item->product_id,
        'cantidad' => 1,
        'precio_unitario' => 80,
    ]]);
    $documento = comprobanteDe($editada);

    expect($editada->id)->toBe($sale->id)
        ->and($editada->estado)->toBe('confirmada')
        ->and($editada->numero_interno)->toBe($sale->numero_interno)
        ->and((float) $editada->total)->toBe(80.0)
        ->and($documento->id)->toBe($antes->id)
        ->and($documento->correlativo)->toBe($antes->correlativo)
        ->and($documento->sunat_estado)->toBe('por_enviar')
        ->and($unidadAnterior->fresh()->estado)->toBe('disponible')
        ->and($otra->fresh()->estado)->toBe('vendido')
        ->and((float) $editada->payments()->sum('monto'))->toBe(80.0)
        ->and(Sale::count())->toBe(1);
});

test('una venta aceptada por SUNAT no se abre para editar', function () {
    $user = vendedorUser();
    $sale = ventaConfirmada(Client::factory()->create(), vendedor: $user);
    $this->travel(7)->hours();
    $this->artisan('billing:enviar-programados');

    $this->actingAs($user)
        ->get(route('vendedor.ventas.edit', ['current_team' => $user->currentTeam, 'sale' => $sale]))
        ->assertRedirect(route('vendedor.ventas.show', ['current_team' => $user->currentTeam, 'sale' => $sale]));

    expect($sale->fresh()->load('electronicDocuments')->sePuedeEditar())->toBeFalse();
});

test('no se puede corregir a factura para un cliente con dni', function () {
    $sale = ventaConfirmada(Client::factory()->dni()->create(), 'boleta');

    expect(fn () => editarVenta($sale, ['comprobante_tipo' => 'factura']))
        ->toThrow(ValidationException::class, 'La factura solo se emite a clientes con RUC');
});

test('un comprobante ya aceptado por SUNAT solo se corrige con nota de credito', function () {
    $sale = ventaConfirmada(Client::factory()->create());
    $this->travel(7)->hours();
    $this->artisan('billing:enviar-programados');

    expect(fn () => editarVenta($sale, ['comprobante_tipo' => 'boleta', 'client_id' => Client::factory()->dni()->create()->id]))
        ->toThrow(ValidationException::class, 'nota de crédito');
});

test('un comprobante rechazado se corrige emitiendo uno nuevo con otro numero y fecha de hoy', function () {
    $sale = ventaConfirmada(Client::factory()->dni()->create(), 'boleta');
    $rechazado = comprobanteDe($sale);
    $rechazado->update(['sunat_estado' => 'rechazado', 'fecha_emision' => now()->subDays(5)]);

    $nuevo = comprobanteDe(editarVenta($sale));

    expect($nuevo->id)->not->toBe($rechazado->id)
        ->and($nuevo->correlativo)->toBe($rechazado->correlativo + 1)
        ->and($nuevo->fecha_emision->isToday())->toBeTrue()
        ->and($nuevo->sunat_estado)->toBe('por_enviar')
        ->and($rechazado->fresh()->sunat_estado)->toBe('rechazado');
});

test('anular una venta por enviar devuelve la unidad al stock y el numero a la serie', function () {
    $sale = ventaConfirmada(Client::factory()->create());
    $unidad = $sale->items()->first()->inventoryUnit;

    app(AnularVentaPorEnviar::class)->handle($sale);

    expect($sale->fresh()->estado)->toBe('anulada')
        ->and($unidad->fresh()->estado)->toBe('disponible')
        ->and(ElectronicDocument::count())->toBe(0);

    $siguiente = comprobanteDe(ventaConfirmada(Client::factory()->create()));
    expect($siguiente->correlativo)->toBe(1);
});

test('con cero horas de ventana el comprobante se envia al confirmar', function () {
    config(['billing.envio_diferido_horas' => 0]);

    expect(comprobanteDe(ventaConfirmada(Client::factory()->create()))->sunat_estado)->toBe('aceptado');
});

test('no se emite nota de credito sobre un comprobante que aun no se envio a SUNAT', function () {
    $documento = comprobanteDe(ventaConfirmada(Client::factory()->create()));

    expect(fn () => app(IssueCreditNote::class)->handle($documento, '01', 'Anulación', 76.7))
        ->toThrow(ValidationException::class, 'solo se emite sobre un comprobante aceptado');
});

test('si el gerente cambia el diseño, el pdf ya emitido se vuelve a dibujar al descargarlo', function () {
    $user = vendedorUser();
    $documento = comprobanteDe(ventaConfirmada(Client::factory()->create(), vendedor: $user));
    $antes = Storage::disk('local')->get($documento->pdf_path);

    $this->travel(5)->minutes();
    CompanySetting::current()->update(['color_marca' => '#1F4E79', 'mensaje_agradecimiento' => 'Gracias por confiar en nosotros']);
    $this->travel(1)->minutes();

    $this->actingAs($user)
        ->get(route('vendedor.facturacion.pdf', ['current_team' => $user->currentTeam, 'electronic_document' => $documento]))
        ->assertOk();

    expect(Storage::disk('local')->get($documento->fresh()->pdf_path))->not->toBe($antes);
});

test('un pdf generado con la plantilla anterior se vuelve a dibujar al descargarlo', function () {
    $user = vendedorUser();
    $documento = comprobanteDe(ventaConfirmada(Client::factory()->create(), vendedor: $user));

    // Simula un PDF hecho antes de instalar la versión actual de la plantilla,
    // con los datos de la empresa sin tocar desde antes de ese PDF.
    $plantilla = max(filemtime(resource_path('views/pdf/comprobante.blade.php')), filemtime(app_path('Services/Billing/ComprobantePdfService.php')));
    Storage::disk('local')->put($documento->pdf_path, '%PDF-viejo');
    touch(Storage::disk('local')->path($documento->pdf_path), $plantilla - 3600);
    $empresa = CompanySetting::current();
    $empresa->timestamps = false;
    $empresa->forceFill(['updated_at' => now()->setTimestamp($plantilla - 7200)])->save();

    $this->actingAs($user)
        ->get(route('vendedor.facturacion.pdf', ['current_team' => $user->currentTeam, 'electronic_document' => $documento]))
        ->assertOk();

    expect(Storage::disk('local')->get($documento->fresh()->pdf_path))->not->toBe('%PDF-viejo')
        ->and(Storage::disk('local')->get($documento->fresh()->pdf_path))->toStartWith('%PDF-');
});

test('billing:redibujar-pdf vuelve a dibujar todas las facturas y boletas', function () {
    $documento = comprobanteDe(ventaConfirmada(Client::factory()->create()));
    Storage::disk('local')->put($documento->pdf_path, '%PDF-viejo');

    $this->artisan('billing:redibujar-pdf')
        ->expectsOutputToContain('1 de 1 comprobante(s) redibujados')
        ->assertSuccessful();

    expect(Storage::disk('local')->get($documento->fresh()->pdf_path))->not->toBe('%PDF-viejo');
});
