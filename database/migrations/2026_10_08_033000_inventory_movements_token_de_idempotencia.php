<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La clave única anterior
 * (unidad + tipo + documento + almacén + cantidad) frenaba bien el doble
 * submit, pero también frenaba un flujo legítimo: si en una venta se cambia un
 * extintor y luego se vuelve al original (U -> U2 -> U), la segunda salida de U
 * chocaba con la de la venta original y la operación reventaba.
 *
 * Se sustituye por un token de idempotencia calculado en PHP a partir de los
 * datos DISTINTIVOS del movimiento (unidad, tipo, documento, almacén, cantidad
 * y observación). Así:
 *   - el doble submit vuelve el MISMO token y la base lo rechaza;
 *   - dos operaciones distintas sobre la misma unidad generan tokens
 *     distintos (su observación las diferencia) y ambas se registran.
 * Los movimientos sin unidad (producto a granel) quedan con token NULL, fuera
 * del índice: un documento puede traer dos líneas idénticas y su devolución las
 * registra como dos movimientos iguales en la misma transacción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            // El índice único que se retira era el que apoyaba la clave foránea
            // de `inventory_unit_id`, y MySQL no deja soltar un índice que
            // necesita una FK. Se crea antes un índice simple sobre esa columna
            // para que la FK tenga dónde apoyarse.
            $table->index('inventory_unit_id', 'inventory_movements_unit_id_idx');
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropUnique('inventory_movements_clave_unica');
            $table->string('idempotencia', 64)->nullable()->after('observacion')
                ->comment('Huella del movimiento para rechazar el doble submit sin frenar operaciones legítimas.');
            $table->unique('idempotencia', 'inventory_movements_idempotencia_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropUnique('inventory_movements_idempotencia_unique');
            $table->dropIndex('inventory_movements_unit_id_idx');
            $table->dropColumn('idempotencia');
            $table->unique(
                ['inventory_unit_id', 'tipo', 'referencia_type', 'referencia_id', 'sede_id', 'cantidad'],
                'inventory_movements_clave_unica'
            );
        });
    }
};
