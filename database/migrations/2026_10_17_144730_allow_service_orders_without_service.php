<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El recojo iniciado en campo queda sin servicio hasta que el vendedor lo
     * cotice. Su nombre ("Recojo de extintores") se deduce, no se guarda.
     */
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->nullable()->change();
        });

        if (Schema::hasColumn('service_orders', 'tipo_servicio')) {
            Schema::table('service_orders', function (Blueprint $table) {
                $table->dropColumn('tipo_servicio');
            });
        }
    }

    public function down(): void
    {
        // ponytail: no se vuelve a exigir service_id; podría haber recojos sin servicio.
    }
};
