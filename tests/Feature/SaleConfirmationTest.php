<?php

use App\Actions\Sales\ConfirmSale;
use App\Contracts\SunatClientInterface;
use App\Models\Client;
use App\Models\ElectronicDocument;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    config(['billing.sunat.cert_path' => base_path('tests/Fixtures/certificates/test-certificate.pem')]);

    $this->app->bind(SunatClientInterface::class, fn () => new class implements SunatClientInterface
    {
        public function send(string $xmlSigned, string $documentName): array
        {
            return ['cdr_zip' => 'fake-zip-content', 'codigo' => 0, 'mensaje' => 'Aceptado', 'notas' => []];
        }
    });
});

if (! function_exists('vendedorUser')) {
    function vendedorUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Vendedor');

        return $user;
    }
}

function draftSaleFor(Client $client, string $comprobanteTipo = 'factura'): Sale
{
    $sale = Sale::factory()->create([
        'client_id' => $client->id,
        'comprobante_tipo' => $comprobanteTipo,
        'condicion_pago' => 'contado',
        'estado' => 'borrador',
        'subtotal' => 100,
        'igv' => 18,
        'total' => 118,
    ]);

    $item = Product::factory()->create(['precio_venta' => 100]);

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $item->id,
        'cantidad' => 1,
        'precio_unitario' => 100,
        'descuento' => 0,
        'subtotal' => 100,
    ]);

    return $sale;
}

test('confirmar una venta con RUC activo y habido la pasa a confirmada y deja el comprobante por enviar', function () {
    $sale = draftSaleFor(Client::factory()->create([
        'estado_contribuyente' => 'ACTIVO',
        'condicion_domicilio' => 'HABIDO',
    ]));

    $confirmed = app(ConfirmSale::class)->handle($sale);

    expect($confirmed->estado)->toBe('confirmada')
        ->and(ElectronicDocument::where('sale_id', $sale->id)->first()?->sunat_estado)->toBe('por_enviar');
});

test('confirmar una venta a un RUC no activo o no habido es bloqueado antes de tocar SUNAT', function () {
    $sale = draftSaleFor(Client::factory()->create([
        'estado_contribuyente' => 'NO ACTIVO',
        'condicion_domicilio' => 'HABIDO',
    ]));

    expect(fn () => app(ConfirmSale::class)->handle($sale))->toThrow(ValidationException::class);

    expect($sale->fresh()->estado)->toBe('borrador')
        ->and(ElectronicDocument::where('sale_id', $sale->id)->exists())->toBeFalse();
});

test('la regla de RUC activo/habido no aplica a boleta con cliente DNI', function () {
    $sale = draftSaleFor(Client::factory()->dni()->create(), 'boleta');

    $confirmed = app(ConfirmSale::class)->handle($sale);

    expect($confirmed->estado)->toBe('confirmada');
});

test('no se puede confirmar una venta que ya no esta en borrador', function () {
    $sale = draftSaleFor(Client::factory()->create());
    $sale->update(['estado' => 'confirmada']);

    expect(fn () => app(ConfirmSale::class)->handle($sale))->toThrow(ValidationException::class);
});

test('endpoint http de confirmar venta funciona para el vendedor', function () {
    $user = vendedorUser();
    $sale = draftSaleFor(Client::factory()->create());
    $sale->update(['vendedor_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('vendedor.ventas.confirmar', ['current_team' => $user->currentTeam, 'sale' => $sale]))
        ->assertRedirect();

    expect($sale->fresh()->estado)->toBe('confirmada');
});

test('los KPIs del listado de ventas solo cuentan las ventas del vendedor autenticado', function () {
    $user = vendedorUser();
    $otroVendedor = vendedorUser();

    $sale = draftSaleFor(Client::factory()->create());
    $sale->update(['vendedor_id' => $user->id, 'fecha' => now()]);

    $ajena = draftSaleFor(Client::factory()->create());
    $ajena->update(['vendedor_id' => $otroVendedor->id, 'fecha' => now(), 'total' => 999]);

    $response = $this->actingAs($user)
        ->get(route('vendedor.ventas.index', ['current_team' => $user->currentTeam]))
        ->assertOk();

    $kpis = $response->viewData('page')['props']['kpis'];

    // La venta propia sigue en borrador: cuenta como borrador, no como vendida.
    expect((float) $kpis['total_vendido'])->toBe(0.0)
        ->and($kpis['borradores'])->toBe(1);

    $sale->update(['estado' => 'confirmada']);
    $ajena->update(['estado' => 'confirmada']);

    $kpis = $this->actingAs($user)
        ->get(route('vendedor.ventas.index', ['current_team' => $user->currentTeam]))
        ->viewData('page')['props']['kpis'];

    expect((float) $kpis['total_vendido'])->toBe(118.0)
        ->and($kpis['ventas'])->toBe(1);
});

test('el listado de ventas muestra solo las de hoy y se filtra por rango de fechas y busqueda', function () {
    $user = vendedorUser();
    $roble = Client::factory()->create(['razon_social' => 'CONSTRUCTORA EL ROBLE S.A.C.']);
    $hoy = Sale::factory()->create(['vendedor_id' => $user->id, 'client_id' => $roble->id, 'estado' => 'confirmada', 'fecha' => today(), 'total' => 100]);
    // Otro cliente con nombre fijo: uno al azar podría llamarse "Robles".
    $otro = Client::factory()->create(['razon_social' => 'FERRETERIA EL PINO S.A.C.', 'numero_documento' => '20601234565']);
    $ayer = Sale::factory()->create(['vendedor_id' => $user->id, 'client_id' => $otro->id, 'estado' => 'confirmada', 'fecha' => today()->subDay(), 'total' => 50, 'condicion_pago' => 'credito']);
    $mesPasado = Sale::factory()->create(['vendedor_id' => $user->id, 'client_id' => $otro->id, 'estado' => 'confirmada', 'fecha' => today()->subDays(40), 'total' => 30]);
    $url = route('vendedor.ventas.index', ['current_team' => $user->currentTeam]);

    $this->actingAs($user)->get($url)->assertInertia(fn ($page) => $page
        ->has('sales.data', 1)
        ->where('sales.data.0.id', $hoy->id)
        ->where('filters.desde', today()->toDateString())
        ->where('kpis.ventas', 1)
        ->where('kpis.total_vendido', 100));

    $this->actingAs($user)->get($url.'?desde='.today()->subDay()->toDateString().'&hasta='.today()->toDateString())
        ->assertInertia(fn ($page) => $page
            ->has('sales.data', 2)
            ->where('kpis.contado', 100)
            ->where('kpis.credito', 50));

    // Fechas al revés se ordenan solas.
    $this->actingAs($user)->get($url.'?desde='.today()->toDateString().'&hasta='.today()->subDays(45)->toDateString())
        ->assertInertia(fn ($page) => $page->has('sales.data', 3));

    $this->actingAs($user)->get($url.'?desde='.today()->subDays(45)->toDateString().'&hasta='.today()->toDateString().'&buscar=roble')
        ->assertInertia(fn ($page) => $page->has('sales.data', 1)->where('sales.data.0.id', $hoy->id));

    expect($ayer->id)->not->toBe($mesPasado->id);
});
