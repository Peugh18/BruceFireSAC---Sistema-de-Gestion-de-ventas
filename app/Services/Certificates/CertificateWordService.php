<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\CertificateParticipant;
use App\Models\CertificateType;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Image as ImageStyle;
use PhpOffice\PhpWord\Style\Language;

/**
 * Versión editable en Word del certificado estilo C, armada con los mismos
 * datos que el PDF. Usa fuentes que ya trae Windows (Arial Black, Bahnschrift,
 * Consolas, Segoe UI) para que se vea igual en cualquier computadora.
 */
class CertificateWordService
{
    private const TITULO = 'Arial Black';

    private const COND = 'Bahnschrift SemiCondensed';

    private const COND_NEGRITA = 'Bahnschrift SemiBold SemiConden';

    private const MONO = 'Consolas';

    private const TEXTO = 'Segoe UI';

    private const SIMBOLOS = 'Segoe UI Symbol';

    private const ROJO = 'D20404';

    private const GRAFITO = '2E2D2C';

    private const VERDE = '0E7A3E';

    private const GRIS_TEXTO = '5C5A58';

    private const BORDE = 'D9D9D9';

    /** Ancho útil de la hoja A4 con márgenes de 13 mm, en twips. */
    private const ANCHO = 10432;

    /** @var list<string> */
    private array $temporales = [];

    public function __construct(
        protected CertificateDocumentData $documentos,
        protected CertificatePdfService $pdf,
    ) {}

    /**
     * Genera el .docx del certificado y devuelve la ruta del archivo temporal.
     */
    public function generate(Certificate $certificate, ?CertificateParticipant $participant = null): string
    {
        $certificate->loadMissing('participants');

        if (($certificate->datos['modo'] ?? 'normal') === 'por_trabajador' && ! $participant) {
            $paginas = $certificate->participants->whereNull('anulado_at')
                ->map(fn (CertificateParticipant $worker) => $this->documentos->desde($certificate, $worker))
                ->values();

            if ($paginas->isNotEmpty()) {
                $word = $this->nuevoDocumento($paginas->first());
                $paginas->each(fn (array $datos) => $this->diploma($word, $datos));

                return $this->guardarWord($word);
            }
        }

        return $this->guardar($this->documentos->desde($certificate, $participant));
    }

    /**
     * .docx de ejemplo de la plantilla de un tipo.
     */
    public function muestra(CertificateType $tipo): string
    {
        return $this->guardar($this->documentos->muestra($tipo));
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected function guardar(array $datos): string
    {
        return $this->guardarWord($this->documento($datos));
    }

    protected function guardarWord(PhpWord $word): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'cert').'.docx';

        try {
            IOFactory::createWriter($word, 'Word2007')->save($ruta);
        } finally {
            foreach ($this->temporales as $temporal) {
                @unlink($temporal);
            }
            $this->temporales = [];
        }

        return $ruta;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function documento(array $datos): PhpWord
    {
        $word = $this->nuevoDocumento($datos);

        if (CertificatePdfService::esDiploma($datos['tipo'])) {
            $this->diploma($word, $datos);

            return $word;
        }

        $seccion = $word->addSection([
            'paperSize' => 'A4',
            'marginTop' => 567,
            'marginBottom' => 794,
            'marginLeft' => 737,
            'marginRight' => 737,
            'headerHeight' => 0,
            'footerHeight' => 340,
        ]);

        $this->decoracion($seccion);
        $this->pie($seccion, $datos['empresa']);
        $this->cabecera($seccion, $datos['empresa']);
        $this->titulo($seccion, $datos);
        $this->datos($seccion, $datos);
        $this->tablaDeEquipos($seccion, $datos);
        $this->pruebas($seccion, $datos['pruebas']);
        $this->bloques($seccion, $datos['tipo']);
        $this->resultado($seccion, $datos);
        $this->firmas($seccion, $datos['firmantes']);

        return $word;
    }

    /** @param array<string, mixed> $datos */
    protected function nuevoDocumento(array $datos): PhpWord
    {
        // Sin esto, un "&" o "<" en la razón social deja el .docx corrupto.
        Settings::setOutputEscapingEnabled(true);

        $word = new PhpWord;
        $word->getSettings()->setThemeFontLang(new Language(Language::ES_ES));
        $word->setDefaultFontName(self::TEXTO);
        $word->setDefaultFontSize(8.5);
        $word->setDefaultParagraphStyle(['spaceBefore' => 0, 'spaceAfter' => 0, 'lineHeight' => 1.0]);
        $word->getDocInfo()->setTitle((string) $datos['certificado']['numero'])->setCreator((string) $datos['empresa']['razon_social']);

        return $word;
    }

    /**
     * Franjas de las esquinas y marca de agua detrás del texto, en todas las hojas.
     */
    /**
     * Diploma de capacitación en A4 horizontal, con el mismo orden del PDF.
     *
     * @param  array<string, mixed>  $datos
     */
    protected function diploma(PhpWord $word, array $datos): void
    {
        $sectionStyle = [
            'paperSize' => 'A4',
            'orientation' => 'landscape',
            'marginTop' => $this->twip(9),
            'marginBottom' => $this->twip(10),
            'marginLeft' => $this->twip(10),
            'marginRight' => $this->twip(10),
            'headerHeight' => 0,
            'footerHeight' => 0,
        ];

        if ($word->getSections() !== []) {
            $sectionStyle['breakType'] = 'nextPage';
        }

        $seccion = $word->addSection($sectionStyle);
        $ancho = $this->twip(277);
        $centro = ['alignment' => Jc::CENTER];

        $encabezado = $seccion->addHeader();
        $encabezado->addImage($this->recurso('certificados/franja-superior-izquierda.png'), $this->fija(0, 0, $this->mm(61)));
        $encabezado->addImage($this->recurso('certificados/franja-inferior-derecha.png'), $this->fija($this->mm(236), $this->mm(149), $this->mm(61)));
        $marca = $this->fija($this->mm(95), $this->mm(42), $this->mm(107));
        $marca['height'] = $this->mm(107) * 1000 / 1047;
        $encabezado->addImage($this->recurso('certificados/marca-agua.png'), $marca);

        $this->pie($seccion, $datos['empresa']);

        $conFotos = $datos['capacitacion']['fotos'] !== [];
        $anchoLogo = $conFotos ? 118 : 150;

        $seccion->addImage($this->recurso('brand/bruce-fire-logo-grafito.png'), ['width' => $this->mm($anchoLogo), 'height' => $this->mm($anchoLogo) * 479 / 2400, 'alignment' => Jc::CENTER]);
        $seccion->addText(mb_strtoupper($datos['tipo']['titulo'] ?: 'CERTIFICADO'), ['name' => self::TITULO, 'size' => $conFotos ? 44 : 55, 'italic' => true, 'color' => '1B1918'], [...$centro, 'spaceBefore' => $conFotos ? 20 : 80]);

        // Subtítulo rojo entre dos líneas finas (la línea es el borde inferior
        // de un párrafo vacío, centrado en la altura del texto).
        $anchoLinea = $this->twip($conFotos ? 40 : 48);
        $cinta = $seccion->addTable(['alignment' => Jc::CENTER, 'cellMargin' => 0]);
        $filaCinta = $cinta->addRow();
        $linea = fn () => $filaCinta->addCell($anchoLinea, ['valign' => 'center'])
            ->addText('', ['size' => $conFotos ? 6 : 7], ['borderBottomSize' => 7, 'borderBottomColor' => self::GRAFITO, 'spaceAfter' => $conFotos ? 90 : 110]);
        $linea();
        $filaCinta->addCell($this->twip($conFotos ? 68 : 80), ['valign' => 'center'])
            ->addText(mb_strtoupper($datos['tipo']['subtitulo'] ?: 'DE CAPACITACIÓN'), ['name' => self::COND_NEGRITA, 'size' => $conFotos ? 13 : 16, 'color' => self::ROJO, 'spacing' => $conFotos ? 100 : 120], $centro);
        $linea();

        $seccion->addText('OTORGADO A', ['name' => self::COND, 'size' => $conFotos ? 10.5 : 12, 'color' => '2E2D2C', 'spacing' => 28], [...$centro, 'spaceBefore' => $conFotos ? 140 : 300]);
        // En Word la letra sale más ancha que en el PDF: el tamaño baja con el
        // largo del nombre para que no se parta en dos líneas.
        $nombre = mb_strtoupper((string) $datos['cliente']['razon_social']);
        $largo = mb_strlen($nombre);
        $tamanoNombre = match (true) {
            $largo <= 24 => 27,
            $largo <= 30 => 23,
            $largo <= 38 => 19,
            default => 16,
        };
        $seccion->addText($nombre, ['name' => self::TITULO, 'size' => $tamanoNombre, 'italic' => true, 'color' => '1B1918'], [
            ...$centro, 'spaceBefore' => 80, 'borderBottomSize' => 10, 'borderBottomColor' => self::ROJO, 'indentation' => ['left' => $this->twip(41), 'right' => $this->twip(41)],
        ]);

        if ($datos['capacitacion']['personal_de']) {
            $personal = 'Personal de '.$datos['capacitacion']['personal_de'];
            $personal .= $datos['capacitacion']['dni'] ? ' · DNI '.$datos['capacitacion']['dni'] : '';
            $seccion->addText($personal, ['name' => self::COND, 'size' => 10.5, 'color' => self::GRIS_TEXTO], [...$centro, 'spaceBefore' => 60]);
        }

        $razonSocial = (string) $datos['empresa']['razon_social'];
        $declaracion = $seccion->addTextRun([...$centro, 'spaceBefore' => $conFotos ? 80 : 160]);
        foreach (preg_split('/('.preg_quote($razonSocial, '/').')/', trim((string) $datos['tipo']['declaracion']).' en:', -1, PREG_SPLIT_DELIM_CAPTURE) as $parte) {
            $declaracion->addText($parte, ['size' => 12, 'color' => '3F3D3B', 'bold' => $parte === $razonSocial]);
        }

        $seccion->addText($datos['capacitacion']['curso'], ['name' => self::TITULO, 'size' => 22, 'italic' => true, 'color' => self::ROJO], [...$centro, 'spaceBefore' => $conFotos ? 60 : 120]);
        $duracion = $seccion->addTextRun([...$centro, 'spaceBefore' => 40]);
        $duracion->addText('Con una duración de ', ['size' => 12, 'color' => '3F3D3B']);
        $duracion->addText(str_pad((string) $datos['capacitacion']['horas'], 2, '0', STR_PAD_LEFT).' horas', ['size' => 12, 'bold' => true, 'color' => self::ROJO]);
        $duracion->addText(', realizada en '.($datos['empresa']['ciudad'] ?? 'Trujillo').' el '.$datos['capacitacion']['fecha_larga'].'.', ['size' => 12, 'color' => '3F3D3B']);

        if ($conFotos) {
            $seccion->addText('', ['size' => 4]);
            $fotos = $seccion->addTable(['alignment' => Jc::CENTER, 'cellMargin' => 60]);
            $filaFotos = $fotos->addRow();
            foreach ($datos['capacitacion']['fotos'] as $foto) {
                $filaFotos->addCell($this->twip(58), ['valign' => 'center', 'borderTopSize' => 18, 'borderTopColor' => self::ROJO])
                    ->addImage($this->imagen($foto), ['width' => $this->mm(55), 'height' => $this->mm(36.7), 'alignment' => Jc::CENTER]);
            }
        }

        $seccion->addText('', ['size' => $conFotos ? 2 : 14]);
        $pie = $seccion->addTable(['width' => $ancho, 'unit' => TblWidth::TWIP, 'borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 0]);
        $fila = $pie->addRow();

        $tarjeta = $fila->addCell($this->twip(74), ['valign' => 'bottom'])
            ->addTable(['borderSize' => 6, 'borderColor' => 'CFCFCF', 'cellMargin' => 70]);
        $filaTarjeta = $tarjeta->addRow();
        $filaTarjeta->addCell($this->twip(25), ['valign' => 'center', 'borderRightColor' => 'FFFFFF'])
            ->addImage($this->imagen($datos['qr']['imagen']), ['width' => $this->mm(22), 'height' => $this->mm(22)]);
        $numero = $filaTarjeta->addCell($this->twip(45), ['valign' => 'center', 'borderLeftColor' => 'FFFFFF']);
        $numero->addText('N.° DE CERTIFICADO', ['name' => self::COND_NEGRITA, 'size' => 7, 'color' => '6B6965']);
        $numero->addText((string) $datos['certificado']['numero'], ['name' => self::MONO, 'size' => 11, 'bold' => true], ['spaceAfter' => 60]);
        $estado = $datos['certificado']['estado'];
        $numero->addTable(['cellMargin' => 50])->addRow()
            ->addCell($this->twip(42), ['bgColor' => $estado['clave'] === 'vigente' ? self::VERDE : 'B91C1C'])
            ->addText(trim(($estado['clave'] === 'vigente' ? '✓ ' : '').$estado['texto'].' '.($estado['fecha'] ?? '')), ['name' => self::SIMBOLOS, 'size' => 8, 'bold' => true, 'color' => 'FFFFFF'], $centro);

        $instructor = $datos['firmantes'][0] ?? ['nombre' => '', 'cargo' => 'Instructor', 'firma' => null];
        if (! empty($datos['capacitacion']['instructor']) && $datos['capacitacion']['instructor'] !== $instructor['nombre']) {
            $instructor = ['nombre' => $datos['capacitacion']['instructor'], 'cargo' => 'Instructor', 'firma' => null];
        }

        $fila->addCell($this->twip(12));
        $fila->addCell($this->twip(55), ['valign' => 'bottom'])
            ->addImage($this->recurso('certificados/sello-con-texto.png'), ['width' => $this->mm($conFotos ? 28 : 36), 'height' => $this->mm($conFotos ? 28 : 36), 'alignment' => Jc::CENTER]);
        $fila->addCell($this->twip(12));

        $firma = $fila->addCell($ancho - $this->twip(74 + 12 + 55 + 12), ['valign' => 'bottom']);
        if ($instructor['firma']) {
            $firma->addImage($this->imagen($instructor['firma']), ['height' => $this->mm(15), 'alignment' => Jc::CENTER]);
        }
        $firma->addText($instructor['nombre'], ['name' => self::COND_NEGRITA, 'size' => 11], [...$centro, 'borderTopSize' => 8, 'borderTopColor' => '1B1918', 'spaceBefore' => 60]);
        $firma->addText(mb_strtoupper($instructor['cargo']), ['name' => self::COND, 'size' => 8.5, 'color' => self::ROJO, 'spacing' => 20], $centro);

    }

    /**
     * Estilo de una imagen fija en la página, detrás del texto.
     *
     * @return array<string, mixed>
     */
    protected function fija(float $izquierda, float $arriba, float $ancho): array
    {
        return [
            'width' => $ancho,
            'height' => $ancho,
            'positioning' => ImageStyle::POSITION_ABSOLUTE,
            'posHorizontal' => ImageStyle::POSITION_ABSOLUTE,
            'posVertical' => ImageStyle::POSITION_ABSOLUTE,
            'posHorizontalRel' => ImageStyle::POSITION_RELATIVE_TO_PAGE,
            'posVerticalRel' => ImageStyle::POSITION_RELATIVE_TO_PAGE,
            'marginLeft' => $izquierda,
            'marginTop' => $arriba,
            'wrappingStyle' => ImageStyle::WRAPPING_STYLE_BEHIND,
        ];
    }

    protected function decoracion(Section $seccion): void
    {
        $encabezado = $seccion->addHeader();
        $fija = fn (float $izquierda, float $arriba, float $ancho) => [
            'width' => $ancho,
            'height' => $ancho,
            'positioning' => ImageStyle::POSITION_ABSOLUTE,
            'posHorizontal' => ImageStyle::POSITION_ABSOLUTE,
            'posVertical' => ImageStyle::POSITION_ABSOLUTE,
            'posHorizontalRel' => ImageStyle::POSITION_RELATIVE_TO_PAGE,
            'posVerticalRel' => ImageStyle::POSITION_RELATIVE_TO_PAGE,
            'marginLeft' => $izquierda,
            'marginTop' => $arriba,
            'wrappingStyle' => ImageStyle::WRAPPING_STYLE_BEHIND,
        ];

        $encabezado->addImage($this->recurso('certificados/franja-superior-izquierda.png'), $fija(0, 0, $this->mm(56)));
        $encabezado->addImage($this->recurso('certificados/franja-inferior-derecha.png'), $fija($this->mm(154), $this->mm(241), $this->mm(56)));

        $marca = $fija($this->mm(55), $this->mm(108), $this->mm(100));
        $marca['height'] = $this->mm(100) * 1000 / 1047;
        $encabezado->addImage($this->recurso('certificados/marca-agua.png'), $marca);
    }

    /**
     * @param  array<string, mixed>  $empresa
     */
    protected function pie(Section $seccion, array $empresa): void
    {
        $texto = collect([$empresa['razon_social'], $empresa['ruc'] ? 'RUC '.$empresa['ruc'] : null, $empresa['ciudad'] ?? null])
            ->filter()->push('Página {PAGE} de {NUMPAGES}')->implode('   ·   ');

        $seccion->addFooter()->addPreserveText($texto, ['name' => self::COND, 'size' => 7.5, 'color' => self::GRIS_TEXTO], ['alignment' => Jc::CENTER]);
    }

    /**
     * @param  array<string, mixed>  $empresa
     */
    protected function cabecera(Section $seccion, array $empresa): void
    {
        $tabla = $seccion->addTable($this->tablaSinBordes());
        $fila = $tabla->addRow();
        $fila->addCell($this->twip(118), ['valign' => 'center'])
            ->addImage($this->recurso('brand/bruce-fire-logo-grafito.png'), ['width' => $this->mm(112), 'height' => $this->mm(112) * 479 / 2400]);

        $derecha = $fila->addCell(self::ANCHO - $this->twip(118), ['valign' => 'center']);
        $ruc = $derecha->addTable(['borderSize' => 10, 'borderColor' => self::ROJO, 'width' => 100 * 50, 'unit' => TblWidth::PERCENT, 'cellMargin' => 40]);
        $celdaRuc = $ruc->addRow()->addCell(self::ANCHO - $this->twip(118));
        $linea = $celdaRuc->addTextRun(['alignment' => Jc::CENTER]);
        $linea->addText('RUC: ', ['name' => self::COND_NEGRITA, 'size' => 12, 'color' => self::ROJO]);
        $linea->addText((string) $empresa['ruc'], ['name' => self::MONO, 'size' => 12, 'bold' => true]);

        foreach ($empresa['telefonos'] as $telefono) {
            $linea = $derecha->addTextRun(['spaceBefore' => 50]);
            $linea->addImage($this->recurso('certificados/icono-whatsapp.png'), ['width' => 11, 'height' => 11]);
            $linea->addText('  '.$telefono, ['name' => self::MONO, 'size' => 11, 'bold' => true]);
        }

        if ($empresa['email']) {
            $linea = $derecha->addTextRun(['spaceBefore' => 50]);
            $linea->addImage($this->recurso('certificados/icono-gmail.png'), ['width' => 11, 'height' => 11]);
            $linea->addText('  '.$empresa['email'], ['name' => self::COND, 'size' => 8.5]);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected function titulo(Section $seccion, array $datos): void
    {
        $seccion->addText('', [], ['spaceAfter' => 60]);
        $tabla = $seccion->addTable($this->tablaSinBordes());
        $fila = $tabla->addRow();
        $izquierda = $fila->addCell(self::ANCHO - $this->twip(66), ['valign' => 'center']);

        // Arial Black es un poco más ancha que la fuente del PDF: se reduce para que entre igual.
        foreach ($this->pdf->titulo($datos['tipo']) as $linea) {
            $izquierda->addText($linea['texto'], [
                'name' => self::TITULO,
                'size' => round($linea['tamano'] * 0.9 * 2) / 2,
                'italic' => true,
                'color' => $linea['clase'] === 't3' ? self::ROJO : '1B1918',
            ], ['lineHeight' => 0.95]);
        }

        $tarjeta = $fila->addCell($this->twip(66), ['valign' => 'center'])
            ->addTable(['borderSize' => 6, 'borderColor' => 'CFCFCF', 'cellMargin' => 70, 'width' => $this->twip(66), 'unit' => TblWidth::TWIP]);
        $filaTarjeta = $tarjeta->addRow();
        $filaTarjeta->addCell($this->twip(25), ['valign' => 'center', 'borderRightColor' => 'FFFFFF'])
            ->addImage($this->imagen($datos['qr']['imagen']), ['width' => $this->mm(22), 'height' => $this->mm(22)]);

        $numero = $filaTarjeta->addCell($this->twip(41), ['valign' => 'center', 'borderLeftColor' => 'FFFFFF']);
        $numero->addText('N.° DE CERTIFICADO', ['name' => self::COND_NEGRITA, 'size' => 7, 'color' => '6B6965']);
        $numero->addText((string) $datos['certificado']['numero'], ['name' => self::MONO, 'size' => 12, 'bold' => true], ['spaceAfter' => 60]);

        $estado = $datos['certificado']['estado'];
        $colores = ['vigente' => self::VERDE, 'vencido' => 'B45309', 'anulado' => 'B91C1C', 'reemplazado' => 'B91C1C'];
        $caja = $numero->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT, 'cellMargin' => 50])->addRow()
            ->addCell($this->twip(38), ['bgColor' => $colores[$estado['clave']] ?? self::VERDE, 'valign' => 'center']);
        $caja->addText($estado['texto'], ['name' => self::COND_NEGRITA, 'size' => 8, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);

        if ($estado['fecha']) {
            $caja->addText($estado['fecha'], ['name' => self::COND_NEGRITA, 'size' => 14, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);
        }

        if ($datos['certificado']['revision'] > 0) {
            $numero->addText('Revisión '.$datos['certificado']['revision'].' · '.$datos['certificado']['revision_fecha'], ['size' => 7, 'color' => '6B6965'], ['alignment' => Jc::CENTER, 'spaceBefore' => 40]);
        }

        if (! empty($datos['tipo']['norma'])) {
            $seccion->addText($datos['tipo']['norma'], ['size' => 9, 'color' => '3F3D3B'], ['alignment' => Jc::CENTER, 'spaceBefore' => 60]);
        }
    }

    /**
     * Datos del cliente y del servicio, lado a lado, y la declaración.
     *
     * @param  array<string, mixed>  $datos
     */
    protected function datos(Section $seccion, array $datos): void
    {
        $cliente = $datos['cliente'];
        $certificado = $datos['certificado'];
        $izquierdo = array_filter([
            'Razón social' => $cliente['razon_social'],
            $cliente['documento_tipo'] => $cliente['documento'],
            'Nombre comercial' => $cliente['nombre_comercial'],
            'Dirección' => $cliente['direccion'],
        ] + ($cliente['referencia'] ? [$cliente['referencia']['etiqueta'] => $cliente['referencia']['valor']] : []));
        $derecho = array_filter([
            'Fecha de emisión' => $certificado['emision'],
            'Vencimiento' => $certificado['vencimiento'],
            'Vigencia' => $certificado['vigencia'],
            'Tipo de atención' => $certificado['tipo_atencion'],
            'Orden de servicio' => $certificado['orden'],
        ]);

        $this->espacio($seccion);
        $tabla = $seccion->addTable($this->tablaSinBordes());
        $fila = $tabla->addRow();
        $anchoIzquierdo = (int) (self::ANCHO * 0.55) - 70;
        $anchoDerecho = self::ANCHO - $anchoIzquierdo - 140;

        foreach ([[$anchoIzquierdo, 'Datos del cliente', $izquierdo], [140, null, null], [$anchoDerecho, 'Datos del servicio', $derecho]] as [$ancho, $titulo, $filas]) {
            $celda = $fila->addCell($ancho);

            if ($titulo === null) {
                continue;
            }

            $this->barra($celda, $titulo, $ancho);
            $datosTabla = $celda->addTable($this->tablaConBordes($ancho));

            foreach ($filas as $etiqueta => $valor) {
                $filaDato = $datosTabla->addRow();
                $filaDato->addCell($this->twip(32), ['bgColor' => 'F6F5F4', 'valign' => 'center'])
                    ->addText($etiqueta, ['name' => self::COND_NEGRITA, 'size' => 8.5, 'color' => self::ROJO]);
                $filaDato->addCell($ancho - $this->twip(32), ['valign' => 'center'])
                    ->addText((string) $valor, ['name' => self::COND_NEGRITA, 'size' => 8.5]);
            }
        }

        if (! empty($datos['tipo']['declaracion'])) {
            $razonSocial = (string) $datos['empresa']['razon_social'];
            $parrafo = $seccion->addTextRun(['alignment' => Jc::BOTH, 'spaceBefore' => 100, 'lineHeight' => 1.15]);

            foreach (preg_split('/('.preg_quote($razonSocial, '/').')/', $datos['tipo']['declaracion'], -1, PREG_SPLIT_DELIM_CAPTURE) as $parte) {
                $parrafo->addText($parte, ['size' => 8.5, 'bold' => $parte === $razonSocial]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected function tablaDeEquipos(Section $seccion, array $datos): void
    {
        $columnas = $datos['tipo']['columnas'] ?? [];

        if (! $columnas || ! $datos['filas']) {
            return;
        }

        $claves = array_column($columnas, 'clave');
        $titulo = in_array('componente', $claves, true) ? 'Componentes inspeccionados'
            : (in_array('ambiente', $claves, true) ? 'Instalación realizada'
            : (in_array('serie', $claves, true) ? 'Información técnica de los extintores' : 'Equipos inspeccionados'));

        $this->espacio($seccion);
        $this->barra($seccion, $titulo, self::ANCHO);
        $tabla = $seccion->addTable($this->tablaConBordes(self::ANCHO));
        $anchos = $this->anchosDeColumnas($columnas);

        $encabezado = $tabla->addRow(null, ['tblHeader' => true, 'cantSplit' => true]);
        foreach ($columnas as $indice => $columna) {
            $encabezado->addCell($anchos[$indice], ['bgColor' => 'F1F0EF', 'valign' => 'center'])
                ->addText($columna['titulo'], ['name' => self::COND_NEGRITA, 'size' => 8.5], ['alignment' => $this->alineacion($columna)]);
        }

        foreach ($datos['filas'] as $numeroFila => $fila) {
            $filaTabla = $tabla->addRow(null, ['cantSplit' => true]);

            foreach ($columnas as $indice => $columna) {
                if (($columna['tipo'] ?? '') === 'resultado') {
                    $clave = $fila['resultado'] ?? 'operativo';
                    $colores = ['operativo' => self::VERDE, 'observado' => 'D97706', 'no_operativo' => 'B91C1C'];
                    $filaTabla->addCell($anchos[$indice], ['bgColor' => $colores[$clave] ?? self::VERDE, 'valign' => 'center'])
                        ->addText(CertificateDocumentData::RESULTADOS[$clave] ?? $clave, ['name' => self::COND_NEGRITA, 'size' => 7.5, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);

                    continue;
                }

                $valor = (string) ($fila[$columna['clave']] ?? '');
                $filaTabla->addCell($anchos[$indice], ['valign' => 'center', 'bgColor' => $numeroFila % 2 === 1 ? 'FBFAF9' : null])
                    ->addText($valor !== '' ? $valor : '—', ['name' => self::COND, 'size' => 8.5], ['alignment' => $this->alineacion($columna)]);
            }
        }
    }

    /**
     * Checklist en dos columnas con casillas ☑ / ☐ fáciles de cambiar en Word.
     *
     * @param  list<array{texto: string, valor: string|null, estado: string}>  $pruebas
     */
    protected function pruebas(Section $seccion, array $pruebas): void
    {
        if (! $pruebas) {
            return;
        }

        $this->espacio($seccion);
        $mitad = (int) ceil(count($pruebas) / 2);
        $ancho = (int) ((self::ANCHO - 140) / 2);
        $tabla = $seccion->addTable($this->tablaSinBordes());
        $fila = $tabla->addRow(null, ['cantSplit' => true]);

        foreach (array_chunk($pruebas, $mitad, true) as $lado => $grupo) {
            if ($lado === 1) {
                $fila->addCell(140);
            }

            $celda = $fila->addCell($ancho);
            $this->barra($celda, $lado === 0 ? 'Pruebas realizadas' : '', $ancho);
            $lista = $celda->addTable($this->tablaConBordes($ancho));
            $cabecera = $lista->addRow();
            $anchoMarca = $this->twip(7);
            $anchoTexto = $ancho - $this->twip(7) - 3 * $anchoMarca;

            foreach ([['N.°', $this->twip(7)], ['VERIFICACIÓN', $anchoTexto], ['C', $anchoMarca], ['NC', $anchoMarca], ['N/A', $anchoMarca]] as [$texto, $anchoCelda]) {
                $cabecera->addCell($anchoCelda, ['bgColor' => 'F1F0EF'])
                    ->addText($texto, ['name' => self::COND_NEGRITA, 'size' => 7.5], ['alignment' => strlen($texto) <= 3 && $texto !== 'N.°' ? Jc::CENTER : Jc::START]);
            }

            foreach ($grupo as $numero => $prueba) {
                $filaPrueba = $lista->addRow(null, ['cantSplit' => true]);
                $filaPrueba->addCell($this->twip(7), ['valign' => 'center'])
                    ->addText(str_pad((string) ($numero + 1), 2, '0', STR_PAD_LEFT), ['name' => self::COND_NEGRITA, 'size' => 8, 'color' => self::ROJO]);
                $texto = $filaPrueba->addCell($anchoTexto, ['valign' => 'center'])->addTextRun();
                $texto->addText($prueba['texto'], ['name' => self::COND, 'size' => 8]);

                if ($prueba['valor']) {
                    $texto->addText(' · '.$prueba['valor'], ['name' => self::COND, 'size' => 8, 'color' => '6B6965']);
                }

                foreach (['C' => self::VERDE, 'NC' => 'B91C1C', 'NA' => '6B6965'] as $opcion => $color) {
                    $marcada = $prueba['estado'] === $opcion;
                    $filaPrueba->addCell($anchoMarca, ['valign' => 'center'])
                        ->addText($marcada ? '☑' : '☐', ['name' => self::SIMBOLOS, 'size' => 10, 'color' => $marcada ? $color : '9C9A98'], ['alignment' => Jc::CENTER]);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $tipo
     */
    protected function bloques(Section $seccion, array $tipo): void
    {
        $bloques = $tipo['bloques'] ?? [];
        $conAsneex = in_array('asneex', $tipo['logos'] ?? [], true);

        if (! $bloques && ! $conAsneex) {
            return;
        }

        $this->espacio($seccion);
        $anchoLogo = $conAsneex ? $this->twip(22) : 0;
        $anchoBloque = $bloques ? (int) ((self::ANCHO - $anchoLogo) / count($bloques)) : 0;
        $tabla = $seccion->addTable(['borderSize' => 4, 'borderColor' => self::BORDE, 'cellMargin' => 80, 'width' => self::ANCHO, 'unit' => TblWidth::TWIP]);
        $fila = $tabla->addRow(null, ['cantSplit' => true]);

        foreach ($bloques as $bloque) {
            $celda = $fila->addCell($anchoBloque, ['borderTopSize' => 14, 'borderTopColor' => self::ROJO]);
            $celda->addText(mb_strtoupper($bloque['titulo']), ['name' => self::COND_NEGRITA, 'size' => 8, 'color' => self::ROJO], ['spaceAfter' => 30]);
            $celda->addText($bloque['texto'], ['size' => 7.5, 'color' => '3F3D3B'], ['lineHeight' => 1.1]);
        }

        if ($conAsneex) {
            $celda = $fila->addCell($anchoLogo, ['valign' => 'center', 'borderTopColor' => 'FFFFFF', 'borderRightColor' => 'FFFFFF', 'borderBottomColor' => 'FFFFFF']);
            $celda->addImage($this->recurso('certificados/logo-asneex.png'), ['width' => $this->mm(16), 'height' => $this->mm(16) * 500 / 397, 'alignment' => Jc::CENTER]);
            $celda->addText('ASOCIADO', ['name' => self::COND_NEGRITA, 'size' => 7, 'color' => '6B6965'], ['alignment' => Jc::CENTER]);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected function resultado(Section $seccion, array $datos): void
    {
        $this->espacio($seccion);
        $this->barra($seccion, 'Resultado de la evaluación', self::ANCHO);
        $tabla = $seccion->addTable($this->tablaConBordes(self::ANCHO));
        $fila = $tabla->addRow($this->twip(9), ['cantSplit' => true]);
        $ancho = (int) (self::ANCHO / 3);

        foreach (CertificateDocumentData::RESULTADOS as $clave => $texto) {
            $elegido = $datos['resultado'] === $clave;
            $linea = $fila->addCell($ancho, ['valign' => 'center', 'bgColor' => $elegido ? 'E7F5EC' : 'F7F7F6'])->addTextRun();
            $linea->addText($elegido ? '☑  ' : '☐  ', ['name' => self::SIMBOLOS, 'size' => 13, 'color' => $elegido ? self::VERDE : '9C9A98']);
            $linea->addText($texto, ['name' => self::COND_NEGRITA, 'size' => 10.5, 'color' => $elegido ? '0B5D30' : self::GRIS_TEXTO]);
        }

        $notas = array_filter([
            'Observaciones' => $datos['observaciones'],
            'Certificado anulado' => $datos['certificado']['estado']['clave'] === 'anulado' ? $datos['certificado']['anulado_motivo'] : null,
        ]);

        foreach ($notas as $etiqueta => $nota) {
            $linea = $seccion->addTextRun(['spaceBefore' => 80]);
            $linea->addText($etiqueta.': ', ['bold' => true, 'size' => 8.5]);
            $linea->addText((string) $nota, ['size' => 8.5]);
        }

        if (! empty($datos['tipo']['responsabilidad'])) {
            $seccion->addText($datos['tipo']['responsabilidad'], ['size' => 7, 'color' => self::GRIS_TEXTO], ['alignment' => Jc::BOTH, 'spaceBefore' => 80, 'keepNext' => true]);
        }
    }

    /**
     * Firmas con el sello al centro; la línea de firma es el borde superior
     * de la celda del nombre.
     *
     * @param  list<array{nombre: string, cargo: string, cip: string|null, firma: string|null}>  $firmantes
     */
    protected function firmas(Section $seccion, array $firmantes): void
    {
        $this->espacio($seccion);
        $posicionSello = intdiv(count($firmantes), 2);
        $anchoSello = $this->twip(28);
        $anchoFirma = (int) ((self::ANCHO - $anchoSello) / max(count($firmantes), 1));
        $celdas = [];

        foreach ($firmantes as $indice => $firmante) {
            if ($indice === $posicionSello) {
                $celdas[] = null;
            }
            $celdas[] = $firmante;
        }

        if ($posicionSello >= count($firmantes)) {
            $celdas[] = null;
        }

        $tabla = $seccion->addTable($this->tablaSinBordes());
        $arriba = $tabla->addRow($this->twip(13), ['cantSplit' => true]);
        $abajo = $tabla->addRow(null, ['cantSplit' => true]);

        foreach ($celdas as $firmante) {
            if ($firmante === null) {
                $arriba->addCell($anchoSello, ['vMerge' => 'restart', 'valign' => 'center'])
                    ->addImage($this->recurso('certificados/sello-con-texto.png'), ['width' => $this->mm(22), 'height' => $this->mm(22), 'alignment' => Jc::CENTER]);
                $abajo->addCell($anchoSello, ['vMerge' => 'continue']);

                continue;
            }

            $firma = $arriba->addCell($anchoFirma, ['valign' => 'bottom']);

            if ($firmante['firma']) {
                $firma->addImage($this->imagen($firmante['firma']), ['height' => $this->mm(11), 'alignment' => Jc::CENTER]);
            }

            $nombre = $abajo->addCell($anchoFirma, ['borderTopSize' => 8, 'borderTopColor' => '1B1918']);
            $nombre->addText($firmante['nombre'], ['name' => self::COND_NEGRITA, 'size' => 9], ['alignment' => Jc::CENTER, 'spaceBefore' => 30]);
            $nombre->addText(mb_strtoupper($firmante['cargo']), ['name' => self::COND, 'size' => 7.5, 'color' => '3F3D3B'], ['alignment' => Jc::CENTER]);

            if ($firmante['cip']) {
                $nombre->addText('CIP N.° '.$firmante['cip'], ['name' => self::COND, 'size' => 7.5, 'color' => '3F3D3B'], ['alignment' => Jc::CENTER]);
            }
        }
    }

    /**
     * Barra grafito con borde rojo y la punta roja inclinada.
     */
    protected function barra(AbstractContainer $contenedor, string $texto, int $ancho): void
    {
        $tabla = $contenedor->addTable(['width' => $ancho, 'unit' => TblWidth::TWIP, 'cellMargin' => 60, 'borderSize' => 0, 'borderColor' => 'FFFFFF']);
        $fila = $tabla->addRow($this->twip(6), ['exactHeight' => true]);
        $fila->addCell($ancho - $this->twip(6), ['bgColor' => self::GRAFITO, 'valign' => 'center', 'borderLeftSize' => 36, 'borderLeftColor' => self::ROJO])
            ->addText(mb_strtoupper($texto), ['name' => self::COND_NEGRITA, 'size' => 10, 'color' => 'FFFFFF']);
        $fila->addCell($this->twip(6), ['valign' => 'center'])
            ->addImage($this->recurso('certificados/cola-seccion.png'), ['width' => $this->mm(5.4), 'height' => $this->mm(4.5)]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function tablaSinBordes(): array
    {
        return ['width' => self::ANCHO, 'unit' => TblWidth::TWIP, 'cellMargin' => 0, 'borderSize' => 0, 'borderColor' => 'FFFFFF'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function tablaConBordes(int $ancho): array
    {
        return ['width' => $ancho, 'unit' => TblWidth::TWIP, 'borderSize' => 4, 'borderColor' => self::BORDE, 'cellMarginLeft' => 80, 'cellMarginRight' => 80, 'cellMarginTop' => 25, 'cellMarginBottom' => 25];
    }

    /**
     * Reparte el ancho: las columnas de texto largo reciben el doble.
     *
     * @param  list<array<string, mixed>>  $columnas
     * @return list<int>
     */
    protected function anchosDeColumnas(array $columnas): array
    {
        $pesos = array_map(fn (array $columna) => match (true) {
            $columna['clave'] === 'item' => 0.6,
            in_array($columna['clave'], ['componente', 'prueba', 'ubicacion', 'ambiente', 'modelo'], true) => 2,
            ($columna['tipo'] ?? '') === 'resultado' => 1.4,
            default => 1,
        }, $columnas);
        $total = array_sum($pesos);

        return array_map(fn (float $peso) => (int) (self::ANCHO * $peso / $total), $pesos);
    }

    /**
     * @param  array<string, mixed>  $columna
     */
    protected function alineacion(array $columna): string
    {
        return in_array($columna['clave'], ['componente', 'prueba', 'ubicacion', 'ambiente', 'modelo', 'marca', 'serie'], true) ? Jc::START : Jc::CENTER;
    }

    protected function espacio(Section $seccion): void
    {
        $seccion->addText('', ['size' => 5], ['spaceAfter' => 0]);
    }

    protected function recurso(string $ruta): string
    {
        return resource_path('images/'.$ruta);
    }

    /**
     * Guarda una imagen "data:" (QR, firma) en un archivo temporal para Word.
     */
    protected function imagen(string $dataUri): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'img').'.png';
        file_put_contents($ruta, base64_decode(substr($dataUri, strpos($dataUri, ',') + 1)));
        $this->temporales[] = $ruta;

        return $ruta;
    }

    protected function mm(float $milimetros): float
    {
        return round($milimetros * 72 / 25.4, 2);
    }

    protected function twip(float $milimetros): int
    {
        return (int) round($milimetros * 1440 / 25.4);
    }
}
