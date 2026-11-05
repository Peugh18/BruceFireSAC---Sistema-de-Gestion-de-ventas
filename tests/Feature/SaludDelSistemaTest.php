<?php

use App\Models\Installment;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaludDelSistema;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Cache;
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

test('el arranque de desarrollo incluye el programador de tareas', function () {
    $commands = collect(DevCommands::commands());

    expect($commands->firstWhere('name', 'scheduler')['command'] ?? null)
        ->toBe('php artisan schedule:work');
});
