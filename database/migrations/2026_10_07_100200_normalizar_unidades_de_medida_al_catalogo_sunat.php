<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Productos y servicios guardaban la unidad en texto libre ("UND", "Par").
 * Se pasan a su código del catálogo 03 de SUNAT; lo que no se reconoce queda
 * como está y Greenter lo rechaza al emitir hasta que se corrija.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const EQUIVALENCIAS = [
        'UND' => 'NIU', 'UNIDAD' => 'NIU', 'SERVICIO' => 'ZZ',
        'KG' => 'KGM', 'KILOGRAMO' => 'KGM', 'METRO' => 'MTR',
        'LITRO' => 'LTR', 'GALON' => 'GLL', 'GLI' => 'GLL',
        'PAR' => 'PR', 'CAJA' => 'BX', 'PAQUETE' => 'PK', 'DOCENA' => 'DZN',
    ];

    public function up(): void
    {
        foreach (['products', 'services'] as $tabla) {
            foreach (DB::table($tabla)->distinct()->pluck('unidad_medida') as $actual) {
                $clave = mb_strtoupper(trim((string) $actual));
                $codigo = self::EQUIVALENCIAS[$clave] ?? $clave;

                if ($codigo !== $actual) {
                    DB::table($tabla)->where('unidad_medida', $actual)->update(['unidad_medida' => $codigo]);
                }
            }
        }
    }

    public function down(): void
    {
        // Los códigos SUNAT son los valores correctos: no se vuelve al texto libre.
    }
};
