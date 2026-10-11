<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->foreignId('deficiency_id')->nullable()->unique()->constrained()->restrictOnDelete();
        });
        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('deficiency_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('deficiency_id'));
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropUnique(['deficiency_id']);
            $table->dropConstrainedForeignId('deficiency_id');
        });
    }
};
