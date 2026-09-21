<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permission catalogue, grouped by module. Every key becomes a
     * `module.action` permission (spatie/laravel-permission, guard "web").
     *
     * @var array<string, list<string>>
     */
    public const MODULES = [
        'dashboard' => ['view_own', 'view_total'],
        'clients' => ['view', 'create', 'update'],
        'client_sites' => ['view', 'create', 'update'],
        'vehicles' => ['view', 'create', 'update'],
        'sedes' => ['view', 'create', 'update'],
        'inventory' => ['view', 'manage'],
        'quotes' => ['view', 'create', 'update', 'convert'],
        'sales' => ['view', 'create', 'scan_units'],
        'certificates' => ['view', 'print', 'generate'],
        'billing' => ['view', 'resend', 'download', 'void', 'credit_note'],
        'guias_remision' => ['view', 'create'],
        'collections' => ['view', 'register_payment'],
        'cashregister' => ['open', 'close', 'view_history'],
        'alerts' => ['view'],
        'service_orders' => ['view', 'create', 'manage'],
        'deficiencies' => ['view', 'create', 'authorize'],
        'communication' => ['view', 'create_event'],
        'roles_permissions' => ['manage'],
    ];

    /**
     * Permissions granted to the Vendedor role, per Documento Maestro §35.2
     * and the refined matrix in documentos/01_MODULOS_ROLES_PERMISOS.md §4.
     *
     * @var list<string>
     */
    public const VENDEDOR_PERMISSIONS = [
        'dashboard.view_own',
        'clients.view', 'clients.create', 'clients.update',
        'client_sites.view', 'client_sites.create', 'client_sites.update',
        'vehicles.view', 'vehicles.create', 'vehicles.update',
        'sedes.view',
        'inventory.view',
        'quotes.view', 'quotes.create', 'quotes.update', 'quotes.convert',
        'sales.view', 'sales.create', 'sales.scan_units',
        'certificates.view', 'certificates.print',
        'billing.view', 'billing.resend', 'billing.download', 'billing.void', 'billing.credit_note',
        'guias_remision.view', 'guias_remision.create',
        'collections.view', 'collections.register_payment',
        'cashregister.open', 'cashregister.close', 'cashregister.view_history',
        'alerts.view',
        'service_orders.view', 'service_orders.create',
        'deficiencies.view', 'deficiencies.authorize',
        'communication.view', 'communication.create_event',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::MODULES as $module => $actions) {
            foreach ($actions as $action) {
                Permission::findOrCreate("{$module}.{$action}", 'web');
            }
        }

        $vendedor = Role::findOrCreate('Vendedor', 'web');
        $vendedor->syncPermissions(self::VENDEDOR_PERMISSIONS);

        // Roles de los otros 4 perfiles: se crean vacíos aquí (sin permisos
        // asignados todavía) para que existan como destino de asignación de
        // usuarios; su matriz de permisos se completa en las fases de esos
        // roles, no en este plan (que cubre solo Vendedor).
        foreach (['Gerente', 'Almacen', 'TecnicoPlanta', 'TecnicoCampo'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
