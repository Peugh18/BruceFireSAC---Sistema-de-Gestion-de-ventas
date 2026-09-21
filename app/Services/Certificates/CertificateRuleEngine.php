<?php

namespace App\Services\Certificates;

class CertificateRuleEngine
{
    /**
     * Determina los códigos de certificados correspondientes según el servicio y equipamiento.
     *
     * @param  array{
     *     servicio_realizado?: string,
     *     tipo_equipo?: string,
     *     destino?: string,
     *     ph_realizada?: bool,
     *     capacitacion_realizada?: bool
     * }  $input
     * @return list<string>
     */
    public function determinarTipos(array $input): array
    {
        $tipoEquipo = $input['tipo_equipo'] ?? null;
        $servicioRealizado = $input['servicio_realizado'] ?? null;
        $destino = $input['destino'] ?? null;
        $phRealizada = (bool) ($input['ph_realizada'] ?? false);
        $capacitacionRealizada = (bool) ($input['capacitacion_realizada'] ?? false);

        if ($tipoEquipo === 'sistema_deteccion') {
            return ['informe_deteccion'];
        }

        if ($tipoEquipo === 'lamina_seguridad') {
            return ['lamina_seguridad'];
        }

        $tipos = [];

        if ($servicioRealizado === 'venta_nueva') {
            $tipos[] = 'operatividad_garantia';
        }

        $additional = match ($destino) {
            'local' => array_filter([
                'operatividad_garantia',
                $capacitacionRealizada ? 'capacitacion' : null,
            ]),
            'vehiculo' => array_filter([
                'operatividad_garantia',
                $phRealizada ? 'prueba_hidrostatica' : null,
            ]),
            default => [],
        };

        $tipos = array_merge($tipos, $additional);

        return array_values(array_unique($tipos));
    }
}
