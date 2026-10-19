<?php

use App\Models\Installment;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    $this->vendedor = User::factory()->create();
    $this->vendedor->assignRole('Vendedor');
    $this->actingAs($this->vendedor);
    $this->venta = Sale::factory()->create([
        'vendedor_id' => $this->vendedor->id,
        'estado' => 'confirmada', 'comprobante_tipo' => 'factura',
        'condicion_pago' => 'contado', 'total' => 1180,
        'codigo_detraccion_snapshot' => '022', 'monto_detraccion_snapshot' => 141.60,
    ]);
    $this->ruta = route('vendedor.ventas.detraccion.store', ['current_team' => $this->vendedor->currentTeam, 'sale' => $this->venta]);
});

test('acredita fecha real y guarda evidencia privada sin ingreso a caja', function () {
    $fecha = today()->subDays(3)->toDateString();
    $this->post($this->ruta, [
        'monto' => 141.60, 'numero_operacion' => '  bn-001  ', 'fecha_deposito' => $fecha,
        'evidencia' => UploadedFile::fake()->create('constancia.pdf', 80, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $pago = $this->venta->payments()->sole();
    expect($pago->fecha->toDateString())->toBe($fecha)
        ->and($pago->numero_operacion)->toBe('BN-001')
        ->and($pago->cash_register_id)->toBeNull()->and($pago->es_detraccion)->toBeTrue();
    Storage::disk('local')->assertExists($pago->constancia_path);
    $ruta = route('vendedor.ventas.detraccion.evidencia', ['current_team' => $this->vendedor->currentTeam, 'sale' => $this->venta, 'payment' => $pago]);
    $this->get($ruta)->assertOk();
    $otro = User::factory()->create();
    $otro->assignRole('Vendedor');
    $this->actingAs($otro)->get(route('vendedor.ventas.detraccion.evidencia', ['current_team' => $otro->currentTeam, 'sale' => $this->venta, 'payment' => $pago]))->assertNotFound();
});

test('exige fecha real pasada o de hoy y rechaza evidencia de contenido no permitido', function () {
    $datos = ['monto' => 30, 'numero_operacion' => 'BN-002'];
    $this->post($this->ruta, $datos)->assertSessionHasErrors('fecha_deposito');
    $this->post($this->ruta, [...$datos, 'fecha_deposito' => today()->addDay()->toDateString()])->assertSessionHasErrors('fecha_deposito');
    $this->post($this->ruta, [...$datos, 'fecha_deposito' => today()->toDateString(), 'evidencia' => UploadedFile::fake()->create('mal.html', 1, 'text/html')])->assertSessionHasErrors('evidencia');
    expect($this->venta->payments()->count())->toBe(0);
});

test('normaliza la constancia y no permite duplicar la misma acreditacion parcial', function () {
    $datos = ['monto' => 30, 'numero_operacion' => ' bn-003 ', 'fecha_deposito' => today()->toDateString()];
    $this->post($this->ruta, $datos)->assertSessionHasNoErrors();
    $this->post($this->ruta, [...$datos, 'numero_operacion' => 'BN-003'])->assertSessionHasErrors('numero_operacion');
    expect($this->venta->payments()->count())->toBe(1);
});

test('una constancia masiva puede aplicarse a cuotas distintas sin superar el spot de la venta', function () {
    $this->venta->update(['condicion_pago' => 'credito']);
    $cuotas = collect([1, 2])->map(fn ($numero) => Installment::factory()->create(['sale_id' => $this->venta->id, 'numero_cuota' => $numero, 'monto' => 590]));
    $datos = ['monto' => 70.80, 'forma_pago' => 'deposito', 'es_detraccion' => true, 'numero_operacion' => 'BN-MASIVA', 'fecha_deposito' => today()->subDay()->toDateString()];
    foreach ($cuotas as $cuota) {
        $ruta = route('vendedor.cobranzas.pagar', ['current_team' => $this->vendedor->currentTeam, 'installment' => $cuota]);
        $this->post($ruta, $datos)->assertSessionHasNoErrors();
    }
    expect((float) $this->venta->payments()->where('es_detraccion', true)->sum('monto'))->toBe(141.60);
    expect($this->venta->payments()->whereNotNull('cash_register_id')->count())->toBe(0);
});

test('el cobro comercial no absorbe el importe de la detraccion pendiente', function () {
    $this->venta->update(['condicion_pago' => 'credito']);
    $cuota = Installment::factory()->create(['sale_id' => $this->venta->id, 'monto' => 1180]);
    $ruta = route('vendedor.cobranzas.pagar', ['current_team' => $this->vendedor->currentTeam, 'installment' => $cuota]);
    $this->post($ruta, ['monto' => 1180, 'forma_pago' => 'transferencia'])->assertSessionHasErrors('monto');
    $this->post($ruta, ['monto' => 1038.40, 'forma_pago' => 'transferencia'])->assertSessionHasNoErrors();
    expect($cuota->fresh()->saldo())->toBe(141.60);
});
