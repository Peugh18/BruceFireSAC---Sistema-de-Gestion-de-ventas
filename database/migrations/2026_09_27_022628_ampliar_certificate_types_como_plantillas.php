<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los tipos de certificado pasan a ser plantillas configurables (qué datos se
     * llenan, textos, numeración). Cada certificado guarda su número de revisión
     * y, si se anula, el motivo y quién lo anuló.
     */
    public function up(): void
    {
        Schema::table('certificate_types', function (Blueprint $table) {
            $table->string('familia', 20)->default('operatividad')->after('nombre');
            $table->string('titulo', 120)->nullable()->after('familia');
            $table->string('subtitulo', 120)->nullable()->after('titulo');
            $table->text('norma')->nullable()->after('subtitulo');
            $table->text('declaracion')->nullable()->after('norma');
            $table->text('responsabilidad')->nullable()->after('declaracion');
            $table->string('prefijo', 12)->nullable()->after('responsabilidad');
            $table->unsignedTinyInteger('digitos')->default(4)->after('prefijo');
            $table->boolean('incluye_anio')->default(true)->after('digitos');
            $table->json('columnas')->nullable()->after('incluye_anio');
            $table->json('checklist')->nullable()->after('columnas');
            $table->unsignedSmallInteger('fotos_minimas')->default(0)->after('checklist');
            $table->boolean('requiere_cip')->default(false)->after('fotos_minimas');
            $table->boolean('activo')->default(true)->after('requiere_cip');
            $table->unsignedInteger('version')->default(1)->after('activo');
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(0)->after('estado');
            $table->text('anulado_motivo')->nullable()->after('revision');
            $table->timestamp('anulado_at')->nullable()->after('anulado_motivo');
            $table->foreignId('anulado_por')->nullable()->after('anulado_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anulado_por');
            $table->dropColumn(['revision', 'anulado_motivo', 'anulado_at']);
        });

        Schema::table('certificate_types', function (Blueprint $table) {
            $table->dropColumn([
                'familia', 'titulo', 'subtitulo', 'norma', 'declaracion', 'responsabilidad',
                'prefijo', 'digitos', 'incluye_anio', 'columnas', 'checklist',
                'fotos_minimas', 'requiere_cip', 'activo', 'version',
            ]);
        });
    }
};
