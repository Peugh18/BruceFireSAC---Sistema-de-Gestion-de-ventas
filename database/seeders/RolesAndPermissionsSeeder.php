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
        'inventory' => ['view', 'manage', 'receive', 'adjust', 'print_stickers', 'lookup'],
        'quotes' => ['view', 'create', 'update', 'convert'],
        'sales' => ['view', 'create', 'scan_units'],
        'certificates' => ['view', 'print', 'generate'],
        'billing' => ['view', 'resend', 'download', 'void', 'credit_note'],
        'guias_remision' => ['view', 'create'],
        'collections' => ['view', 'register_payment'],
        'cashregister' => ['open', 'close', 'view_history', 'view_all'],
        'alerts' => ['view'],
        'service_orders' => ['view', 'create', 'manage', 'assign', 'receive', 'execute', 'close'],
        'deficiencies' => ['view', 'create', 'authorize', 'resolve'],
        'communication' => ['view', 'create_event'],
        'equipment' => ['view', 'create', 'update'],
        'products' => ['view', 'create', 'update', 'toggle_status'],
        'reports' => ['view', 'export'],
        'audit' => ['view'],
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

    /**
     * Permissions granted to the Almacen role, per Documento Maestro §84.4.
     *
     * @var list<string>
     */
    public const ALMACEN_PERMISSIONS = [
        'dashboard.view_own',
        'inventory.view',
        'inventory.receive',
        'inventory.adjust',
        'inventory.print_stickers',
        'inventory.lookup',
        'sedes.view',
        'guias_remision.view', 'guias_remision.create',
    ];

    /**
     * Permissions granted to the TecnicoPlanta role, per Documento Maestro §36 y §85.
     *
     * @var list<string>
     */
    public const TECNICO_PLANTA_PERMISSIONS = [
        'dashboard.view_own',
        'service_orders.view',
        'service_orders.receive',
        'service_orders.execute',
        'deficiencies.view',
        'deficiencies.create',
        'deficiencies.resolve',
        'inventory.view',
        'inventory.lookup',
        'certificates.view',
        'communication.view',
        'communication.create_event',
        'equipment.view',
        'equipment.create',
        'equipment.update',
        'sedes.view',
    ];

    /**
     * Permissions granted to the TecnicoCampo role, per Documento Maestro §36 y §85.
     *
     * @var list<string>
     */
    public const TECNICO_CAMPO_PERMISSIONS = [
        'dashboard.view_own',
        'service_orders.view',
        'service_orders.receive',
        'service_orders.execute',
        'service_orders.close',
        'deficiencies.view',
        'deficiencies.create',
        'inventory.view',
        'inventory.lookup',
        'certificates.view',
        'communication.view',
        'communication.create_event',
        'equipment.view',
        'equipment.create',
        'guias_remision.view', 'guias_remision.create',
        'equipment.update',
        'sedes.view',
    ];

    /**
     * Permissions granted to the Gerente role, per Documento Maestro §35.1, §35.6, §36 y §86.
     *
     * @var list<string>
     */
    /**
     * Los 5 roles de negocio fijos del sistema (§35.6: "Administrador"
     * nunca se creó como rol separado — Gerente lo absorbe). No crear un
     * sexto rol sin decisión explícita del usuario.
     *
     * @var list<string>
     */
    public const BUSINESS_ROLES = [
        'Vendedor',
        'Almacen',
        'Gerente',
        'TecnicoPlanta',
        'TecnicoCampo',
    ];

    public const GERENTE_PERMISSIONS = [
        'dashboard.view_total',
        'clients.view',
        'sedes.view',
        'inventory.view',
        'quotes.view',
        'sales.view',
        'certificates.view',
        'billing.view',
        'guias_remision.view', 'guias_remision.create',
        'collections.view',
        'cashregister.view_history',
        'cashregister.view_all',
        'service_orders.view',
        'equipment.view',
        'products.view', 'products.create', 'products.update', 'products.toggle_status',
        'reports.view', 'reports.export',
        'audit.view',
        'roles_permissions.manage',
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

        $almacen = Role::findOrCreate('Almacen', 'web');
        $almacen->syncPermissions(self::ALMACEN_PERMISSIONS);

        $tecnicoPlanta = Role::findOrCreate('TecnicoPlanta', 'web');
        $tecnicoPlanta->syncPermissions(self::TECNICO_PLANTA_PERMISSIONS);

        $tecnicoCampo = Role::findOrCreate('TecnicoCampo', 'web');
        $tecnicoCampo->syncPermissions(self::TECNICO_CAMPO_PERMISSIONS);

        $gerente = Role::findOrCreate('Gerente', 'web');
        $gerente->syncPermissions(self::GERENTE_PERMISSIONS);
    }
}
