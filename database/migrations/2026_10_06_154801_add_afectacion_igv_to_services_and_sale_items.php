<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('igv_revisado_at')->nullable();
        });
        Schema::table('services', function (Blueprint $table) {
            $table->string('tipo_afectacion_igv', 2)->nullable();
            $table->timestamp('igv_revisado_at')->nullable();
        });
        DB::table('services')->where('aplica_igv', true)->update(['tipo_afectacion_igv' => '10']);
        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('tipo_afectacion_igv', 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', fn (Blueprint $table) => $table->dropColumn('tipo_afectacion_igv'));
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn(['tipo_afectacion_igv', 'igv_revisado_at']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('igv_revisado_at'));
    }
};
