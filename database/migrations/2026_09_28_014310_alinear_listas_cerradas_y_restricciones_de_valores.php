<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('deficiencies')->orderBy('id')->each(function (object $deficiency): void {
            $original = trim((string) $deficiency->condicion);
            $condition = $this->deficiencyCondition($original);
            $note = trim((string) $deficiency->nota);
            $detail = "Condición original: {$original}";

            DB::table('deficiencies')->where('id', $deficiency->id)->update([
                'condicion' => $condition,
                'nota' => $note === '' ? $detail : "{$note}\n{$detail}",
            ]);
        });

        DB::table('technical_checklists')->orderBy('id')->each(function (object $checklist): void {
            DB::table('technical_checklists')->where('id', $checklist->id)->update([
                'tipo_equipo' => $this->equipmentType($checklist->tipo_equipo),
            ]);
        });

        Schema::table('service_order_events', function (Blueprint $table): void {
            $table->enum('tipo', ['creada', 'recibida', 'deficiencia_detectada', 'notificacion_vendedor', 'autorizacion_registrada', 'trabajo_completado', 'entrega_registrada', 'otro'])->change();
        });
        Schema::table('equipment', function (Blueprint $table): void {
            $table->enum('estado', ['activo', 'en_servicio', 'operativo', 'baja'])->default('activo')->change();
        });
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->enum('prioridad', ['normal', 'alta', 'urgente'])->default('normal')->change();
        });
        Schema::table('sales', function (Blueprint $table): void {
            $table->enum('comprobante_tipo', ['factura', 'boleta', 'nota_venta'])->change();
            $table->enum('medio_pago', ['efectivo', 'transferencia', 'yape', 'plin', 'pos', 'deposito', 'otro'])->nullable()->change();
        });
        Schema::table('certificate_types', function (Blueprint $table): void {
            $table->enum('familia', ['operatividad', 'instalacion', 'diploma', 'externo'])->default('operatividad')->change();
        });
        Schema::table('deficiencies', function (Blueprint $table): void {
            $table->enum('condicion', ['danado', 'vencido', 'con_fuga', 'ausente', 'desgastado', 'roto', 'faltante', 'corrosion', 'otro'])->change();
        });
        Schema::table('technical_checklists', function (Blueprint $table): void {
            $table->enum('tipo_equipo', ['pqs', 'co2', 'agua', 'espuma', 'acetato_potasio', 'agente_limpio', 'otro'])->nullable()->change();
        });

        // Sin único en sale_items.inventory_unit_id: una venta anulada deja su
        // línea como historial y la unidad vuelve al stock para venderse otra
        // vez. Que no se venda dos veces a la vez lo cuida ProcessSaleItem
        // (bloquea la unidad y exige que esté disponible).
        Schema::table('installments', function (Blueprint $table): void {
            $table->unique(['sale_id', 'numero_cuota'], 'installments_sale_numero_unique');
        });
        Schema::table('certificate_units', function (Blueprint $table): void {
            $table->unique(['certificate_id', 'equipment_id'], 'certificate_units_certificate_equipment_unique');
        });
        Schema::table('client_sites', function (Blueprint $table): void {
            $table->unique(['client_id', 'nombre'], 'client_sites_client_nombre_unique');
        });

        if (DB::getDriverName() === 'mysql') {
            foreach ($this->checks() as [$table, $name, $expression]) {
                DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$expression})");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (array_reverse($this->checks()) as [$table, $name]) {
                DB::statement("ALTER TABLE `{$table}` DROP CHECK `{$name}`");
            }
        }

        Schema::table('client_sites', fn (Blueprint $table) => $table->dropUnique('client_sites_client_nombre_unique'));
        Schema::table('certificate_units', fn (Blueprint $table) => $table->dropUnique('certificate_units_certificate_equipment_unique'));
        Schema::table('installments', fn (Blueprint $table) => $table->dropUnique('installments_sale_numero_unique'));

        Schema::table('technical_checklists', fn (Blueprint $table) => $table->string('tipo_equipo')->nullable()->change());
        Schema::table('deficiencies', fn (Blueprint $table) => $table->string('condicion')->change());
        Schema::table('certificate_types', fn (Blueprint $table) => $table->string('familia', 20)->default('operatividad')->change());
        Schema::table('sales', function (Blueprint $table): void {
            $table->string('comprobante_tipo')->change();
            $table->string('medio_pago', 20)->nullable()->change();
        });
        Schema::table('service_orders', fn (Blueprint $table) => $table->string('prioridad')->default('normal')->change());
        Schema::table('equipment', fn (Blueprint $table) => $table->string('estado')->default('activo')->change());
        Schema::table('service_order_events', function (Blueprint $table): void {
            $table->enum('tipo', ['creada', 'recibida', 'deficiencia_detectada', 'notificacion_vendedor', 'autorizacion_registrada', 'trabajo_completado', 'otro'])->change();
        });
    }

    private function deficiencyCondition(string $condition): string
    {
        $normalized = mb_strtolower($condition);

        return match (true) {
            str_contains($normalized, 'fuga') => 'con_fuga',
            str_contains($normalized, 'vencid') => 'vencido',
            str_contains($normalized, 'ausen') => 'ausente',
            str_contains($normalized, 'falt') => 'faltante',
            str_contains($normalized, 'desgast') => 'desgastado',
            str_contains($normalized, 'rot'), str_contains($normalized, 'fisura') => 'roto',
            str_contains($normalized, 'corrosi'), str_contains($normalized, 'óxido') => 'corrosion',
            str_contains($normalized, 'dañ') => 'danado',
            default => 'otro',
        };
    }

    private function equipmentType(?string $type): ?string
    {
        if ($type === null || trim($type) === '') {
            return null;
        }

        $normalized = mb_strtolower($type);

        return match (true) {
            str_contains($normalized, 'co2') => 'co2',
            str_contains($normalized, 'pqs') => 'pqs',
            str_contains($normalized, 'acetato') => 'acetato_potasio',
            str_contains($normalized, 'espuma') => 'espuma',
            str_contains($normalized, 'agua') => 'agua',
            str_contains($normalized, 'limpio') => 'agente_limpio',
            default => 'otro',
        };
    }

    /** @return list<array{string, string, string}> */
    private function checks(): array
    {
        return [
            ['sale_items', 'sale_items_cantidad_positive', '`cantidad` > 0'],
            ['sale_items', 'sale_items_amounts_nonnegative', '`precio_unitario` >= 0 AND `descuento` >= 0 AND `subtotal` >= 0'],
            ['quote_items', 'quote_items_cantidad_positive', '`cantidad` > 0'],
            ['quote_items', 'quote_items_amounts_nonnegative', '`precio_unitario` >= 0 AND `descuento` >= 0 AND `subtotal` >= 0'],
            ['reception_items', 'reception_items_quantities_valid', '`cantidad` > 0 AND `cantidad_conforme` >= 0 AND `cantidad_conforme` <= `cantidad`'],
            ['installments', 'installments_values_valid', '`numero_cuota` >= 1 AND `monto` >= 0'],
            ['sale_payments', 'sale_payments_monto_nonnegative', '`monto` >= 0'],
            ['sales', 'sales_amounts_nonnegative', '`subtotal` >= 0 AND `igv` >= 0 AND `total` >= 0'],
            ['quotes', 'quotes_amounts_nonnegative', '`subtotal` >= 0 AND `igv` >= 0 AND `total` >= 0'],
            ['electronic_documents', 'electronic_documents_importe_nonnegative', '`importe` IS NULL OR `importe` >= 0'],
            ['products', 'products_precio_nonnegative', '`precio_venta` >= 0'],
            ['services', 'services_precio_nonnegative', '`precio_venta` >= 0'],
            ['cash_registers', 'cash_registers_amounts_nonnegative', '`monto_apertura` >= 0 AND (`monto_contado_cierre` IS NULL OR `monto_contado_cierre` >= 0) AND (`monto_esperado_calculado` IS NULL OR `monto_esperado_calculado` >= 0)'],
        ];
    }
};
