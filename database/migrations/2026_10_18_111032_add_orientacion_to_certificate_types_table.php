<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hoja del certificado: vertical (informes con tabla) u horizontal (tablas
     * anchas y el diploma de capacitación).
     */
    public function up(): void
    {
        Schema::table('certificate_types', function (Blueprint $table) {
            $table->string('orientacion', 10)->default('vertical')->after('familia');
        });

        DB::table('certificate_types')->where('familia', 'diploma')->update(['orientacion' => 'horizontal']);
    }

    public function down(): void
    {
        Schema::table('certificate_types', function (Blueprint $table) {
            $table->dropColumn('orientacion');
        });
    }
};
