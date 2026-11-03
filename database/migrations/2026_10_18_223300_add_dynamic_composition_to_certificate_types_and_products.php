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
        Schema::table('certificate_types', function (Blueprint $table) {
            $table->json('composicion')->nullable();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('certificate_type_id')->nullable()->constrained('certificate_types')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('certificate_type_id');
        });
        Schema::table('certificate_types', function (Blueprint $table) {
            $table->dropColumn('composicion');
        });
    }
};
