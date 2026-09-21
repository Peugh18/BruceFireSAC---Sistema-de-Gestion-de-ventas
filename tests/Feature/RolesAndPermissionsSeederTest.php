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

test('seeder crea los cuatro roles adicionales vacios sin permisos asignados', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $emptyRoles = ['Gerente', 'Almacen', 'TecnicoPlanta', 'TecnicoCampo'];

    foreach ($emptyRoles as $roleName) {
        $role = Role::findByName($roleName, 'web');

        expect($role)->not->toBeNull()
            ->and($role->name)->toBe($roleName)
            ->and($role->permissions)->toBeEmpty();
    }
});
