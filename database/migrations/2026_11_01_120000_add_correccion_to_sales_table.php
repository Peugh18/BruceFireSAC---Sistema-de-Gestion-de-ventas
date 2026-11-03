<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('corrige_sale_id')->nullable()->after('quote_id')->constrained('sales')->nullOnDelete();
            $table->timestamp('correccion_desde')->nullable()->after('confirmada_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corrige_sale_id');
            $table->dropColumn('correccion_desde');
        });
    }
};
