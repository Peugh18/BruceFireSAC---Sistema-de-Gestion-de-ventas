<?php

use App\Models\Product;
use App\Models\Sede;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\CatalogoDemoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TipoDeServicioSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

test('la capacitacion crea un usuario por rol con clave aleatoria y no la cambia al repetir', function () {
    $this->seed([RolesAndPermissionsSeeder::class, TipoDeServicioSeeder::class]);

    $this->artisan('sistema:capacitacion')->assertSuccessful();

    expect(User::count())->toBe(5)
        ->and(User::role('TecnicoCampo')->sole()->sede_id)->not->toBeNull()
        ->and(Product::count())->toBeGreaterThan(0)
        // Nunca la clave conocida de las pruebas.
        ->and(Hash::check('password', User::role('Gerente')->sole()->password))->toBeFalse();

    $clave = User::role('Vendedor')->sole()->password;
    $this->artisan('sistema:capacitacion')->expectsOutputToContain('ya existían')->assertSuccessful();
    expect(User::count())->toBe(5)->and(User::role('Vendedor')->sole()->password)->toBe($clave);
});

test('la capacitacion revierte usuarios si falla el catalogo y permite reintentar mostrando las claves', function () {
    $this->seed([RolesAndPermissionsSeeder::class, TipoDeServicioSeeder::class]);
    $this->mock(CatalogoDemoSeeder::class)
        ->shouldReceive('sembrar')
        ->once()
        ->andThrow(new RuntimeException('Catálogo no disponible'));

    expect(fn () => Artisan::call('sistema:capacitacion'))
        ->toThrow(RuntimeException::class, 'Catálogo no disponible');

    expect(User::count())->toBe(0)
        ->and(Sede::count())->toBe(0)
        ->and(Team::count())->toBe(0);

    $this->app->forgetInstance(CatalogoDemoSeeder::class);

    $this->artisan('sistema:capacitacion')
        ->expectsOutputToContain('Anota estas contraseñas')
        ->assertSuccessful();

    expect(User::count())->toBe(5)
        ->and(Product::count())->toBeGreaterThan(0);
});
