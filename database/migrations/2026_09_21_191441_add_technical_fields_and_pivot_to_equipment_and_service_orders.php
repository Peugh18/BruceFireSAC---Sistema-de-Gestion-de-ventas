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
            $table->string('tipo_agente')->nullable()->after('product_id');
            $table->string('capacidad')->nullable()->after('tipo_agente');
            $table->string('marca')->nullable()->after('capacidad');
            $table->string('serie_fabricante')->nullable()->after('marca');
            $table->string('anio_fabricacion')->nullable()->after('serie_fabricante');
            $table->string('foto_general_path')->nullable()->after('anio_fabricacion');
            $table->string('foto_placa_path')->nullable()->after('foto_general_path');
            $table->text('notas')->nullable()->after('foto_placa_path');
        });

        Schema::create('service_order_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->boolean('recibido')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['service_order_id', 'equipment_id']);
        });

        Schema::table('service_orders', function (Blueprint $table) {
            $table->foreignId('equipment_id')->nullable()->after('client_id')->constrained('equipment')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_id');
        });

        Schema::dropIfExists('service_order_equipment');

        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_agente',
                'capacidad',
                'marca',
                'serie_fabricante',
                'anio_fabricacion',
                'foto_general_path',
                'foto_placa_path',
                'notas',
            ]);
        });
    }
};
