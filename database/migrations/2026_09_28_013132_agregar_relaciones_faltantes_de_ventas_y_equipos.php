<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relaciones que existían en los datos pero no en la base: el certificado y
     * la orden con la venta que los originó o cobró, y cada extintor del
     * certificado con su ficha. Si se borra la venta o el extintor, el
     * certificado y la orden se conservan (la referencia queda vacía).
     */
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->foreign('sale_id')->references('id')->on('sales')->nullOnDelete();
        });

        Schema::table('service_orders', function (Blueprint $table) {
            $table->foreign('sale_id')->references('id')->on('sales')->nullOnDelete();
        });

        Schema::table('certificate_units', function (Blueprint $table) {
            $table->foreign('equipment_id')->references('id')->on('equipment')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certificate_units', function (Blueprint $table) {
            $table->dropForeign(['equipment_id']);
        });

        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
        });
    }
};
