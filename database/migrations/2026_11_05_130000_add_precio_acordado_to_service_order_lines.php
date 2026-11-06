<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precio que campo deja al cliente en el recojo: el vendedor cobra ese precio
 * y, si bajó más del límite, el gerente lo aprueba antes del cobro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_order_lines', function (Blueprint $table) {
            $table->decimal('precio_lista', 10, 2)->nullable()->after('cantidad');
            $table->decimal('precio_unitario', 10, 2)->nullable()->after('precio_lista');
            $table->string('precio_estado', 20)->nullable()->after('precio_unitario');
        });
    }

    public function down(): void
    {
        Schema::table('service_order_lines', function (Blueprint $table) {
            $table->dropColumn(['precio_lista', 'precio_unitario', 'precio_estado']);
        });
    }
};
