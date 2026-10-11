<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('codigo_detraccion', 3)->nullable();
        });
        Schema::table('sale_payments', function (Blueprint $table): void {
            $table->boolean('es_detraccion')->default(false);
        });
        Schema::table('sales', function (Blueprint $table): void {
            $table->string('codigo_detraccion_snapshot', 3)->nullable();
            $table->decimal('monto_detraccion_snapshot', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn(['codigo_detraccion_snapshot', 'monto_detraccion_snapshot']));
        Schema::table('sale_payments', fn (Blueprint $table) => $table->dropColumn('es_detraccion'));
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn('codigo_detraccion'));
    }
};
