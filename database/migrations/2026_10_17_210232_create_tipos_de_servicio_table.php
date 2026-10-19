<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipos de servicio que administra el gerente. Cada tipo define una sola
     * vez el certificado que emite, su código de detracción (catálogo 54) y su
     * código de producto SUNAT (catálogo 25); sus servicios los toman de aquí.
     */
    public function up(): void
    {
        Schema::create('tipos_de_servicio', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->string('codigo_detraccion', 3)->nullable();
            $table->foreignId('certificate_type_id')->nullable()->constrained('certificate_types')->nullOnDelete();
            $table->string('codigo_sunat', 8)->nullable();
            $table->boolean('pide_extintor')->default(false);
            // recarga | mantenimiento: cómo se rotula la atención en el certificado.
            $table->string('atencion', 20)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('tipo_de_servicio_id')->nullable()->after('id')->constrained('tipos_de_servicio')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipo_de_servicio_id');
        });
        Schema::dropIfExists('tipos_de_servicio');
    }
};
