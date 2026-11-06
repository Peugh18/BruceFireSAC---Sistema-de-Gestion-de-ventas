<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Qué herramientas usó Chispa en cada respuesta: el gerente ve qué consultó o preparó.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chispa_registros', function (Blueprint $table) {
            $table->json('herramientas')->nullable()->after('fuente');
        });
    }

    public function down(): void
    {
        Schema::table('chispa_registros', function (Blueprint $table) {
            $table->dropColumn('herramientas');
        });
    }
};
