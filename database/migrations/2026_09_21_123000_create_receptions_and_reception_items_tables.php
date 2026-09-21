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
        // 1. Agregar campos marca y anio_fabricacion a inventory_units (§84.8)
        Schema::table('inventory_units', function (Blueprint $table) {
            $table->string('marca')->nullable()->after('numero_serie');
            $table->unsignedSmallInteger('anio_fabricacion')->nullable()->after('marca');
        });

        // 2. Crear tabla receptions (§84.8)
        Schema::create('receptions', function (Blueprint $table) {
            $table->id();
            $table->string('proveedor', 150);
            $table->string('documento_referencia', 50)->nullable();
            $table->date('fecha');
            $table->foreignId('sede_almacen_id')->constrained('sedes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacion')->nullable();
            $table->timestamps();
        });

        // 3. Crear tabla reception_items (§84.8)
        Schema::create('reception_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reception_id')->constrained('receptions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('cantidad');
            $table->integer('cantidad_conforme');
            $table->text('observacion_item')->nullable();
            $table->timestamps();
        });

        // 4. Crear tabla inventory_sequences para correlativos transaccionales (BF-EQ-XXXXXX) (§84.8)
        Schema::create('inventory_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique();
            $table->string('prefijo', 20);
            $table->unsignedBigInteger('correlativo_actual')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_sequences');
        Schema::dropIfExists('reception_items');
        Schema::dropIfExists('receptions');

        Schema::table('inventory_units', function (Blueprint $table) {
            $table->dropColumn(['marca', 'anio_fabricacion']);
        });
    }
};
