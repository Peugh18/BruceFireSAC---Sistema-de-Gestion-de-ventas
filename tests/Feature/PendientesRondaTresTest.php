<?php

use App\Models\Equipment;
use App\Models\Installment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\Avisos\ExtintoresPorVencer;
use App\Services\Billing\GreenterService;
use App\Services\Certificates\CertificatePdfService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('por vencer trae solo lo que vence en 30 dias o ya vencio', function () {
    $pronto = Equipment::factory()->create(['proxima_fecha_atencion' => today()->addDays(10)]);
    $vencido = Equipment::factory()->create(['proxima_fecha_atencion' => today()->subDays(5)]);
    Equipment::factory()->create(['proxima_fecha_atencion' => today()->addDays(90)]);

    $segmentos = app(ExtintoresPorVencer::class)->segmentos(today());
    $ids = collect($segmentos)->flatten(1)->pluck('equipment_id')->filter()->all();

    expect($ids)->toEqualCanonicalizing([$pronto->id, $vencido->id]);
});

test('el pdf publico del certificado muestra el dni a medias y el interno completo', function () {
    $servicio = app(CertificatePdfService::class);
    $datos = [
        'cliente' => ['documento_tipo' => 'DNI', 'documento' => '45678912'],
        'capacitacion' => ['dni' => '12345678'],
    ];
    $paraQuien = fn (bool $publico) => (fn () => $this->paraQuienLoAbre($datos, $publico))->call($servicio);

    expect($paraQuien(true)['cliente']['documento'])->toBe('45••••12')
        ->and($paraQuien(true)['capacitacion']['dni'])->toBe('12••••78')
        ->and($paraQuien(false)['cliente']['documento'])->toBe('45678912');

    $ruc = (fn () => $this->paraQuienLoAbre(['cliente' => ['documento_tipo' => 'RUC', 'documento' => '20601234565']], true))->call($servicio);
    expect($ruc['cliente']['documento'])->toBe('20601234565');
});

test('el cobro anulado se ve tachado en cobranzas con su motivo', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $sale = Sale::factory()->create(['estado' => 'confirmada', 'vendedor_id' => $vendedor->id]);
    $cuota = Installment::factory()->create(['sale_id' => $sale->id, 'monto' => 300, 'estado' => 'parcial', 'fecha_vencimiento' => today()]);
    $pago = SalePayment::factory()->create(['sale_id' => $sale->id, 'installment_id' => $cuota->id, 'monto' => 50, 'fecha' => today()]);
    $pago->anular('Se cobró dos veces', $vendedor->id);

    $this->actingAs($vendedor)
        ->get(route('vendedor.cobranzas.index', ['current_team' => $vendedor->currentTeam]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('installments.data.0.saldo', 300)
            ->where('installments.data.0.anulados.0.motivo', 'Se cobró dos veces')
            ->where('installments.data.0.anulados.0.por', $vendedor->name));
});

test('par y caja salen a SUNAT con su codigo', function () {
    $codigo = fn (string $unidad) => (fn () => $this->unidadCatalogo03(new Product(['unidad_medida' => $unidad])))->call(app(GreenterService::class));

    expect($codigo('PR'))->toBe('PR')
        ->and($codigo('BX'))->toBe('BX')
        ->and($codigo('KGM'))->toBe('KGM')
        ->and($codigo('NIU'))->toBe('NIU');
});
