<?php

use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\User;
use App\Services\Quotes\QuotePdfService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->vendedor = User::factory()->create();
    $this->vendedor->assignRole('Vendedor');
});

function cotizacionConItems(array $cliente = [], array $datos = []): Quote
{
    $quote = Quote::factory()->create([
        'client_id' => Client::factory()->create($cliente)->id,
        'vendedor_id' => test()->vendedor->id,
        ...$datos,
    ]);
    QuoteItem::factory()->count(2)->create(['quote_id' => $quote->id]);

    return $quote;
}

test('el vendedor abre y descarga el pdf de la cotizacion', function () {
    $quote = cotizacionConItems();
    $ruta = ['current_team' => $this->vendedor->currentTeam, 'quote' => $quote];

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.cotizaciones.pdf', $ruta))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.cotizaciones.pdf', [...$ruta, 'descargar' => 1]))
        ->assertOk()
        ->assertDownload("cotizacion-{$quote->numero}.pdf");
});

test('el cliente abre la cotizacion con el enlace firmado y sin firma no entra', function () {
    $quote = cotizacionConItems();

    $this->get(app(QuotePdfService::class)->enlacePublico($quote))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->get(route('cotizaciones.publico', ['quote' => $quote]))->assertForbidden();
});

test('la lista trae el whatsapp del cliente con el 51 y el enlace del pdf', function () {
    cotizacionConItems(['whatsapp' => '987 654 321']);

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.cotizaciones.index', ['current_team' => $this->vendedor->currentTeam]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('quotes.data.0.whatsapp', '51987654321')
            ->where('quotes.data.0.enlace_pdf', fn (string $enlace) => str_contains($enlace, '/cotizacion/') && str_contains($enlace, 'signature=')));
});

test('una cotizacion convertida enlaza a su venta', function () {
    $quote = cotizacionConItems(datos: ['estado' => 'convertida']);
    $sale = Sale::factory()->create(['quote_id' => $quote->id, 'client_id' => $quote->client_id, 'numero_interno' => 'VTA-0077']);

    $this->actingAs($this->vendedor)
        ->get(route('vendedor.cotizaciones.index', ['current_team' => $this->vendedor->currentTeam]))
        ->assertInertia(fn ($page) => $page
            ->where('quotes.data.0.venta.id', $sale->id)
            ->where('quotes.data.0.venta.numero', 'VTA-0077'));
});
