<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_order_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        // Antes de borrar la columna, el extintor que tenía cada orden pasa a su
        // lista de extintores (service_order_equipment), para no perder el dato.
        DB::table('service_orders')
            ->whereNotNull('equipment_id')
            ->orderBy('id')
            ->each(function (object $orden) {
                $yaEsta = DB::table('service_order_equipment')
                    ->where('service_order_id', $orden->id)
                    ->where('equipment_id', $orden->equipment_id)
                    ->exists();

                if (! $yaEsta) {
                    DB::table('service_order_equipment')->insert([
                        'service_order_id' => $orden->id,
                        'equipment_id' => $orden->equipment_id,
                        'recibido' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->foreignId('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
        });

        Schema::dropIfExists('service_order_sequences');
    }
};
