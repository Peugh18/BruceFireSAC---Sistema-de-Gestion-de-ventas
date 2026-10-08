<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La base respeta las reglas de PROYECTO.md §4:
     * - Las sedes no se borran, se desactivan: toda referencia a sedes queda
     *   en restrict (antes sales/document_series/cash_registers/users/
     *   service_orders la perdían con nullOnDelete y product_lots la borraba
     *   en cascada con su historial).
     * - Las líneas históricas no pierden su producto ni su servicio
     *   (sale_items y quote_items estaban en nullOnDelete).
     * - Los usuarios no se borran: todas las referencias de negocio pasan a
     *   restrict (antes ~15 tablas las perdían con nullOnDelete, y
     *   audit_logs perdía al actor de la auditoría).
     * - Las ventas no se borran: certificates.sale_id y service_orders.sale_id
     *   pasan de nullOnDelete a restrict.
     * - Evidencias se unifica con sus hermanos: restrict en vez de cascade.
     * - Los lotes y el Kardex no se pierden: product_lots pasa de cascade a
     *   restrict y sus referencias (inventory_movements y reception_items)
     *   dejan de soltar el lote con nullOnDelete.
     *
     * No se tocó ninguna migración antigua: este es el estado nuevo y su
     * down() devuelve exactamente el estado anterior.
     */
    public function up(): void
    {
        $this->replaceForeignKeys([
            // Sedes: se desactivan, no se borran.
            ['sales', 'sede_id', 'sedes', 'restrict'],
            ['quotes', 'sede_id', 'sedes', 'restrict'],
            ['service_orders', 'sede_id', 'sedes', 'restrict'],
            ['document_series', 'sede_id', 'sedes', 'restrict'],
            ['cash_registers', 'sede_id', 'sedes', 'restrict'],
            ['users', 'sede_id', 'sedes', 'restrict'],
            ['product_lots', 'sede_id', 'sedes', 'restrict'],
            ['sedes', 'almacen_id', 'sedes', 'restrict'],

            // Líneas históricas: conservan su producto o servicio.
            ['sale_items', 'product_id', 'products', 'restrict'],
            ['sale_items', 'service_id', 'services', 'restrict'],
            ['quote_items', 'product_id', 'products', 'restrict'],
            ['quote_items', 'service_id', 'services', 'restrict'],

            // Usuarios: no se borran (PROYECTO.md §4).
            ['alert_contacts', 'user_id', 'users', 'restrict'],
            ['audit_logs', 'user_id', 'users', 'restrict'],
            ['certificate_revisions', 'user_id', 'users', 'restrict'],
            ['certificates', 'anulado_por', 'users', 'restrict'],
            ['deficiencies', 'reported_by_user_id', 'users', 'restrict'],
            ['deficiency_authorizations', 'vendedor_id', 'users', 'restrict'],
            ['evidencias', 'user_id', 'users', 'restrict'],
            ['inventory_movements', 'user_id', 'users', 'restrict'],
            ['receptions', 'user_id', 'users', 'restrict'],
            ['sale_payments', 'user_id', 'users', 'restrict'],
            ['sale_payments', 'anulado_por', 'users', 'restrict'],
            ['sale_refunds', 'user_id', 'users', 'restrict'],
            ['service_order_events', 'user_id', 'users', 'restrict'],
            ['service_orders', 'tecnico_id', 'users', 'restrict'],
            ['technical_checklists', 'user_id', 'users', 'restrict'],

            // Ventas: no se borran (PROYECTO.md §4).
            ['certificates', 'sale_id', 'sales', 'restrict'],
            ['service_orders', 'sale_id', 'sales', 'restrict'],

            // Evidencias: misma política que deficiencies y technical_checklists.
            ['evidencias', 'service_order_id', 'service_orders', 'restrict'],

            // Lotes: el historial de Kardex no pierde su lote.
            ['product_lots', 'product_id', 'products', 'restrict'],
            ['inventory_movements', 'product_lot_id', 'product_lots', 'restrict'],
            ['reception_items', 'product_lot_id', 'product_lots', 'restrict'],
        ]);
    }

    /**
     * Vuelve al estado exacto que había antes de esta migración (el que se
     * ve en information_schema): las columnas de arriba quedaban en
     * nullOnDelete o cascadeOnDelete según la lista.
     */
    public function down(): void
    {
        $this->replaceForeignKeys([
            ['sedes', 'almacen_id', 'sedes', 'null'],
            ['product_lots', 'sede_id', 'sedes', 'cascade'],
            ['users', 'sede_id', 'sedes', 'null'],
            ['cash_registers', 'sede_id', 'sedes', 'null'],
            ['document_series', 'sede_id', 'sedes', 'null'],
            ['service_orders', 'sede_id', 'sedes', 'null'],
            ['quotes', 'sede_id', 'sedes', 'null'],
            ['sales', 'sede_id', 'sedes', 'null'],

            ['quote_items', 'service_id', 'services', 'null'],
            ['quote_items', 'product_id', 'products', 'null'],
            ['sale_items', 'service_id', 'services', 'null'],
            ['sale_items', 'product_id', 'products', 'null'],

            ['technical_checklists', 'user_id', 'users', 'null'],
            ['service_orders', 'tecnico_id', 'users', 'null'],
            ['service_order_events', 'user_id', 'users', 'null'],
            ['sale_refunds', 'user_id', 'users', 'null'],
            ['sale_payments', 'anulado_por', 'users', 'null'],
            ['sale_payments', 'user_id', 'users', 'null'],
            ['receptions', 'user_id', 'users', 'null'],
            ['inventory_movements', 'user_id', 'users', 'null'],
            ['evidencias', 'user_id', 'users', 'null'],
            ['deficiency_authorizations', 'vendedor_id', 'users', 'null'],
            ['deficiencies', 'reported_by_user_id', 'users', 'null'],
            ['certificates', 'anulado_por', 'users', 'null'],
            ['certificate_revisions', 'user_id', 'users', 'null'],
            ['audit_logs', 'user_id', 'users', 'null'],
            ['alert_contacts', 'user_id', 'users', 'null'],

            ['service_orders', 'sale_id', 'sales', 'null'],
            ['certificates', 'sale_id', 'sales', 'null'],

            ['evidencias', 'service_order_id', 'service_orders', 'cascade'],

            ['reception_items', 'product_lot_id', 'product_lots', 'null'],
            ['inventory_movements', 'product_lot_id', 'product_lots', 'null'],
            ['product_lots', 'product_id', 'products', 'cascade'],
        ]);
    }

    /**
     * Cambia la acción de borrado de una clave foránea existente. La columna
     * se marca nullable cuando la acción lo pide (una FK restrict puede estar
     * en una columna nullable: solo impide borrar al padre).
     *
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
