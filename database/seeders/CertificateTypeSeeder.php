<?php

namespace Database\Seeders;

use App\Models\CertificateSequence;
use App\Models\CertificateType;
use App\Models\Signer;
use App\Services\Certificates\CertificateNumberGenerator;
use Illuminate\Database\Seeder;

/**
 * Tipos de certificado de Bruce Fire configurados como plantillas, a partir de
 * los certificados reales que entrega la empresa (extintores, luces de
 * emergencia, detección y alarma, capacitación…). Se puede volver a correr:
 * actualiza la configuración sin tocar los certificados ya emitidos.
 */
class CertificateTypeSeeder extends Seeder
{
    public function run(CertificateNumberGenerator $numeros): void
    {
        $firmantes = $this->firmantes();

        foreach ($this->tipos() as $codigo => $tipo) {
            $siguiente = $tipo['continuar_desde'] ?? null;
            $firmas = $tipo['firmantes'] ?? [];
            unset($tipo['continuar_desde'], $tipo['firmantes']);

            $certificateType = CertificateType::updateOrCreate(['codigo' => $codigo], $tipo);

            $certificateType->signers()->sync(collect($firmas)->mapWithKeys(
                fn (string $clave, int $indice) => [$firmantes[$clave]->id => ['orden' => $indice + 1]],
            )->all());

            $yaTieneSerie = CertificateSequence::query()
                ->where('certificate_type_id', $certificateType->id)
                ->exists();

            if ($siguiente && ! $yaTieneSerie) {
                $numeros->continuarDesde($certificateType, $siguiente);
            }
        }
    }

    /**
     * Firmantes que aparecen en los certificados actuales de la empresa.
     *
     * @return array<string, Signer>
     */
    protected function firmantes(): array
    {
        $datos = [
            'administrador' => ['nombre' => 'Edgar Guevara Cabrera', 'cargo' => 'Administrador'],
            'administrador_og' => ['nombre' => 'Grober Guevara C.', 'cargo' => 'Administrador'],
            'ingeniero_electricista' => ['nombre' => 'Ing. Sixto Leiva Marín', 'cargo' => 'Ingeniero mecánico electricista', 'cip' => '218485', 'especialidad' => 'Mecánica eléctrica'],
            'ingeniero_encargado' => ['nombre' => 'T. Homar Flores G.', 'cargo' => 'Ingeniero encargado', 'cip' => '293886'],
            'tecnico' => ['nombre' => 'Ander Avalos C.', 'cargo' => 'Técnico de operación'],
            'instructor' => ['nombre' => 'Edgar Guevara Cabrera', 'cargo' => 'Instructor'],
        ];

        return collect($datos)->map(fn (array $firmante) => Signer::updateOrCreate(
            ['nombre' => $firmante['nombre'], 'cargo' => $firmante['cargo']],
            [...$firmante, 'activo' => true],
        ))->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function tipos(): array
    {
        $resultado = ['clave' => 'resultado', 'titulo' => 'Resultado', 'tipo' => 'resultado', 'obligatorio' => true];

        return [
            'operatividad_garantia' => [
                'nombre' => 'Operatividad y Garantía',
                'vigencia_meses' => 12,
                'generado_por_rol' => 'tecnico_planta',
                'familia' => 'operatividad',
                'titulo' => 'CERTIFICADO DE OPERATIVIDAD',
                'subtitulo' => 'Y GARANTÍA DE EXTINTORES',
                'norma' => 'Conforme a la NTP 350.043-1:2011, la NTP 833.030 y la NFPA 10.',
                'declaracion' => 'Mediante el presente se certifica que los extintores detallados han sido sometidos a mantenimiento conforme a la NTP 350.043-1:2011, la NFPA 10 y las especificaciones técnicas establecidas por el fabricante.',
                'responsabilidad' => 'La garantía es de 12 meses a partir de la fecha indicada en la etiqueta de mantenimiento y recarga otorgada por nuestra empresa. Se expide la presente constancia para los fines que el cliente estime pertinentes.',
                'prefijo' => 'OG',
                'digitos' => 4,
                'incluye_anio' => true,
                'columnas' => [
                    ['clave' => 'item', 'titulo' => 'Ítem', 'tipo' => 'texto'],
                    ['clave' => 'capacidad', 'titulo' => 'Capacidad', 'tipo' => 'texto', 'obligatorio' => true],
                    ['clave' => 'serie', 'titulo' => 'Serie', 'tipo' => 'texto', 'obligatorio' => true],
                    ['clave' => 'marca', 'titulo' => 'Marca', 'tipo' => 'texto'],
                    ['clave' => 'tipo', 'titulo' => 'Tipo', 'tipo' => 'texto'],
                    ['clave' => 'anio', 'titulo' => 'Año fab.', 'tipo' => 'numero'],
                    ['clave' => 'proxima_ph', 'titulo' => 'Próx. P.H.', 'tipo' => 'texto'],
                    ['clave' => 'vencimiento', 'titulo' => 'Vencimiento servicio', 'tipo' => 'texto'],
                ],
                'checklist' => null,
                'bloques' => [
                    ['titulo' => 'Porcentaje de carga PQS', 'texto' => 'Para el cumplimiento de las normas del fabricante, los extintores PQS del tipo BC son recargados con bicarbonato de potasio y los ABC con fosfato de amonio.'],
                    ['titulo' => 'Prueba de conductividad', 'texto' => 'Se certifica que las mangueras de los extintores de CO2 fueron sometidas a prueba de conductividad, garantizando la adecuada disipación de cargas electrostáticas y la protección del operador.'],
                    ['titulo' => 'Prueba hidrostática', 'texto' => 'Conforme a la NTP 833.030:2012 (intervalos no mayores de cinco años). Baja presión: 600 PSI (PQS, agentes líquidos y halogenados). Alta presión: 3000 PSI (CO2).'],
                ],
                'logos' => ['asneex'],
                'firmantes' => ['tecnico', 'administrador_og', 'ingeniero_encargado'],
            ],
            'prueba_hidrostatica' => [
                'nombre' => 'Prueba Hidrostática',
                'vigencia_meses' => 60,
                'generado_por_rol' => 'tecnico_planta',
                'familia' => 'operatividad',
                'titulo' => 'CERTIFICADO DE PRUEBA HIDROSTÁTICA',
                'subtitulo' => 'EXTINTORES PORTÁTILES',
                'norma' => 'Conforme a la NTP 350.043-1:2011 y la NTP 833.030:2012.',
                'declaracion' => 'Los extintores detallados fueron sometidos a prueba hidrostática conforme a lo establecido en la NTP 833.030:2012, que recomienda realizarla a intervalos no mayores de cinco años.',
                'responsabilidad' => 'Extintores de baja presión probados a 600 PSI (PQS, agentes líquidos y halogenados); de alta presión a 3000 PSI (CO2).',
                'prefijo' => 'PH',
                'digitos' => 4,
                'incluye_anio' => true,
                'columnas' => [
                    ['clave' => 'item', 'titulo' => 'Ítem', 'tipo' => 'texto'],
                    ['clave' => 'capacidad', 'titulo' => 'Capacidad', 'tipo' => 'texto', 'obligatorio' => true],
                    ['clave' => 'serie', 'titulo' => 'Serie', 'tipo' => 'texto', 'obligatorio' => true],
                    ['clave' => 'marca', 'titulo' => 'Marca', 'tipo' => 'texto'],
                    ['clave' => 'presion_ph', 'titulo' => 'Presión de prueba', 'tipo' => 'texto', 'unidad' => 'PSI'],
                    ['clave' => 'tiempo_ph', 'titulo' => 'Tiempo', 'tipo' => 'texto'],
                    $resultado,
                ],
                'checklist' => null,
                'firmantes' => ['tecnico', 'ingeniero_encargado'],
            ],
            'luces_emergencia' => [
                'nombre' => 'Operatividad de Luces de Emergencia',
                'vigencia_meses' => 12,
                'generado_por_rol' => 'tecnico_campo',
                'familia' => 'operatividad',
                'titulo' => 'CERTIFICADO DE OPERATIVIDAD',
                'subtitulo' => 'LUCES DE EMERGENCIA',
                'norma' => 'De conformidad con el Reglamento Nacional de Edificaciones, Norma A.130, y la NTP IEC 60598-2-22.',
                'declaracion' => 'BRUCE FIRE S.A.C. certifica que las luces de emergencia detalladas en el presente documento fueron evaluadas por nuestro personal técnico especializado, constatando su funcionamiento de acuerdo con el Reglamento Nacional de Edificaciones, Norma A.130, y demás disposiciones aplicables.',
                'responsabilidad' => 'Las luces de emergencia descritas fueron evaluadas por personal técnico de BRUCE FIRE en la fecha indicada. El resultado corresponde exclusivamente a las condiciones verificadas durante la inspección y puede variar por uso, manipulación, reubicación, suministro eléctrico o intervención de terceros.',
                'prefijo' => 'LM',
                'digitos' => 7,
                'incluye_anio' => false,
                'continuar_desde' => 6540,
                'columnas' => [
                    ['clave' => 'item', 'titulo' => 'Ítem', 'tipo' => 'texto'],
                    ['clave' => 'ubicacion', 'titulo' => 'Ubicación', 'tipo' => 'texto', 'obligatorio' => true],
                    ['clave' => 'marca', 'titulo' => 'Marca', 'tipo' => 'texto', 'obligatorio' => true],
                    ['clave' => 'modelo', 'titulo' => 'Modelo', 'tipo' => 'texto'],
                    ['clave' => 'potencia', 'titulo' => 'Potencia', 'tipo' => 'texto', 'unidad' => 'W'],
                    ['clave' => 'bateria', 'titulo' => 'Batería', 'tipo' => 'texto'],
                    ['clave' => 'autonomia', 'titulo' => 'Autonomía', 'tipo' => 'numero', 'unidad' => 'h', 'minimo' => 1.5],
                    $resultado,
                ],
                'checklist' => [
                    ['texto' => 'Encendido de faros (pulsador o interruptores)'],
                    ['texto' => 'Activación automática ante corte de energía', 'unidad' => 's', 'maximo' => 10],
                    ['texto' => 'Recarga al restablecer energía (cargador)'],
                    ['texto' => 'Estado de batería (tensión)', 'unidad' => 'V'],
                    ['texto' => 'Autonomía verificada', 'unidad' => 'h', 'minimo' => 1.5],
                    ['texto' => 'Indicadores luminosos del equipo'],
                    ['texto' => 'Estado físico de carcasa, lentes y faros'],
                    ['texto' => 'Orientación de faros hacia la ruta de evacuación'],
                    ['texto' => 'Fijación e instalación'],
                    ['texto' => 'Conexión eléctrica (cable, enchufe y toma)'],
                ],
                'firmantes' => ['administrador', 'ingeniero_electricista'],
            ],
            'informe_deteccion' => [
                'nombre' => 'Operatividad del Sistema de Detección y Alarma',
                'vigencia_meses' => 12,
                'generado_por_rol' => 'tecnico_campo',
                'familia' => 'operatividad',
                'titulo' => 'CERTIFICADO DE OPERATIVIDAD',
                'subtitulo' => 'SISTEMA DE DETECCIÓN Y ALARMA CONTRA INCENDIOS',
                'norma' => 'De conformidad con el Reglamento Nacional de Edificaciones, Norma A.130, y la NFPA 72.',
                'declaracion' => 'Por medio del presente certificamos que el sistema de detección y alarma contra incendios instalado en las dependencias mencionadas fue sometido a inspección, verificación funcional y pruebas operativas, comprobándose que sus componentes se encuentran en el estado indicado al momento de la evaluación.',
                'responsabilidad' => 'El sistema fue evaluado de acuerdo con los requisitos de la Norma A.130 del RNE y la NFPA 72. El resultado corresponde a las condiciones observadas durante la inspección y puede variar por uso, manipulación, modificaciones o intervención de terceros.',
                'prefijo' => 'SDA',
                'digitos' => 6,
                'incluye_anio' => false,
                'continuar_desde' => 125426,
                'columnas' => [
                    ['clave' => 'item', 'titulo' => 'N.°', 'tipo' => 'texto'],
                    ['clave' => 'componente', 'titulo' => 'Componente o equipo', 'tipo' => 'texto', 'obligatorio' => true],
                    ['clave' => 'ubicacion', 'titulo' => 'Ubicación', 'tipo' => 'texto', 'obligatorio' => true],
                    ['clave' => 'prueba', 'titulo' => 'Prueba o verificación', 'tipo' => 'texto'],
                    $resultado,
                ],
                'checklist' => null,
                'requiere_cip' => true,
                'firmantes' => ['administrador', 'ingeniero_electricista'],
            ],
            'lamina_seguridad' => [
                'nombre' => 'Instalación de Lámina de Seguridad',
                'vigencia_meses' => 12,
                'generado_por_rol' => 'tecnico_campo',
                'familia' => 'instalacion',
                'titulo' => 'CERTIFICADO DE INSTALACIÓN',
                'subtitulo' => 'LÁMINA DE SEGURIDAD',
                'norma' => 'Conforme a la Norma E.040 Vidrio del Reglamento Nacional de Edificaciones.',
                'declaracion' => 'BRUCE FIRE S.A.C. certifica la instalación de lámina de seguridad en los ambientes detallados, de modo que, en caso de rotura, los fragmentos de vidrio se mantengan adheridos a la lámina.',
                'responsabilidad' => 'La instalación fue verificada en la fecha indicada. La lámina conserva sus propiedades mientras no sea retirada, rayada o manipulada por terceros.',
                'prefijo' => 'LS',
                'digitos' => 4,
                'incluye_anio' => true,
                'columnas' => [
                    ['clave' => 'item', 'titulo' => 'Ítem', 'tipo' => 'texto'],
                    ['clave' => 'ambiente', 'titulo' => 'Ambiente', 'tipo' => 'texto', 'obligatorio' => true],
                    ['clave' => 'marca', 'titulo' => 'Marca', 'tipo' => 'texto'],
                    ['clave' => 'espesor', 'titulo' => 'Espesor', 'tipo' => 'numero', 'unidad' => 'micras', 'minimo' => 4],
                    ['clave' => 'metros', 'titulo' => 'Área', 'tipo' => 'numero', 'unidad' => 'm²'],
                    $resultado,
                ],
                'checklist' => null,
                'fotos_minimas' => 1,
                'firmantes' => ['administrador'],
            ],
            'capacitacion' => [
                'nombre' => 'Capacitación en Uso y Manejo de Extintores',
                'vigencia_meses' => 0,
                'generado_por_rol' => 'vendedor',
                'familia' => 'diploma',
                'titulo' => 'CERTIFICADO',
                'subtitulo' => 'DE CAPACITACIÓN',
                'norma' => null,
                'declaracion' => 'Por haber participado en la capacitación e instrucción teórico-práctica impartida por BRUCE FIRE S.A.C.',
                'responsabilidad' => null,
                'prefijo' => 'BF-UME',
                'digitos' => 4,
                'incluye_anio' => true,
                'columnas' => null,
                'checklist' => null,
                'firmantes' => ['instructor'],
            ],
        ];
    }
}
