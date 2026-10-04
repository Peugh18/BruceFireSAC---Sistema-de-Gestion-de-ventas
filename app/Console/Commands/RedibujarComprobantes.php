<?php

namespace App\Console\Commands;

use App\Models\ElectronicDocument;
use App\Services\Billing\ComprobantePdfService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('billing:redibujar-pdf')]
#[Description('Vuelve a dibujar el PDF de todas las facturas y boletas con el diseño actual (el XML enviado a SUNAT no cambia)')]
class RedibujarComprobantes extends Command
{
    public function handle(ComprobantePdfService $pdfService): int
    {
        $documentos = ElectronicDocument::query()->whereIn('tipo', ['factura', 'boleta'])->orderBy('id')->get();
        $redibujados = 0;
        $sinXml = [];

        foreach ($documentos as $documento) {
            $numero = "{$documento->serie}-{$documento->correlativo}";

            try {
                $pdfService->redibujar($documento) !== null ? $redibujados++ : $sinXml[] = $numero;
            } catch (Throwable $exception) {
                $this->warn("{$numero}: no se pudo dibujar ({$exception->getMessage()}).");
            }
        }

        $this->info("{$redibujados} de {$documentos->count()} comprobante(s) redibujados con el diseño actual.");
        $this->line('Firma del diseño: '.substr($pdfService->firmaDeDiseno(), 0, 12).' · plantilla: '.resource_path('views/pdf/comprobante.blade.php'));

        if ($sinXml !== []) {
            $this->warn('Sin XML guardado, no se pudieron redibujar: '.implode(', ', $sinXml));
        }

        return self::SUCCESS;
    }
}
