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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_unit_id')->nullable()->constrained('inventory_units')->nullOnDelete();
            $table->foreignId('catalog_item_id')->constrained('catalog_items')->cascadeOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->cascadeOnDelete();
            $table->enum('tipo', ['ingreso', 'salida_venta', 'ajuste', 'traslado']);
            $table->integer('cantidad');
            $table->nullableMorphs('referencia');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('observacion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
