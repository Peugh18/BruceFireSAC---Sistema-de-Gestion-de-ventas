<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\CertificateParticipant;
use App\Models\CertificateType;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfWrapper;

class CertificatePdfService
{
    public function __construct(protected CertificateDocumentData $documentos) {}

    /**
     * Genera el PDF del certificado con un QR hacia la página pública de
     * verificación, con la plantilla estilo C de su tipo: diploma horizontal
     * para las capacitaciones y documento vertical para el resto.
     */
    public function generate(Certificate $certificate, ?CertificateParticipant $participant = null, bool $publico = false): DompdfWrapper
    {
        $certificate->loadMissing(['certificateType', 'client', 'participants']);
        $marca = ['sello_estado' => $this->selloDeEstado($certificate)];
        $datos = fn (?CertificateParticipant $persona): array => $this->paraQuienLoAbre(
            [...$this->documentos->desde($certificate, $persona), ...$marca],
            $publico,
        );

        if (($certificate->datos['modo'] ?? 'normal') === 'por_trabajador' && ! $participant) {
            $paginas = array_values($certificate->participants
                ->whereNull('anulado_at')
                ->map(fn (CertificateParticipant $worker) => $datos($worker))
                ->all());

            if ($paginas !== []) {
                return $this->diplomaPaginas($paginas);
            }
        }

        return $this->dibujar($datos($participant));
    }

    /**
     * El PDF que cualquiera abre con el QR o el enlace público muestra el DNI
     * de una persona a medias (12••••78); el RUC es público. El que imprime
     * la empresa lo lleva completo.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    protected function paraQuienLoAbre(array $datos, bool $publico): array
    {
        if (! $publico) {
            return $datos;
        }

        if (isset($datos['cliente']['documento']) && strtoupper((string) ($datos['cliente']['documento_tipo'] ?? '')) !== 'RUC') {
            $datos['cliente']['documento'] = self::documentoParcial((string) $datos['cliente']['documento']);
        }

        if (! empty($datos['capacitacion']['dni'])) {
            $datos['capacitacion']['dni'] = self::documentoParcial((string) $datos['capacitacion']['dni']);
        }

        return $datos;
    }

    public static function documentoParcial(string $numero): string
    {
        return strlen($numero) < 6
            ? $numero
            : substr($numero, 0, 2).str_repeat('•', strlen($numero) - 4).substr($numero, -2);
    }

    /**
     * Marca de agua roja de un certificado que ya no vale: «ANULADO» si se
     * anuló y «VENCIDO» si pasó su fecha de vigencia.
     */
    public function selloDeEstado(Certificate $certificate): ?string
    {
        if ($certificate->estado === 'anulado') {
            return 'ANULADO';
        }

        if ($certificate->estado === 'vencido'
            || $certificate->fecha_vigencia_hasta?->lt(today())) {
            return 'VENCIDO';
        }

        return null;
    }

    /**
     * Vista previa de la plantilla de un tipo con datos de ejemplo.
     */
    public function muestra(CertificateType $tipo): DompdfWrapper
    {
        return $this->dibujar($this->documentos->muestra($tipo));
    }

    /**
     * @param  array<string, mixed>  $tipo
     */
    public static function esDiploma(array $tipo): bool
    {
        return ($tipo['familia'] ?? null) === 'diploma' || ($tipo['codigo'] ?? null) === 'capacitacion';
    }

    /**
     * Dibuja la plantilla estilo C. Con muchas filas la tabla continúa en más
     * hojas repitiendo su encabezado, y cada hoja lleva "Página X de Y".
     *
     * @param  array<string, mixed>  $datos
     */
    public function dibujar(array $datos): DompdfWrapper
    {
        $recursos = $this->recursos();

        if (self::esDiploma($datos['tipo'])) {
            return $this->diploma($datos, $recursos);
        }

        $pdf = Pdf::setOption('isFontSubsettingEnabled', true)
            ->loadView('pdf.certificado-operatividad', [
                ...$datos,
                'recursos' => $recursos,
                'titulo' => $this->titulo($datos['tipo']),
                'resultados' => CertificateDocumentData::RESULTADOS,
            ])
            ->setPaper('a4', 'portrait');

        $pdf->render();
        $this->pie($pdf, $datos['empresa']);

        return $pdf;
    }

    /**
     * Diploma de capacitación en A4 horizontal. El nombre y el curso toman el
     * tamaño más grande que entra en una línea.
     *
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $recursos
     */
    protected function diploma(array $datos, array $recursos): DompdfWrapper
    {
        return $this->diplomaPaginas([$datos], $recursos);
    }

    /** @param list<array<string, mixed>> $paginas */
    protected function diplomaPaginas(array $paginas, ?array $recursos = null): DompdfWrapper
    {
        $recursos ??= $this->recursos();
        $fuentes = $recursos['fuentes'];
        $paginas = array_map(fn (array $datos) => [
            ...$datos,
            'diploma' => [
                'tamano_nombre' => $this->tamanoQueEntra(mb_strtoupper((string) $datos['cliente']['razon_social']), $fuentes['subtitulo'], 30, 620),
                'tamano_curso' => $this->tamanoQueEntra($datos['capacitacion']['curso'], $fuentes['subtitulo'], 26, 640),
            ],
        ], $paginas);
        $datos = $paginas[0];

        $pdf = Pdf::setOption('isFontSubsettingEnabled', true)
            ->loadView('pdf.certificado-diploma', [
                ...$datos,
                'recursos' => $recursos,
                'paginas' => $paginas,
            ])
            ->setPaper('a4', 'landscape');

        $pdf->render();
        $this->pie($pdf, $datos['empresa']);

        return $pdf;
    }

    /**
     * Líneas del título ("CERTIFICADO" / "DE OPERATIVIDAD" / subtítulo en rojo),
     * cada una con el tamaño más grande que entra en el ancho del bloque.
     *
     * @param  array<string, mixed>  $tipo
     * @return list<array{texto: string, clase: string, tamano: float}>
     */
    public function titulo(array $tipo): array
    {
        $fuentes = $this->fuentes();
        $palabras = explode(' ', mb_strtoupper(trim((string) ($tipo['titulo'] ?: 'CERTIFICADO'))), 2);
        $subtitulo = mb_strtoupper(trim((string) ($tipo['subtitulo'] ?? '')));

        $lineas = [
            ['texto' => $palabras[0], 'clase' => 't1', 'tamano' => $this->tamanoQueEntra($palabras[0], $fuentes['titulo'], 40)],
        ];

        if (isset($palabras[1])) {
            $lineas[] = ['texto' => $palabras[1], 'clase' => 't2', 'tamano' => $this->tamanoQueEntra($palabras[1], $fuentes['titulo'], 27)];
        }

        if ($subtitulo !== '') {
            $tamano = $this->tamanoQueEntra($subtitulo, $fuentes['subtitulo'], 19);
            $partes = [$subtitulo];

            // Un subtítulo muy largo se parte en dos líneas para no quedar diminuto.
            if ($tamano < 14 && str_contains($subtitulo, ' ')) {
                $palabrasSubtitulo = explode(' ', $subtitulo);
                $corte = (int) ceil(count($palabrasSubtitulo) / 2);

                // "SISTEMA DE DETECCIÓN / Y ALARMA…": los conectores bajan a la segunda línea.
                while ($corte > 1 && in_array($palabrasSubtitulo[$corte - 1], ['Y', 'E', 'DE', 'DEL', 'LA', 'EL', 'EN', 'PARA'], true)) {
                    $corte--;
                }

                $partes = [implode(' ', array_slice($palabrasSubtitulo, 0, $corte)), implode(' ', array_slice($palabrasSubtitulo, $corte))];
                $tamano = min(array_map(fn (string $parte) => $this->tamanoQueEntra($parte, $fuentes['subtitulo'], 18), $partes));
            }

            foreach ($partes as $parte) {
                $lineas[] = ['texto' => $parte, 'clase' => 't3', 'tamano' => $tamano];
            }
        }

        return $lineas;
    }

    /**
     * Tamaño en puntos para que el texto ocupe como máximo el ancho dado (por
     * defecto el bloque del título, unos 112 mm), medido con la fuente del PDF.
     */
    public function tamanoQueEntra(string $texto, string $fuente, float $maximo, float $anchoEnPuntos = 312): float
    {
        $caja = imagettfbbox(100, 0, $fuente, $texto);
        $anchoACien = ($caja[2] - $caja[0]) * 0.75;

        return round(min($maximo, 100 * $anchoEnPuntos / max($anchoACien, 1)), 1);
    }

    /**
     * Pie de todas las hojas: empresa, RUC, ciudad y "Página X de Y", centrado
     * entre dos líneas rojas.
     *
     * @param  array<string, mixed>  $empresa
     */
    protected function pie(DompdfWrapper $pdf, array $empresa): void
    {
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $metricas = $dompdf->getFontMetrics();
        $fuente = $metricas->getFont('cond') ?? $metricas->getFont('helvetica');
        $tamano = 7.4;
        $color = [0.36, 0.35, 0.34];
        $rojo = [0.82, 0.02, 0.02];

        $texto = collect([$empresa['razon_social'], $empresa['ruc'] ? 'RUC '.$empresa['ruc'] : null, $empresa['ciudad'] ?? null])
            ->filter()->push('Página {PAGE_NUM} de {PAGE_COUNT}')->implode('   ·   ');
        $ancho = $metricas->getTextWidth(str_replace(['{PAGE_NUM}', '{PAGE_COUNT}'], '9', $texto), $fuente, $tamano);
        $x = ($canvas->get_width() - $ancho) / 2;
        $y = $canvas->get_height() - 27;

        $canvas->page_text($x, $y, $texto, $fuente, $tamano, $color);
        $canvas->page_line(40, $y + 4.5, $x - 10, $y + 4.5, $rojo, 0.6);
        $canvas->page_line($x + $ancho + 10, $y + 4.5, $canvas->get_width() - 150, $y + 4.5, $rojo, 0.6);
    }

    /**
     * Fuentes (rutas locales dentro del proyecto) e imágenes de la plantilla.
     *
     * @return array<string, mixed>
     */
    protected function recursos(): array
    {
        $imagen = fn (string $ruta) => 'data:image/png;base64,'.base64_encode((string) file_get_contents(resource_path('images/'.$ruta)));

        return [
            'fuentes' => $this->fuentes(),
            'logo' => $imagen('brand/bruce-fire-logo-grafito.png'),
            'marca_agua' => $imagen('certificados/marca-agua.png'),
            'franja_superior' => $imagen('certificados/franja-superior-izquierda.png'),
            'franja_inferior' => $imagen('certificados/franja-inferior-derecha.png'),
            'cola' => $imagen('certificados/cola-seccion.png'),
            'sello' => $imagen('certificados/sello-con-texto.png'),
            'icono_whatsapp' => $imagen('certificados/icono-whatsapp.png'),
            'icono_gmail' => $imagen('certificados/icono-gmail.png'),
            'logo_asneex' => $imagen('certificados/logo-asneex.png'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function fuentes(): array
    {
        return [
            'titulo' => resource_path('fonts/montserrat-black-italic.ttf'),
            'subtitulo' => resource_path('fonts/montserrat-extrabold-italic.ttf'),
            'cond' => resource_path('fonts/barlow-semicondensed-medium.ttf'),
            'cond_negrita' => resource_path('fonts/barlow-semicondensed-bold.ttf'),
            'texto' => resource_path('fonts/barlow-regular.ttf'),
            'texto_negrita' => resource_path('fonts/barlow-semibold.ttf'),
            'mono' => resource_path('fonts/ibmplexmono-medium.ttf'),
            'mono_negrita' => resource_path('fonts/ibmplexmono-semibold.ttf'),
        ];
    }
}
