<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foto de la configuración de un tipo de certificado cada vez que se guarda.
     * Cada certificado recuerda con qué versión se emitió, así los certificados
     * viejos se siguen imprimiendo igual aunque la plantilla cambie.
     */
    public function up(): void
    {
        Schema::create('certificate_type_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_type_id')->constrained('certificate_types')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('configuracion');
            $table->timestamps();

            $table->unique(['certificate_type_id', 'version']);
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->foreignId('certificate_type_version_id')->nullable()->after('certificate_type_id')
                ->constrained('certificate_type_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('certificate_type_version_id');
        });

        Schema::dropIfExists('certificate_type_versions');
    }
};
