<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->unsignedInteger('cantidad')->default(1);
            $table->foreignId('quote_item_id')->nullable()->constrained('quote_items')->nullOnDelete();
            $table->boolean('adicional')->default(false);
            $table->timestamps();

            $table->index(['service_order_id', 'adicional']);
        });

        Schema::table('service_order_equipment', function (Blueprint $table): void {
            $table->foreignId('service_order_line_id')->nullable()->after('equipment_id')->constrained('service_order_lines')->nullOnDelete();
            $table->unsignedInteger('posicion')->nullable()->after('service_order_line_id');
        });

        $now = now();
        DB::table('service_orders')
            ->whereNotNull('service_id')
            ->orderBy('id')
            ->select(['id', 'service_id'])
            ->each(function (object $order) use ($now): void {
                $lineId = DB::table('service_order_lines')->insertGetId([
                    'service_order_id' => $order->id,
                    'service_id' => $order->service_id,
                    'cantidad' => max(1, DB::table('service_order_equipment')->where('service_order_id', $order->id)->count()),
                    'adicional' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('service_order_equipment')
                    ->where('service_order_id', $order->id)
                    ->orderBy('id')
                    ->get(['id'])
                    ->values()
                    ->each(fn (object $pivot, int $index) => DB::table('service_order_equipment')->where('id', $pivot->id)->update([
                        'service_order_line_id' => $lineId,
                        'posicion' => $index + 1,
                    ]));
            });
    }

    public function down(): void
    {
        Schema::table('service_order_equipment', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_order_line_id');
            $table->dropColumn('posicion');
        });

        Schema::dropIfExists('service_order_lines');
    }
};
