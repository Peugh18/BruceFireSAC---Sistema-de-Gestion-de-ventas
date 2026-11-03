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
        Schema::table('equipment', function (Blueprint $table) {
            $table->date('fecha_ultima_ph')->nullable();
        });
        Schema::table('certificate_units', function (Blueprint $table) {
            $table->date('proxima_prueba_hidrostatica')->nullable();
            $table->boolean('fechas_snapshot')->default(false);
        });
        Schema::table('company_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('intervalo_ph_anios')->default(5);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn('fecha_ultima_ph');
        });
        Schema::table('certificate_units', function (Blueprint $table) {
            $table->dropColumn(['proxima_prueba_hidrostatica', 'fechas_snapshot']);
        });
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('intervalo_ph_anios');
        });
    }
};
