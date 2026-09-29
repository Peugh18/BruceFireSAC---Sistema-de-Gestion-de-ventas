<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Formas normales:
 * - 1FN: los números liberados de una serie estaban como lista JSON en una
 *   columna; pasan a una tabla, una fila por número.
 * - 3FN: la diferencia de caja dependía de otras dos columnas (contado menos
 *   esperado); ahora la calcula la base de datos y nadie la guarda a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correlativos_liberados', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_series_id')->constrained('document_series')->cascadeOnDelete();
            $table->unsignedInteger('correlativo');
            $table->timestamps();
            $table->unique(['document_series_id', 'correlativo']);
        });

        foreach (DB::table('document_series')->whereNotNull('correlativos_liberados')->get(['id', 'correlativos_liberados']) as $serie) {
            foreach (array_unique((array) json_decode((string) $serie->correlativos_liberados, true)) as $correlativo) {
                DB::table('correlativos_liberados')->insert([
                    'document_series_id' => $serie->id,
                    'correlativo' => (int) $correlativo,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('document_series', fn (Blueprint $table) => $table->dropColumn('correlativos_liberados'));

        DB::statement('ALTER TABLE cash_registers MODIFY diferencia DECIMAL(10,2) AS (monto_contado_cierre - monto_esperado_calculado) STORED');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE cash_registers MODIFY diferencia DECIMAL(10,2) NULL');

        Schema::table('document_series', fn (Blueprint $table) => $table->json('correlativos_liberados')->nullable()->after('correlativo_actual'));

        foreach (DB::table('correlativos_liberados')->get()->groupBy('document_series_id') as $serieId => $filas) {
            DB::table('document_series')->where('id', $serieId)->update([
                'correlativos_liberados' => json_encode($filas->pluck('correlativo')->map(fn ($numero) => (int) $numero)->sort()->values()->all()),
            ]);
        }

        Schema::dropIfExists('correlativos_liberados');
    }
};
