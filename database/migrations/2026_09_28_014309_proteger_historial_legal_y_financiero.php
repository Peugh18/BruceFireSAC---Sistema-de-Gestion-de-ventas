<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->replaceForeignKeys([
            ['cash_registers', 'vendedor_id', 'users', 'restrict'],
            ['certificates', 'certificate_type_id', 'certificate_types', 'restrict'],
            ['certificates', 'client_id', 'clients', 'restrict'],
            ['deficiencies', 'service_order_id', 'service_orders', 'restrict'],
            ['deficiency_authorizations', 'vendedor_id', 'users', 'null'],
            ['electronic_documents', 'sale_id', 'sales', 'restrict'],
            ['equipment', 'client_id', 'clients', 'restrict'],
            ['equipment', 'product_id', 'products', 'restrict'],
            ['installments', 'sale_id', 'sales', 'restrict'],
            ['inventory_movements', 'product_id', 'products', 'restrict'],
            ['inventory_movements', 'sede_id', 'sedes', 'restrict'],
            ['inventory_units', 'product_id', 'products', 'restrict'],
            ['inventory_units', 'sede_almacen_id', 'sedes', 'restrict'],
            ['quotes', 'client_id', 'clients', 'restrict'],
            ['quotes', 'vendedor_id', 'users', 'restrict'],
            ['reception_items', 'product_id', 'products', 'restrict'],
            ['receptions', 'sede_almacen_id', 'sedes', 'restrict'],
            ['sale_items', 'sale_id', 'sales', 'restrict'],
            ['sale_payments', 'sale_id', 'sales', 'restrict'],
            ['sales', 'client_id', 'clients', 'restrict'],
            ['sales', 'vendedor_id', 'users', 'restrict'],
            ['service_order_events', 'service_order_id', 'service_orders', 'restrict'],
            ['service_orders', 'client_id', 'clients', 'restrict'],
            ['technical_checklists', 'equipment_id', 'equipment', 'restrict'],
            ['technical_checklists', 'service_order_id', 'service_orders', 'restrict'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->replaceForeignKeys([
            ['cash_registers', 'vendedor_id', 'users', 'cascade'],
            ['certificates', 'certificate_type_id', 'certificate_types', 'cascade'],
            ['certificates', 'client_id', 'clients', 'cascade'],
            ['deficiencies', 'service_order_id', 'service_orders', 'cascade'],
            ['deficiency_authorizations', 'vendedor_id', 'users', 'cascade'],
            ['electronic_documents', 'sale_id', 'sales', 'cascade'],
            ['equipment', 'client_id', 'clients', 'cascade'],
            ['equipment', 'product_id', 'products', 'cascade'],
            ['installments', 'sale_id', 'sales', 'cascade'],
            ['inventory_movements', 'product_id', 'products', 'cascade'],
            ['inventory_movements', 'sede_id', 'sedes', 'cascade'],
            ['inventory_units', 'product_id', 'products', 'cascade'],
            ['inventory_units', 'sede_almacen_id', 'sedes', 'cascade'],
            ['quotes', 'client_id', 'clients', 'cascade'],
            ['quotes', 'vendedor_id', 'users', 'cascade'],
            ['reception_items', 'product_id', 'products', 'cascade'],
            ['receptions', 'sede_almacen_id', 'sedes', 'cascade'],
            ['sale_items', 'sale_id', 'sales', 'cascade'],
            ['sale_payments', 'sale_id', 'sales', 'cascade'],
            ['sales', 'client_id', 'clients', 'cascade'],
            ['sales', 'vendedor_id', 'users', 'cascade'],
            ['service_order_events', 'service_order_id', 'service_orders', 'cascade'],
            ['service_orders', 'client_id', 'clients', 'cascade'],
            ['technical_checklists', 'equipment_id', 'equipment', 'cascade'],
            ['technical_checklists', 'service_order_id', 'service_orders', 'cascade'],
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: 'cascade'|'null'|'restrict'}>  $foreignKeys
     */
    private function replaceForeignKeys(array $foreignKeys): void
    {
        foreach ($foreignKeys as [$tableName, $column, $referencedTable, $deleteAction]) {
            Schema::table($tableName, function (Blueprint $table) use ($column): void {
                $table->dropForeign([$column]);
            });

            if ($deleteAction === 'null') {
                Schema::table($tableName, function (Blueprint $table) use ($column): void {
                    $table->unsignedBigInteger($column)->nullable()->change();
                });
            }

            Schema::table($tableName, function (Blueprint $table) use ($column, $referencedTable, $deleteAction): void {
                $foreign = $table->foreign($column)->references('id')->on($referencedTable);

                match ($deleteAction) {
                    'cascade' => $foreign->cascadeOnDelete(),
                    'null' => $foreign->nullOnDelete(),
                    'restrict' => $foreign->restrictOnDelete(),
                };
            });
        }
    }
};
