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
        Schema::table('service_orders', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->after('sale_id')->constrained('services')->nullOnDelete();
        });

        DB::table('service_orders')
            ->whereNull('service_id')
            ->orderBy('id')
            ->each(function (object $order): void {
                $serviceId = DB::table('services')
                    ->whereRaw('LOWER(nombre) = LOWER(?)', [$order->tipo_servicio])
                    ->value('id');

                if ($serviceId !== null) {
                    DB::table('service_orders')->where('id', $order->id)->update(['service_id' => $serviceId]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
        });
    }
};
