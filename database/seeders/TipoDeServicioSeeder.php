<?php

namespace Database\Seeders;

use App\Models\CertificateType;
use App\Models\TipoDeServicio;
use Illuminate\Database\Seeder;

/**
 * Tipos de servicio iniciales de BRUCE FIRE; el gerente puede cambiarlos.
 * La detracción queda "pendiente" en todos: la contadora confirma el código
 * de cada uno (dijo que la mayoría será 022). No se inventa un código.
 */
class TipoDeServicioSeeder extends Seeder
{
    public function run(): void
    {
        $certificado = fn (string $codigo): ?int => CertificateType::query()->where('codigo', $codigo)->value('id');

        $tipos = [
            ['Recarga de extintor', null, 'operatividad_garantia', '72101516', true, 'recarga'],
            ['Mantenimiento de extintor', null, 'operatividad_garantia', '72101516', true, 'mantenimiento'],
            ['Prueba hidrostática', null, 'prueba_hidrostatica', '72101516', true, null],
            ['Inspección', null, null, '72101516', false, null],
            ['Capacitación', null, 'capacitacion', null, false, null],
            ['Instalación de lámina de seguridad', null, 'lamina_seguridad', null, false, null],
            ['Luces de emergencia', null, 'luces_emergencia', null, false, null],
            ['Sistema de detección y alarma', null, 'informe_deteccion', null, false, null],
        ];

        foreach ($tipos as [$nombre, $detraccion, $codigoCertificado, $codigoSunat, $pideExtintor, $atencion]) {
            TipoDeServicio::query()->firstOrCreate(['nombre' => $nombre], [
                'codigo_detraccion' => $detraccion,
                'estado_detraccion' => 'pendiente',
                'certificate_type_id' => $codigoCertificado ? $certificado($codigoCertificado) : null,
                'codigo_sunat' => $codigoSunat,
                'pide_extintor' => $pideExtintor,
                'atencion' => $atencion,
            ]);
        }
    }
}
