<?php

namespace App\Console\Commands;

use App\Models\SunatCodigoProducto;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sunat:importar-codigos-producto {archivo? : CSV con codigo,descripcion[,clase] (por defecto database/data/sunat_codigos_producto.csv)}')]
#[Description('Carga o actualiza el catálogo 25 de SUNAT (código de producto UNSPSC) desde un CSV')]
class ImportarCodigosProductoSunat extends Command
{
    public function handle(): int
    {
        $archivo = (string) ($this->argument('archivo') ?? database_path('data/sunat_codigos_producto.csv'));
        $manejador = is_readable($archivo) ? fopen($archivo, 'r') : false;

        if ($manejador === false) {
            $this->error("No se encontró el archivo {$archivo}.");

            return self::FAILURE;
        }

        $lote = [];
        $total = 0;
        while (($fila = fgetcsv($manejador, escape: '')) !== false) {
            $codigo = trim((string) ($fila[0] ?? ''));
            $descripcion = trim((string) ($fila[1] ?? ''));
            $clase = trim((string) ($fila[2] ?? ''));

            // Salta el encabezado y las filas que no son un código de 8 dígitos.
            if (preg_match('/^\d{8}$/', $codigo) !== 1 || $descripcion === '') {
                continue;
            }

            $lote[] = ['codigo' => $codigo, 'descripcion' => mb_substr($descripcion, 0, 255), 'clase' => mb_substr($clase, 0, 160)];
            if (count($lote) === 1000) {
                $total += $this->guardar($lote);
                $lote = [];
            }
        }
        fclose($manejador);
        $total += $this->guardar($lote);

        $this->info("Catálogo 25 de SUNAT: {$total} códigos cargados.");

        return self::SUCCESS;
    }

    /** @param  list<array{codigo: string, descripcion: string, clase: string}>  $lote */
    private function guardar(array $lote): int
    {
        if ($lote !== []) {
            SunatCodigoProducto::query()->upsert($lote, ['codigo'], ['descripcion', 'clase']);
        }

        return count($lote);
    }
}
