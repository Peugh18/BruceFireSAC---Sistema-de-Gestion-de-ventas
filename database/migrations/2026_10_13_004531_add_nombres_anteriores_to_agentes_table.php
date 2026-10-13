<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agentes', function (Blueprint $table): void {
            $table->json('nombres_anteriores')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agentes', function (Blueprint $table): void {
            $table->dropColumn('nombres_anteriores');
        });
    }
};
