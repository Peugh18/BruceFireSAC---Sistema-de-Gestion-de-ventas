<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;

test('seeder asigna exactamente los permisos de VENDEDOR_PERMISSIONS al rol Vendedor', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $vendedor = Role::findByName('Vendedor', 'web');

    $actualPermissions = $vendedor->permissions->pluck('name')->sort()->values()->all();
    $expectedPermissions = collect(RolesAndPermissionsSeeder::VENDEDOR_PERMISSIONS)->sort()->values()->all();

    expect($actualPermissions)->toBe($expectedPermissions)
        ->and(count($actualPermissions))->toBe(count(RolesAndPermissionsSeeder::VENDEDOR_PERMISSIONS));
});

test('seeder asigna exactamente los permisos de ALMACEN_PERMISSIONS al rol Almacen', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $almacen = Role::findByName('Almacen', 'web');

    $actualPermissions = $almacen->permissions->pluck('name')->sort()->values()->all();
    $expectedPermissions = collect(RolesAndPermissionsSeeder::ALMACEN_PERMISSIONS)->sort()->values()->all();

    expect($actualPermissions)->toBe($expectedPermissions)
        ->and(count($actualPermissions))->toBe(count(RolesAndPermissionsSeeder::ALMACEN_PERMISSIONS));
});

test('seeder asigna exactamente los permisos de TECNICO_PLANTA_PERMISSIONS al rol TecnicoPlanta', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $planta = Role::findByName('TecnicoPlanta', 'web');

    $actualPermissions = $planta->permissions->pluck('name')->sort()->values()->all();
    $expectedPermissions = collect(RolesAndPermissionsSeeder::TECNICO_PLANTA_PERMISSIONS)->sort()->values()->all();

    expect($actualPermissions)->toBe($expectedPermissions)
        ->and(count($actualPermissions))->toBe(count(RolesAndPermissionsSeeder::TECNICO_PLANTA_PERMISSIONS));
});

test('seeder asigna exactamente los permisos de TECNICO_CAMPO_PERMISSIONS al rol TecnicoCampo', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $campo = Role::findByName('TecnicoCampo', 'web');

    $actualPermissions = $campo->permissions->pluck('name')->sort()->values()->all();
    $expectedPermissions = collect(RolesAndPermissionsSeeder::TECNICO_CAMPO_PERMISSIONS)->sort()->values()->all();

    expect($actualPermissions)->toBe($expectedPermissions)
        ->and(count($actualPermissions))->toBe(count(RolesAndPermissionsSeeder::TECNICO_CAMPO_PERMISSIONS));
});

test('seeder crea el rol Gerente vacio sin permisos asignados', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $gerente = Role::findByName('Gerente', 'web');

    expect($gerente)->not->toBeNull()
        ->and($gerente->name)->toBe('Gerente')
        ->and($gerente->permissions)->toBeEmpty();
});
