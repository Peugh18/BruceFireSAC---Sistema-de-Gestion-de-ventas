<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotencia y unicidad contra el doble submit (un formulario enviado
     * dos veces). La clave de cada unicidad se eligió para que ningún flujo
     * legítimo la rompa:
     *
     * - inventory_movements: un movimiento ligado a un documento no puede
     *   repetirse para la misma unidad, tipo, documento, almacén y cantidad
     *   (el doble submit de una línea de venta). Lo que se revierte usa otro
     *   tipo ('ingreso' vs 'salida_venta') u otra cantidad (el traslado sale
     *   con -1 del origen y entra con +1 al destino), así la reversión y el
     *   traslado siguen funcionando. Al ser un índice sobre columnas normales,
     *   las filas con algún NULL quedan fuera: los movimientos manuales (sin
     *   documento) y los de producto sin serie se pueden repetir, y hace
     *   falta: un documento puede traer dos líneas idénticas del mismo
     *   producto y su devolución las registra como dos movimientos iguales en
     *   la misma transacción.
     * - sale_payments: un mismo número de operación no puede cobrarse dos
     *   veces para la misma venta y la misma cuota. Un cobro que se reimputa
     *   (pasa de cuota) o que se anula (queda tachado) no estorba: la clave
     *   se libera al anular y se recalcula al reimputar. Los cobros sin
     *   número de operación (efectivo) quedan fuera a propósito: pagar una
     *   cuota en dos veces el mismo día es legítimo y no hay token que los
     *   distinga de un doble submit.
     * - deficiency_authorizations: una sola autorización por deficiencia
     *   (Deficiency::authorization es hasOne).
     * - certificate_units: la misma serie no puede repetirse dentro de un
     *   certificado, aunque la fila ya no tenga equipo (equipment_id es
     *   nullable y su índice único no protege ese caso). Las filas sin serie
     *   quedan libres.
     * - cash_registers: un solo turno abierto por vendedor (PROYECTO.md §4).
     * - note_requests: una sola solicitud pendiente por documento y tipo.
     */
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->unique(['inventory_unit_id', 'tipo', 'referencia_type', 'referencia_id', 'sede_id', 'cantidad'], 'inventory_movements_clave_unica');
        });

        Schema::table('deficiency_authorizations', function (Blueprint $table): void {
            $table->unique('deficiency_id', 'deficiency_authorizations_deficiency_id_unique');
        });

        if (DB::getDriverName() === 'mysql') {
            $this->generatedKeys([
                ['sale_payments', 'clave_cobro', 'VARCHAR(191)',
                    "CASE WHEN `numero_operacion` IS NULL OR TRIM(`numero_operacion`) = '' OR `deleted_at` IS NOT NULL THEN NULL ELSE CONCAT_WS('|', `sale_id`, COALESCE(`installment_id`, 0), `forma_pago`, TRIM(`numero_operacion`)) END",
                    'sale_payments_clave_cobro_unique'],
                ['certificate_units', 'clave_serie', 'VARCHAR(255)',
                    "CASE WHEN TRIM(`numero_serie_snapshot`) = '' THEN NULL ELSE `numero_serie_snapshot` END",
                    null],
                ['cash_registers', 'turno_abierto', 'BIGINT UNSIGNED',
                    "CASE WHEN `estado` = 'abierto' THEN `vendedor_id` ELSE NULL END",
                    'cash_registers_un_turno_abierto_unique'],
                ['note_requests', 'solicitud_pendiente', 'VARCHAR(191)',
                    "CASE WHEN `estado` = 'por_aprobar' THEN CONCAT(`electronic_document_id`, '|', `tipo`) ELSE NULL END",
                    'note_requests_solicitud_pendiente_unique'],
            ]);

            // La misma serie no se repite dentro de un certificado, venga o no
            // de un equipo registrado (la del equipo es
            // certificate_units_certificate_equipment_unique).
            DB::statement('CREATE UNIQUE INDEX `certificate_units_certificate_serie_unica` ON `certificate_units` (`certificate_id`, `clave_serie`)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // El índice (certificate_id, clave_serie) es el que usa la FK de
            // certificate_id: se repone antes de quitarlo.
            DB::statement('CREATE INDEX `certificate_units_certificate_id_foreign` ON `certificate_units` (`certificate_id`)');
            DB::statement('DROP INDEX `certificate_units_certificate_serie_unica` ON `certificate_units`');

            foreach ([
                ['sale_payments', 'clave_cobro', 'sale_payments_clave_cobro_unique'],
                ['certificate_units', 'clave_serie', null],
                ['cash_registers', 'turno_abierto', 'cash_registers_un_turno_abierto_unique'],
                ['note_requests', 'solicitud_pendiente', 'note_requests_solicitud_pendiente_unique'],
            ] as [$tableName, $column, $unique]) {
                if ($unique !== null) {
                    DB::statement("DROP INDEX `{$unique}` ON `{$tableName}`");
                }

                DB::statement("ALTER TABLE `{$tableName}` DROP COLUMN `{$column}`");
            }
        }

        Schema::table('deficiency_authorizations', function (Blueprint $table): void {
            // El índice único es el que usa la FK de deficiency_id (InnoDB
            // elimina los índices redundantes): se repone antes de quitarlo.
            $table->index('deficiency_id');
            $table->dropUnique('deficiency_authorizations_deficiency_id_unique');
        });
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->index('inventory_unit_id');
            $table->dropUnique('inventory_movements_clave_unica');
        });
    }

    /**
     * Crea una columna generada con su índice único. La columna es NULL
     * cuando la fila no entra en la unicidad (MySQL permite repetidos con
     * NULL, que es lo que queremos para los casos legítimos).
     *
     * @param  list<array{0: string, 1: string, 2: string, 3: string, 4: string|null}>  $keys
     */
    private function generatedKeys(array $keys): void
    {
        foreach ($keys as [$tableName, $column, $type, $expression, $unique]) {
            DB::statement("ALTER TABLE `{$tableName}` ADD COLUMN `{$column}` {$type} GENERATED ALWAYS AS ({$expression}) VIRTUAL");

            if ($unique !== null) {
                DB::statement("CREATE UNIQUE INDEX `{$unique}` ON `{$tableName}` (`{$column}`)");
            }
        }
    }
};
