<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices que faltan para las consultas que ya existen:
     * - electronic_documents.sunat_estado: la cola de envío y los avisos de
     *   SaludDelSistema filtran por estado SUNAT.
     * - installments.estado y installments.fecha_vencimiento: cobranzas,
     *   marcarVencidas() y los consolidados del Gerente.
     * - inventory_units.estado: el stock disponible por almacén.
     * - sales.fecha: todos los reportes por rango de fechas.
     * - certificates(estado, fecha_vigencia_hasta): avisos de certificados por
     *   vencer (RecomputeAlerts).
     * - equipment(proxima_fecha_atencion, proxima_prueba_hidrostatica, estado):
     *   ExtintoresPorVencer.
     * - cash_registers(vendedor_id, estado, fecha_apertura): el turno abierto
     *   del vendedor (CashRegister::abiertaDe).
     * - inventory_movements(product_id, sede_id): el saldo de Kardex por
     *   producto y almacén (Product::saldoKardex).
     *
     * Y se quitan los índices redundantes:
     * - audit_logs.indexaba auditable_type y auditable_id por separado; las
     *   consultas del polimórfico usan ambos, así que se un solo índice.
     * - passkeys.user_id NO tiene índice doble: el índice
     *   passkeys_user_id_index es el que usa la clave foránea, así que no hay
     *   nada que quitar (borrarlo rompería la FK).
     */
    public function up(): void
    {
        Schema::table('electronic_documents', fn (Blueprint $table) => $table->index('sunat_estado'));
        Schema::table('installments', function (Blueprint $table): void {
            $table->index('estado');
            $table->index('fecha_vencimiento');
        });
        Schema::table('inventory_units', fn (Blueprint $table) => $table->index('estado'));
        Schema::table('sales', fn (Blueprint $table) => $table->index('fecha'));
        Schema::table('certificates', fn (Blueprint $table) => $table->index(['estado', 'fecha_vigencia_hasta'], 'certificates_estado_vigencia_index'));
        Schema::table('equipment', fn (Blueprint $table) => $table->index(['proxima_fecha_atencion', 'proxima_prueba_hidrostatica', 'estado'], 'equipment_alertas_index'));
        Schema::table('cash_registers', fn (Blueprint $table) => $table->index(['vendedor_id', 'estado', 'fecha_apertura'], 'cash_registers_turno_index'));
        Schema::table('inventory_movements', fn (Blueprint $table) => $table->index(['product_id', 'sede_id'], 'inventory_movements_producto_sede_index'));

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_logs_auditable_type_index');
            $table->dropIndex('audit_logs_auditable_id_index');
            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_auditable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_logs_auditable_index');
            $table->index('auditable_id');
            $table->index('auditable_type');
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            // El índice (product_id, sede_id) es el que usa la FK de
            // product_id (InnoDB elimina los índices redundantes): se repone
            // antes de quitarlo.
            $table->index('product_id');
            $table->dropIndex('inventory_movements_producto_sede_index');
        });
        Schema::table('cash_registers', function (Blueprint $table): void {
            // El índice (vendedor_id, estado, fecha_apertura) es el que usa la
            // FK de vendedor_id: se repone antes de quitarlo.
            $table->index('vendedor_id');
            $table->dropIndex('cash_registers_turno_index');
        });
        Schema::table('equipment', fn (Blueprint $table) => $table->dropIndex('equipment_alertas_index'));
        Schema::table('certificates', fn (Blueprint $table) => $table->dropIndex('certificates_estado_vigencia_index'));
        Schema::table('sales', fn (Blueprint $table) => $table->dropIndex('sales_fecha_index'));
        Schema::table('inventory_units', fn (Blueprint $table) => $table->dropIndex('inventory_units_estado_index'));
        Schema::table('installments', function (Blueprint $table): void {
            $table->dropIndex('installments_fecha_vencimiento_index');
            $table->dropIndex('installments_estado_index');
        });
        Schema::table('electronic_documents', fn (Blueprint $table) => $table->dropIndex('electronic_documents_sunat_estado_index'));
    }
};
