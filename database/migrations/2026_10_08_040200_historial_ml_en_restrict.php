<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El histórico de entrenamiento de ML es inmutable (solo se importa y se
 * lee), pero sus claves se borraban en cascada: borrar un cliente histórico
 * arrastraba sus comprobantes y todas sus líneas sin dejar rastro. Pasa a
 * restrict, la misma política que el historial legal y financiero de la
 * migración 2026_10_07_210000: nada del histórico se borra con su padre.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->reemplazarClavesForaneas(restrict: true);
    }

    public function down(): void
    {
        $this->reemplazarClavesForaneas(restrict: false);
    }

    /**
     * Suelta las dos claves foráneas en cascada y las vuelve a crear en
     * restrict (o al revés, según el parámetro).
     */
    private function reemplazarClavesForaneas(bool $restrict): void
    {
        $claves = [
            ['ml_comprobantes_historicos', 'documento_cliente', 'ml_clientes_historicos', 'documento'],
            ['ml_lineas_historicas', 'comprobante', 'ml_comprobantes_historicos', 'comprobante'],
        ];

        foreach ($claves as [$tabla, $columna, $tablaReferenciada, $columnaReferenciada]) {
            Schema::table($tabla, function (Blueprint $table) use ($columna) {
                $table->dropForeign([$columna]);
            });

            Schema::table($tabla, function (Blueprint $table) use ($columna, $tablaReferenciada, $columnaReferenciada, $restrict) {
                $foranea = $table->foreign($columna)->references($columnaReferenciada)->on($tablaReferenciada);
                $restrict ? $foranea->restrictOnDelete() : $foranea->cascadeOnDelete();
            });
        }
    }
};
