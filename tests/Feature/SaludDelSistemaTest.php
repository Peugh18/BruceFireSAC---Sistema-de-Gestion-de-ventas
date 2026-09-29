<?php

use App\Models\Installment;
use App\Models\MlEntrenamiento;
use App\Models\Sale;
use App\Models\User;
use App\Services\Ml\CargaHistorico;
use App\Services\SaludDelSistema;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('la revision diaria detecta datos que no cuadran y el Gerente la ve en su panel', function () {
    $venta = Sale::factory()->create(['condicion_pago' => 'credito', 'estado' => 'confirmada', 'total' => 500]);
    Installment::factory()->create(['sale_id' => $venta->id, 'monto' => 300]);

    $this->artisan('sistema:verificar')->assertSuccessful();

    $gerente = User::factory()->create();
    $gerente->assignRole('Gerente');

    $this->actingAs($gerente)
        ->get(route('gerente.dashboard', ['current_team' => $gerente->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sistema.programadorActivo', false)
            ->where('sistema.problemas', fn ($problemas) => collect($problemas)->contains('clave', 'cuotas_no_suman'))
        );
});

test('el panel sabe si las tareas automaticas estan corriendo por su latido', function () {
    $salud = app(SaludDelSistema::class);

    Cache::forever(SaludDelSistema::CACHE_LATIDO, now()->subMinutes(30)->toIso8601String());
    expect($salud->resumen()['programadorActivo'])->toBeFalse();

    Cache::forever(SaludDelSistema::CACHE_LATIDO, now()->toIso8601String());
    expect($salud->resumen()['programadorActivo'])->toBeTrue();
});

test('ml:reentrenar entrena, guarda la precision en el historial y recalcula', function () {
    Process::fake(['*' => Process::result(output: 'Modelo exportado')]);
    app(CargaHistorico::class)->reemplazar([
        ['fecha' => '2025-01-10', 'tipo_doc' => 'F', 'comprobante' => 'F001-1', 'documento_cliente' => '20111111111', 'nombre_cliente' => 'X', 'categoria' => 'extintor', 'producto_original' => 'EXTINTOR', 'cantidad' => 1, 'total' => 70, 'archivo_origen' => 'a'],
    ]);

    $this->artisan('ml:reentrenar', ['--cortes' => '2025-02-01'])->assertSuccessful();

    Process::assertRan(fn ($proceso) => str_contains(implode(' ', (array) $proceso->command), 'train_retention_model.py'));
    expect(MlEntrenamiento::query()->count())->toBe(1)
        ->and((float) MlEntrenamiento::query()->value('roc_auc'))->toBeGreaterThan(0.5);
});

test('si Python falla, ml:reentrenar avisa y no toca el historial', function () {
    Process::fake(['*' => Process::result(errorOutput: 'No module named xgboost', exitCode: 1)]);
    app(CargaHistorico::class)->reemplazar([
        ['fecha' => '2025-01-10', 'tipo_doc' => 'F', 'comprobante' => 'F001-1', 'documento_cliente' => '20111111111', 'nombre_cliente' => 'X', 'categoria' => 'extintor', 'producto_original' => 'EXTINTOR', 'cantidad' => 1, 'total' => 70, 'archivo_origen' => 'a'],
    ]);

    $this->artisan('ml:reentrenar', ['--cortes' => '2025-02-01'])
        ->expectsOutputToContain('No module named xgboost')
        ->assertFailed();

    expect(MlEntrenamiento::query()->count())->toBe(0);
});
