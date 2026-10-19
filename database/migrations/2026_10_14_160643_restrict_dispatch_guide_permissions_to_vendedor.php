<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (Role::query()->where('guard_name', 'web')->where('name', '!=', 'Vendedor')->get() as $role) {
            $role->revokePermissionTo($role->permissions()->whereIn('name', ['guias_remision.view', 'guias_remision.create'])->get());
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No restaurar privilegios retirados al revertir cambios de esquema.
    }
};
