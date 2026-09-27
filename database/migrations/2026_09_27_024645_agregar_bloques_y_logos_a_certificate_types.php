<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bloques de información adicional (por ejemplo, porcentaje de carga o prueba
     * de conductividad en extintores) y logos de acreditación (ASNEEX) por tipo.
     */
    public function up(): void
    {
        Schema::table('certificate_types', function (Blueprint $table) {
            $table->json('bloques')->nullable()->after('checklist');
            $table->json('logos')->nullable()->after('bloques');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_types', function (Blueprint $table) {
            $table->dropColumn(['bloques', 'logos']);
        });
    }
};
