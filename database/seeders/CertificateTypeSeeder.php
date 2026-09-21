<?php

namespace Database\Seeders;

use App\Models\CertificateType;
use Illuminate\Database\Seeder;

class CertificateTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CertificateType::firstOrCreate(
            ['codigo' => 'operatividad_garantia'],
            [
                'nombre' => 'Operatividad y Garantía',
                'vigencia_meses' => 12,
                'generado_por_rol' => 'tecnico_planta',
            ]
        );

        CertificateType::firstOrCreate(
            ['codigo' => 'prueba_hidrostatica'],
            [
                'nombre' => 'Prueba Hidrostática',
                'vigencia_meses' => 60,
                'generado_por_rol' => 'tecnico_planta',
            ]
        );

        CertificateType::firstOrCreate(
            ['codigo' => 'informe_deteccion'],
            [
                'nombre' => 'Informe Técnico de Sistema de Detección',
                'vigencia_meses' => 12,
                'generado_por_rol' => 'tecnico_campo',
            ]
        );

        CertificateType::firstOrCreate(
            ['codigo' => 'lamina_seguridad'],
            [
                'nombre' => 'Certificado de Instalación de Lámina de Seguridad',
                'vigencia_meses' => 12,
                'generado_por_rol' => 'tecnico_campo',
            ]
        );
    }
}
