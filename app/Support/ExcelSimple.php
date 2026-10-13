<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Archivo Excel (.xlsx) de una sola hoja, sin librerías: encabezado en negrita
 * con fondo, primera fila fija, filtros y anchos de columna. Todas las celdas
 * son texto, así un DNI con ceros al inicio no pierde nada.
 */
class ExcelSimple
{
    /**
     * @param  list<string>  $encabezados
     * @param  iterable<list<scalar|null>>  $filas
     * @param  list<int>  $anchos  Ancho de cada columna en caracteres (opcional).
     */
    public static function descargar(string $nombreArchivo, string $hoja, array $encabezados, iterable $filas, array $anchos = []): StreamedResponse
    {
        $contenido = self::generar($hoja, $encabezados, $filas, $anchos);

        return response()->streamDownload(function () use ($contenido): void {
            echo $contenido;
        }, $nombreArchivo, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * @param  list<string>  $encabezados
     * @param  iterable<list<scalar|null>>  $filas
     * @param  list<int>  $anchos
     */
    public static function generar(string $hoja, array $encabezados, iterable $filas, array $anchos = []): string
    {
        $columnas = count($encabezados);
        $xmlFilas = self::fila(1, $encabezados, 1);
        $n = 1;

        foreach ($filas as $fila) {
            $xmlFilas .= self::fila(++$n, array_values($fila), 0);
        }

        $cols = '';
        foreach (array_values($anchos) as $i => $ancho) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$ancho.'" customWidth="1"/>';
        }

        $ultima = self::letra($columnas).$n;
        $hojaXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .($cols !== '' ? "<cols>{$cols}</cols>" : '')
            ."<sheetData>{$xmlFilas}</sheetData>"
            ."<autoFilter ref=\"A1:{$ultima}\"/>"
            .'</worksheet>';

        $nombreHoja = htmlspecialchars(mb_substr((string) preg_replace('/[\\\\\/?*\[\]:]/', ' ', $hoja), 0, 31), ENT_XML1);
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;

        if ($zip->open($ruta, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo Excel.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$nombreHoja.'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFB91C1C"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $hojaXml);
        $zip->close();

        $bytes = (string) file_get_contents($ruta);
        @unlink($ruta);

        return $bytes;
    }

    /** @param  list<scalar|null>  $celdas */
    private static function fila(int $numero, array $celdas, int $estilo): string
    {
        $xml = '';

        foreach (array_values($celdas) as $i => $valor) {
            $texto = trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) ($valor ?? '')));

            if ($texto === '') {
                continue;
            }

            $xml .= '<c r="'.self::letra($i + 1).$numero.'" s="'.$estilo.'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars($texto, ENT_XML1 | ENT_SUBSTITUTE).'</t></is></c>';
        }

        return "<row r=\"{$numero}\">{$xml}</row>";
    }

    private static function letra(int $columna): string
    {
        $letra = '';

        while ($columna > 0) {
            $columna--;
            $letra = chr(65 + $columna % 26).$letra;
            $columna = intdiv($columna, 26);
        }

        return $letra;
    }
}
