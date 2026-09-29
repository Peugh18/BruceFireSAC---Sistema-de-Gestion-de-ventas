<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

test('en produccion el seeder no crea usuarios de demostracion', function () {
    app()->detectEnvironment(fn () => 'production');

    app(DatabaseSeeder::class)->setContainer(app())->__invoke();

    expect(User::query()->count())->toBe(0);
});

test('sistema:crear-gerente crea el primer Gerente con la empresa y una contrasena aleatoria', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->artisan('sistema:crear-gerente', ['email' => 'Dueno@BruceFire.pe', 'nombre' => 'Dueño Bruce Fire'])
        ->expectsOutputToContain('Gerente creado: dueno@brucefire.pe')
        ->assertSuccessful();

    $gerente = User::query()->where('email', 'dueno@brucefire.pe')->sole();

    expect($gerente->hasRole('Gerente'))->toBeTrue()
        ->and($gerente->currentTeam?->name)->toBe('BRUCE FIRE')
        ->and($gerente->email_verified_at)->not->toBeNull();

    $this->artisan('sistema:crear-gerente', ['email' => 'dueno@brucefire.pe', 'nombre' => 'Otro'])->assertFailed();
});
